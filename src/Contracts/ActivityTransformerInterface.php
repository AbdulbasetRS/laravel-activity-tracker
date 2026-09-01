<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ActivityTransformerInterface
{
    /**
     * Build a normalized activity payload for an Eloquent lifecycle event.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function fromModelEvent(string $action, Model $model, array $extra = []): array;

    /**
     * Build a normalized activity payload for a raw/aggregate query event.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function fromQueryEvent(string $action, array $data): array;

    /**
     * Build sanitized old_values/new_values/changed_values for an update.
     *
     * @return array{old_values: array<string, mixed>, new_values: array<string, mixed>, changed_values: array<string, mixed>}
     */
    public function diffFor(Model $model): array;

    /**
     * Sanitized snapshot of a model's current in-memory attributes — used
     * as new_values for "created" and old_values for "deleted"/
     * "force_deleted" (the model instance still holds its pre-deletion
     * attributes at the point those events fire).
     *
     * @return array<string, mixed>
     */
    public function attributesFor(Model $model): array;
}
