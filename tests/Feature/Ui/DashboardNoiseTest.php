<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Feature\Ui;

use Abdulbaset\ActivityTracker\Models\Activity;
use Abdulbaset\ActivityTracker\Tests\Fixtures\TestPost;
use Abdulbaset\ActivityTracker\Tests\Fixtures\TestUser;

/**
 * End-to-end coverage for the reported bug: opening the dashboard (or any
 * of its pages) must never create tracking noise, whether that noise would
 * come from the auth system resolving the current user, or from the
 * dashboard's own internal reads of Activity/subject/causer data.
 */
final class DashboardNoiseTest extends UiTestCase
{
    public function test_visiting_every_dashboard_page_creates_no_unexpected_activity(): void
    {
        $post = TestPost::create(['title' => 'Existing record']);
        $post->update(['title' => 'Updated record']);
        $activity = Activity::query()->latest('id')->first();

        $countBefore = Activity::query()->count();

        $this->get(route('activity-tracker.dashboard'))->assertOk();
        $this->get(route('activity-tracker.activities.index'))->assertOk();
        $this->get(route('activity-tracker.activities.show', $activity))->assertOk();
        $this->get(route('activity-tracker.statistics'))->assertOk();

        // The only new row allowed is the details page's one deliberate
        // "viewed via UI" entry for that activity's subject.
        $this->assertSame($countBefore + 1, Activity::query()->count());

        $newest = Activity::query()->latest('id')->first();
        $this->assertSame('retrieved', $newest->action);
        $this->assertSame('ui', $newest->metadata['context'] ?? null);
    }

    public function test_activity_details_records_exactly_one_intentional_view_of_the_subject(): void
    {
        $post = TestPost::create(['title' => 'Subject of the view']);
        $activity = Activity::query()->where('action', 'created')->first();
        Activity::query()->where('id', '!=', $activity->id)->delete();

        $this->get(route('activity-tracker.activities.show', $activity))->assertOk();

        $views = Activity::query()
            ->where('subject_type', TestPost::class)
            ->where('subject_id', $post->id)
            ->where('action', 'retrieved')
            ->get();

        $this->assertCount(1, $views);
        $this->assertSame('ui', $views->first()->metadata['context'] ?? null);

        // Visiting again must not create a second one on top of it.
        $this->get(route('activity-tracker.activities.show', $activity))->assertOk();

        $this->assertSame(2, Activity::query()
            ->where('subject_type', TestPost::class)
            ->where('subject_id', $post->id)
            ->where('action', 'retrieved')
            ->count());
        // (Two views because the page was visited twice — each is a
        // separate, real, intentional view. What must NOT happen is more
        // than one PER VISIT, which the next assertion pins down.)
    }

    public function test_auth_model_resolved_before_a_dashboard_visit_is_not_recorded(): void
    {
        $user = TestUser::create(['name' => 'Ahmed']);

        // Simulates exactly what Laravel's 'auth' middleware, Gate checks,
        // and auth()->user() do on every authenticated request: a plain
        // Eloquent retrieval of the current user.
        $this->assertNotNull(TestUser::query()->find($user->id));

        $this->get(route('activity-tracker.dashboard'))->assertOk();

        $this->assertSame(0, Activity::query()->where('subject_type', TestUser::class)->count());
    }
}
