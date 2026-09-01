<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ActivityLoggerInterface
{
    /**
     * Log a model-lifecycle-driven activity (created, updated, deleted, ...).
     *
     * @param  array<string, mixed>  $extra
     */
    public function logModelEvent(string $action, Model $model, array $extra = []): void;

    /**
     * Log a query-driven activity (count, exists, aggregates, bulk ops, raw).
     *
     * @param  array<string, mixed>  $data
     */
    public function logQueryEvent(string $action, array $data): void;
}
