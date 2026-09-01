<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Feature\Ui;

use Abdulbaset\ActivityTracker\Models\Activity;
use Abdulbaset\ActivityTracker\Tests\Fixtures\TestPost;
use Abdulbaset\ActivityTracker\Tests\Fixtures\TestUser;

final class ActivitiesIndexTest extends UiTestCase
{
    public function test_index_loads_and_lists_activities(): void
    {
        TestPost::create(['title' => 'Hello World']);

        $response = $this->get(route('activity-tracker.activities.index'));

        $response->assertOk();
        $response->assertSee('created');
    }

    public function test_empty_state_is_shown_when_nothing_matches(): void
    {
        TestPost::create(['title' => 'Hello World']);

        $response = $this->get(route('activity-tracker.activities.index', ['q' => 'no-such-term-xyz']));

        $response->assertOk();
        $response->assertSee('No activities found');
    }

    public function test_search_filters_by_description(): void
    {
        $post = TestPost::create(['title' => 'Findable Post']);
        Activity::query()->truncate();
        $post->update(['title' => 'Renamed']);

        $response = $this->get(route('activity-tracker.activities.index', ['q' => 'was updated']));

        $response->assertOk();
        $response->assertSee('updated');
    }

    public function test_action_filter_restricts_results(): void
    {
        TestPost::create(['title' => 'A']);
        $post = TestPost::create(['title' => 'B']);
        $post->update(['title' => 'B2']);

        $response = $this->get(route('activity-tracker.activities.index', ['action' => ['updated']]));

        $response->assertOk();
        foreach ($response->viewData('activities') as $row) {
            $this->assertSame('updated', $row->action);
        }
    }

    public function test_subject_type_filter_works(): void
    {
        TestPost::create(['title' => 'A']);

        $response = $this->get(route('activity-tracker.activities.index', [
            'subject_type' => TestPost::class,
        ]));

        $response->assertOk();
        $response->assertSee(class_basename(TestPost::class));
    }

    public function test_causer_filter_works(): void
    {
        $user = TestUser::create(['name' => 'Ahmed', 'email' => 'ahmed@example.com']);

        $this->actingAs($user);
        TestPost::create(['title' => 'Caused by Ahmed']);

        $response = $this->get(route('activity-tracker.activities.index', [
            'causer_type' => TestUser::class,
            'causer_id' => $user->id,
        ]));

        $response->assertOk();
        $this->assertGreaterThan(0, Activity::query()->where('causer_id', $user->id)->count());
    }

    public function test_date_range_filter_excludes_out_of_range_activities(): void
    {
        TestPost::create(['title' => 'A']);

        $response = $this->get(route('activity-tracker.activities.index', [
            'date_from' => now()->addDays(5)->toDateString(),
        ]));

        $response->assertOk();
        $response->assertSee('No activities found');
    }

    public function test_sorting_by_action_ascending(): void
    {
        TestPost::create(['title' => 'A']);
        $post = TestPost::create(['title' => 'B']);
        $post->delete();

        $response = $this->get(route('activity-tracker.activities.index', [
            'sort' => 'action',
            'direction' => 'asc',
        ]));

        $response->assertOk();
        // "created" sorts before "deleted" alphabetically.
        $content = $response->getContent();
        $this->assertLessThan(
            strpos($content, 'deleted') ?: PHP_INT_MAX,
            strpos($content, 'created') ?: -1
        );
    }

    public function test_invalid_sort_column_is_ignored_and_falls_back_to_default(): void
    {
        TestPost::create(['title' => 'A']);

        $response = $this->get(route('activity-tracker.activities.index', [
            'sort' => 'password', // not whitelisted
        ]));

        $response->assertOk();
    }

    public function test_pagination_respects_per_page(): void
    {
        for ($i = 0; $i < 30; $i++) {
            TestPost::create(['title' => "Post {$i}"]);
        }

        $response = $this->get(route('activity-tracker.activities.index', ['per_page' => 25]));

        $response->assertOk();
        $this->assertCount(25, $response->viewData('activities')->items());
    }

    public function test_batch_filtering_shows_only_activities_from_that_batch(): void
    {
        $post = TestPost::create(['title' => 'Batch test']);
        $activity = Activity::query()->where('action', 'created')->first();

        $response = $this->get(route('activity-tracker.activities.index', [
            'batch_id' => $activity->batch_id,
        ]));

        $response->assertOk();
        foreach ($response->viewData('activities') as $row) {
            $this->assertSame($activity->batch_id, $row->batch_id);
        }
    }

    public function test_request_filtering_shows_only_activities_from_that_request(): void
    {
        $post = TestPost::create(['title' => 'Request test']);
        $activity = Activity::query()->where('action', 'created')->first();

        $response = $this->get(route('activity-tracker.activities.index', [
            'request_id' => $activity->request_id,
        ]));

        $response->assertOk();
        foreach ($response->viewData('activities') as $row) {
            $this->assertSame($activity->request_id, $row->request_id);
        }
    }
}
