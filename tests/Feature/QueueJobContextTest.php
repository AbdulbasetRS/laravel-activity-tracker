<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Feature;

use Abdulbaset\ActivityTracker\Models\Activity;
use Abdulbaset\ActivityTracker\Tests\Fixtures\TestPost;
use Abdulbaset\ActivityTracker\Tests\TestCase;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;

/**
 * Exercises the JobProcessing/JobProcessed listeners directly with a mock
 * Job (rather than a real queue worker) — the 'sync' queue driver never
 * fires these events at all, so this is the reliable way to test the
 * listener logic itself without standing up a full queue worker.
 */
final class QueueJobContextTest extends TestCase
{
    public function test_job_context_is_captured_for_activities_recorded_during_a_job(): void
    {
        $job = $this->createMock(Job::class);
        $job->method('getName')->willReturn('App\\Jobs\\SyncUsers');
        $job->method('getQueue')->willReturn('default');
        $job->method('attempts')->willReturn(2);

        Event::dispatch(new JobProcessing('redis', $job));

        TestPost::create(['title' => 'From a job']);

        Event::dispatch(new JobProcessed('redis', $job));

        $activity = Activity::query()->where('action', 'created')->latest('id')->first();

        $this->assertSame('queue', $activity->execution_context);
        $this->assertSame('App\\Jobs\\SyncUsers', $activity->job_name);
        $this->assertSame('default', $activity->queue_name);
        $this->assertSame('redis', $activity->queue_connection);
        $this->assertSame(2, $activity->queue_attempt);
    }

    public function test_job_context_does_not_leak_into_activities_outside_a_job(): void
    {
        $job = $this->createMock(Job::class);
        $job->method('getName')->willReturn('App\\Jobs\\SyncUsers');
        $job->method('getQueue')->willReturn('default');
        $job->method('attempts')->willReturn(1);

        Event::dispatch(new JobProcessing('redis', $job));
        Event::dispatch(new JobProcessed('redis', $job));

        // Back to normal (CLI, in this test process) execution.
        TestPost::create(['title' => 'Outside any job']);

        $activity = Activity::query()->where('action', 'created')->latest('id')->first();

        $this->assertSame('cli', $activity->execution_context);
        $this->assertNull($activity->job_name);
        $this->assertNull($activity->queue_name);
    }

    public function test_job_context_does_not_leak_between_two_different_jobs(): void
    {
        $firstJob = $this->createMock(Job::class);
        $firstJob->method('getName')->willReturn('App\\Jobs\\FirstJob');
        $firstJob->method('getQueue')->willReturn('high');
        $firstJob->method('attempts')->willReturn(1);

        Event::dispatch(new JobProcessing('redis', $firstJob));
        Event::dispatch(new JobProcessed('redis', $firstJob));

        $secondJob = $this->createMock(Job::class);
        $secondJob->method('getName')->willReturn('App\\Jobs\\SecondJob');
        $secondJob->method('getQueue')->willReturn('low');
        $secondJob->method('attempts')->willReturn(1);

        Event::dispatch(new JobProcessing('redis', $secondJob));
        TestPost::create(['title' => 'From second job']);
        Event::dispatch(new JobProcessed('redis', $secondJob));

        $activity = Activity::query()->where('action', 'created')->latest('id')->first();

        $this->assertSame('App\\Jobs\\SecondJob', $activity->job_name);
        $this->assertSame('low', $activity->queue_name);
    }
}
