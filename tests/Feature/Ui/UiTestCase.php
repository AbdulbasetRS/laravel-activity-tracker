<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Feature\Ui;

use Abdulbaset\ActivityTracker\Tests\TestCase;

/**
 * Base class for UI tests that exercise the dashboard's actual behavior
 * (search, filters, sorting, pagination, details) rather than the
 * authorization layer itself. Auth/Gate enforcement is dropped here so
 * these tests aren't coupled to how the host application wires
 * authentication — see AuthorizationTest for the Gate/middleware behavior.
 */
abstract class UiTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('activity-tracker.ui.enabled', true);
        $app['config']->set('activity-tracker.ui.middleware', ['web']);
        $app['config']->set('activity-tracker.ui.authorize', false);
    }
}
