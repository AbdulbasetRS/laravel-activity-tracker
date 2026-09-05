<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Feature;

use Abdulbaset\ActivityTracker\Models\Activity;
use Abdulbaset\ActivityTracker\Tests\Fixtures\TestUser;
use Abdulbaset\ActivityTracker\Tests\TestCase;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;

final class AuthenticationTrackingTest extends TestCase
{
    public function test_login_is_tracked_with_guard_and_causer(): void
    {
        $user = TestUser::create(['name' => 'Ahmed']);
        Activity::query()->truncate();

        Event::dispatch(new Attempting('web', ['email' => 'ahmed@example.com', 'password' => 'secret'], false));
        Event::dispatch(new Login('web', $user, false));

        $activity = Activity::query()->where('action', 'login')->first();

        $this->assertNotNull($activity);
        $this->assertSame('web', $activity->auth_guard);
        $this->assertSame(TestUser::class, $activity->causer_type);
        $this->assertSame($user->id, $activity->causer_id);
    }

    public function test_login_duration_is_measured_from_the_attempt(): void
    {
        $user = TestUser::create(['name' => 'Ahmed']);
        Activity::query()->truncate();

        Event::dispatch(new Attempting('web', ['email' => 'a@example.com', 'password' => 'x'], false));
        Event::dispatch(new Login('web', $user, false));

        $activity = Activity::query()->where('action', 'login')->first();

        $this->assertNotNull($activity->duration_ms);
        $this->assertIsFloat($activity->duration_ms);
    }

    public function test_failed_login_is_tracked_with_a_masked_identifier(): void
    {
        Activity::query()->truncate();

        Event::dispatch(new Failed('web', null, ['email' => 'ahmed@example.com', 'password' => 'super-secret']));

        $activity = Activity::query()->where('action', 'login_failed')->first();

        $this->assertNotNull($activity);
        $this->assertSame('a***@example.com', $activity->auth_identifier);
        $this->assertStringNotContainsString('ahmed', $activity->auth_identifier);
    }

    public function test_failed_login_never_stores_the_password_anywhere(): void
    {
        Activity::query()->truncate();

        Event::dispatch(new Failed('web', null, ['email' => 'ahmed@example.com', 'password' => 'super-secret-value']));

        $activity = Activity::query()->where('action', 'login_failed')->first();
        $raw = json_encode($activity->toArray());

        $this->assertStringNotContainsString('super-secret-value', $raw);
    }

    public function test_logout_is_tracked_with_the_causer_captured_before_it_is_gone(): void
    {
        $user = TestUser::create(['name' => 'Ahmed']);
        Activity::query()->truncate();

        Event::dispatch(new Logout('web', $user));

        $activity = Activity::query()->where('action', 'logout')->first();

        $this->assertNotNull($activity);
        $this->assertSame($user->id, $activity->causer_id);
    }

    public function test_authenticated_is_not_tracked_by_default(): void
    {
        $user = TestUser::create(['name' => 'Ahmed']);
        Activity::query()->truncate();

        Event::dispatch(new Authenticated('web', $user));

        $this->assertSame(0, Activity::query()->where('action', 'authenticated')->count());
    }

    public function test_authenticated_can_be_enabled(): void
    {
        config()->set('activity-tracker.authentication.track.authenticated', true);
        $user = TestUser::create(['name' => 'Ahmed']);
        Activity::query()->truncate();

        Event::dispatch(new Authenticated('web', $user));

        $this->assertSame(1, Activity::query()->where('action', 'authenticated')->count());
    }

    public function test_password_reset_is_tracked(): void
    {
        $user = TestUser::create(['name' => 'Ahmed']);
        Activity::query()->truncate();

        Event::dispatch(new PasswordReset($user));

        $this->assertSame(1, Activity::query()->where('action', 'password_reset')->count());
    }

    public function test_email_verified_is_tracked(): void
    {
        $user = TestUser::create(['name' => 'Ahmed']);
        Activity::query()->truncate();

        Event::dispatch(new Verified($user));

        $this->assertSame(1, Activity::query()->where('action', 'email_verified')->count());
    }

    public function test_authentication_throttled_is_tracked_with_a_masked_identifier(): void
    {
        Activity::query()->truncate();

        $request = Request::create('/login', 'POST', ['email' => 'ahmed@example.com']);
        Event::dispatch(new Lockout($request));

        $activity = Activity::query()->where('action', 'authentication_throttled')->first();

        $this->assertNotNull($activity);
        $this->assertSame('a***@example.com', $activity->auth_identifier);
    }

    public function test_authorization_denied_is_tracked_via_gate(): void
    {
        Gate::define('test-ability', fn () => false);
        Activity::query()->truncate();

        Gate::check('test-ability');

        $activity = Activity::query()->where('action', 'authorization_denied')->first();

        $this->assertNotNull($activity);
        $this->assertSame(403, $activity->http_status);
        $this->assertSame('test-ability', $activity->metadata['ability'] ?? null);
    }

    public function test_authorization_allowed_is_never_tracked(): void
    {
        Gate::define('test-ability-allow', fn () => true);
        Activity::query()->truncate();

        Gate::check('test-ability-allow');

        $this->assertSame(0, Activity::query()->where('action', 'authorization_denied')->count());
    }

    public function test_authentication_tracking_can_be_disabled_entirely(): void
    {
        config()->set('activity-tracker.authentication.enabled', false);
        $user = TestUser::create(['name' => 'Ahmed']);
        Activity::query()->truncate();

        Event::dispatch(new Login('web', $user, false));

        $this->assertSame(0, Activity::query()->where('action', 'login')->count());
    }
}
