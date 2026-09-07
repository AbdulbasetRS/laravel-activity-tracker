<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Feature;

use Abdulbaset\ActivityTracker\Contracts\ActivityLoggerInterface;
use Abdulbaset\ActivityTracker\Models\Activity;
use Abdulbaset\ActivityTracker\Services\ActivityTrackerRetrievalFlusher;
use Abdulbaset\ActivityTracker\Tests\Fixtures\TestPost;
use Abdulbaset\ActivityTracker\Tests\Fixtures\TestUser;
use Abdulbaset\ActivityTracker\Tests\TestCase;
use Illuminate\Auth\EloquentUserProvider;

/**
 * Regression coverage for two related "retrieved" bugs.
 *
 * Bug 1 (fixed earlier): opening the dashboard recorded a spurious
 * "retrieved User" activity, because Laravel's auth system resolves the
 * current guard's user via a plain Eloquent retrieval
 * (Illuminate\Auth\EloquentUserProvider::retrieveById(), used by the 'auth'
 * middleware, Gate checks, and auth()->user()) on virtually every
 * authenticated request — not just dashboard requests — and the package
 * listened to Eloquent's 'retrieved' event globally.
 *
 * Bug 2 (this fix): the first fix over-corrected. It excluded "retrieved"
 * for the model CLASS configured as an auth provider's model — which
 * silently suppressed EVERY retrieval of that class, including a direct
 * `User::find($id)` from real application code (a profile page, an admin
 * panel — anything). The model was never the problem; the call site is.
 *
 * Current fix: ActivityTrackerObserver::isAuthProviderResolution() checks
 * the real call stack, synchronously, inside the Eloquent "retrieved"
 * event — before the retrieval is buffered — and only excludes a
 * retrieval when a `Illuminate\Contracts\Auth\UserProvider` implementation
 * is genuinely on the stack (i.e. a guard is actually resolving the
 * current user). This can't be decided later inside
 * ActivityTrackerManager, because "retrieved"/"retrieved_many" are
 * buffered and flushed as one aggregated activity at the end of the
 * request/job, by which point the original call stack is gone — see
 * TrackingContext::bufferRetrieval() / RetrievalFlusher.
 *
 * Independent of and in addition to this, the package wraps its OWN
 * internal reads (dashboard, statistics, activities index/details) in
 * TrackingContext::withoutTracking() — see DashboardNoiseTest.
 */
final class RetrievalNoiseTest extends TestCase
{
    /**
     * The actual bug: `User::find($id)` called directly from application
     * code (a profile page, an admin panel, anything) MUST still be
     * tracked like any other model. It must NOT be confused with the auth
     * guard resolving the current session/token user (see the next test).
     */
    public function test_direct_user_find_from_application_code_is_tracked_as_retrieved(): void
    {
        $user = TestUser::create(['name' => 'Ahmed']);
        Activity::query()->truncate();

        TestUser::find($user->id);
        $this->app->make(ActivityTrackerRetrievalFlusher::class)->flush();

        $this->assertSame(
            1,
            Activity::query()->where('subject_type', TestUser::class)->where('action', 'retrieved')->count()
        );
    }

    /**
     * This is the actual framework mechanic the exclusion targets: a
     * `UserProvider` resolving a user (exactly what an auth guard does
     * internally) — genuinely different from the test above because
     * `EloquentUserProvider::retrieveById()` is really on the call stack
     * here, not merely because the model happens to be the same class.
     */
    public function test_retrieval_via_a_user_provider_is_not_tracked(): void
    {
        $user = TestUser::create(['name' => 'Ahmed']);
        Activity::query()->truncate();

        $provider = new EloquentUserProvider($this->app->make('hash'), TestUser::class);
        $resolved = $provider->retrieveById($user->id);

        $this->app->make(ActivityTrackerRetrievalFlusher::class)->flush();

        $this->assertNotNull($resolved);
        $this->assertSame(0, Activity::query()->where('subject_type', TestUser::class)->count());
    }

    public function test_retrieving_a_non_auth_model_is_still_tracked_normally(): void
    {
        $post = TestPost::create(['title' => 'A']);
        Activity::query()->truncate();

        TestPost::find($post->id);
        $this->app->make(ActivityTrackerRetrievalFlusher::class)->flush();

        $this->assertSame(1, Activity::query()->where('subject_type', TestPost::class)->where('action', 'retrieved')->count());
    }

    public function test_exclude_auth_models_can_be_disabled_to_audit_login_reads_too(): void
    {
        config()->set('activity-tracker.retrieval.exclude_auth_models', false);

        $user = TestUser::create(['name' => 'Ahmed']);
        Activity::query()->truncate();

        $provider = new EloquentUserProvider($this->app->make('hash'), TestUser::class);
        $provider->retrieveById($user->id);

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
