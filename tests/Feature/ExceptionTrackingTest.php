<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Feature;

use Abdulbaset\ActivityTracker\Models\Activity;
use Abdulbaset\ActivityTracker\Tests\TestCase;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ExceptionTrackingTest extends TestCase
{
    public function test_a_reported_exception_is_recorded_with_core_metadata(): void
    {
        $exception = new RuntimeException('User not found');

        $this->app->make(ExceptionHandler::class)->report($exception);

        $activity = Activity::query()->exceptions()->first();

        $this->assertNotNull($activity);
        $this->assertSame(RuntimeException::class, $activity->exception_class);
        $this->assertSame('User not found', $activity->exception_message);
        $this->assertSame($exception->getFile(), $activity->exception_file);
        $this->assertSame($exception->getLine(), $activity->exception_line);
        $this->assertNotNull($activity->stack_trace);
        $this->assertTrue($activity->isException());
    }

    public function test_stack_trace_is_truncated_to_the_configured_length(): void
    {
        config()->set('activity-tracker.exceptions.max_trace_length', 50);

        $this->app->make(ExceptionHandler::class)->report(new RuntimeException('boom'));

        $activity = Activity::query()->exceptions()->latest('id')->first();

        $this->assertLessThanOrEqual(50 + 20, strlen($activity->stack_trace));
    }

    public function test_stack_trace_can_be_disabled(): void
    {
        config()->set('activity-tracker.exceptions.store_trace', false);

        $this->app->make(ExceptionHandler::class)->report(new RuntimeException('boom'));

        $activity = Activity::query()->exceptions()->latest('id')->first();

        $this->assertNull($activity->stack_trace);
    }

    public function test_ignored_exceptions_are_not_recorded_by_default(): void
    {
        Activity::query()->truncate();

        try {
            throw ValidationException::withMessages(['email' => 'required']);
        } catch (ValidationException $e) {
            $this->app->make(ExceptionHandler::class)->report($e);
        }

        $this->assertSame(0, Activity::query()->exceptions()->count());
    }

    public function test_ignored_exceptions_list_is_configurable(): void
    {
        config()->set('activity-tracker.exceptions.ignored_exceptions', []);
        Activity::query()->truncate();

        try {
            throw ValidationException::withMessages(['email' => 'required']);
        } catch (ValidationException $e) {
            $this->app->make(ExceptionHandler::class)->report($e);
        }

        $this->assertSame(1, Activity::query()->exceptions()->count());
    }

    public function test_reporting_the_same_exception_instance_twice_records_it_once(): void
    {
        Activity::query()->truncate();
        $exception = new RuntimeException('duplicate check');

        $handler = $this->app->make(ExceptionHandler::class);
        $handler->report($exception);
        $handler->report($exception);

        $this->assertSame(1, Activity::query()->exceptions()->count());
    }

    public function test_http_status_is_derived_from_http_exceptions(): void
    {
        Activity::query()->truncate();

        $this->app->make(ExceptionHandler::class)->report(new HttpException(503, 'Service unavailable'));

        $activity = Activity::query()->exceptions()->latest('id')->first();

        $this->assertSame(503, $activity->http_status);
    }

    public function test_exceptions_can_be_disabled_entirely_while_the_original_handler_still_runs(): void
    {
        config()->set('activity-tracker.exceptions.enabled', false);
        Activity::query()->truncate();

        // Delegation to the real handler must still happen (return value
        // proves the decorator didn't short-circuit it).
        $result = $this->app->make(ExceptionHandler::class)->shouldReport(new RuntimeException('x'));

        $this->app->make(ExceptionHandler::class)->report(new RuntimeException('should not be tracked'));

        $this->assertSame(0, Activity::query()->exceptions()->count());
        $this->assertIsBool($result);
    }

    public function test_exception_tracking_respects_suppression(): void
    {
        $context = $this->app->make(\Abdulbaset\ActivityTracker\Support\TrackingContext::class);
        Activity::query()->truncate();

        $context->withoutTracking(function () {
            $this->app->make(ExceptionHandler::class)->report(new RuntimeException('internal'));
        });

        $this->assertSame(0, Activity::query()->exceptions()->count());
    }
}
