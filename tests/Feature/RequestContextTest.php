<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Feature;

use Abdulbaset\ActivityTracker\Models\Activity;
use Abdulbaset\ActivityTracker\Tests\Fixtures\TestPost;
use Abdulbaset\ActivityTracker\Tests\TestCase;
use Illuminate\Support\Facades\Route;

final class RequestContextTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        Route::get('/__test/create-post', function () {
            TestPost::create(['title' => 'Via HTTP']);

            return response('ok', 201);
        })->name('__test.create-post');

        Route::get('/__test/fail', function () {
            throw new \RuntimeException('boom via http');
        })->name('__test.fail');
    }

    public function test_full_url_and_query_string_are_recorded(): void
    {
        $this->get('/__test/create-post?tab=permissions');

        $activity = Activity::query()->where('action', 'created')->latest('id')->first();

        $this->assertStringContainsString('/__test/create-post', $activity->url);
        $this->assertStringContainsString('tab=permissions', $activity->url);
        $this->assertSame('__test/create-post', $activity->path);
        $this->assertSame('__test.create-post', $activity->route_name);
    }

    public function test_sensitive_query_parameters_are_redacted_from_the_url(): void
    {
        $this->get('/__test/create-post?token=secret-value-123&tab=general');

        $activity = Activity::query()->where('action', 'created')->latest('id')->first();

        $this->assertStringNotContainsString('secret-value-123', $activity->url);
        $this->assertStringContainsString('[REDACTED]', $activity->url);
        $this->assertStringContainsString('tab=general', $activity->url);
    }

    public function test_referrer_is_captured_when_present(): void
    {
        $this->withHeader('referer', 'https://example.com/admin/users')
            ->get('/__test/create-post');

        $activity = Activity::query()->where('action', 'created')->latest('id')->first();

        $this->assertSame('https://example.com/admin/users', $activity->referrer_url);
    }

    public function test_referrer_is_null_when_absent(): void
    {
        $this->get('/__test/create-post');

        $activity = Activity::query()->where('action', 'created')->latest('id')->first();

        $this->assertNull($activity->referrer_url);
    }

    public function test_sensitive_query_parameters_are_redacted_from_the_referrer(): void
    {
        $this->withHeader('referer', 'https://example.com/reset?token=abc123&ok=1')
            ->get('/__test/create-post');

        $activity = Activity::query()->where('action', 'created')->latest('id')->first();

        $this->assertStringNotContainsString('abc123', $activity->referrer_url);
        $this->assertStringContainsString('[REDACTED]', $activity->referrer_url);
    }

    public function test_long_referrer_is_truncated(): void
    {
        config()->set('activity-tracker.context.max_referrer_length', 40);

        $this->withHeader('referer', 'https://example.com/'.str_repeat('a', 200))
            ->get('/__test/create-post');

        $activity = Activity::query()->where('action', 'created')->latest('id')->first();

        $this->assertLessThanOrEqual(40, strlen($activity->referrer_url));
    }

    public function test_http_status_is_backfilled_after_the_response_is_sent(): void
    {
        $this->get('/__test/create-post');

        $activity = Activity::query()->where('action', 'created')->latest('id')->first();

        $this->assertSame(201, $activity->http_status);
    }

    public function test_execution_context_is_http_for_a_real_request(): void
    {
        $this->get('/__test/create-post');

        $activity = Activity::query()->where('action', 'created')->latest('id')->first();

        $this->assertSame('http', $activity->execution_context);
    }

    public function test_exception_thrown_during_a_request_gets_the_same_request_id_and_status(): void
    {
        $this->get('/__test/fail');

        $activity = Activity::query()->exceptions()->latest('id')->first();

        $this->assertNotNull($activity);
        $this->assertSame('RuntimeException', class_basename($activity->exception_class));
        $this->assertNotNull($activity->request_id);
    }
}
