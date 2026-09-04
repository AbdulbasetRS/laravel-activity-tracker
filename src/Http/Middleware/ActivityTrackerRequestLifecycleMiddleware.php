<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Http\Middleware;

use Abdulbaset\ActivityTracker\Models\Activity;
use Abdulbaset\ActivityTracker\Support\TrackingContext;
use Closure;
use Illuminate\Http\Request;
use Throwable;

/**
 * Backfills http_status once the response actually exists.
 *
 * Registered globally (Kernel::pushMiddleware(), not just the "web" group)
 * so it covers every HTTP route, including "api"-only applications. Most
 * activities are recorded mid-request, before a status code exists at all —
 * `terminate()` is Laravel's own mechanism for running code once the
 * response has been determined, so this is the correct integration point
 * rather than guessing or delaying activity storage itself.
 *
 * A single UPDATE, scoped to this request's request_id, catches every
 * activity (and every exception activity) recorded during it — no N+1, and
 * skipped entirely for requests that never tracked anything at all.
 */
final class ActivityTrackerRequestLifecycleMiddleware
{
    public function __construct(private readonly TrackingContext $trackingContext)
    {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        return $next($request);
    }

    public function terminate(Request $request, mixed $response): void
    {
        if (! config('activity-tracker.context.capture_status', true)) {
            return;
        }

        if (! $this->trackingContext->hasRequestId()) {
            return;
        }

        $status = $this->statusFrom($response);

        if ($status === null) {
            return;
        }

        try {
            $this->trackingContext->withoutTracking(function () use ($status) {
                Activity::query()
                    ->where('request_id', $this->trackingContext->requestId())
                    ->whereNull('http_status')
                    ->update(['http_status' => $status]);
            });
        } catch (Throwable) {
            // Backfilling is best-effort — never let it break the response
            // that has already been sent to the client.
        }
    }

    private function statusFrom(mixed $response): ?int
    {
        if (is_object($response) && method_exists($response, 'getStatusCode')) {
            return (int) $response->getStatusCode();
        }

        return null;
    }
}
