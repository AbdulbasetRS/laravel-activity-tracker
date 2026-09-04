<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Http\Controllers;

use Abdulbaset\ActivityTracker\Services\ActivityTrackerStatisticsService;
use Abdulbaset\ActivityTracker\Support\TrackingContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class ActivityTrackerStatisticsController extends Controller
{
    private const PERIODS = [
        'today' => 1,
        '7' => 7,
        '30' => 30,
        '90' => 90,
    ];

    public function __construct(
        private readonly ActivityTrackerStatisticsService $statistics,
        private readonly TrackingContext $trackingContext,
    ) {
    }

    public function index(Request $request): View
    {
        $periodKey = (string) $request->string('period', '7')->value();
        $days = self::PERIODS[$periodKey] ?? 7;

        // See ActivityTrackerDashboardController::index() — statistics
        // queries are internal package reads, never application activities.
        $data = $this->trackingContext->withoutTracking(fn () => [
            'byAction' => $this->statistics->activitiesByAction(),
            'overTime' => $this->statistics->activitiesOverTime($days),
            'topSubjects' => $this->statistics->topSubjects(),
            'topCausers' => $this->statistics->topCausers(),
        ]);

        return view('activity-tracker::statistics.index', array_merge($data, [
            'period' => array_key_exists($periodKey, self::PERIODS) ? $periodKey : '7',
            'periods' => array_keys(self::PERIODS),
        ]));
    }
}
