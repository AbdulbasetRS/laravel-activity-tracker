<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Feature;

use Abdulbaset\ActivityTracker\Services\ActivityStatisticsService;
use Abdulbaset\ActivityTracker\Tests\Fixtures\TestPost;
use Abdulbaset\ActivityTracker\Tests\TestCase;

final class ActivityStatisticsServiceTest extends TestCase
{
    public function test_totals_and_breakdowns_reflect_real_data(): void
    {
        TestPost::create(['title' => 'A']);
        $post = TestPost::create(['title' => 'B']);
        $post->update(['title' => 'B2']);

        $service = $this->app->make(ActivityStatisticsService::class);

        $this->assertGreaterThanOrEqual(3, $service->totalActivities());
        $this->assertSame($service->totalActivities(), $service->todayActivities());
        $this->assertGreaterThanOrEqual(2, $service->countByAction('created'));
        $this->assertGreaterThanOrEqual(1, $service->countByAction('updated'));

        $byAction = $service->activitiesByAction();
        $this->assertArrayHasKey('created', $byAction->toArray());

        $overTime = $service->activitiesOverTime(7);
        $this->assertCount(7, $overTime);
        $this->assertGreaterThanOrEqual(3, array_sum($overTime));

        $topSubjects = $service->topSubjects();
        $this->assertNotEmpty($topSubjects);
    }
}
