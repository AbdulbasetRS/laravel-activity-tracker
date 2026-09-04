<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Services;

use Abdulbaset\ActivityTracker\Contracts\ActivityLoggerInterface;
use Abdulbaset\ActivityTracker\Support\TrackingContext;
use Throwable;

/**
 * Policy layer for exception tracking: decides WHETHER a reported exception
 * becomes an activity (enabled?, suppressed?, an ignored/"expected"
 * exception class?, already recorded once already?) before delegating the
 * actual recording to ActivityLoggerInterface::logException().
 *
 * Called from ActivityTrackerExceptionHandlerDecorator::report() — see that
 * class for why this integration point is safe (never replaces Laravel's
 * own exception handling, never swallows the original exception).
 */
final class ActivityTrackerExceptionService
{
    public function __construct(
        private readonly ActivityLoggerInterface $tracker,
        private readonly TrackingContext $trackingContext,
    ) {
    }

    public function handle(Throwable $exception): void
    {
        if (! config('activity-tracker.enabled', true)) {
            return;
        }

        if (! config('activity-tracker.exceptions.enabled', true)) {
            return;
        }

        if ($this->trackingContext->isSuppressed()) {
            return;
        }

        if ($this->isIgnored($exception)) {
            return;
        }

        // Laravel can, in rare cases, call report() more than once for the
        // very same exception instance. Dedup by object identity — never by
        // message/trace content, which two unrelated exceptions could share.
        if (! $this->trackingContext->claimException(spl_object_id($exception))) {
            return;
        }

        try {
            $this->tracker->logException($exception);
        } catch (Throwable) {
            // Recording the exception must never itself throw, and must
            // never prevent Laravel's own handler from still reporting the
            // ORIGINAL exception normally — see the decorator.
        }
    }

    private function isIgnored(Throwable $exception): bool
    {
        foreach ((array) config('activity-tracker.exceptions.ignored_exceptions', []) as $ignoredClass) {
            if (is_string($ignoredClass) && class_exists($ignoredClass) && $exception instanceof $ignoredClass) {
                return true;
            }
        }

        return false;
    }
}
