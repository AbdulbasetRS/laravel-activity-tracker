<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Http\Controllers;

use Abdulbaset\ActivityTracker\Models\Activity;
use Abdulbaset\ActivityTracker\Services\ActivityTrackerFilters;
use Abdulbaset\ActivityTracker\Support\TrackingContext;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

/**
 * A focused overview + entry point into the existing, already AJAX-enabled
 * activities index (pre-filtered to authentication actions) — rather than
 * duplicating the entire search/filter/sort/pagination table a second time
 * for a data set that is still, structurally, just Activity rows.
 */
final class ActivityTrackerAuthenticationController extends Controller
{
    private const AUTH_ACTIONS = [
        'login', 'login_failed', 'logout', 'authenticated',
        'password_reset', 'email_verified', 'authentication_throttled',
        'authorization_denied',
    ];

    public function __construct(private readonly TrackingContext $trackingContext)
    {
    }

    public function index(): View
    {
        $data = $this->trackingContext->withoutTracking(function () {
            $since = Carbon::now()->subDays(7);

            $counts = Activity::query()
                ->authentication()
                ->where('created_at', '>=', $since)
                ->selectRaw('action, count(*) as aggregate_count')
                ->groupBy('action')
                ->pluck('aggregate_count', 'action');

            return [
                'successfulLogins' => (int) ($counts['login'] ?? 0),
                'failedLogins' => (int) ($counts['login_failed'] ?? 0),
                'logouts' => (int) ($counts['logout'] ?? 0),
                'passwordResets' => (int) ($counts['password_reset'] ?? 0),
                'throttles' => (int) ($counts['authentication_throttled'] ?? 0),
                'authorizationDenials' => (int) ($counts['authorization_denied'] ?? 0),
                'recent' => Activity::query()->authentication()->latest('id')->limit(15)->get(),
                'authActions' => self::AUTH_ACTIONS,
                'indexUrl' => route('activity-tracker.activities.index', ['action' => self::AUTH_ACTIONS]),
            ];
        });

        return view('activity-tracker::authentication.index', $data);
    }
}
