<x-activity-tracker::layout title="Dashboard">
    <div class="at-stat-grid">
        <x-activity-tracker::card>
            <div class="at-stat-label">Total activities</div>
            <div class="at-stat-value">{{ number_format($total) }}</div>
        </x-activity-tracker::card>
        <x-activity-tracker::card>
            <div class="at-stat-label">Today</div>
            <div class="at-stat-value">{{ number_format($today) }}</div>
        </x-activity-tracker::card>
        <x-activity-tracker::card>
            <div class="at-stat-label">This week</div>
            <div class="at-stat-value">{{ number_format($week) }}</div>
        </x-activity-tracker::card>
        <x-activity-tracker::card>
            <div class="at-stat-label">This month</div>
            <div class="at-stat-value">{{ number_format($month) }}</div>
        </x-activity-tracker::card>
    </div>

    <div class="at-stat-grid">
        <x-activity-tracker::card>
            <div class="at-stat-label">Created</div>
            <div class="at-stat-value">{{ number_format($created) }}</div>
        </x-activity-tracker::card>
        <x-activity-tracker::card>
            <div class="at-stat-label">Updated</div>
            <div class="at-stat-value">{{ number_format($updated) }}</div>
        </x-activity-tracker::card>
        <x-activity-tracker::card>
            <div class="at-stat-label">Deleted</div>
            <div class="at-stat-value">{{ number_format($deleted) }}</div>
        </x-activity-tracker::card>
        <x-activity-tracker::card>
            <div class="at-stat-label">Retrieved</div>
            <div class="at-stat-value">{{ number_format($retrieved) }}</div>
        </x-activity-tracker::card>
    </div>

    <div class="at-grid-2">
        <x-activity-tracker::card header="Activity over the last 7 days">
            @php($max = max(1, max($overTime)))
            <div class="at-chart">
                @foreach ($overTime as $day => $count)
                    <div class="at-chart-bar" style="height: {{ (int) round(($count / $max) * 100) }}%" title="{{ $day }}: {{ $count }}"></div>
                @endforeach
            </div>
            <div class="at-chart-labels">
                @foreach ($overTime as $day => $count)
                    <span>{{ \Illuminate\Support\Carbon::parse($day)->format('D') }}</span>
                @endforeach
            </div>
        </x-activity-tracker::card>

        <x-activity-tracker::card header="Activities by action">
            <div class="at-table-wrap" style="border:none;">
                <table class="at-table">
                    <tbody>
                    @forelse ($byAction as $action => $count)
                        <tr>
                            <td><x-activity-tracker::badge :action="$action" /></td>
                            <td style="text-align:right;font-weight:600;">{{ number_format($count) }}</td>
                        </tr>
                    @empty
                        <tr><td class="at-text-muted">No activity recorded yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-activity-tracker::card>
    </div>

    <div style="margin-top:16px;">
        <x-activity-tracker::card header="Recent activity">
            @if ($recent->isEmpty())
                <x-activity-tracker::empty-state title="No activities yet" description="Activities will appear here as your application runs." />
            @else
                <div class="at-table-wrap" style="border:none;">
                    <table class="at-table">
                        <thead>
                        <tr>
                            <th>Action</th>
                            <th>Subject</th>
                            <th>Description</th>
                            <th>Date</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($recent as $activity)
                            <tr>
                                <td><x-activity-tracker::badge :action="$activity->action" /></td>
                                <td>{{ $activity->subject_type ? class_basename($activity->subject_type).' #'.$activity->subject_id : '—' }}</td>
                                <td>{{ $activity->description ?? '—' }}</td>
                                <td>
                                    <a href="{{ route('activity-tracker.activities.show', $activity) }}">
                                        {{ $activity->created_at?->diffForHumans() }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-activity-tracker::card>
    </div>
</x-activity-tracker::layout>
