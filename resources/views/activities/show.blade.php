@php
    use Abdulbaset\ActivityTracker\Support\DurationFormatter;

    $isUpdate = $activity->action === 'updated' || ($activity->action === 'restored' && $activity->changed_values);
    $isCreated = $activity->action === 'created';
    $isDeleted = in_array($activity->action, ['deleted', 'force_deleted'], true);
    $isRetrieval = in_array($activity->action, ['retrieved', 'retrieved_many'], true);
    $isAggregate = in_array($activity->action, ['sum', 'avg', 'min', 'max'], true);
    $isBulk = in_array($activity->action, ['bulk_updated', 'bulk_deleted', 'raw_insert'], true);
    $isException = $activity->isException();
    $executionContext = $activity->execution_context ?? $activity->metadata['execution_context'] ?? null;
    $duration = DurationFormatter::format($activity->duration_ms);
    $durationClass = DurationFormatter::classify($activity->duration_ms);
@endphp
<x-activity-tracker::layout title="Activity #{{ $activity->id }}">
    <div style="margin-bottom:16px;">
        <a href="{{ route('activity-tracker.activities.index') }}">&larr; Back to activities</a>
    </div>

    <x-activity-tracker::card>
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <x-activity-tracker::badge :action="$activity->action" />
                <div>
                    <div style="font-weight:600;">{{ $activity->description ?? 'Activity #'.$activity->id }}</div>
                    <div class="at-text-muted" title="{{ $activity->created_at }}">{{ $activity->created_at?->format('F j, Y H:i:s') }}</div>
                </div>
            </div>
            <div class="at-text-muted" style="text-align:right;">
                <div>Activity #{{ $activity->id }}</div>
                @if ($executionContext && $executionContext !== 'http')
                    <div>Execution context: {{ strtoupper($executionContext) }}</div>
                @endif
                @if ($duration)
                    <div class="at-duration at-duration-{{ $durationClass }}">{{ $duration }}</div>
                @endif
            </div>
        </div>
    </x-activity-tracker::card>

    @if ($isException)
        <x-activity-tracker::card header="Exception" class="at-mt-16">
            <div class="at-meta-grid">
                <x-activity-tracker::meta-item label="Class" :value="$activity->exception_class" />
                <x-activity-tracker::meta-item label="HTTP status" :value="$activity->http_status" />
                <x-activity-tracker::meta-item label="File" :value="$activity->exception_file ? basename($activity->exception_file).':'.$activity->exception_line : null" />
                <x-activity-tracker::meta-item label="Execution context" :value="$executionContext ? strtoupper($executionContext) : null" />
            </div>
            <div class="at-meta-item" style="margin-top:10px;">
                <div class="at-meta-label">Message</div>
                <div class="at-meta-value">{{ $activity->exception_message ?? '—' }}</div>
            </div>
            @if ($activity->stack_trace)
                <div style="margin-top:14px;">
                    <div class="at-collapsible-toggle" data-at-collapsible-toggle="at-stack-trace-body" role="button" tabindex="0" aria-expanded="false">
                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
                        Stack trace
                    </div>
                    <div id="at-stack-trace-body" class="at-collapsible-body">
                        <div class="at-json-viewer" data-at-json="{{ json_encode($activity->stack_trace) }}" style="position:relative;">
                            <button type="button" class="at-btn at-btn-icon at-json-copy" data-at-json-copy aria-label="Copy stack trace">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="11" height="11" rx="1"/><path d="M5 15V5a2 2 0 012-2h10"/></svg>
                            </button>
                            <pre class="at-stack-trace">{{ $activity->stack_trace }}</pre>
                        </div>
                    </div>
                </div>
            @endif
        </x-activity-tracker::card>
    @endif

    <div class="at-grid-2" style="margin-top:16px;">
        <x-activity-tracker::card header="Subject">
            @if ($activity->subject_type)
                <div class="at-meta-grid">
                    <x-activity-tracker::meta-item label="Subject type" :value="class_basename($activity->subject_type)" />
                    <x-activity-tracker::meta-item label="Subject ID" :value="$activity->subject_id" />
                </div>
                @if (! $subject)
                    <p class="at-text-muted" style="margin-top:10px;">Model no longer exists.</p>
                @endif
            @else
                <p class="at-text-muted">This activity has no associated subject.</p>
            @endif
        </x-activity-tracker::card>

        <x-activity-tracker::card header="Causer">
            @if ($activity->causer_type)
                <div class="at-meta-grid">
                    <x-activity-tracker::meta-item label="Causer type" :value="class_basename($activity->causer_type)" />
                    <x-activity-tracker::meta-item label="Causer ID" :value="$activity->causer_id" />
                    <x-activity-tracker::meta-item label="Name" :value="optional($causer)->name" />
                    <x-activity-tracker::meta-item label="Email" :value="optional($causer)->email" />
                </div>
                @if (! $causer)
                    <p class="at-text-muted" style="margin-top:10px;">Causer no longer exists.</p>
                @endif
            @else
                <p class="at-text-muted">No authenticated causer (guest, CLI, or system operation).</p>
            @endif
        </x-activity-tracker::card>
    </div>

    @if ($isRetrieval)
        <x-activity-tracker::card header="Retrieved records" class="at-mt-16">
            <div class="at-meta-grid">
                <x-activity-tracker::meta-item label="Model type" :value="$activity->subject_type ? class_basename($activity->subject_type) : null" />
                <x-activity-tracker::meta-item label="Result count" :value="$activity->result_count" />
            </div>
            @if (! empty($activity->metadata['ids'] ?? null))
                <details style="margin-top:12px;">
                    <summary class="at-label" style="cursor:pointer;">Retrieved IDs ({{ count($activity->metadata['ids']) }})</summary>
                    <x-activity-tracker::json-viewer :data="$activity->metadata['ids']" />
                </details>
            @endif
        </x-activity-tracker::card>
    @elseif ($isAggregate)
        <x-activity-tracker::card header="Aggregate operation">
            <div class="at-meta-grid">
                <x-activity-tracker::meta-item label="Operation" :value="strtoupper($activity->action)" />
                <x-activity-tracker::meta-item label="Table" :value="$activity->table" />
                <x-activity-tracker::meta-item label="Result" :muted="true" value="Not captured — see README section on Limitations." />
            </div>
        </x-activity-tracker::card>
    @elseif ($isBulk)
        <x-activity-tracker::card header="Bulk / raw operation">
            <div class="at-meta-grid">
                <x-activity-tracker::meta-item label="Operation" :value="strtoupper($activity->action)" />
                <x-activity-tracker::meta-item label="Table" :value="$activity->table" />
                <x-activity-tracker::meta-item label="Affected rows" :muted="true" value="Not captured — see README section on Limitations." />
            </div>
        </x-activity-tracker::card>
    @elseif ($isCreated && $activity->new_values)
        <x-activity-tracker::card header="Created values">
            <x-activity-tracker::json-viewer :data="$activity->new_values" />
        </x-activity-tracker::card>
    @elseif ($isDeleted && $activity->old_values)
        <x-activity-tracker::card header="Values at time of deletion">
            <x-activity-tracker::json-viewer :data="$activity->old_values" />
        </x-activity-tracker::card>
    @elseif ($activity->changed_values)
        <x-activity-tracker::card header="Changes">
            <div class="at-table-wrap" style="border:none;">
                <table class="at-table at-diff-table" style="white-space:normal;">
                    <thead>
                    <tr>
                        <th>Field</th>
                        <th>Old value</th>
                        <th>New value</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($activity->changed_values as $field => $change)
                        <tr>
                            <td>{{ $field }}</td>
                            <td class="at-diff-old">{{ is_scalar($change['old'] ?? null) ? $change['old'] : json_encode($change['old'] ?? null) }}</td>
                            <td class="at-diff-new">{{ is_scalar($change['new'] ?? null) ? $change['new'] : json_encode($change['new'] ?? null) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </x-activity-tracker::card>
    @endif

    @if ($activity->query)
        <x-activity-tracker::card header="Database query" class="at-mt-16">
            <div class="at-meta-grid" style="margin-bottom:12px;">
                <x-activity-tracker::meta-item label="Query type" :value="$activity->query_type ? strtoupper($activity->query_type) : null" />
                <x-activity-tracker::meta-item label="Result count" :value="$activity->result_count" />
                <x-activity-tracker::meta-item label="Duration" :value="$duration" />
                <x-activity-tracker::meta-item label="Connection" :value="$activity->database_connection" />
            </div>
            <div class="at-json-viewer">
                <pre style="white-space:pre-wrap;">{{ $activity->query }}</pre>
            </div>
        </x-activity-tracker::card>
    @endif

    <div class="at-grid-2" style="margin-top:16px;">
        <x-activity-tracker::card header="Request">
            @if ($activity->url || $activity->http_method)
                <div class="at-meta-grid">
                    <x-activity-tracker::meta-item label="HTTP method" :value="$activity->http_method" />
                    <x-activity-tracker::meta-item label="HTTP status" :value="$activity->http_status" />
                    <x-activity-tracker::meta-item label="Route (secondary)" :value="$activity->route_name" />
                    <x-activity-tracker::meta-item label="IP address" :value="$activity->ip_address" />
                </div>
                @if ($activity->url)
                    <div class="at-meta-item" style="margin-top:10px;">
                        <div class="at-meta-label">URL (primary location)</div>
                        <div class="at-meta-value" style="word-break:break-all;">{{ $activity->url }}</div>
                    </div>
                @endif
                @if ($activity->referrer_url)
                    <div class="at-meta-item" style="margin-top:10px;">
                        <div class="at-meta-label">Referrer</div>
                        <div class="at-meta-value" style="word-break:break-all;">{{ $activity->referrer_url }}</div>
                    </div>
                @endif
                @if ($activity->user_agent)
                    <div class="at-meta-item" style="margin-top:10px;">
                        <div class="at-meta-label">User agent</div>
                        <div class="at-meta-value" style="word-break:break-all;">{{ $activity->user_agent }}</div>
                    </div>
                @endif
            @elseif ($activity->command)
                <p class="at-text-muted">Execution context: CLI</p>
                <x-activity-tracker::meta-item label="Command" :value="$activity->command" />
            @else
                <p class="at-text-muted">Execution context: {{ strtoupper($executionContext ?? 'cli') }}. No HTTP request was involved.</p>
            @endif
            @if ($activity->request_id)
                <div class="at-meta-item" style="margin-top:10px;">
                    <x-activity-tracker::meta-item label="Request ID" :value="$activity->request_id" />
                </div>
            @endif
        </x-activity-tracker::card>

        <x-activity-tracker::card header="Correlation">
            <div class="at-meta-grid">
                <x-activity-tracker::meta-item label="Batch ID" :value="$activity->batch_id" />
                <x-activity-tracker::meta-item label="Request ID" :value="$activity->request_id" />
            </div>
            <div class="at-filter-actions" style="margin-top:14px;">
                @if ($activity->batch_id)
                    <a href="{{ route('activity-tracker.activities.index', ['batch_id' => $activity->batch_id]) }}" class="at-btn">View batch activities</a>
                @endif
                @if ($activity->request_id)
                    <a href="{{ route('activity-tracker.activities.index', ['request_id' => $activity->request_id]) }}" class="at-btn">View request activities</a>
                @endif
            </div>
        </x-activity-tracker::card>
    </div>

    @if ($activity->metadata)
        <x-activity-tracker::card header="Metadata" class="at-mt-16">
            <x-activity-tracker::json-viewer :data="$activity->metadata" />
        </x-activity-tracker::card>
    @endif
</x-activity-tracker::layout>
