<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Services;

use Abdulbaset\ActivityTracker\Models\Activity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard/statistics queries. Every method here is a single, efficient
 * aggregate query — nothing loads full Activity rows into memory just to
 * count or group them.
 */
final class ActivityTrackerStatisticsService
{
    public function totalActivities(): int
    {
        return Activity::query()->count();
    }

    public function todayActivities(): int
    {
        return Activity::query()->whereDate('created_at', Carbon::today())->count();
    }

    public function weekActivities(): int
    {
        return Activity::query()->where('created_at', '>=', Carbon::now()->subDays(7))->count();
    }

    public function monthActivities(): int
    {
        return Activity::query()->where('created_at', '>=', Carbon::now()->subDays(30))->count();
    }

    public function countByAction(string $action): int
    {
        return Activity::query()->where('action', $action)->count();
    }

    /**
     * @return Collection<string, int> action => count, ordered by count desc
     */
    public function activitiesByAction(): Collection
    {
        return Activity::query()
            ->select('action', DB::raw('count(*) as aggregate_count'))
            ->groupBy('action')
            ->orderByDesc('aggregate_count')
            ->pluck('aggregate_count', 'action');
    }

    /**
     * Daily activity counts for the given period, always including days
     * with zero activities so charts don't show gaps.
     *
     * @return array<string, int> 'Y-m-d' => count
     */
    public function activitiesOverTime(int $days = 7): array
    {
        $days = max(1, min($days, 90));
        $start = Carbon::now()->subDays($days - 1)->startOfDay();

        $rows = Activity::query()
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('count(*) as aggregate_count'))
            ->where('created_at', '>=', $start)
            ->groupBy('day')
            ->pluck('aggregate_count', 'day');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->format('Y-m-d');
            $series[$date] = (int) ($rows[$date] ?? 0);
        }

        return $series;
    }

    /**
     * @return Collection<int, object{subject_type: string, aggregate_count: int}>
     */
    public function topSubjects(int $limit = 5): Collection
    {
        return Activity::query()
            ->select('subject_type', DB::raw('count(*) as aggregate_count'))
            ->whereNotNull('subject_type')
            ->groupBy('subject_type')
            ->orderByDesc('aggregate_count')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, object{causer_type: string, causer_id: mixed, aggregate_count: int}>
     */
    public function topCausers(int $limit = 5): Collection
    {
        return Activity::query()
            ->select('causer_type', 'causer_id', DB::raw('count(*) as aggregate_count'))
            ->whereNotNull('causer_id')
            ->groupBy('causer_type', 'causer_id')
            ->orderByDesc('aggregate_count')
            ->limit($limit)
            ->get();
    }
}
