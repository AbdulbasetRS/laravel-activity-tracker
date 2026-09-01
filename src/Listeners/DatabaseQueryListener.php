<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Listeners;

use Abdulbaset\ActivityTracker\Contracts\ActivityLoggerInterface;
use Abdulbaset\ActivityTracker\Contracts\QueryClassifierInterface;
use Abdulbaset\ActivityTracker\Contracts\SensitiveDataSanitizerInterface;
use Abdulbaset\ActivityTracker\Support\TrackingContext;
use Illuminate\Database\Events\QueryExecuted;

/**
 * Handles Laravel's DB::listen(QueryExecuted) event.
 *
 * This is the layer that catches everything Eloquent model events cannot:
 * count()/exists()/sum()/avg()/min()/max(), mass "query builder" updates
 * and deletes, and raw DB::table() operations.
 *
 * Plain SELECT queries are intentionally never logged here — Eloquent's
 * retrieved/created/updated events already cover model-level read/write
 * semantics, and logging every SELECT in addition would both duplicate
 * those activities and flood the activity table with noise.
 *
 * KNOWN LIMITATION: Laravel's QueryExecuted event exposes only the SQL,
 * bindings, and timing — not the query's return value or affected-row
 * count. This listener can reliably say *that* a count/sum/exists/bulk
 * operation happened and against which table, but it cannot report the
 * resulting number without deeper, driver-specific interception that this
 * package does not attempt. `result_count` / the numeric result therefore
 * stays null for query-listener-sourced activities; this is documented in
 * the README rather than faked.
 */
final class DatabaseQueryListener
{
    public function __construct(
        private readonly QueryClassifierInterface $classifier,
        private readonly ActivityLoggerInterface $tracker,
        private readonly TrackingContext $trackingContext,
        private readonly SensitiveDataSanitizerInterface $sanitizer,
    ) {
    }

    public function handle(QueryExecuted $event): void
    {
        if ($this->trackingContext->isSuppressed()) {
            return;
        }

        $sql = $event->sql;
        $classification = $this->classifier->classify($sql);

        if ($classification === 'select' || $classification === 'unknown') {
            return;
        }

        $table = $this->classifier->extractTable($sql);

        if (in_array($classification, ['insert', 'update', 'delete'], true)) {
            $this->handleMutation($classification, $table, $sql, $event);

            return;
        }

        // count / exists / sum / avg / min / max
        $this->tracker->logQueryEvent($classification, [
            'table' => $table,
            'query' => $this->maybeQuery($sql),
            'query_type' => $classification,
            'metadata' => $this->maybeBindings($event),
        ]);
    }

    private function handleMutation(string $classification, ?string $table, string $sql, QueryExecuted $event): void
    {
        // If an Eloquent lifecycle hook (creating/updating/deleting/
        // restoring/forceDeleting) already registered an expectation for
        // this exact query shape, the paired post-hook (created/updated/
        // deleted/restored/forceDeleted) is the authoritative activity —
        // skip logging this raw query to avoid double-recording the same
        // logical operation.
        if ($this->trackingContext->consumeExpectedQuery($classification, $table)) {
            return;
        }

        $action = match ($classification) {
            'update' => 'bulk_updated',
            'delete' => 'bulk_deleted',
            'insert' => 'raw_insert',
            default => null,
        };

        if ($action === null) {
            return;
        }

        $this->tracker->logQueryEvent($action, [
            'table' => $table,
            'query' => $this->maybeQuery($sql),
            'query_type' => $classification,
            'metadata' => $this->maybeBindings($event),
        ]);
    }

    private function maybeQuery(string $sql): ?string
    {
        return config('activity-tracker.query_log.store_sql', true) ? $sql : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function maybeBindings(QueryExecuted $event): ?array
    {
        if (! config('activity-tracker.query_log.store_bindings', false)) {
            return null;
        }

        return ['bindings' => $this->sanitizer->sanitizeBindings($event->bindings)];
    }
}
