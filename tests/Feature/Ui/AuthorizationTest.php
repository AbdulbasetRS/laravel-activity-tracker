<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Feature\Ui;

use Abdulbaset\ActivityTracker\Tests\TestCase;
use Illuminate\Support\Facades\Gate;

final class AuthorizationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('activity-tracker.ui.enabled', true);
        // Drop the 'auth' middleware so this test exercises the Gate itself
        // rather than Laravel's guest-redirect-to-login behavior, which
        // depends on a 'login' route the host application defines.
        $app['config']->set('activity-tracker.ui.middleware', ['web']);
        $app['config']->set('activity-tracker.ui.authorize', true);
    }

    public function test_default_gate_denies_access_outside_the_local_environment(): void
    {
        // Orchestra Testbench boots with APP_ENV=testing by default, which
        // is exactly the scenario the default Gate is meant to protect
        // against — only 'local' is auto-allowed.
        $this->get(route('activity-tracker.dashboard'))->assertForbidden();
    }

    public function test_host_application_can_grant_access_via_a_custom_gate(): void
    {
        Gate::define('viewActivityTracker', fn () => true);

        $this->get(route('activity-tracker.dashboard'))->assertOk();
    }

    public function test_host_application_can_deny_access_via_a_custom_gate(): void
    {
        Gate::define('viewActivityTracker', fn () => false);

        $this->get(route('activity-tracker.dashboard'))->assertForbidden();
    }
}
