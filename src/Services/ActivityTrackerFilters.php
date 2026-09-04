<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Services;

use Abdulbaset\ActivityTracker\Models\Activity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Parses, validates, and applies Activities-index filters/search/sort/
 * pagination against the Activity model. Deliberately framework-request
 * driven (not controller-driven) so it stays reusable outside the UI, e.g.
 * from an Artisan command or an API endpoint, per the design brief.
 *
 * Every value that reaches a query is either whitelisted (sort columns,
 * per-page) or passed through Eloquent's parameter binding (everything
 * else) — nothing here interpolates raw user input into SQL.
 */
final class ActivityTrackerFilters
{
    /**
     * Public sort keys mapped to real, whitelisted column names. Never let
     * a raw `sort` query parameter reach orderBy() directly.
     *
     * @var array<string, string>
     */
    private const SORTABLE = [
        'id' => 'id',
        'created_at' => 'created_at',
        'action' => 'action',
        'subject_type' => 'subject_type',
        'subject_id' => 'subject_id',
        'causer' => 'causer_id',
        'duration_ms' => 'duration_ms',
        'http_status' => 'http_status',
    ];

    private const KNOWN_ACTIONS = [
        'created', 'updated', 'deleted', 'restored', 'force_deleted',
        'retrieved', 'retrieved_many',
        'sum', 'avg', 'min', 'max',
        'bulk_updated', 'bulk_deleted', 'raw_insert',
        'exception',
    ];

    private const HTTP_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

    private const EXECUTION_CONTEXTS = ['http', 'cli', 'queue'];

    public function __construct(private readonly Request $request)
    {
    }

    public static function knownActions(): array
    {
        return self::KNOWN_ACTIONS;
    }

    public static function httpMethods(): array
    {
        return self::HTTP_METHODS;
    }

    public static function executionContexts(): array
    {
        return self::EXECUTION_CONTEXTS;
    }

    /**
     * Distinct subject types actually present in the data, for the filter
     * dropdown. Cached briefly — this list changes rarely and the table can
     * be large.
     *
     * @return array<int, string>
     */
    public function subjectTypeOptions(): array
    {
        return Cache::remember('activity-tracker:subject-types', 60, function () {
            return Activity::query()
                ->whereNotNull('subject_type')
                ->distinct()
                ->orderBy('subject_type')
                ->pluck('subject_type')
                ->all();
        });
    }

    /**
     * Distinct exception classes actually present, for the exception filter
     * dropdown. Cached briefly, same reasoning as subjectTypeOptions().
     *
     * @return array<int, string>
     */
    public function exceptionClassOptions(): array
    {
        return Cache::remember('activity-tracker:exception-classes', 60, function () {
            return Activity::query()
                ->whereNotNull('exception_class')
                ->distinct()
                ->orderBy('exception_class')
                ->pluck('exception_class')
                ->all();
        });
    }

    public function paginate(): LengthAwarePaginator
    {
        return $this->query()
            ->paginate($this->perPage(), ['*'], 'page')
            ->withQueryString();
    }

    public function query(): Builder
    {
        $query = Activity::query();

        $this->applySearch($query);
        $this->applyActionFilter($query);
        $this->applySubjectFilter($query);
        $this->applyCauserFilter($query);
        $this->applyDateRange($query);
        $this->applyIpFilter($query);
        $this->applyHttpMethodFilter($query);
        $this->applyHttpStatusFilter($query);
        $this->applyExecutionContextFilter($query);
        $this->applyExceptionClassFilter($query);
        $this->applySlowFilter($query);
        $this->applyRouteFilter($query);
        $this->applyExactFilter($query, 'request_id');
        $this->applyExactFilter($query, 'batch_id');
        $this->applySort($query);

        return $query;
    }

    /**
     * Normalized filter values, safe to redisplay in the filter form and to
     * echo back into pagination / batch / request links.
     *
     * @return array<string, mixed>
     */
    public function inputs(): array
    {
        return [
            'q' => $this->search(),
            'action' => $this->selectedActions(),
            'subject_type' => $this->request->string('subject_type')->trim()->value() ?: null,
            'causer_type' => $this->request->string('causer_type')->trim()->value() ?: null,
            'causer_id' => $this->request->string('causer_id')->trim()->value() ?: null,
            'date_from' => $this->request->string('date_from')->trim()->value() ?: null,
            'date_to' => $this->request->string('date_to')->trim()->value() ?: null,
            'ip_address' => $this->request->string('ip_address')->trim()->value() ?: null,
            'http_method' => $this->httpMethodFilter(),
            'http_status' => $this->httpStatusFilter(),
            'execution_context' => $this->executionContextFilter(),
            'exception_class' => $this->request->string('exception_class')->trim()->value() ?: null,
            'slow' => $this->request->boolean('slow'),
            'route' => $this->request->string('route')->trim()->value() ?: null,
            'request_id' => $this->request->string('request_id')->trim()->value() ?: null,
            'batch_id' => $this->request->string('batch_id')->trim()->value() ?: null,
            'sort' => $this->sortKey(),
            'direction' => $this->sortDirection(),
            'per_page' => $this->perPage(),
        ];
    }

    public function hasActiveFilters(): bool
    {
        foreach ($this->inputs() as $key => $value) {
            if (in_array($key, ['sort', 'direction', 'per_page'], true)) {
                continue;
            }

            if (! empty($value)) {
                return true;
            }
        }

        return false;
    }

    private function search(): ?string
    {
        $q = $this->request->string('q')->trim()->value();

        return $q === '' ? null : $q;
    }

    /**
     * @return array<int, string>
     */
    private function selectedActions(): array
    {
        $actions = (array) $this->request->input('action', []);

        return array_values(array_intersect($actions, self::KNOWN_ACTIONS));
    }

    private function httpMethodFilter(): ?string
    {
        $method = strtoupper((string) $this->request->string('http_method')->trim()->value());

        return in_array($method, self::HTTP_METHODS, true) ? $method : null;
    }

    private function httpStatusFilter(): ?int
    {
        $status = $this->request->string('http_status')->trim()->value();

        return ctype_digit($status) ? (int) $status : null;
    }

    private function executionContextFilter(): ?string
    {
        $context = strtolower((string) $this->request->string('execution_context')->trim()->value());

        return in_array($context, self::EXECUTION_CONTEXTS, true) ? $context : null;
    }

    private function sortKey(): string
    {
        $sort = (string) $this->request->string('sort')->trim()->value();

        return array_key_exists($sort, self::SORTABLE) ? $sort : 'created_at';
    }

    private function sortDirection(): string
    {
        $direction = strtolower((string) $this->request->string('direction')->trim()->value());

        return $direction === 'asc' ? 'asc' : 'desc';
    }

    private function perPage(): int
    {
        $configured = (array) config('activity-tracker.ui.per_page_options', [25, 50, 100]);
        $requested = (int) $this->request->input('per_page', config('activity-tracker.ui.per_page', 25));

        return in_array($requested, $configured, true)
            ? $requested
            : (int) config('activity-tracker.ui.per_page', 25);
    }

    private function applySearch(Builder $query): void
    {
        $term = $this->search();

        if ($term === null) {
            return;
        }

        $query->where(function (Builder $q) use ($term) {
            $q->where('description', 'like', "%{$term}%")
                ->orWhere('action', 'like', "%{$term}%")
                ->orWhere('subject_type', 'like', "%{$term}%")
                ->orWhere('subject_id', 'like', "%{$term}%")
                ->orWhere('causer_id', 'like', "%{$term}%")
                ->orWhere('ip_address', 'like', "%{$term}%")
                ->orWhere('route_name', 'like', "%{$term}%")
                ->orWhere('request_id', 'like', "%{$term}%")
                ->orWhere('batch_id', 'like', "%{$term}%")
                ->orWhere('exception_class', 'like', "%{$term}%")
                ->orWhere('exception_message', 'like', "%{$term}%")
                ->orWhere('exception_file', 'like', "%{$term}%");
        });
    }

    private function applyActionFilter(Builder $query): void
    {
        $actions = $this->selectedActions();

        if ($actions !== []) {
            $query->whereIn('action', $actions);
        }
    }

    private function applySubjectFilter(Builder $query): void
    {
        $subjectType = $this->inputs()['subject_type'] ?? null;

        if ($subjectType !== null) {
            $query->where('subject_type', $subjectType);
        }
    }

    private function applyCauserFilter(Builder $query): void
    {
        $causerType = $this->request->string('causer_type')->trim()->value();
        $causerId = $this->request->string('causer_id')->trim()->value();

        if ($causerType !== '') {
            $query->where('causer_type', $causerType);
        }

        if ($causerId !== '') {
            $query->where('causer_id', $causerId);
        }
    }

    private function applyDateRange(Builder $query): void
    {
        $from = $this->request->string('date_from')->trim()->value();
        $to = $this->request->string('date_to')->trim()->value();

        if ($from !== '') {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to !== '') {
            $query->whereDate('created_at', '<=', $to);
        }
    }

    private function applyIpFilter(Builder $query): void
    {
        $ip = $this->request->string('ip_address')->trim()->value();

        if ($ip !== '') {
            $query->where('ip_address', 'like', "%{$ip}%");
        }
    }

    private function applyHttpMethodFilter(Builder $query): void
    {
        $method = $this->httpMethodFilter();

        if ($method !== null) {
            $query->where('http_method', $method);
        }
    }

    private function applyHttpStatusFilter(Builder $query): void
    {
        $status = $this->httpStatusFilter();

        if ($status !== null) {
            $query->where('http_status', $status);
        }
    }

    private function applyExecutionContextFilter(Builder $query): void
    {
        $context = $this->executionContextFilter();

        if ($context !== null) {
            $query->where('execution_context', $context);
        }
    }

    private function applyExceptionClassFilter(Builder $query): void
    {
        $class = $this->request->string('exception_class')->trim()->value();

        if ($class !== '') {
            $query->where('exception_class', $class);
        }
    }

    private function applySlowFilter(Builder $query): void
    {
        if (! $this->request->boolean('slow')) {
            return;
        }

        $query->where('duration_ms', '>=', (float) config('activity-tracker.performance.slow_ms', 100));
    }

    private function applyRouteFilter(Builder $query): void
    {
        $route = $this->request->string('route')->trim()->value();

        if ($route !== '') {
            $query->where('route_name', 'like', "%{$route}%");
        }
    }

    private function applyExactFilter(Builder $query, string $column): void
    {
        $value = $this->request->string($column)->trim()->value();

        if ($value !== '') {
            $query->where($column, $value);
        }
    }

    private function applySort(Builder $query): void
    {
        $column = self::SORTABLE[$this->sortKey()];

        $query->orderBy($column, $this->sortDirection());

        if ($column !== 'created_at') {
            // Stable secondary sort so equal-value rows don't jump around
            // between pages.
            $query->orderBy('created_at', 'desc');
        }
    }
}
