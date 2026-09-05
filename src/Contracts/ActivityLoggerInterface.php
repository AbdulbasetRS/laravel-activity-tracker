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
     * Log a query-driven activity (aggregates, bulk ops, raw queries).
     *
     * @param  array<string, mixed>  $data
     */
    public function logQueryEvent(string $action, array $data): void;

    /**
     * Log a deliberate "this record was viewed through the audit UI" event,
     * independent of the automatic Eloquent "retrieved" listener. Use this
     * from application code (or the package's own dashboard) to record
     * intentional views without relying on incidental model hydration.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function logIntentionalView(Model $model, array $metadata = []): void;

    /**
     * Log an unhandled/reported exception as a dedicated "exception"
     * activity — never as a CRUD action. Callers (normally
     * ActivityTrackerExceptionService, via the exception handler decorator)
     * are responsible for deciding WHETHER to call this (ignored-class
     * filtering, deduplication); this method assumes that decision has
     * already been made and simply records it.
     */
    public function logException(\Throwable $exception): void;

    /**
     * Log an authentication or account-security event (login, logout,
     * login_failed, authorization_denied, ...) as a dedicated activity —
     * never as a CRUD action on the user model.
     *
     * @param  array<string, mixed>  $data
     */
    public function logAuthEvent(string $authAction, array $data): void;

    /**
     * Log an observed broadcast operation (a queued ShouldBroadcast job
     * completing or failing) as a dedicated activity.
     *
     * @param  array<string, mixed>  $data
     */
    public function logBroadcastEvent(string $status, array $data): void;
}
