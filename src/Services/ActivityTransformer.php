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
            'ip_address' => $this->requestContext->ipAddress(),
            'user_agent' => $this->requestContext->userAgent(),
            'route_name' => $this->requestContext->routeName(),
            'http_method' => $this->requestContext->method(),
            'url' => $this->requestContext->url(),
        ], $extra, ['metadata' => $metadata]);

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
            'ip_address' => $this->requestContext->ipAddress(),
            'user_agent' => $this->requestContext->userAgent(),
            'route_name' => $this->requestContext->routeName(),
            'http_method' => $this->requestContext->method(),
            'url' => $this->requestContext->url(),
        ], $overrides, ['metadata' => $metadata]);
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

    /**
     * Always attach execution_context (http/cli/queue) to metadata, without
     * clobbering any caller-supplied metadata under the same key.
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
