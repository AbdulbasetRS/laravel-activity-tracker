<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Jobs;

use Abdulbaset\ActivityTracker\Contracts\ActivityStorageInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Persists an activity payload asynchronously.
 *
 * The payload is a plain array of scalars/JSON-safe values (never a
 * serialized Eloquent model) so this job never depends on the original
 * HTTP request, auth session, or database state still being "current" by
 * the time it runs.
 */
final class StoreActivity implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public readonly array $payload)
    {
        $this->onConnection(config('activity-tracker.queue.connection'));
        $this->onQueue(config('activity-tracker.queue.queue', 'default'));
    }

    public function handle(ActivityStorageInterface $storage): void
    {
        $storage->store($this->payload);
    }
}
