<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Services;

use Abdulbaset\ActivityTracker\Contracts\ActivityLoggerInterface;
use Abdulbaset\ActivityTracker\Contracts\ActivityStorageInterface;
use Abdulbaset\ActivityTracker\Contracts\ActivityTransformerInterface;
use Abdulbaset\ActivityTracker\Jobs\StoreActivity;
use Abdulbaset\ActivityTracker\Models\Activity;
use Abdulbaset\ActivityTracker\Support\TrackingContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Central entry point for recording activities. Both the Eloquent observer
 * and the database query listener funnel through here, which is where all
 * "should we even track this?" policy decisions (config, ignore lists,
 * suppression) live in one place.
 */
final class ActivityTrackerManager implements ActivityLoggerInterface
{
    public function __construct(
        private readonly ActivityTransformerInterface $transformer,
        private readonly ActivityStorageInterface $storage,
        private readonly TrackingContext $trackingContext,
    ) {
    }

    public function logModelEvent(string $action, Model $model, array $extra = []): void
    {
        if (! $this->shouldTrack($action, $model->getTable(), $model::class)) {
            return;
        }

        $payload = $this->transformer->fromModelEvent($action, $model, $extra);

        $this->dispatch($payload);
    }

    public function logQueryEvent(string $action, array $data): void
    {
        $table = $data['table'] ?? null;
        $modelType = $data['model_type'] ?? null;

        if (! $this->shouldTrack($action, $table, $modelType)) {
            return;
        }

        $payload = $this->transformer->fromQueryEvent($action, $data);

        $this->dispatch($payload);
    }

    /**
     * Records a deliberate "someone viewed this record through the audit UI"
     * event — entirely separate from the automatic Eloquent "retrieved"
     * listener. This is the mechanism behind the Activity Details page's
     * "viewed via dashboard" entry: unlike a blind Eloquent hydration event,
     * a call here is an explicit statement of intent from calling code, so
     * it is NEVER subject to the auth-provider-model exclusion that filters
     * out incidental framework noise (an admin intentionally viewing a User
     * record's audit trail is exactly the kind of read worth recording,
     * even though "the User model got retrieved" as a bare fact is not).
     *
     * @param  array<string, mixed>  $metadata
     */
    public function logIntentionalView(Model $model, array $metadata = []): void
    {
        $table = $model->getTable();
        $modelType = $model::class;

        if ($this->isExcludedByBaseRules($table, $modelType)) {
            return;
        }

        if (! config('activity-tracker.retrieval.track_ui_views', true)) {
            return;
        }

        $payload = $this->transformer->fromModelEvent('retrieved', $model, [
            'metadata' => array_merge(['context' => 'ui', 'intent' => 'view'], $metadata),
        ]);

        $this->dispatch($payload);
    }

    public function logException(\Throwable $exception): void
    {
        if ($this->isExcludedByBaseRules(null, null)) {
            return;
        }

        $payload = $this->transformer->fromException($exception);

        $this->dispatch($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function logAuthEvent(string $authAction, array $data): void
    {
        if ($this->isExcludedByBaseRules(null, null)) {
            return;
        }

        $payload = $this->transformer->fromAuthEvent($authAction, $data);

        $this->dispatch($payload);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function logBroadcastEvent(string $status, array $data): void
    {
        if ($this->isExcludedByBaseRules(null, null)) {
            return;
        }

        $payload = $this->transformer->fromBroadcastEvent($status, $data);

        $this->dispatch($payload);
    }

    private function shouldTrack(string $action, ?string $table, ?string $modelType): bool
    {
        // "count" and "exists" are intentionally never tracked (they produce
        // no usable audit signal — see README § Tracked operations). This is
        // a hard rule enforced here, not merely a default left to
        // configuration, so it can never be silently re-enabled.
        if (in_array($action, ['count', 'exists'], true)) {
            return false;
        }

        if ($this->isExcludedByBaseRules($table, $modelType)) {
            return false;
        }

        if (in_array($action, (array) config('activity-tracker.ignored_actions', []), true)) {
            return false;
        }

        // NOTE: there is deliberately no auth-provider-model exclusion here
        // for "retrieved"/"retrieved_many". That exclusion (Laravel's auth
        // guard resolving the current user via a UserProvider — not the
        // model class itself) is decided per-event, synchronously, inside
        // ActivityTrackerObserver::isAuthProviderResolution() — BEFORE the
        // retrieval is buffered — because "retrieved"/"retrieved_many" are
        // buffered and flushed as one aggregated activity at the end of the
        // request/job (see TrackingContext::bufferRetrieval() /
        // RetrievalFlusher). By the time shouldTrack() runs for a flushed
        // "retrieved" activity, the original call stack that could tell
        // guard-internal apart from a genuine `User::find($id)` is long
        // gone, so checking it here would be a no-op — worse, checking by
        // model class alone here previously suppressed EVERY retrieval of
        // that class, including real application reads. See README §
        // Retrieval strategy and the CHANGELOG for the full root-cause
        // writeup.

        $trackKey = $this->trackConfigKey($action);

        if ($trackKey !== null && ! config("activity-tracker.track.{$trackKey}", true)) {
            return false;
        }

        return true;
    }

    /**
     * Exclusions shared by every entry point — automatic tracking AND the
     * explicit intentional-view log: master switch, active suppression, the
     * package's own table/model, and configured ignore lists.
     */
    private function isExcludedByBaseRules(?string $table, ?string $modelType): bool
    {
        if (! config('activity-tracker.enabled', true)) {
            return true;
        }

        if ($this->trackingContext->isSuppressed()) {
            return true;
        }

        $ownTable = config('activity-tracker.table', 'activities');
        if ($table === $ownTable) {
            return true;
        }

        if ($modelType === Activity::class) {
            return true;
        }

        if ($modelType !== null && in_array($modelType, (array) config('activity-tracker.ignored_models', []), true)) {
            return true;
        }

        if ($table !== null && in_array($table, (array) config('activity-tracker.ignored_tables', []), true)) {
            return true;
        }

        return false;
    }

    /**
     * ROOT CAUSE FIX (see README § Retrieval strategy and CHANGELOG): the
     * auth-provider exclusion used to live here, keyed only on model
     * class — which silently suppressed EVERY retrieval of that class,
     * not just the framework-internal one. It has moved to
     * ActivityTrackerObserver::isAuthProviderResolution(), which runs
     * synchronously inside the real Eloquent "retrieved" event (while the
     * call stack that actually distinguishes the two cases still exists)
     * instead of here, after buffering has already erased that context.
     */
    private function trackConfigKey(string $action): ?string
    {
        return match ($action) {
            'created' => 'created',
            'updated' => 'updated',
            'deleted' => 'deleted',
            'restored' => 'restored',
            'force_deleted' => 'force_deleted',
            'retrieved', 'retrieved_many' => 'retrieved',
            'sum', 'avg', 'min', 'max' => 'aggregates',
            'bulk_updated' => 'bulk_updated',
            'bulk_deleted' => 'bulk_deleted',
            'raw_insert', 'raw_update', 'raw_delete' => 'raw_queries',
            default => null,
        };
    }

    private function dispatch(array $payload): void
    {
        if (config('activity-tracker.queue.enabled', false)) {
            StoreActivity::dispatch($payload);

            return;
        }

        $this->storage->store($payload);
    }
}
