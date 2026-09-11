<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Listeners;

use Abdulbaset\ActivityTracker\Contracts\ActivityLoggerInterface;
use Abdulbaset\ActivityTracker\Support\TrackingContext;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Throwable;

/**
 * Observes queued broadcast operations — never overrides Laravel's
 * broadcasting behavior. Only `ShouldBroadcast` (queued) events are visible
 * this way; `ShouldBroadcastNow` events dispatch synchronously with no
 * queue-job hook to observe, and are NOT tracked in this release — see
 * README § Broadcast Monitoring for why.
 *
 * "sent" means the queued broadcast job completed without throwing — i.e.
 * the application/provider accepted the broadcast operation. It does NOT
 * mean any connected browser received or rendered it; Laravel has no
 * client-acknowledgement mechanism to observe that.
 */
final class ActivityTrackerBroadcastTracker
{
    public function __construct(
        private readonly ActivityLoggerInterface $tracker,
        private readonly TrackingContext $trackingContext,
    ) {
    }

    public function handleProcessing(JobProcessing $event): void
    {
        $this->safely(function () use ($event) {
            if ($this->isBroadcastJob($event->job)) {
                $this->trackingContext->startTimer(spl_object_id($event->job));
            }
        });
    }

    public function handleProcessed(JobProcessed $event): void
    {
        $this->safely(function () use ($event) {
            if ($this->isBroadcastJob($event->job)) {
                $this->record($event->job, 'sent');
            }
        });
    }

    public function handleFailed(JobFailed $event): void
    {
        $this->safely(function () use ($event) {
            if ($this->isBroadcastJob($event->job)) {
                $this->record($event->job, 'failed', $event->exception);
            }
        });
    }

    private function record(Job $job, string $status, ?Throwable $exception = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        $duration = $this->trackingContext->stopTimer(spl_object_id($job));
        $broadcastable = $this->resolveBroadcastable($job);
        $eventName = $this->eventNameFor($broadcastable);
        $channels = $this->channelsFor($broadcastable);

        $base = [
            'event' => $eventName,
            'duration_ms' => $duration,
            'exception_class' => $exception !== null ? $exception::class : null,
            'exception_message' => $exception?->getMessage(),
            'metadata' => [
                'queue_name' => $job->getQueue(),
            ],
        ];

        if ($channels === []) {
            // Couldn't determine channels (e.g. the broadcastable couldn't
            // be safely unserialized) — still record that a broadcast
            // operation happened, just without per-channel detail.
            $this->tracker->logBroadcastEvent($status, array_merge($base, [
                'channel' => null,
                'channel_type' => null,
            ]));

            return;
        }

        foreach ($channels as $channel) {
            $this->tracker->logBroadcastEvent($status, array_merge($base, [
                'channel' => $channel['name'],
                'channel_type' => $channel['type'],
            ]));
        }
    }

    private function isBroadcastJob(Job $job): bool
    {
        try {
            return $job->resolveName() === BroadcastEvent::class;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * The queued job's payload contains a PHP-serialized copy of the
     * BroadcastEvent command (the same data Laravel itself unserializes
     * moments before/after, within this same trusted process) — this is
     * how the actual broadcastable event object (and therefore its
     * channels/event name) is recovered, since the low-level Job contract
     * exposes only raw payload data, not the resolved command instance.
     */
    private function resolveBroadcastable(Job $job): ?object
    {
        try {
            $payload = $job->payload();
            $serializedCommand = $payload['data']['command'] ?? null;

            if (! is_string($serializedCommand)) {
                return null;
            }

            $command = @unserialize($serializedCommand);

            if (! is_object($command)) {
                return null;
            }

            // The expected case: the unserialized command IS the
            // BroadcastEvent job, with the actual broadcastable on its
            // public $event property (this matches Laravel's own internal
            // shape as of this writing).
            if ($command instanceof BroadcastEvent && is_object($command->event ?? null)) {
                return $command->event;
            }

            // Defensive fallback for a future Laravel version that renames
            // or restructures this property: if the unserialized command
            // itself is already broadcastable, use it directly; otherwise
            // scan its public properties for anything that looks like a
            // broadcastable object. Never throws — an unmatched shape
            // simply yields no per-channel detail (see the "$channels ===
            // []" branch in record()), not a broken worker.
            if ($this->looksBroadcastable($command)) {
                return $command;
            }

            foreach (get_object_vars($command) as $value) {
                if (is_object($value) && $this->looksBroadcastable($value)) {
                    return $value;
                }
            }

            return null;
        } catch (Throwable) {
            return null;
        }
    }

    private function looksBroadcastable(object $value): bool
    {
        return method_exists($value, 'broadcastOn');
    }

    private function eventNameFor(?object $broadcastable): ?string
    {
        if ($broadcastable === null) {
            return null;
        }

        try {
            return method_exists($broadcastable, 'broadcastAs')
                ? ($broadcastable->broadcastAs() ?? class_basename($broadcastable))
                : class_basename($broadcastable);
        } catch (Throwable) {
            return class_basename($broadcastable);
        }
    }

    /**
     * @return array<int, array{name: string, type: string}>
     */
    private function channelsFor(?object $broadcastable): array
    {
        if ($broadcastable === null || ! method_exists($broadcastable, 'broadcastOn')) {
            return [];
        }

        try {
            $channels = $broadcastable->broadcastOn();
            $channels = is_array($channels) ? $channels : [$channels];

            $result = [];

            foreach ($channels as $channel) {
                if (! $channel instanceof Channel) {
                    continue;
                }

                $result[] = [
                    'name' => $channel->name,
                    'type' => match (true) {
                        $channel instanceof PresenceChannel => 'presence',
                        $channel instanceof PrivateChannel => 'private',
                        default => 'public',
                    },
                ];
            }

            return $result;
        } catch (Throwable) {
            return [];
        }
    }

    private function enabled(): bool
    {
        return (bool) config('activity-tracker.broadcast_monitoring.enabled', true);
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable) {
            // Broadcast monitoring must never break a queue worker.
        }
    }
}
