<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Feature\Ui;

use Abdulbaset\ActivityTracker\Tests\Fixtures\TestPost;

final class DashboardTest extends UiTestCase
{
    public function test_dashboard_loads(): void
    {
        $this->get(route('activity-tracker.dashboard'))->assertOk();
    }

    public function test_dashboard_shows_dynamic_totals_not_hardcoded_data(): void
    {
        TestPost::create(['title' => 'One']);
        TestPost::create(['title' => 'Two']);

        $total = \Abdulbaset\ActivityTracker\Models\Activity::query()->count();

        $response = $this->get(route('activity-tracker.dashboard'));

        $response->assertOk();
        $response->assertSee('Total activities');
        $response->assertSee((string) $total);
    }

    public function test_statistics_page_loads_with_each_period(): void
    {
        TestPost::create(['title' => 'One']);

        foreach (['today', '7', '30', '90'] as $period) {
            $this->get(route('activity-tracker.statistics', ['period' => $period]))->assertOk();
        }
    }

    public function test_assets_are_served_without_publishing(): void
    {
        $css = $this->get(route('activity-tracker.assets', ['file' => 'css/app.css']));
        $css->assertOk();
        $css->assertHeader('Content-Type', 'text/css; charset=UTF-8');

        $js = $this->get(route('activity-tracker.assets', ['file' => 'js/app.js']));
        $js->assertOk();
    }

    public function test_unknown_asset_file_is_rejected(): void
    {
        $this->get('/activity-tracker/assets/nonexistent.css')->assertNotFound();
        $this->get('/activity-tracker/assets/config/activity-tracker.php')->assertNotFound();
    }
}
