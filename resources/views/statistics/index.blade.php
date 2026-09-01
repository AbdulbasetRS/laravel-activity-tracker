<x-activity-tracker::layout title="Statistics">
    <div style="margin-bottom:16px;display:flex;gap:8px;">
        @foreach ($periods as $key)
            <a href="{{ route('activity-tracker.statistics', ['period' => $key]) }}"
               class="at-btn {{ $period === $key ? 'at-btn-primary' : '' }}">
                {{ $key === 'today' ? 'Today' : $key.' days' }}
            </a>
        @endforeach
    </div>

    <x-activity-tracker::card header="Activities over time">
        @php($max = max(1, max($overTime)))
        <div class="at-chart">
            @foreach ($overTime as $day => $count)
                <div class="at-chart-bar" style="height: {{ (int) round(($count / $max) * 100) }}%" title="{{ $day }}: {{ $count }}"></div>
            @endforeach
        </div>
        <div class="at-chart-labels">
            @foreach ($overTime as $day => $count)
                <span>{{ \Illuminate\Support\Carbon::parse($day)->format('M j') }}</span>
            @endforeach
        </div>
    </x-activity-tracker::card>

    <div class="at-grid-2 at-mt-16">
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

        <x-activity-tracker::card header="Top models">
            <div class="at-table-wrap" style="border:none;">
                <table class="at-table">
                    <tbody>
                    @forelse ($topSubjects as $row)
                        <tr>
                            <td>{{ class_basename($row->subject_type) }}</td>
                            <td style="text-align:right;font-weight:600;">{{ number_format($row->aggregate_count) }}</td>
                        </tr>
                    @empty
                        <tr><td class="at-text-muted">No subjects recorded yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </x-activity-tracker::card>
    </div>

    <div class="at-mt-16">
        <x-activity-tracker::card header="Top causers">
            @if ($topCausers->isEmpty())
                <x-activity-tracker::empty-state title="No causers recorded" description="Activities performed by an authenticated user will appear here." />
            @else
                <div class="at-table-wrap" style="border:none;">
                    <table class="at-table">
                        <thead>
                        <tr>
                            <th>Causer type</th>
                            <th>Causer ID</th>
                            <th>Activity count</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($topCausers as $row)
                            <tr>
                                <td>{{ class_basename($row->causer_type) }}</td>
                                <td>
                                    <a href="{{ route('activity-tracker.activities.index', ['causer_type' => $row->causer_type, 'causer_id' => $row->causer_id]) }}">
                                        #{{ $row->causer_id }}
                                    </a>
                                </td>
                                <td>{{ number_format($row->aggregate_count) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-activity-tracker::card>
    </div>
</x-activity-tracker::layout>
