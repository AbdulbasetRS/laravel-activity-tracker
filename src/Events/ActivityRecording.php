<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Events;

/**
 * Dispatched immediately before an activity payload is persisted. Listeners
 * may mutate the payload array by reference (e.g. to attach custom
 * metadata) before it reaches storage.
 */
final class ActivityRecording
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload)
    {
    }
}
