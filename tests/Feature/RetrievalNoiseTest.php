<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Feature;

use Abdulbaset\ActivityTracker\Contracts\ActivityLoggerInterface;
use Abdulbaset\ActivityTracker\Models\Activity;
use Abdulbaset\ActivityTracker\Services\ActivityTrackerRetrievalFlusher;
use Abdulbaset\ActivityTracker\Tests\Fixtures\TestPost;
use Abdulbaset\ActivityTracker\Tests\Fixtures\TestUser;
use Abdulbaset\ActivityTracker\Tests\TestCase;

/**
 * Regression coverage for the "opening the dashboard records a spurious
 * 'retrieved User' activity" bug.
 *
 * Root cause: Laravel's own auth system resolves the current guard's user
 * via a plain Eloquent retrieval (Illuminate\Auth\EloquentUserProvider::
 * retrieveById(), used by the 'auth' middleware, Gate checks, and
 * auth()->user()) on virtually every authenticated request — not just
 * dashboard requests. Because the package listens to Eloquent's 'retrieved'
 * event globally, that framework-internal read was being recorded exactly
 * like a meaningful application read.
 *
 * Fix: every model configured under auth.providers.*.model is excluded from
 * "retrieved"/"retrieved_many" tracking by default
 * (activity-tracker.retrieval.exclude_auth_models), independent of and in
 * addition to the package wrapping its OWN internal reads (dashboard,
 * statistics, activities index/details) in TrackingContext::withoutTracking().
 */
final class RetrievalNoiseTest extends TestCase
{
    public function test_retrieving_the_configured_auth_model_is_not_tracked_by_default(): void
    {
        $user = TestUser::create(['name' => 'Ahmed']);
        Activity::query()->truncate();

        $this->app->make(ActivityLoggerInterface::class)->logModelEvent('retrieved', $user->fresh());
        $this->app->make(ActivityTrackerRetrievalFlusher::class)->flush();

        $this->assertSame(0, Activity::query()->where('subject_type', TestUser::class)->count());
    }

    public function test_retrieving_a_non_auth_model_is_still_tracked_normally(): void
    {
        $post = TestPost::create(['title' => 'A']);
        Activity::query()->truncate();

        $this->app->make(ActivityLoggerInterface::class)->logModelEvent('retrieved', $post->fresh());
        $this->app->make(ActivityTrackerRetrievalFlusher::class)->flush();

        $this->assertSame(1, Activity::query()->where('subject_type', TestPost::class)->where('action', 'retrieved')->count());
    }

    public function test_exclude_auth_models_can_be_disabled_to_audit_login_reads_too(): void
    {
        config()->set('activity-tracker.retrieval.exclude_auth_models', false);

        $user = TestUser::create(['name' => 'Ahmed']);
        Activity::query()->truncate();

        $this->app->make(ActivityLoggerInterface::class)->logModelEvent('retrieved', $user->fresh());
        $this->app->make(ActivityTrackerRetrievalFlusher::class)->flush();

        $this->assertSame(1, Activity::query()->where('subject_type', TestUser::class)->count());
    }

    public function test_intentional_ui_view_records_exactly_one_activity_with_ui_context(): void
    {
        $post = TestPost::create(['title' => 'Viewed record']);
        Activity::query()->truncate();

        $this->app->make(ActivityLoggerInterface::class)->logIntentionalView($post, ['via' => 'test']);

        $activities = Activity::query()->where('subject_type', TestPost::class)->get();

        $this->assertCount(1, $activities);
        $this->assertSame('retrieved', $activities->first()->action);
        $this->assertSame('ui', $activities->first()->metadata['context'] ?? null);
    }

    public function test_intentional_ui_view_respects_ignored_models(): void
    {
        config()->set('activity-tracker.ignored_models', [TestPost::class]);

        $post = TestPost::create(['title' => 'Ignored']);
        Activity::query()->truncate();

        $this->app->make(ActivityLoggerInterface::class)->logIntentionalView($post);

        $this->assertSame(0, Activity::query()->count());
    }

    public function test_intentional_ui_view_can_be_disabled_via_config(): void
    {
        config()->set('activity-tracker.retrieval.track_ui_views', false);

        $post = TestPost::create(['title' => 'A']);
        Activity::query()->truncate();

        $this->app->make(ActivityLoggerInterface::class)->logIntentionalView($post);

        $this->assertSame(0, Activity::query()->count());
    }

    public function test_without_tracking_suppresses_reads_and_restores_afterward(): void
    {
        $context = $this->app->make(\Abdulbaset\ActivityTracker\Support\TrackingContext::class);
        $logger = $this->app->make(ActivityLoggerInterface::class);

        Activity::query()->truncate();

        $context->withoutTracking(function () use ($logger) {
            $post = TestPost::create(['title' => 'Suppressed']);
            $logger->logModelEvent('retrieved', $post);
        });

        $this->assertSame(0, Activity::query()->count());
        $this->assertFalse($context->isSuppressed());

        // Tracking must be back on afterward.
        TestPost::create(['title' => 'Tracked again']);
        $this->assertSame(1, Activity::query()->where('action', 'created')->count());
    }

    public function test_without_tracking_restores_even_when_the_callback_throws(): void
    {
        $context = $this->app->make(\Abdulbaset\ActivityTracker\Support\TrackingContext::class);

        try {
            $context->withoutTracking(function () {
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertFalse($context->isSuppressed());
    }

    public function test_without_tracking_is_nestable(): void
    {
        $context = $this->app->make(\Abdulbaset\ActivityTracker\Support\TrackingContext::class);

        $context->withoutTracking(function () use ($context) {
            $this->assertTrue($context->isSuppressed());

            $context->withoutTracking(function () use ($context) {
                $this->assertTrue($context->isSuppressed());
            });

            // Still suppressed — the inner block ending must not lift the
            // outer block's suppression.
            $this->assertTrue($context->isSuppressed());
        });

        $this->assertFalse($context->isSuppressed());
    }
}
