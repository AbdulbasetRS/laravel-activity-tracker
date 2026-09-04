@if ($activities->isEmpty())
    <x-activity-tracker::card>
        <x-activity-tracker::empty-state
            :reset-url="$hasActiveFilters ? route('activity-tracker.activities.index') : null"
        />
    </x-activity-tracker::card>
@else
    <div class="at-table-wrap">
        <table class="at-table">
            <thead>
            <tr>
                <x-activity-tracker::sortable-th sort-key="id" label="ID" :current-sort="$inputs['sort']" :current-direction="$inputs['direction']" />
                <x-activity-tracker::sortable-th sort-key="action" label="Action" :current-sort="$inputs['sort']" :current-direction="$inputs['direction']" />
                <x-activity-tracker::sortable-th sort-key="subject_type" label="Subject" :current-sort="$inputs['sort']" :current-direction="$inputs['direction']" />
                <x-activity-tracker::sortable-th sort-key="causer" label="Causer" :current-sort="$inputs['sort']" :current-direction="$inputs['direction']" />
                <th>URL</th>
                <th>Method</th>
                <x-activity-tracker::sortable-th sort-key="http_status" label="Status" :current-sort="$inputs['sort']" :current-direction="$inputs['direction']" />
                <x-activity-tracker::sortable-th sort-key="duration_ms" label="Duration" :current-sort="$inputs['sort']" :current-direction="$inputs['direction']" />
                <x-activity-tracker::sortable-th sort-key="created_at" label="Date" :current-sort="$inputs['sort']" :current-direction="$inputs['direction']" />
            </tr>
            </thead>
            <tbody>
            @foreach ($activities as $activity)
                <tr class="at-row-enter {{ $activity->isException() ? 'at-row-exception' : '' }}">
                    <td><a href="{{ route('activity-tracker.activities.show', $activity) }}">#{{ $activity->id }}</a></td>
                    <td>
                        <x-activity-tracker::badge :action="$activity->action" />
                        @if ($activity->isException())
                            <div class="at-text-muted" style="margin-top:3px;font-size:11px;">{{ class_basename($activity->exception_class) }}</div>
                        @endif
                    </td>
                    <td>{{ $activity->subject_type ? class_basename($activity->subject_type).' #'.$activity->subject_id : '—' }}</td>
                    <td>{{ $activity->causer_type ? class_basename($activity->causer_type).' #'.$activity->causer_id : '—' }}</td>
                    <td class="at-truncate-cell" title="{{ $activity->url }}">{{ $activity->url ?? $activity->path ?? '—' }}</td>
                    <td>{{ $activity->http_method ?? '—' }}</td>
                    <td>
                        @if ($activity->http_status)
                            <span class="at-badge {{ $activity->http_status >= 500 ? 'at-badge-danger' : ($activity->http_status >= 400 ? 'at-badge-warning' : 'at-badge-success') }}">{{ $activity->http_status }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @php($duration = \Abdulbaset\ActivityTracker\Support\DurationFormatter::format($activity->duration_ms))
                        @if ($duration)
                            <span class="at-duration at-duration-{{ \Abdulbaset\ActivityTracker\Support\DurationFormatter::classify($activity->duration_ms) }}">{{ $duration }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('activity-tracker.activities.show', $activity) }}" title="{{ $activity->created_at }}">
                            {{ $activity->created_at?->diffForHumans() }}
                        </a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <x-activity-tracker::pagination :paginator="$activities" />
@endif
