<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Observers;

use Abdulbaset\ActivityTracker\Contracts\ActivityLoggerInterface;
use Abdulbaset\ActivityTracker\Contracts\ActivityTransformerInterface;
use Abdulbaset\ActivityTracker\Support\TrackingContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Listens to Laravel's wildcard Eloquent event bus ("eloquent.*") rather
 * than being registered per-model. This is what makes tracking automatic:
 * every model in the application fires these events natively, with zero
 * traits, base classes, or per-model registration required.
 *
 * Event name shape: "eloquent.{hook}: {ModelClass}", e.g.
 * "eloquent.created: App\Models\User".
 *
 * Two Laravel internals make this more subtle than a flat hook -> action
 * map:
 *
 * - `SoftDeletes::restore()` sets the column and then calls `$this->save()`
 *   internally, which means a restore ALSO fires the full "updating"/
 *   "updated" pair. Left unhandled, one restore() call would produce both
 *   an "updated" activity and a "restored" activity for the same change.
 * - `SoftDeletes::forceDelete()` calls `$this->delete()` internally, which
 *   fires the generic "deleting"/"deleted" pair BEFORE "forceDeleted" is
 *   fired. Left unhandled, one forceDelete() call would produce both a
 *   "deleted" activity and a "force_deleted" activity.
 *
 * Both cases are resolved the same way: mark the model as "in progress" on
 * the outer operation's hook, and have the inner generic hook check that
 * marker and defer to the outer, more specific hook instead of logging its
 * own activity.
 */
final class EloquentActivityObserver
{
    /**
     * @var array<int, true> spl_object_id() => true, for models currently
     *                       inside SoftDeletes::restore()'s internal save().
     */
    private array $restoringModels = [];

    /**
     * @var array<int, true> spl_object_id() => true, for models currently
     *                       inside SoftDeletes::forceDelete()'s internal
     *                       delete().
     */
    private array $forceDeletingModels = [];

    /**
     * Diff captured from restore()'s internal "updating"/"updated" pair,
     * held briefly so the "restored" hook can attach it as changed_values
     * instead of the data being discarded.
     *
     * @var array<int, array{old_values: array<string, mixed>, new_values: array<string, mixed>, changed_values: array<string, mixed>}>
     */
    private array $pendingRestoreDiffs = [];

    public function __construct(
        private readonly ActivityLoggerInterface $tracker,
        private readonly ActivityTransformerInterface $transformer,
        private readonly TrackingContext $trackingContext,
    ) {
    }

    /**
     * Registered as the handler for the "eloquent.*" wildcard event.
     *
     * @param  array<int, mixed>  $payload
     */
    public function handle(string $eventName, array $payload): void
    {
        [$hook, ] = $this->parseEventName($eventName);

        if ($hook === null) {
            return;
        }

        $model = $payload[0] ?? null;

        if (! $model instanceof Model) {
            return;
        }

        match ($hook) {
            'retrieved' => $this->trackingContext->bufferRetrieval($model::class, $model->getKey()),
            'creating' => $this->trackingContext->expectQuery('insert', $model->getTable()),
            'updating' => $this->trackingContext->expectQuery('update', $model->getTable()),
            'restoring' => $this->restoringModels[spl_object_id($model)] = true,
            'deleting' => $this->handleDeletingHook($model),
            'created' => $this->handleCreated($model),
            'updated' => $this->handleUpdated($model),
            'deleted' => $this->handleDeleted($model),
            'restored' => $this->handleRestored($model),
            'forceDeleted' => $this->handleForceDeleted($model),
            default => null, // saving/saved/booting/booted/replicating/... are intentionally ignored
        };
    }

    private function handleDeletingHook(Model $model): void
    {
        $isForceDeleting = $this->isForceDeleting($model);

        if ($isForceDeleting) {
            $this->forceDeletingModels[spl_object_id($model)] = true;
        }

        $queryType = $isForceDeleting || ! $this->usesSoftDeletes($model) ? 'delete' : 'update';

        $this->trackingContext->expectQuery($queryType, $model->getTable());
    }

    private function handleCreated(Model $model): void
    {
        $this->tracker->logModelEvent('created', $model, [
            'new_values' => $this->transformer->attributesFor($model),
        ]);
    }

    private function handleUpdated(Model $model): void
    {
        $id = spl_object_id($model);

        $diff = $this->transformer->diffFor($model);

        if (isset($this->restoringModels[$id])) {
            // This "updated" pair is a side effect of restore()'s internal
            // save() call. Hand the diff to the "restored" hook instead of
            // discarding it or logging a separate "updated" activity.
            unset($this->restoringModels[$id]);

            if ($diff['changed_values'] !== []) {
                $this->pendingRestoreDiffs[$id] = $diff;
            }

            return;
        }

        // Nothing meaningful changed (e.g. a resaved model with identical
        // attributes) — do not record a no-op update.
        if ($diff['changed_values'] === []) {
            return;
        }

        $this->tracker->logModelEvent('updated', $model, $diff);
    }

    private function handleDeleted(Model $model): void
    {
        $id = spl_object_id($model);

        if (isset($this->forceDeletingModels[$id])) {
            // This "deleted" event is a side effect of forceDelete()'s
            // internal delete() call. The "forceDeleted" hook (fired right
            // after this one) logs the authoritative activity instead.
            return;
        }

        $this->tracker->logModelEvent('deleted', $model, [
            'old_values' => $this->transformer->attributesFor($model),
        ]);
    }

    private function handleRestored(Model $model): void
    {
        $id = spl_object_id($model);
        unset($this->restoringModels[$id]);

        $extra = $this->pendingRestoreDiffs[$id] ?? [];
        unset($this->pendingRestoreDiffs[$id]);

        $this->tracker->logModelEvent('restored', $model, $extra);
    }

    private function handleForceDeleted(Model $model): void
    {
        unset($this->forceDeletingModels[spl_object_id($model)]);

        $this->tracker->logModelEvent('force_deleted', $model, [
            'old_values' => $this->transformer->attributesFor($model),
        ]);
    }

    private function usesSoftDeletes(Model $model): bool
    {
        return method_exists($model, 'trashed');
    }

    /**
     * SoftDeletes exposes a public `$forceDeleting` property specifically
     * so external code can detect this mid-operation. We read it
     * defensively in case a given Laravel version changes its visibility.
     */
    private function isForceDeleting(Model $model): bool
    {
        return property_exists($model, 'forceDeleting') && $model->forceDeleting === true;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function parseEventName(string $eventName): array
    {
        if (! str_starts_with($eventName, 'eloquent.')) {
            return [null, null];
        }

        $withoutPrefix = substr($eventName, strlen('eloquent.'));

        [$hook, $modelClass] = array_pad(explode(':', $withoutPrefix, 2), 2, null);

        return [trim((string) $hook), $modelClass !== null ? trim($modelClass) : null];
    }
}
