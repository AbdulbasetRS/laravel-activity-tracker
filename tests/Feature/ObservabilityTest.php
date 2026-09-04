<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Feature;

use Abdulbaset\ActivityTracker\Models\Activity;
use Abdulbaset\ActivityTracker\Tests\Fixtures\TestPost;
use Abdulbaset\ActivityTracker\Tests\TestCase;

final class ObservabilityTest extends TestCase
{
    public function test_duration_is_recorded_for_created_and_is_a_positive_number(): void
    {
        TestPost::create(['title' => 'Timed']);

        $activity = Activity::query()->where('action', 'created')->latest('id')->first();

        $this->assertNotNull($activity->duration_ms);
        $this->assertIsFloat($activity->duration_ms);
        $this->assertGreaterThanOrEqual(0, $activity->duration_ms);
    }

    public function test_duration_is_recorded_for_updated_and_deleted(): void
    {
        $post = TestPost::create(['title' => 'A']);
        $post->update(['title' => 'B']);
        $post->delete();

        $updated = Activity::query()->where('action', 'updated')->latest('id')->first();
        $deleted = Activity::query()->where('action', 'deleted')->latest('id')->first();

        $this->assertNotNull($updated->duration_ms);
        $this->assertNotNull($deleted->duration_ms);
    }

    public function test_duration_tracking_can_be_disabled(): void
    {
        config()->set('activity-tracker.performance.track_duration', false);

        TestPost::create(['title' => 'No timing']);

        $activity = Activity::query()->where('action', 'created')->latest('id')->first();

        $this->assertNull($activity->duration_ms);
    }

    public function test_duration_does_not_break_existing_tracking_behavior(): void
    {
        $post = TestPost::create(['title' => 'Still works']);

        $this->assertSame('created', Activity::query()->latest('id')->first()->action);
        $this->assertSame($post->id, Activity::query()->latest('id')->first()->subject_id);
    }

    public function test_aggregate_query_duration_uses_laravel_native_timing(): void
    {
        TestPost::create(['title' => 'A']);
        Activity::query()->truncate();

        TestPost::query()->max('id');

        $activity = Activity::query()->where('action', 'max')->first();

        $this->assertNotNull($activity);
        $this->assertNotNull($activity->duration_ms);
        $this->assertIsFloat($activity->duration_ms);
    }

    public function test_execution_context_is_recorded_as_a_dedicated_column(): void
    {
        TestPost::create(['title' => 'Context test']);

        $activity = Activity::query()->where('action', 'created')->latest('id')->first();

        $this->assertSame('cli', $activity->execution_context);
    }

    public function test_database_connection_is_recorded(): void
    {
        TestPost::create(['title' => 'Connection test']);

        $activity = Activity::query()->where('action', 'created')->latest('id')->first();

        $this->assertSame('testing', $activity->database_connection);
    }

    public function test_retrieved_and_retrieved_many_have_no_single_meaningful_duration(): void
    {
        $post = TestPost::create(['title' => 'A']);
        TestPost::create(['title' => 'B']);
        Activity::query()->truncate();

        TestPost::all();

        $this->app->make(\Abdulbaset\ActivityTracker\Services\ActivityTrackerRetrievalFlusher::class)->flush();

        $many = Activity::query()->where('action', 'retrieved_many')->first();

        $this->assertNotNull($many);
        $this->assertNull($many->duration_ms);
    }
}
