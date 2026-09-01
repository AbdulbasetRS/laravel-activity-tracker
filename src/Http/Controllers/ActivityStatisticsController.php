<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Http\Controllers;

use Abdulbaset\ActivityTracker\Services\ActivityStatisticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class ActivityStatisticsController extends Controller
{
    private const PERIODS = [
        'today' => 1,
        '7' => 7,
        '30' => 30,
        '90' => 90,
    ];

    public function __construct(private readonly ActivityStatisticsService $statistics)
    {
    }

    public function index(Request $request): View
    {
        $periodKey = (string) $request->string('period', '7')->value();
        $days = self::PERIODS[$periodKey] ?? 7;

        return view('activity-tracker::statistics.index', [
            'period' => array_key_exists($periodKey, self::PERIODS) ? $periodKey : '7',
            'periods' => array_keys(self::PERIODS),
            'byAction' => $this->statistics->activitiesByAction(),
            'overTime' => $this->statistics->activitiesOverTime($days),
            'topSubjects' => $this->statistics->topSubjects(),
            'topCausers' => $this->statistics->topCausers(),
        ]);
    }
}
