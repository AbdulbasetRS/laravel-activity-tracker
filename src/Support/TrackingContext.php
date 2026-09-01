<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Support;

use Illuminate\Support\Str;

/**
 * Request/job-scoped state container for the tracker.
 *
 * This class intentionally avoids a single global boolean flag. Tracking can
 * be suppressed in nested layers (e.g. the storage layer suppresses tracking
 * while it writes the Activity model itself), so suppression is modeled as a
 * depth counter rather than an on/off switch. This makes it safe for nested
 * calls: only when the counter returns to zero is tracking re-enabled.
 *
 * All state here is instance state, and the instance is bound as a singleton
 * in the container. Queue workers must call reset() between jobs (handled by
 * the service provider via job lifecycle hooks) so that no causer, batch, or
 * request identifier ever leaks from one job to the next.
 */
final class TrackingContext
{
    private int $suppressionDepth = 0;

    private ?string $batchId = null;

    private ?string $requestId = null;

    /**
     * Counts of "an authoritative Eloquent lifecycle event is about to issue
     * a SQL statement of this shape for this table" pushed by the observer's
     * pre-hooks (creating/updating/deleting/restoring/forceDeleting) and
     * consumed by the DatabaseQueryListener. When a matching query is
     * consumed, the query listener skips it entirely because the paired
     * post-hook (created/updated/deleted/...) already logs the richer,
     * authoritative activity. This is the correlation mechanism that keeps
     * a single model save() from producing multiple unrelated activities.
     *
     * Keyed by "{queryType}:{table}".
     *
     * @var array<string, int>
     */
    private array $expectedQueryCounts = [];

    /**
     * Buffered Eloquent "retrieved" events, keyed by model class. Rather
     * than logging every retrieval immediately (which would create one
     * activity per row for a 10,000-row collection), retrievals accumulate
     * here and are flushed once at the end of the request/job/command via
     * RetrievalFlusher. A final count of 1 becomes a "retrieved" activity;
     * more than 1 becomes a single "retrieved_many" activity.
     *
     * @var array<class-string, array{count: int, ids: array<int, mixed>}>
     */
    private array $retrievalBuffer = [];

    /**
     * Set true for the duration of a queue job (JobProcessing -> JobProcessed)
     * so activities recorded during that window can be labeled with an
     * accurate execution context (queue vs. CLI vs. HTTP) instead of being
     * indistinguishable from a plain console command. Deliberately NOT
     * cleared by reset() — the service provider sets/clears it explicitly
     * around each job so the two don't race against each other.
     */
    private bool $inQueueJob = false;

    public function markInQueueJob(bool $value): void
    {
        $this->inQueueJob = $value;
    }

    public function isInQueueJob(): bool
    {
        return $this->inQueueJob;
    }

    public function isSuppressed(): bool
    {
        return $this->suppressionDepth > 0;
    }

    /**
     * Run a callback with tracking suppressed. Safe to nest.
     */
    public function withoutTracking(callable $callback): mixed
    {
        $this->suppressionDepth++;

        try {
            return $callback();
        } finally {
            $this->suppressionDepth--;
        }
    }

    public function batchId(): string
    {
        return $this->batchId ??= (string) Str::uuid();
    }

    public function requestId(): string
    {
        return $this->requestId ??= (string) Str::uuid();
    }

    public function expectQuery(string $type, string $table): void
    {
        $key = "{$type}:{$table}";
        $this->expectedQueryCounts[$key] = ($this->expectedQueryCounts[$key] ?? 0) + 1;
    }

    /**
     * Attempt to consume a previously registered expectation. Returns true
     * if a matching Eloquent-driven expectation existed (caller should skip
     * logging this query directly), false otherwise (caller should treat
     * the query as an unrelated/bulk/raw operation).
     */
    public function consumeExpectedQuery(string $type, ?string $table): bool
    {
        if ($table === null) {
            return false;
        }

        $key = "{$type}:{$table}";

        if (($this->expectedQueryCounts[$key] ?? 0) > 0) {
            $this->expectedQueryCounts[$key]--;

            if ($this->expectedQueryCounts[$key] === 0) {
                unset($this->expectedQueryCounts[$key]);
            }

            return true;
        }

        return false;
    }

    public function bufferRetrieval(string $modelClass, mixed $id): void
    {
        if (! isset($this->retrievalBuffer[$modelClass])) {
            $this->retrievalBuffer[$modelClass] = ['count' => 0, 'ids' => []];
        }

        $this->retrievalBuffer[$modelClass]['count']++;

        $maxIds = (int) config('activity-tracker.retrieval.max_ids', 100);

        if (count($this->retrievalBuffer[$modelClass]['ids']) < $maxIds) {
            $this->retrievalBuffer[$modelClass]['ids'][] = $id;
        }
    }

    /**
     * Return the buffered retrievals and clear the buffer.
     *
     * @return array<class-string, array{count: int, ids: array<int, mixed>}>
     */
    public function pullRetrievalBuffer(): array
    {
        $buffer = $this->retrievalBuffer;
        $this->retrievalBuffer = [];

        return $buffer;
    }

    /**
     * Reset all per-request/per-job state. Must be called between queue jobs
     * and is safe to call at the start of each HTTP request/console command.
     */
    public function reset(): void
    {
        $this->suppressionDepth = 0;
        $this->batchId = null;
        $this->requestId = null;
        $this->expectedQueryCounts = [];
        $this->retrievalBuffer = [];
    }
}
