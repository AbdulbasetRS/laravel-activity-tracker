<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Services;

use Abdulbaset\ActivityTracker\Contracts\ActivityStorageInterface;
use Abdulbaset\ActivityTracker\Events\ActivityRecorded;
use Abdulbaset\ActivityTracker\Events\ActivityRecording;
use Abdulbaset\ActivityTracker\Models\Activity;
use Abdulbaset\ActivityTracker\Support\TrackingContext;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

/**
 * Default synchronous storage backend: writes an Activity row directly.
 *
 * Critically, the write itself is wrapped in TrackingContext::withoutTracking
 * so that the INSERT this class performs is never re-detected by the
 * ActivityTrackerQueryListener or ActivityTrackerObserver and turned into another
 * activity (which would recurse forever).
 */
final class ActivityTrackerRepository implements ActivityStorageInterface
{
    public function __construct(
        private readonly TrackingContext $trackingContext,
        private readonly Dispatcher $events,
    ) {
    }

    public function store(array $payload): void
    {
        $event = new ActivityRecording($payload);
        $this->events->dispatch($event);
        $payload = $event->payload;

        $this->trackingContext->withoutTracking(function () use ($payload): void {
            try {
                $activity = Activity::query()->create($payload);

                $this->events->dispatch(new ActivityRecorded($activity));
            } catch (Throwable $e) {
                // Activity logging must never break the application. Swallow
                // storage failures (e.g. missing/unmigrated table) after the
                // fact rather than letting a tracker bug take down a request.
                report($e);
            }
        });
    }
}
