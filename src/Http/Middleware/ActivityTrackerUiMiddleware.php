<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Http\Middleware;

use Abdulbaset\ActivityTracker\Support\TrackingContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ActivityTrackerUiMiddleware
{
    public function __construct(
        private readonly TrackingContext $trackingContext,
    ) {
    }

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        return $this->trackingContext->withoutTracking(
            fn () => $next($request)
        );
    }
}