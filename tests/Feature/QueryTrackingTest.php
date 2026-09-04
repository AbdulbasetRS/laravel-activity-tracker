<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Feature;

use Abdulbaset\ActivityTracker\Models\Activity;
use Abdulbaset\ActivityTracker\Tests\Fixtures\TestPost;
use Abdulbaset\ActivityTracker\Tests\TestCase;
use Illuminate\Support\Facades\DB;

final class QueryTrackingTest extends TestCase
{
    /**
     * Regression test: "count" and "exists" must NEVER produce an Activity,
     * under any config, not merely be hidden from the UI. See README §
     * Tracked operations.
     */
    public function test_count_is_never_tracked(): void
    {
        TestPost::create(['title' => 'A']);
        TestPost::create(['title' => 'B']);
        Activity::query()->truncate();

        TestPost::query()->count();
        TestPost::query()->where('title', 'A')->count();
        DB::table('test_posts')->count();

        $this->assertSame(0, Activity::query()->where('action', 'count')->count());
        $this->assertSame(0, Activity::query()->count());
    }

    public function test_exists_is_never_tracked(): void
    {
        TestPost::create(['title' => 'A']);
        Activity::query()->truncate();

        TestPost::query()->where('title', 'A')->exists();
        DB::table('test_posts')->where('title', 'A')->exists();

        $this->assertSame(0, Activity::query()->where('action', 'exists')->count());
        $this->assertSame(0, Activity::query()->count());
    }

    public function test_count_and_exists_stay_untracked_even_if_reenabled_in_config(): void
    {
        // "count"/"exists" have no config toggle at all anymore — confirm
        // there is no backdoor via the generic 'track' array.
        config()->set('activity-tracker.track.count', true);
        config()->set('activity-tracker.track.exists', true);

        TestPost::create(['title' => 'A']);
        Activity::query()->truncate();

        TestPost::query()->count();
        TestPost::query()->exists();

        $this->assertSame(0, Activity::query()->count());
    }

    public function test_aggregates_are_tracked(): void
    {
        TestPost::create(['title' => 'A']);
        Activity::query()->truncate();

        TestPost::query()->max('id');

        $this->assertSame(1, Activity::query()->where('action', 'max')->count());
    }

    public function test_bulk_update_via_query_builder_is_tracked_without_per_model_events(): void
    {
        TestPost::create(['title' => 'A', 'status' => 'draft']);
        TestPost::create(['title' => 'B', 'status' => 'draft']);
        Activity::query()->truncate();

        TestPost::query()->where('status', 'draft')->update(['status' => 'published']);

        $activity = Activity::query()->where('action', 'bulk_updated')->first();
        $this->assertNotNull($activity);

        // Mass updates bypass individual model events entirely, so no
        // per-model "updated" activity should exist either.
        $this->assertSame(0, Activity::query()->where('action', 'updated')->count());
    }

    public function test_bulk_delete_via_query_builder_is_tracked(): void
    {
        TestPost::create(['title' => 'A']);
        TestPost::create(['title' => 'B']);
        Activity::query()->truncate();

        TestPost::query()->where('title', 'A')->delete();

        $this->assertSame(1, Activity::query()->where('action', 'bulk_deleted')->count());
    }

    public function test_raw_query_builder_operations_are_tracked(): void
    {
        Activity::query()->truncate();

        DB::table('test_posts')->insert(['title' => 'Raw insert', 'status' => 'draft']);

        $this->assertSame(1, Activity::query()->where('action', 'raw_insert')->count());
    }

    public function test_plain_select_queries_are_never_logged_directly(): void
    {
        TestPost::create(['title' => 'A']);
        Activity::query()->truncate();

        DB::table('test_posts')->where('title', 'A')->get();

        $this->assertSame(0, Activity::query()->where('query_type', 'select')->count());
    }
}
