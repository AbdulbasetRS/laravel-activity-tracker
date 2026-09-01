<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Services;

use Abdulbaset\ActivityTracker\Contracts\ActivityLoggerInterface;
use Abdulbaset\ActivityTracker\Support\TrackingContext;

/**
 * Converts buffered "retrieved" counts into either a single "retrieved"
 * activity (exactly one model instance was hydrated) or a single
 * "retrieved_many" activity (a collection was hydrated), regardless of how
 * many underlying queries or rows were involved.
 *
 * Must be invoked at the end of every request/console command/queue job —
 * the service provider wires this via terminating callbacks. Never invoke
 * it mid-request, or a single collection retrieval mid-flight could get
 * split across multiple flushes.
 */
final class RetrievalFlusher
{
    public function __construct(
        private readonly TrackingContext $trackingContext,
        private readonly ActivityLoggerInterface $tracker,
    ) {
    }

    public function flush(): void
    {
        $buffer = $this->trackingContext->pullRetrievalBuffer();

        foreach ($buffer as $modelClass => $info) {
            $this->flushOne($modelClass, $info);
        }
    }

    /**
     * @param  array{count: int, ids: array<int, mixed>}  $info
     */
    private function flushOne(string $modelClass, array $info): void
    {
        if ($info['count'] <= 0) {
            return;
        }

        $table = $this->resolveTable($modelClass);

        if ($info['count'] === 1) {
            if (! config('activity-tracker.retrieval.track_single', true)) {
                return;
            }

            $this->tracker->logQueryEvent('retrieved', [
                'model_type' => $modelClass,
                'model_id' => $info['ids'][0] ?? null,
                'table' => $table,
                'result_count' => 1,
            ]);

            return;
        }

        if (! config('activity-tracker.retrieval.track_many', true)) {
            return;
        }

        $data = [
            'model_type' => $modelClass,
            'table' => $table,
            'result_count' => $info['count'],
            'description' => sprintf('%d %s models were retrieved.', $info['count'], class_basename($modelClass)),
        ];

        if (config('activity-tracker.retrieval.store_ids', false)) {
            $data['metadata'] = ['ids' => $info['ids']];
        }

        $this->tracker->logQueryEvent('retrieved_many', $data);
    }

    private function resolveTable(string $modelClass): ?string
    {
        if (! class_exists($modelClass)) {
            return null;
        }

        try {
            return (new $modelClass)->getTable();
        } catch (\Throwable) {
            return null;
        }
    }
}
