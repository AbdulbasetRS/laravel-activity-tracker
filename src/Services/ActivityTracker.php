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
final class ActivityTracker implements ActivityLoggerInterface
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

    private function shouldTrack(string $action, ?string $table, ?string $modelType): bool
    {
        if (! config('activity-tracker.enabled', true)) {
            return false;
        }

        if ($this->trackingContext->isSuppressed()) {
            return false;
        }

        if (in_array($action, (array) config('activity-tracker.ignored_actions', []), true)) {
            return false;
        }

        $ownTable = config('activity-tracker.table', 'activities');
        if ($table === $ownTable) {
            return false;
        }

        if ($modelType === Activity::class) {
            return false;
        }

        if ($modelType !== null && in_array($modelType, (array) config('activity-tracker.ignored_models', []), true)) {
            return false;
        }

        if ($table !== null && in_array($table, (array) config('activity-tracker.ignored_tables', []), true)) {
            return false;
        }

        $trackKey = $this->trackConfigKey($action);

        if ($trackKey !== null && ! config("activity-tracker.track.{$trackKey}", true)) {
            return false;
        }

        return true;
    }

    private function trackConfigKey(string $action): ?string
    {
        return match ($action) {
            'created' => 'created',
            'updated' => 'updated',
            'deleted' => 'deleted',
            'restored' => 'restored',
            'force_deleted' => 'force_deleted',
            'retrieved', 'retrieved_many' => 'retrieved',
            'count' => 'count',
            'exists' => 'exists',
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
