<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Support;

/**
 * Formats a stored duration_ms value for display, and classifies it against
 * the configurable performance thresholds. Used by both the activities
 * table and the activity details page, so formatting stays consistent.
 */
final class DurationFormatter
{
    public static function format(?float $milliseconds): ?string
    {
        if ($milliseconds === null) {
            return null;
        }

        if ($milliseconds >= 1000) {
            return number_format($milliseconds / 1000, 2).' s';
        }

        if ($milliseconds >= 10) {
            return number_format($milliseconds, 2).' ms';
        }

        return number_format($milliseconds, 2).' ms';
    }

    /**
     * 'fast', 'normal', 'slow', or 'very_slow', or null when duration is
     * unavailable. Thresholds come from activity-tracker.performance.
     */
    public static function classify(?float $milliseconds): ?string
    {
        if ($milliseconds === null) {
            return null;
        }

        $verySlow = (float) config('activity-tracker.performance.very_slow_ms', 1000);
        $slow = (float) config('activity-tracker.performance.slow_ms', 100);

        if ($milliseconds >= $verySlow) {
            return 'very_slow';
        }

        if ($milliseconds >= $slow) {
            return 'slow';
        }

        return $milliseconds <= ($slow / 4) ? 'fast' : 'normal';
    }
}
