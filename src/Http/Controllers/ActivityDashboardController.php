<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Http\Controllers;

use Abdulbaset\ActivityTracker\Models\Activity;
use Abdulbaset\ActivityTracker\Services\ActivityStatisticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

final class ActivityDashboardController extends Controller
{
    public function __construct(private readonly ActivityStatisticsService $statistics)
    {
    }

    public function index(): View
    {
        $recent = Activity::query()->latest('id')->limit(10)->get();

        return view('activity-tracker::dashboard.index', [
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
            'recent' => $recent,
        ]);
    }
}
