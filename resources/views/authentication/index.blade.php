<x-activity-tracker::layout title="Authentication">
    <div class="at-stat-grid">
        <x-activity-tracker::card>
            <div class="at-stat-label">Successful logins</div>
            <div class="at-stat-value">{{ number_format($successfulLogins) }}</div>
        </x-activity-tracker::card>
        <x-activity-tracker::card>
            <div class="at-stat-label">Failed logins</div>
            <div class="at-stat-value">{{ number_format($failedLogins) }}</div>
        </x-activity-tracker::card>
        <x-activity-tracker::card>
            <div class="at-stat-label">Logouts</div>
            <div class="at-stat-value">{{ number_format($logouts) }}</div>
        </x-activity-tracker::card>
        <x-activity-tracker::card>
            <div class="at-stat-label">Password resets</div>
            <div class="at-stat-value">{{ number_format($passwordResets) }}</div>
        </x-activity-tracker::card>
    </div>

    <div class="at-stat-grid">
        <x-activity-tracker::card>
            <div class="at-stat-label">Authentication throttles</div>
            <div class="at-stat-value">{{ number_format($throttles) }}</div>
        </x-activity-tracker::card>
        <x-activity-tracker::card>
            <div class="at-stat-label">Authorization denials</div>
            <div class="at-stat-value">{{ number_format($authorizationDenials) }}</div>
        </x-activity-tracker::card>
    </div>

    <p class="at-text-muted" style="margin:4px 0 16px;">Last 7 days. Identifiers (emails/usernames) shown below are masked — see the config to change the masked field.</p>

    <x-activity-tracker::card header="Recent authentication activity">
        @if ($recent->isEmpty())
            <x-activity-tracker::empty-state title="No authentication activity yet" description="Logins, logouts, and related security events will appear here." />
        @else
            <div class="at-table-wrap" style="border:none;">
                <table class="at-table">
                    <thead>
                    <tr>
                        <th>Action</th>
                        <th>Causer</th>
                        <th>Identifier</th>
                        <th>Guard</th>
                        <th>IP address</th>
                        <th>Date</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($recent as $activity)
                        <tr>
                            <td><a href="{{ route('activity-tracker.activities.show', $activity) }}"><x-activity-tracker::badge :action="$activity->action" /></a></td>
                            <td>{{ $activity->causer_type ? class_basename($activity->causer_type).' #'.$activity->causer_id : '—' }}</td>
                            <td>{{ $activity->auth_identifier ?? '—' }}</td>
                            <td>{{ $activity->auth_guard ?? '—' }}</td>
                            <td>{{ $activity->ip_address ?? '—' }}</td>
                            <td title="{{ $activity->created_at }}">{{ $activity->created_at?->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="at-filter-actions" style="margin-top:14px;">
            <a href="{{ $indexUrl }}" class="at-btn at-btn-primary">Browse all authentication activity</a>
        </div>
    </x-activity-tracker::card>
</x-activity-tracker::layout>
