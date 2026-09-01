<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Feature\Ui;

use Abdulbaset\ActivityTracker\Models\Activity;
use Abdulbaset\ActivityTracker\Tests\Fixtures\TestPost;
use Abdulbaset\ActivityTracker\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\DB;

final class ActivityDetailsTest extends UiTestCase
{
    public function test_details_page_loads_for_a_created_activity(): void
    {
        $post = TestPost::create(['title' => 'Details Test']);
        $activity = Activity::query()->where('action', 'created')->first();

        $response = $this->get(route('activity-tracker.activities.show', $activity));

        $response->assertOk();
        $response->assertSee('Details Test');
    }

    public function test_details_page_shows_old_and_new_values_for_update(): void
    {
        $post = TestPost::create(['title' => 'Original Title']);
        $post->update(['title' => 'New Title']);

        $activity = Activity::query()->where('action', 'updated')->latest('id')->first();

        $response = $this->get(route('activity-tracker.activities.show', $activity));

        $response->assertOk();
        $response->assertSee('Original Title');
        $response->assertSee('New Title');
    }

    public function test_deleted_subject_does_not_break_the_page(): void
    {
        $post = TestPost::create(['title' => 'Will be hard-deleted']);
        $activity = Activity::query()->where('action', 'created')->first();

        // Remove the underlying row directly (bypassing Eloquent) so the
        // subject truly no longer exists, independent of the tracker.
        DB::table('test_posts')->where('id', $post->id)->delete();

        $response = $this->get(route('activity-tracker.activities.show', $activity));

        $response->assertOk();
        $response->assertSee('Model no longer exists');
    }

    public function test_deleted_causer_does_not_break_the_page(): void
    {
        $user = TestUser::create(['name' => 'Temp User']);
        $this->actingAs($user);

        TestPost::create(['title' => 'Caused by temp user']);
        $activity = Activity::query()->where('action', 'created')->first();

        $user->delete();

        $response = $this->get(route('activity-tracker.activities.show', $activity));

        $response->assertOk();
        $response->assertSee('Causer no longer exists');
    }

    public function test_sensitive_values_are_never_displayed_on_the_details_page(): void
    {
        $post = TestPost::create(['title' => 'Secure', 'password' => 'super-secret-value']);
        $post->update(['password' => 'another-secret']);

        $activity = Activity::query()->where('action', 'updated')->latest('id')->first();

        $response = $this->get(route('activity-tracker.activities.show', $activity));

        $response->assertOk();
        $response->assertDontSee('super-secret-value');
        $response->assertDontSee('another-secret');
    }

    public function test_batch_and_request_links_are_present_when_ids_exist(): void
    {
        TestPost::create(['title' => 'Correlated']);
        $activity = Activity::query()->where('action', 'created')->first();

        $response = $this->get(route('activity-tracker.activities.show', $activity));

        $response->assertOk();
        $response->assertSee('View batch activities');
        $response->assertSee('View request activities');
    }
}
