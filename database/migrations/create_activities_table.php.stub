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
        Schema::connection($this->connection())->create(config('activity-tracker.table', 'activities'), function (Blueprint $table) {
            $table->id();

            $table->uuid('batch_id')->nullable()->index();
            $table->uuid('request_id')->nullable()->index();

            $table->nullableMorphs('causer');

            $table->string('action', 60)->index();

            $table->nullableMorphs('subject');
            $table->string('table')->nullable()->index();

            $table->text('description')->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('changed_values')->nullable();

            $table->text('query')->nullable();
            $table->string('query_type', 30)->nullable();

            $table->unsignedBigInteger('result_count')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->string('route_name')->nullable();
            $table->string('http_method', 10)->nullable();
            $table->text('url')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'action']);
            $table->index(['causer_type', 'causer_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection())->dropIfExists(config('activity-tracker.table', 'activities'));
    }
};
