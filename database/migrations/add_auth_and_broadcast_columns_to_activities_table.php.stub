<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only, like the observability migration before it — every column
 * is nullable, existing rows are untouched, nothing here is destructive.
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
            // Authentication tracking
            $table->string('auth_action', 40)->nullable()->after('exception_line');
            $table->string('auth_guard')->nullable()->after('auth_action');
            $table->string('auth_provider')->nullable()->after('auth_guard');
            // Already masked (e.g. "a***@example.com") before it ever
            // reaches storage — see AuthenticationSanitizer.
            $table->string('auth_identifier')->nullable()->after('auth_provider');

            // Broadcast monitoring
            $table->string('broadcast_event')->nullable()->after('auth_identifier');
            $table->string('broadcast_channel')->nullable()->after('broadcast_event');
            $table->string('broadcast_channel_type', 20)->nullable()->after('broadcast_channel');
            $table->string('broadcast_status', 20)->nullable()->after('broadcast_channel_type');

            $table->index('auth_action');
            $table->index('broadcast_channel');
            $table->index('broadcast_status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection())->table(config('activity-tracker.table', 'activities'), function (Blueprint $table) {
            $table->dropIndex(['auth_action']);
            $table->dropIndex(['broadcast_channel']);
            $table->dropIndex(['broadcast_status']);

            $table->dropColumn([
                'auth_action', 'auth_guard', 'auth_provider', 'auth_identifier',
                'broadcast_event', 'broadcast_channel', 'broadcast_channel_type', 'broadcast_status',
            ]);
        });
    }
};
