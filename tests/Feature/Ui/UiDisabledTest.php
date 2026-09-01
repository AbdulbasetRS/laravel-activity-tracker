<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Feature\Ui;

use Abdulbaset\ActivityTracker\Tests\TestCase;
use Illuminate\Routing\Exceptions\UrlGenerationException;

final class UiDisabledTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('activity-tracker.ui.enabled', false);
    }

    public function test_dashboard_route_is_not_registered_when_ui_is_disabled(): void
    {
        $this->expectException(UrlGenerationException::class);

        route('activity-tracker.dashboard');
    }

    public function test_dashboard_url_returns_404_when_ui_is_disabled(): void
    {
        $this->get('/activity-tracker')->assertNotFound();
        $this->get('/activity-tracker/activities')->assertNotFound();
    }

    public function test_tracking_engine_still_works_when_ui_is_disabled(): void
    {
        \Abdulbaset\ActivityTracker\Tests\Fixtures\TestPost::create(['title' => 'Still tracked']);

        $this->assertSame(
            1,
            \Abdulbaset\ActivityTracker\Models\Activity::query()->where('action', 'created')->count()
        );
    }
}
