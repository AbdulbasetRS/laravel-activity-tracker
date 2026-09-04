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
     * consumed by the ActivityTrackerQueryListener. When a matching query is
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
     * ActivityTrackerRetrievalFlusher. A final count of 1 becomes a "retrieved" activity;
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

    /**
     * @var array{job_name: string|null, queue_name: string|null, queue_connection: string|null, queue_attempt: int|null}
     */
    private array $jobContext = [
        'job_name' => null,
        'queue_name' => null,
        'queue_connection' => null,
        'queue_attempt' => null,
    ];

    public function setJobContext(?string $jobName, ?string $queueName, ?string $queueConnection, ?int $attempt): void
    {
        $this->jobContext = [
            'job_name' => $jobName,
            'queue_name' => $queueName,
            'queue_connection' => $queueConnection,
            'queue_attempt' => $attempt,
        ];
    }

    /**
     * @return array{job_name: string|null, queue_name: string|null, queue_connection: string|null, queue_attempt: int|null}
     */
    public function jobContext(): array
    {
        return $this->jobContext;
    }

    /**
     * High-resolution timers for measuring a tracked operation's own
     * duration (e.g. between an Eloquent "creating" hook and its matching
     * "created" hook) — never the whole HTTP request. Keyed by an arbitrary
     * integer the caller controls (typically spl_object_id($model)), so
     * concurrent operations on different model instances never collide.
     *
     * @var array<int, int> key => hrtime(true) nanoseconds at start
     */
    private array $timers = [];

    public function startTimer(int $key): void
    {
        $this->timers[$key] = hrtime(true);
    }

    /**
     * Stops and removes the timer, returning the elapsed time in
     * milliseconds (3 decimal places), or null if no timer was started for
     * this key (e.g. the corresponding pre-hook never fired).
     */
    public function stopTimer(int $key): ?float
    {
        if (! isset($this->timers[$key])) {
            return null;
        }

        $elapsedNanoseconds = hrtime(true) - $this->timers[$key];
        unset($this->timers[$key]);

        return round($elapsedNanoseconds / 1_000_000, 3);
    }

    /**
     * Exception-instance dedup: Laravel can, in rare edge cases, call the
     * handler's report() more than once for the very same exception object
     * (e.g. an application catching and manually re-reporting). Keyed by
     * spl_object_id($exception), which is stable for the object's lifetime
     * and needs no hashing of message/trace content.
     *
     * @var array<int, true>
     */
    private array $reportedExceptions = [];

    /**
     * Returns true if this is the first time this exact exception instance
     * has been claimed (and marks it claimed); false on any subsequent call
     * for the same instance.
     */
    public function claimException(int $objectId): bool
    {
        if (isset($this->reportedExceptions[$objectId])) {
            return false;
        }

        $this->reportedExceptions[$objectId] = true;

        return true;
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

    /**
     * True if requestId() has already been called during this request/job —
     * lets callers (like the status-backfill middleware) avoid generating a
     * request ID, and therefore avoid a wasted query, for a request that
     * never actually tracked anything.
     */
    public function hasRequestId(): bool
    {
        return $this->requestId !== null;
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
        $this->timers = [];
        $this->reportedExceptions = [];
    }
}
