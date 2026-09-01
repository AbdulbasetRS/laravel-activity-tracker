<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Feature;

use Abdulbaset\ActivityTracker\Models\Activity;
use Abdulbaset\ActivityTracker\Services\RetrievalFlusher;
use Abdulbaset\ActivityTracker\Tests\Fixtures\TestPost;
use Abdulbaset\ActivityTracker\Tests\TestCase;

final class EloquentTrackingTest extends TestCase
{
    public function test_creating_a_model_is_tracked_automatically(): void
    {
        $post = TestPost::create(['title' => 'Hello World']);

        $activity = Activity::query()->where('action', 'created')->first();

        $this->assertNotNull($activity);
        $this->assertSame(TestPost::class, $activity->subject_type);
        $this->assertSame($post->id, $activity->subject_id);
    }

    public function test_updating_a_model_captures_old_and_new_values(): void
    {
        $post = TestPost::create(['title' => 'Original', 'status' => 'draft']);

        $post->update(['title' => 'Updated Title']);

        $activity = Activity::query()->where('action', 'updated')->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertSame('Original', $activity->old_values['title']);
        $this->assertSame('Updated Title', $activity->new_values['title']);
        $this->assertArrayNotHasKey('status', $activity->changed_values);
    }

    public function test_updating_with_no_real_change_is_not_tracked(): void
    {
        $post = TestPost::create(['title' => 'Same']);
        Activity::query()->truncate();

        $post->title = 'Same';
        $post->save();

        $this->assertSame(0, Activity::query()->where('action', 'updated')->count());
    }

    public function test_soft_delete_and_restore_are_distinguished_from_force_delete(): void
    {
        $post = TestPost::create(['title' => 'To be deleted']);

        $post->delete();
        $this->assertSame(1, Activity::query()->where('action', 'deleted')->count());

        $post->restore();
        $this->assertSame(1, Activity::query()->where('action', 'restored')->count());

        $post->forceDelete();
        $this->assertSame(1, Activity::query()->where('action', 'force_deleted')->count());

        // A soft delete must not ALSO appear as a generic bulk_updated
        // activity from the underlying UPDATE it issues.
        $this->assertSame(0, Activity::query()->where('action', 'bulk_updated')->count());
    }

    public function test_single_model_retrieval_is_tracked_as_one_activity(): void
    {
        $post = TestPost::create(['title' => 'Find me']);
        Activity::query()->truncate();

        TestPost::find($post->id);

        $this->flushRetrievals();

        $activity = Activity::query()->where('action', 'retrieved')->first();
        $this->assertNotNull($activity);
    }

    public function test_collection_retrieval_produces_a_single_aggregated_activity(): void
    {
        TestPost::create(['title' => 'One']);
        TestPost::create(['title' => 'Two']);
        TestPost::create(['title' => 'Three']);
        Activity::query()->truncate();

        TestPost::all();

        $this->flushRetrievals();

        $many = Activity::query()->where('action', 'retrieved_many')->first();
        $single = Activity::query()->where('action', 'retrieved')->count();

        $this->assertNotNull($many);
        $this->assertSame(3, $many->result_count);
        $this->assertSame(0, $single);
    }

    public function test_sensitive_fields_are_never_stored(): void
    {
        $post = TestPost::create(['title' => 'Secure', 'password' => 'super-secret']);

        $post->update(['password' => 'changed-secret']);

        $activity = Activity::query()->where('action', 'updated')->latest('id')->first();

        $this->assertArrayNotHasKey('password', $activity->old_values ?? []);
        $this->assertArrayNotHasKey('password', $activity->new_values ?? []);
        $this->assertArrayNotHasKey('password', $activity->changed_values ?? []);
    }

    public function test_ignored_models_produce_no_activity(): void
    {
        config()->set('activity-tracker.ignored_models', [TestPost::class]);

        TestPost::create(['title' => 'Should be ignored']);

        $this->assertSame(0, Activity::query()->count());
    }

    public function test_writing_the_activity_record_itself_never_recurses(): void
    {
        TestPost::create(['title' => 'Trigger recursion check']);

        // If recursion guarding failed, the activities table's own inserts
        // would themselves be classified/logged, and this count would keep
        // growing indefinitely rather than settling at exactly 1 "created".
        $this->assertSame(1, Activity::query()->where('action', 'created')->count());
    }

    private function flushRetrievals(): void
    {
        $this->app->make(RetrievalFlusher::class)->flush();
    }
}
