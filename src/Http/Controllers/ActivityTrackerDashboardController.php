<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Http\Controllers;

use Abdulbaset\ActivityTracker\Models\Activity;
use Abdulbaset\ActivityTracker\Services\ActivityTrackerStatisticsService;
use Abdulbaset\ActivityTracker\Support\TrackingContext;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

final class ActivityTrackerDashboardController extends Controller
{
    public function __construct(
        private readonly ActivityTrackerStatisticsService $statistics,
        private readonly TrackingContext $trackingContext,
    ) {
    }

    public function index(): View
    {
        // The dashboard is a CONSUMER of activity data, not an application
        // action — loading Activity rows/aggregates to render itself must
        // never become a tracked activity in its own right. The Activity
        // model is already globally excluded (see
        // ActivityTrackerManager::isExcludedByBaseRules); this wrap makes
        // that guarantee explicit at the call site as well.
        $data = $this->trackingContext->withoutTracking(fn () => [
            'total' => $this->statistics->totalActivities(),
            'today' => $this->statistics->todayActivities(),
            'week' => $this->statistics->weekActivities(),
            'month' => $this->statistics->monthActivities(),
            'created' => $this->statistics->countByAction('created'),
            'updated' => $this->statistics->countByAction('updated'),
            'deleted' => $this->statistics->countByAction('deleted'),
            'retrieved' => $this->statistics->countByAction('retrieved') + $this->statistics->countByAction('retrieved_many'),
            'byAction' => $this->statistics->activitiesByAction(),
            'overTime' => $this->statistics->activitiesOverTime(7),
            'recent' => Activity::query()->latest('id')->limit(10)->get(),
        ]);

        return view('activity-tracker::dashboard.index', $data);
    }
}
