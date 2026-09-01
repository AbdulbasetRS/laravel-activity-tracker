<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Events;

use Abdulbaset\ActivityTracker\Models\Activity;

/**
 * Dispatched after an activity has been persisted (synchronously or via the
 * queue). Applications can listen to this to react to tracked operations
 * without coupling to the package's internals.
 */
final class ActivityRecorded
{
    public function __construct(public readonly Activity $activity)
    {
    }
}
