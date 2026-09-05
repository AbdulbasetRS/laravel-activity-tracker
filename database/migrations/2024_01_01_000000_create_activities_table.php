<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function connection(): ?string
    {
        return config('activity-tracker.connection');
    }

    public function up(): void
    {
        Schema::connection($this->connection())->create(
            config('activity-tracker.table', 'activities'),
            function (Blueprint $table) {
                $table->id();

                // Correlation
                $table->uuid('batch_id')->nullable()->index();
                $table->uuid('request_id')->nullable()->index();

                // Causer / Subject
                $table->nullableMorphs('causer');
                $table->nullableMorphs('subject');

                // Activity
                $table->string('action', 60)->index();
                $table->string('table')->nullable()->index();
                $table->text('description')->nullable();

                // Model changes
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->json('changed_values')->nullable();

                // Database query
                $table->text('query')->nullable();
                $table->string('query_type', 30)->nullable();
                $table->unsignedBigInteger('result_count')->nullable();
                $table->string('database_connection')->nullable();

                // Performance
                $table->decimal('duration_ms', 12, 3)->nullable();
                $table->unsignedBigInteger('memory_usage')->nullable();
                $table->unsignedBigInteger('memory_peak')->nullable();

                // HTTP / Request
                $table->string('route_name')->nullable();
                $table->string('http_method', 10)->nullable();
                $table->text('url')->nullable();
                $table->string('path')->nullable();
                $table->text('referrer_url')->nullable();
                $table->unsignedSmallInteger('http_status')->nullable();
                $table->string('execution_context', 20)->nullable();

                // Client
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();

                // CLI
                $table->string('command')->nullable();

                // Queue
                $table->string('job_name')->nullable();
                $table->string('queue_name')->nullable();
                $table->string('queue_connection')->nullable();
                $table->unsignedInteger('queue_attempt')->nullable();

                // Exceptions
                $table->string('exception_class')->nullable();
                $table->text('exception_message')->nullable();
                $table->text('exception_file')->nullable();
                $table->unsignedInteger('exception_line')->nullable();
                $table->longText('stack_trace')->nullable();

                // Authentication
                $table->string('auth_action', 40)->nullable();
                $table->string('auth_guard')->nullable();
                $table->string('auth_provider')->nullable();
                $table->string('auth_identifier')->nullable();

                // Broadcast
                $table->string('broadcast_event')->nullable();
                $table->string('broadcast_channel')->nullable();
                $table->string('broadcast_channel_type', 20)->nullable();
                $table->string('broadcast_status', 20)->nullable();

                // Extra
                $table->json('metadata')->nullable();

                $table->timestamps();

                // Indexes
                $table->index(['subject_type', 'subject_id', 'action']);
                $table->index('created_at');

                $table->index('http_status');
                $table->index('execution_context');
                $table->index('exception_class');

                $table->index('auth_action');
                $table->index('broadcast_channel');
                $table->index('broadcast_status');
            }
        );
    }

    public function down(): void
    {
        Schema::connection($this->connection())->dropIfExists(config('activity-tracker.table', 'activities'));
    }
};
