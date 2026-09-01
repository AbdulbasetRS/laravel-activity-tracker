<x-activity-tracker::layout title="Activities">
    <x-activity-tracker::filter-panel
        :inputs="$inputs"
        :known-actions="$knownActions"
        :subject-type-options="$subjectTypeOptions"
        :http-methods="$httpMethods"
        :has-active-filters="$hasActiveFilters"
    />

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
                    <th>ID</th>
                    <x-activity-tracker::sortable-th sort-key="action" label="Action" :current-sort="$inputs['sort']" :current-direction="$inputs['direction']" />
                    <x-activity-tracker::sortable-th sort-key="subject_type" label="Subject" :current-sort="$inputs['sort']" :current-direction="$inputs['direction']" />
                    <x-activity-tracker::sortable-th sort-key="causer" label="Causer" :current-sort="$inputs['sort']" :current-direction="$inputs['direction']" />
                    <th>Description</th>
                    <th>IP address</th>
                    <th>Method</th>
                    <x-activity-tracker::sortable-th sort-key="created_at" label="Date" :current-sort="$inputs['sort']" :current-direction="$inputs['direction']" />
                </tr>
                </thead>
                <tbody>
                @foreach ($activities as $activity)
                    <tr>
                        <td><a href="{{ route('activity-tracker.activities.show', $activity) }}">#{{ $activity->id }}</a></td>
                        <td><x-activity-tracker::badge :action="$activity->action" /></td>
                        <td>{{ $activity->subject_type ? class_basename($activity->subject_type).' #'.$activity->subject_id : '—' }}</td>
                        <td>{{ $activity->causer_type ? class_basename($activity->causer_type).' #'.$activity->causer_id : '—' }}</td>
                        <td style="white-space:normal;max-width:320px;">{{ $activity->description ?? '—' }}</td>
                        <td>{{ $activity->ip_address ?? '—' }}</td>
                        <td>{{ $activity->http_method ?? '—' }}</td>
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
</x-activity-tracker::layout>
