<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string|null $batch_id
 * @property string|null $request_id
 * @property string|null $causer_type
 * @property int|string|null $causer_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|string|null $subject_id
 * @property string|null $table
 * @property string|null $description
 * @property array|null $old_values
 * @property array|null $new_values
 * @property array|null $changed_values
 * @property string|null $query
 * @property string|null $query_type
 * @property int|null $result_count
 * @property float|null $duration_ms
 * @property int|null $memory_usage
 * @property int|null $memory_peak
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $route_name
 * @property string|null $http_method
 * @property string|null $url
 * @property string|null $path
 * @property string|null $referrer_url
 * @property int|null $http_status
 * @property string|null $execution_context
 * @property string|null $command
 * @property string|null $job_name
 * @property string|null $queue_name
 * @property string|null $queue_connection
 * @property int|null $queue_attempt
 * @property string|null $database_connection
 * @property string|null $exception_class
 * @property string|null $exception_message
 * @property string|null $exception_file
 * @property int|null $exception_line
 * @property string|null $stack_trace
 * @property string|null $auth_action
 * @property string|null $auth_guard
 * @property string|null $auth_provider
 * @property string|null $auth_identifier
 * @property string|null $broadcast_event
 * @property string|null $broadcast_channel
 * @property string|null $broadcast_channel_type
 * @property string|null $broadcast_status
 * @property array|null $metadata
 */
class Activity extends Model
{
    protected $guarded = [];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_values' => 'array',
        'metadata' => 'array',
        'result_count' => 'integer',
        'duration_ms' => 'float',
        'memory_usage' => 'integer',
        'memory_peak' => 'integer',
        'http_status' => 'integer',
        'exception_line' => 'integer',
        'queue_attempt' => 'integer',
    ];

    public function __construct(array $attributes = [])
    {
        $this->setConnection(config('activity-tracker.connection'));
        $this->setTable(config('activity-tracker.table', 'activities'));

        parent::__construct($attributes);
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeCausedBy(Builder $query, Model $causer): Builder
    {
        return $query->where('causer_type', $causer->getMorphClass())
            ->where('causer_id', $causer->getKey());
    }

    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey());
    }

    public function scopeWhereAction(Builder $query, string|array $action): Builder
    {
        return is_array($action)
            ? $query->whereIn('action', $action)
            : $query->where('action', $action);
    }

    public function scopeInBatch(Builder $query, string $batchId): Builder
    {
        return $query->where('batch_id', $batchId);
    }

    public function scopeExceptions(Builder $query): Builder
    {
        return $query->where('action', 'exception');
    }

    public function scopeAuthentication(Builder $query): Builder
    {
        return $query->whereNotNull('auth_action');
    }

    public function scopeBroadcasts(Builder $query): Builder
    {
        return $query->whereNotNull('broadcast_event');
    }

    public function scopeSlowerThan(Builder $query, float $milliseconds): Builder
    {
        return $query->where('duration_ms', '>=', $milliseconds);
    }

    public function isException(): bool
    {
        return $this->action === 'exception';
    }

    public function isAuthEvent(): bool
    {
        return $this->auth_action !== null;
    }

    public function isBroadcastEvent(): bool
    {
        return $this->broadcast_event !== null;
    }
}
