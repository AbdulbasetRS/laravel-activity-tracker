@props(['inputs', 'knownActions', 'subjectTypeOptions', 'httpMethods', 'hasActiveFilters', 'exceptionClassOptions' => [], 'executionContexts' => []])
@php
    $filterOnlyKeys = ['q', 'action', 'subject_type', 'causer_type', 'causer_id', 'date_from', 'date_to', 'ip_address', 'http_method', 'http_status', 'execution_context', 'exception_class', 'slow', 'route', 'request_id', 'batch_id'];
    $activeCount = 0;
    foreach ($filterOnlyKeys as $key) {
        $value = $inputs[$key] ?? null;
        if (! empty($value)) {
            $activeCount++;
        }
    }
@endphp
<form method="GET" action="{{ route('activity-tracker.activities.index') }}" data-at-filter-form>
    <div class="at-search-bar">
        <input type="text" name="q" value="{{ $inputs['q'] }}" class="at-input" placeholder="Search description, action, subject, causer, IP, route, request ID, batch ID&hellip;" aria-label="Search activities" data-at-search-input autocomplete="off">
        <button type="submit" class="at-btn at-btn-primary">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.3-4.3"/></svg>
            Search
        </button>
        <button type="button" class="at-btn" data-at-filter-toggle aria-expanded="{{ $hasActiveFilters ? 'true' : 'false' }}">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
            Filters
            @if ($activeCount > 0)
                <span class="at-filter-count" data-at-filter-count>{{ $activeCount }} active</span>
            @endif
        </button>
        <select name="per_page" class="at-select" style="width:auto;" data-at-ajax-select aria-label="Results per page">
            @foreach (config('activity-tracker.ui.per_page_options', [25, 50, 100, 250]) as $option)
                <option value="{{ $option }}" @selected((int) $inputs['per_page'] === (int) $option)>{{ $option }} / page</option>
            @endforeach
        </select>
        @if ($hasActiveFilters)
            <a href="{{ route('activity-tracker.activities.index') }}" class="at-btn" data-at-ajax-link>Reset</a>
        @endif
    </div>

    <div class="at-filter-panel {{ $hasActiveFilters ? 'is-open' : '' }}" data-at-filter-panel>
        <div class="at-form-grid">
            <div class="at-field">
                <label class="at-label" for="at-filter-subject-type">Subject type</label>
                <select id="at-filter-subject-type" name="subject_type" class="at-select">
                    <option value="">All</option>
                    @foreach ($subjectTypeOptions as $type)
                        <option value="{{ $type }}" @selected($inputs['subject_type'] === $type)>{{ class_basename($type) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="at-field">
                <label class="at-label" for="at-filter-causer-id">Causer ID</label>
                <input id="at-filter-causer-id" type="text" name="causer_id" value="{{ $inputs['causer_id'] }}" class="at-input">
            </div>

            <div class="at-field">
                <label class="at-label" for="at-filter-causer-type">Causer type</label>
                <input id="at-filter-causer-type" type="text" name="causer_type" value="{{ $inputs['causer_type'] }}" class="at-input" placeholder="App\Models\User">
            </div>

            <div class="at-field">
                <label class="at-label" for="at-filter-date-from">Date from</label>
                <input id="at-filter-date-from" type="date" name="date_from" value="{{ $inputs['date_from'] }}" class="at-input">
            </div>

            <div class="at-field">
                <label class="at-label" for="at-filter-date-to">Date to</label>
                <input id="at-filter-date-to" type="date" name="date_to" value="{{ $inputs['date_to'] }}" class="at-input">
            </div>

            <div class="at-field">
                <label class="at-label" for="at-filter-ip">IP address</label>
                <input id="at-filter-ip" type="text" name="ip_address" value="{{ $inputs['ip_address'] }}" class="at-input">
            </div>

            <div class="at-field">
                <label class="at-label" for="at-filter-method">HTTP method</label>
                <select id="at-filter-method" name="http_method" class="at-select">
                    <option value="">All</option>
                    @foreach ($httpMethods as $method)
                        <option value="{{ $method }}" @selected($inputs['http_method'] === $method)>{{ $method }}</option>
                    @endforeach
                </select>
            </div>

            <div class="at-field">
                <label class="at-label" for="at-filter-route">Route name</label>
                <input id="at-filter-route" type="text" name="route" value="{{ $inputs['route'] }}" class="at-input">
            </div>

            <div class="at-field">
                <label class="at-label" for="at-filter-request-id">Request ID</label>
                <input id="at-filter-request-id" type="text" name="request_id" value="{{ $inputs['request_id'] }}" class="at-input">
            </div>

            <div class="at-field">
                <label class="at-label" for="at-filter-batch-id">Batch ID</label>
                <input id="at-filter-batch-id" type="text" name="batch_id" value="{{ $inputs['batch_id'] }}" class="at-input">
            </div>

            <div class="at-field">
                <label class="at-label" for="at-filter-status">HTTP status</label>
                <input id="at-filter-status" type="text" inputmode="numeric" name="http_status" value="{{ $inputs['http_status'] }}" class="at-input" placeholder="e.g. 500">
            </div>

            <div class="at-field">
                <label class="at-label" for="at-filter-context">Execution context</label>
                <select id="at-filter-context" name="execution_context" class="at-select">
                    <option value="">All</option>
                    @foreach ($executionContexts as $context)
                        <option value="{{ $context }}" @selected($inputs['execution_context'] === $context)>{{ strtoupper($context) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="at-field">
                <label class="at-label" for="at-filter-exception-class">Exception class</label>
                <select id="at-filter-exception-class" name="exception_class" class="at-select">
                    <option value="">All</option>
                    @foreach ($exceptionClassOptions as $class)
                        <option value="{{ $class }}" @selected($inputs['exception_class'] === $class)>{{ class_basename($class) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="at-field" style="margin-top:12px;">
            <label class="at-checkbox-item">
                <input type="checkbox" name="slow" value="1" @checked($inputs['slow'])>
                Slow activities only (&ge; {{ config('activity-tracker.performance.slow_ms', 100) }} ms)
            </label>
        </div>

        <div class="at-field" style="margin-top:4px;">
            <label class="at-label">Actions</label>
            <div class="at-checkbox-list">
                @foreach ($knownActions as $action)
                    <label class="at-checkbox-item">
                        <input type="checkbox" name="action[]" value="{{ $action }}" @checked(in_array($action, $inputs['action'], true))>
                        {{ str_replace('_', ' ', $action) }}
                    </label>
                @endforeach
            </div>
        </div>

        <input type="hidden" name="sort" value="{{ $inputs['sort'] }}" data-at-sort-field>
        <input type="hidden" name="direction" value="{{ $inputs['direction'] }}" data-at-direction-field>

        <div class="at-filter-actions">
            <button type="submit" class="at-btn at-btn-primary">Apply filters</button>
            <a href="{{ route('activity-tracker.activities.index') }}" class="at-btn" data-at-ajax-link>Reset</a>
        </div>
    </div>
</form>
