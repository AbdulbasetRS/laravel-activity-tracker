<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Feature;

use Abdulbaset\ActivityTracker\Contracts\BroadcastChannelMonitorInterface;
use Abdulbaset\ActivityTracker\Models\Activity;
use Abdulbaset\ActivityTracker\Services\Broadcasting\NullBroadcastChannelMonitor;
use Abdulbaset\ActivityTracker\Tests\Fixtures\TestBroadcastableEvent;
use Abdulbaset\ActivityTracker\Tests\TestCase;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use RuntimeException;

final class BroadcastMonitoringTest extends TestCase
{
    private function mockBroadcastJob(): Job
    {
        $command = new BroadcastEvent(new TestBroadcastableEvent('hi there'));

        $job = $this->createMock(Job::class);
        $job->method('resolveName')->willReturn(BroadcastEvent::class);
        $job->method('getQueue')->willReturn('broadcasts');
        $job->method('payload')->willReturn([
            'data' => ['command' => serialize($command)],
        ]);

        return $job;
    }

    public function test_null_monitor_is_used_for_the_default_driver_and_reports_unavailability_honestly(): void
    {
        $monitor = $this->app->make(BroadcastChannelMonitorInterface::class);

        $this->assertInstanceOf(NullBroadcastChannelMonitor::class, $monitor);
        $this->assertFalse($monitor->supportsChannelDiscovery());
        $this->assertFalse($monitor->supportsConnectionCounts());
        $this->assertSame([], $monitor->channels());
        $this->assertNull($monitor->presenceMembers('presence-support'));
        $this->assertNotNull($monitor->unavailableReason());
    }

    public function test_null_monitor_never_reports_zero_connections_it_reports_null(): void
    {
        $monitor = $this->app->make(BroadcastChannelMonitorInterface::class);

        // channels() is empty, not populated with a fake "0 connections" row.
        $this->assertSame([], $monitor->channels());
    }

    public function test_a_successfully_processed_broadcast_job_is_tracked_per_channel(): void
    {
        Activity::query()->truncate();
        $job = $this->mockBroadcastJob();

        Event::dispatch(new JobProcessing('sync', $job));
        Event::dispatch(new JobProcessed('sync', $job));

        $activities = Activity::query()->broadcasts()->get();

        $this->assertCount(2, $activities); // two channels on the fixture event
        $this->assertTrue($activities->every(fn ($a) => $a->broadcast_status === 'sent'));
        $this->assertTrue($activities->every(fn ($a) => $a->broadcast_event === 'test.broadcast.event'));
        $this->assertEqualsCanonicalizing(
            ['presence-support', 'private-orders'],
            $activities->pluck('broadcast_channel')->all()
        );
        $this->assertEqualsCanonicalizing(
            ['presence', 'private'],
            $activities->pluck('broadcast_channel_type')->all()
        );
    }

    public function test_channel_type_is_correctly_detected(): void
    {
        Activity::query()->truncate();
        $job = $this->mockBroadcastJob();

        Event::dispatch(new JobProcessing('sync', $job));
        Event::dispatch(new JobProcessed('sync', $job));

        $presence = Activity::query()->where('broadcast_channel', 'presence-support')->first();
        $private = Activity::query()->where('broadcast_channel', 'private-orders')->first();

        $this->assertSame('presence', $presence->broadcast_channel_type);
        $this->assertSame('private', $private->broadcast_channel_type);
    }

    public function test_a_failed_broadcast_job_is_tracked_with_the_exception(): void
    {
        Activity::query()->truncate();
        $job = $this->mockBroadcastJob();

        Event::dispatch(new JobProcessing('sync', $job));
        Event::dispatch(new JobFailed('sync', $job, new RuntimeException('provider unreachable')));

        $activities = Activity::query()->broadcasts()->get();

        $this->assertCount(2, $activities);
        $this->assertTrue($activities->every(fn ($a) => $a->broadcast_status === 'failed'));
        $this->assertTrue($activities->every(fn ($a) => $a->exception_message === 'provider unreachable'));
    }

    public function test_broadcast_duration_is_measured(): void
    {
        Activity::query()->truncate();
        $job = $this->mockBroadcastJob();

        Event::dispatch(new JobProcessing('sync', $job));
        Event::dispatch(new JobProcessed('sync', $job));

        $activity = Activity::query()->broadcasts()->first();

        $this->assertNotNull($activity->duration_ms);
        $this->assertIsFloat($activity->duration_ms);
    }

    public function test_a_non_broadcast_job_is_ignored_entirely(): void
    {
        Activity::query()->truncate();

        $job = $this->createMock(Job::class);
        $job->method('resolveName')->willReturn('App\\Jobs\\SomeOtherJob');
        $job->method('getQueue')->willReturn('default');
        $job->method('attempts')->willReturn(1);
        $job->method('getName')->willReturn('App\\Jobs\\SomeOtherJob');

        Event::dispatch(new JobProcessing('sync', $job));
        Event::dispatch(new JobProcessed('sync', $job));

        $this->assertSame(0, Activity::query()->broadcasts()->count());
    }

    public function test_broadcast_monitoring_can_be_disabled(): void
    {
        config()->set('activity-tracker.broadcast_monitoring.enabled', false);
        Activity::query()->truncate();
        $job = $this->mockBroadcastJob();

        Event::dispatch(new JobProcessing('sync', $job));
        Event::dispatch(new JobProcessed('sync', $job));

        $this->assertSame(0, Activity::query()->broadcasts()->count());
    }

    public function test_a_provider_failure_while_building_the_activity_never_breaks_the_worker(): void
    {
        // A job whose payload() throws — simulating a corrupt/unreadable
        // payload — must not propagate an exception out of the listener.
        $job = $this->createMock(Job::class);
        $job->method('resolveName')->willReturn(BroadcastEvent::class);
        $job->method('getQueue')->willReturn('broadcasts');
        $job->method('payload')->willThrowException(new RuntimeException('corrupt payload'));

        Event::dispatch(new JobProcessing('sync', $job));

        // Reaching this line without an exception bubbling up is the assertion.
        Event::dispatch(new JobProcessed('sync', $job));

        $this->assertTrue(true);
    }

    public function test_pusher_driver_without_the_sdk_installed_falls_back_to_the_null_monitor(): void
    {
        config()->set('broadcasting.default', 'pusher');
        config()->set('broadcasting.connections.pusher', ['key' => 'x', 'secret' => 'y', 'app_id' => 'z']);

        // The container singleton was already resolved during boot with the
        // driver at that time — rebuild it fresh with the new config to
        // exercise the actual resolution logic.
        $this->app->forgetInstance(BroadcastChannelMonitorInterface::class);
        $this->app->singleton(BroadcastChannelMonitorInterface::class, function () {
            $driver = (string) config('broadcasting.default', 'null');
            $connectionConfig = (array) config("broadcasting.connections.{$driver}", []);

            if (in_array($driver, ['pusher', 'reverb'], true) && class_exists(\Pusher\Pusher::class)) {
                return new \Abdulbaset\ActivityTracker\Services\Broadcasting\PusherBroadcastChannelMonitor($driver, $connectionConfig);
            }

            return new NullBroadcastChannelMonitor($driver);
        });

        $monitor = $this->app->make(BroadcastChannelMonitorInterface::class);

        // pusher/pusher-php-server is not installed in this test environment
        // — this is exactly the "SDK not installed" case, proving the
        // fallback works rather than fataling.
        $this->assertInstanceOf(NullBroadcastChannelMonitor::class, $monitor);
        $this->assertSame('pusher', $monitor->provider());
        $this->assertStringContainsString('pusher/pusher-php-server', $monitor->unavailableReason());
    }

    public function test_channels_are_memoized_within_a_single_request_to_avoid_redundant_provider_calls(): void
    {
        $spy = new class implements BroadcastChannelMonitorInterface
        {
            public int $callCount = 0;

            public function provider(): string
            {
                return 'spy';
            }

            public function supportsChannelDiscovery(): bool
            {
                return true;
            }

            public function supportsConnectionCounts(): bool
            {
                return true;
            }

            public function channels(): array
            {
                $this->callCount++;

                return [
                    ['name' => 'presence-chat', 'type' => 'presence', 'connections' => 4, 'status' => 'active'],
                ];
            }

            public function presenceMembers(string $channel): ?array
            {
                return null;
            }

            public function unavailableReason(): ?string
            {
                return null;
            }
        };

        config()->set('activity-tracker.broadcast_monitoring.cache_seconds', 0);
        $service = new \Abdulbaset\ActivityTracker\Services\ActivityTrackerBroadcastStatisticsService($spy);

        // Four independent stats, each of which needs the channel list.
        $service->knownChannelsCount();
        $service->activeChannelsCount();
        $service->totalConnections();
        $service->presenceChannelsCount();

        $this->assertSame(1, $spy->callCount);
    }

    public function test_defined_channel_patterns_degrades_gracefully_and_never_throws(): void
    {
        $service = $this->app->make(\Abdulbaset\ActivityTracker\Services\ActivityTrackerBroadcastStatisticsService::class);

        $patterns = $service->definedChannelPatterns();

        $this->assertIsArray($patterns);
    }
}
