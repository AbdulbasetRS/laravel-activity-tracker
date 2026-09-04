<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Feature\Ui;

use Abdulbaset\ActivityTracker\Models\Activity;
use Abdulbaset\ActivityTracker\Tests\Fixtures\TestPost;

final class ActivitiesAjaxTest extends UiTestCase
{
    public function test_ajax_request_returns_json_partial_instead_of_a_full_page(): void
    {
        TestPost::create(['title' => 'A']);

        $response = $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('activity-tracker.activities.index'));

        $response->assertOk();
        $response->assertJsonStructure(['success', 'data' => ['html', 'total', 'hasActiveFilters']]);
        $response->assertJson(['success' => true]);
        $this->assertStringContainsString('created', $response->json('data.html'));
    }

    public function test_non_ajax_request_still_returns_a_full_html_page(): void
    {
        TestPost::create(['title' => 'A']);

        $response = $this->get(route('activity-tracker.activities.index'));

        $response->assertOk();
        $response->assertViewIs('activity-tracker::activities.index');
    }

    public function test_ajax_search_filters_results(): void
    {
        TestPost::create(['title' => 'Findable Alpha']);
        Activity::query()->truncate();
        TestPost::first()->update(['title' => 'Renamed Beta']);

        $response = $this->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->get(route('activity-tracker.activities.index', ['q' => 'was updated']));

        $response->assertOk();
        $this->assertStringContainsString('updated', $response->json('data.html'));
    }

    public function test_sorting_by_id_works(): void
    {
        $first = TestPost::create(['title' => 'First']);
        $second = TestPost::create(['title' => 'Second']);

        $response = $this->get(route('activity-tracker.activities.index', [
            'sort' => 'id',
            'direction' => 'desc',
        ]));

        $response->assertOk();
        $ids = $response->viewData('activities')->pluck('id')->all();
        $sorted = $ids;
        rsort($sorted);
        $this->assertSame($sorted, $ids);
    }

    public function test_sort_parameter_is_whitelisted_against_malicious_input(): void
    {
        TestPost::create(['title' => 'A']);

        foreach (['users.password', 'DROP TABLE activities;--', '1; SELECT 1', 'subject_id, (SELECT 1)'] as $malicious) {
            $response = $this->get(route('activity-tracker.activities.index', ['sort' => $malicious]));
            $response->assertOk();
            $response->assertSee('activities'); // page still renders normally, no SQL error
        }
    }

    public function test_direction_parameter_is_restricted_to_asc_or_desc(): void
    {
        TestPost::create(['title' => 'A']);

        $response = $this->get(route('activity-tracker.activities.index', [
            'sort' => 'id',
            'direction' => 'TRUNCATE TABLE activities',
        ]));

        $response->assertOk();
        $this->assertSame(1, Activity::query()->count());
    }
}
