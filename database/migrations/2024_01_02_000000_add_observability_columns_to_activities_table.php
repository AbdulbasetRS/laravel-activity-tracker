<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only — never edits the original activities migration. Existing
 * installations run this via a normal `php artisan migrate` after upgrading;
 * every new column is nullable, so existing rows remain fully readable with
 * simply-absent new metadata (no backfill required, nothing destructive).
 */
return new class extends Migration
{
    public function connection(): ?string
    {
        return config('activity-tracker.connection');
    }

    public function up(): void
    {
        Schema::connection($this->connection())->table(config('activity-tracker.table', 'activities'), function (Blueprint $table) {
            // Performance
            $table->decimal('duration_ms', 12, 3)->nullable()->after('result_count');
            $table->unsignedBigInteger('memory_usage')->nullable()->after('duration_ms');
            $table->unsignedBigInteger('memory_peak')->nullable()->after('memory_usage');

            // Request context (url/route_name/http_method/ip_address/user_agent already exist)
            $table->string('path')->nullable()->after('url');
            $table->text('referrer_url')->nullable()->after('path');
            $table->unsignedSmallInteger('http_status')->nullable()->after('referrer_url');
            $table->string('execution_context', 20)->nullable()->after('http_status');

            // CLI / queue context
            $table->string('command')->nullable()->after('execution_context');
            $table->string('job_name')->nullable()->after('command');
            $table->string('queue_name')->nullable()->after('job_name');
            $table->string('queue_connection')->nullable()->after('queue_name');
            $table->unsignedInteger('queue_attempt')->nullable()->after('queue_connection');

            // Database
            $table->string('database_connection')->nullable()->after('queue_attempt');

            // Exception tracking
            $table->string('exception_class')->nullable()->after('database_connection');
            $table->text('exception_message')->nullable()->after('exception_class');
            $table->string('exception_file')->nullable()->after('exception_message');
            $table->unsignedInteger('exception_line')->nullable()->after('exception_file');
            $table->longText('stack_trace')->nullable()->after('exception_line');

            $table->index('http_status');
            $table->index('execution_context');
            $table->index('exception_class');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection())->table(config('activity-tracker.table', 'activities'), function (Blueprint $table) {
            $table->dropIndex(['http_status']);
            $table->dropIndex(['execution_context']);
            $table->dropIndex(['exception_class']);

            $table->dropColumn([
                'duration_ms', 'memory_usage', 'memory_peak',
                'path', 'referrer_url', 'http_status', 'execution_context',
                'command', 'job_name', 'queue_name', 'queue_connection', 'queue_attempt',
                'database_connection',
                'exception_class', 'exception_message', 'exception_file', 'exception_line', 'stack_trace',
            ]);
        });
    }
};
