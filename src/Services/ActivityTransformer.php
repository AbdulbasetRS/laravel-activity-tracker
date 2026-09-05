<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Services;

use Abdulbaset\ActivityTracker\Contracts\ActivityTransformerInterface;
use Abdulbaset\ActivityTracker\Contracts\SensitiveDataSanitizerInterface;
use Abdulbaset\ActivityTracker\Support\CauserResolver;
use Abdulbaset\ActivityTracker\Support\RequestContextResolver;
use Abdulbaset\ActivityTracker\Support\TrackingContext;
use Illuminate\Database\Eloquent\Model;

final class ActivityTransformer implements ActivityTransformerInterface
{
    public function __construct(
        private readonly SensitiveDataSanitizerInterface $sanitizer,
        private readonly CauserResolver $causerResolver,
        private readonly RequestContextResolver $requestContext,
        private readonly TrackingContext $trackingContext,
    ) {
    }

    public function fromModelEvent(string $action, Model $model, array $extra = []): array
    {
        [$causerType, $causerId] = $this->causerResolver->resolve();

        $metadata = $this->mergeMetadata($extra);

        $payload = array_merge([
            'batch_id' => $this->trackingContext->batchId(),
            'request_id' => $this->trackingContext->requestId(),
            'causer_type' => $causerType,
            'causer_id' => $causerId,
            'action' => $action,
            'subject_type' => $model->getMorphClass(),
            'subject_id' => $model->getKey(),
            'table' => $model->getTable(),
            'description' => $this->describe($action, $model),
            'old_values' => null,
            'new_values' => null,
            'changed_values' => null,
            'query' => null,
            'query_type' => null,
            'result_count' => null,
            'duration_ms' => null,
            'memory_usage' => null,
            'memory_peak' => null,
        ], $this->contextDefaults(), $extra, ['metadata' => $metadata]);

        return $payload;
    }

    public function fromQueryEvent(string $action, array $data): array
    {
        [$causerType, $causerId] = $this->causerResolver->resolve();

        $overrides = $data['overrides'] ?? [];
        $metadata = $this->mergeMetadata(['metadata' => $data['metadata'] ?? null] + $overrides);

        return array_merge([
            'batch_id' => $this->trackingContext->batchId(),
            'request_id' => $this->trackingContext->requestId(),
            'causer_type' => $causerType,
            'causer_id' => $causerId,
            'action' => $action,
            'subject_type' => $data['model_type'] ?? null,
            'subject_id' => $data['model_id'] ?? null,
            'table' => $data['table'] ?? null,
            'description' => $data['description'] ?? null,
            'old_values' => null,
            'new_values' => null,
            'changed_values' => null,
            'query' => $data['query'] ?? null,
            'query_type' => $data['query_type'] ?? null,
            'result_count' => $data['result_count'] ?? null,
            'duration_ms' => $data['duration_ms'] ?? null,
            'memory_usage' => null,
            'memory_peak' => null,
        ], $this->contextDefaults(), $overrides, ['metadata' => $metadata]);
    }

    /**
     * Build old_values/new_values/changed_values for an update, with
     * sensitive columns stripped and unchanged attributes omitted.
     *
     * @return array{old_values: array<string, mixed>, new_values: array<string, mixed>, changed_values: array<string, array{old: mixed, new: mixed}>}
     */
    public function diffFor(Model $model): array
    {
        $changes = $model->getChanges();
        $original = $model->getOriginal();

        $oldValues = [];
        $newValues = [];
        $changedValues = [];

        foreach ($changes as $key => $newValue) {
            $oldValues[$key] = $original[$key] ?? null;
            $newValues[$key] = $newValue;
            $changedValues[$key] = [
                'old' => $original[$key] ?? null,
                'new' => $newValue,
            ];
        }

        return [
            'old_values' => $this->sanitizer->sanitizeAttributes($oldValues),
            'new_values' => $this->sanitizer->sanitizeAttributes($newValues),
            'changed_values' => $this->sanitizer->sanitizeAttributes($changedValues),
        ];
    }

    public function attributesFor(Model $model): array
    {
        return $this->sanitizer->sanitizeAttributes($model->getAttributes());
    }

    public function fromException(\Throwable $exception): array
    {
        [$causerType, $causerId] = $this->causerResolver->resolve();

        $metadata = $this->mergeMetadata([]);

        return array_merge(
            $this->contextDefaults(),
            [
                'batch_id' => $this->trackingContext->batchId(),
                'request_id' => $this->trackingContext->requestId(),
                'causer_type' => $causerType,
                'causer_id' => $causerId,
                'action' => 'exception',
                'subject_type' => null,
                'subject_id' => null,
                'table' => null,
                'description' => sprintf('%s: %s', class_basename($exception), $exception->getMessage()),
                'old_values' => null,
                'new_values' => null,
                'changed_values' => null,
                'query' => null,
                'query_type' => null,
                'result_count' => null,
                'duration_ms' => null,
                'memory_usage' => null,
                'memory_peak' => null,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
                'exception_file' => $exception->getFile(),
                'exception_line' => $exception->getLine(),
                'stack_trace' => $this->truncatedTrace($exception),
                // Overrides contextDefaults()'s null placeholder when the
                // exception itself carries a status code (most HTTP-layer
                // exceptions do). Order matters: this array is merged AFTER
                // contextDefaults() specifically so this can win.
                'http_status' => $this->statusCodeFor($exception),
            ],
            ['metadata' => $metadata]
        );
    }

    private function truncatedTrace(\Throwable $exception): ?string
    {
        if (! config('activity-tracker.exceptions.store_trace', true)) {
            return null;
        }

        $trace = $exception->getTraceAsString();
        $max = (int) config('activity-tracker.exceptions.max_trace_length', 10000);

        return mb_strlen($trace) > $max
            ? mb_substr($trace, 0, $max)."\n... (truncated)"
            : $trace;
    }

    /**
     * Only derivable when the exception itself carries a status code (most
     * HTTP-layer exceptions do); otherwise left null and, in an HTTP
     * context, backfilled later by ActivityTrackerRequestLifecycleMiddleware
     * from the actual response — see contextDefaults().
     */
    private function statusCodeFor(\Throwable $exception): ?int
    {
        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        return null;
    }

    public function fromAuthEvent(string $authAction, array $data): array
    {
        // The causer is usually the authenticated user themselves for
        // login/logout — but $data can override it (e.g. a failed login has
        // no authenticated causer at all, and authorization_denied's causer
        // is whoever was denied, already resolved by the caller).
        [$resolvedCauserType, $resolvedCauserId] = $this->causerResolver->resolve();
        $causerType = $data['causer_type'] ?? $resolvedCauserType;
        $causerId = $data['causer_id'] ?? $resolvedCauserId;

        $metadata = $this->mergeMetadata(['metadata' => $data['metadata'] ?? null]);

        return array_merge(
            $this->contextDefaults(),
            [
                'batch_id' => $this->trackingContext->batchId(),
                'request_id' => $this->trackingContext->requestId(),
                'causer_type' => $causerType,
                'causer_id' => $causerId,
                'action' => $authAction,
                'subject_type' => $data['subject_type'] ?? null,
                'subject_id' => $data['subject_id'] ?? null,
                'table' => null,
                'description' => $data['description'] ?? $this->describeAuthEvent($authAction),
                'old_values' => null,
                'new_values' => null,
                'changed_values' => null,
                'query' => null,
                'query_type' => null,
                'result_count' => null,
                'duration_ms' => $data['duration_ms'] ?? null,
                'memory_usage' => null,
                'memory_peak' => null,
                'http_status' => $data['http_status'] ?? null,
                'auth_action' => $authAction,
                'auth_guard' => $data['guard'] ?? null,
                'auth_provider' => $data['provider'] ?? null,
                'auth_identifier' => $data['identifier'] ?? null,
            ],
            ['metadata' => $metadata]
        );
    }

    public function fromBroadcastEvent(string $status, array $data): array
    {
        [$causerType, $causerId] = $this->causerResolver->resolve();

        $metadata = $this->mergeMetadata(['metadata' => $data['metadata'] ?? null]);

        return array_merge(
            $this->contextDefaults(),
            [
                'batch_id' => $this->trackingContext->batchId(),
                'request_id' => $this->trackingContext->requestId(),
                'causer_type' => $causerType,
                'causer_id' => $causerId,
                'action' => 'broadcast',
                'subject_type' => null,
                'subject_id' => null,
                'table' => null,
                'description' => $data['description'] ?? sprintf(
                    'Broadcast %s on %s: %s',
                    $status,
                    $data['channel'] ?? 'unknown channel',
                    $data['event'] ?? 'unknown event'
                ),
                'old_values' => null,
                'new_values' => null,
                'changed_values' => null,
                'query' => null,
                'query_type' => null,
                'result_count' => null,
                'duration_ms' => $data['duration_ms'] ?? null,
                'memory_usage' => null,
                'memory_peak' => null,
                'exception_class' => $data['exception_class'] ?? null,
                'exception_message' => $data['exception_message'] ?? null,
                'broadcast_event' => $data['event'] ?? null,
                'broadcast_channel' => $data['channel'] ?? null,
                'broadcast_channel_type' => $data['channel_type'] ?? null,
                'broadcast_status' => $status,
            ],
            ['metadata' => $metadata]
        );
    }

    private function describeAuthEvent(string $authAction): string
    {
        return match ($authAction) {
            'login' => 'A user logged in.',
            'logout' => 'A user logged out.',
            'login_failed' => 'A login attempt failed.',
            'authenticated' => 'A user was authenticated from stored session/token.',
            'password_reset' => "A user's password was reset.",
            'email_verified' => 'A user verified their email address.',
            'authentication_throttled' => 'Too many login attempts — throttled.',
            'authorization_denied' => 'An authorization check was denied.',
            default => "Authentication event '{$authAction}' occurred.",
        };
    }

    /**
     * Request/CLI/database context shared by every activity, regardless of
     * source. Each accessor already degrades to null outside its
     * applicable context (e.g. command() is null in HTTP, url() is null in
     * CLI), and each already respects its own capture_* config toggle.
     *
     * @return array<string, mixed>
     */
    private function contextDefaults(): array
    {
        $jobContext = $this->trackingContext->isInQueueJob() ? $this->trackingContext->jobContext() : [
            'job_name' => null,
            'queue_name' => null,
            'queue_connection' => null,
            'queue_attempt' => null,
        ];

        return array_merge([
            'ip_address' => $this->requestContext->ipAddress(),
            'user_agent' => $this->requestContext->userAgent(),
            'route_name' => $this->requestContext->routeName(),
            'http_method' => $this->requestContext->method(),
            'url' => $this->requestContext->url(),
            'path' => $this->requestContext->path(),
            'referrer_url' => $this->requestContext->referrer(),
            // http_status is deliberately NOT set here — it genuinely
            // doesn't exist yet at the point most activities are recorded
            // mid-request. It is backfilled once, after the response is
            // sent, by ActivityTrackerRequestLifecycleMiddleware.
            'http_status' => null,
            'execution_context' => $this->executionContext(),
            'command' => $this->requestContext->command(),
            'database_connection' => $this->requestContext->databaseConnection(),
        ], $jobContext);
    }

    /**
     * Always attach execution_context to metadata too (kept for backward
     * compatibility with anything reading it from there pre-upgrade), on
     * top of the dedicated column set by contextDefaults().
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function mergeMetadata(array $extra): array
    {
        $callerMetadata = (array) ($extra['metadata'] ?? []);

        return array_merge(['execution_context' => $this->executionContext()], $callerMetadata);
    }

    private function executionContext(): string
    {
        if ($this->trackingContext->isInQueueJob()) {
            return 'queue';
        }

        return app()->runningInConsole() ? 'cli' : 'http';
    }

    private function describe(string $action, Model $model): string
    {
        $name = class_basename($model);

        return match ($action) {
            'created' => "{$name} was created.",
            'updated' => "{$name} was updated.",
            'deleted' => "{$name} was deleted.",
            'force_deleted' => "{$name} was permanently deleted.",
            'restored' => "{$name} was restored.",
            'retrieved' => "{$name} was retrieved.",
            default => "{$name} action '{$action}' occurred.",
        };
    }
}
