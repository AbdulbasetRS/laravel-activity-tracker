@php
    use Abdulbaset\ActivityTracker\Support\DurationFormatter;
    $connections = $liveInfo['connections'] ?? null;
@endphp
<x-activity-tracker::layout title="Channel: {{ $channel }}">
    <div style="margin-bottom:16px;">
        <a href="{{ route('activity-tracker.broadcasts') }}">&larr; Back to broadcast monitoring</a>
    </div>

    <x-activity-tracker::card>
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div>
                <div style="font-weight:600;font-size:16px;">{{ $channel }}</div>
                <div class="at-text-muted">Type: {{ ucfirst($channelType) }} &middot; Provider: {{ strtoupper($provider) }}</div>
            </div>
            <div class="at-text-muted" style="text-align:right;">
                <div>Connections: {{ $connections !== null ? number_format($connections) : '—' }}</div>
            </div>
        </div>
    </x-activity-tracker::card>

    @if ($unavailableReason)
        <div style="margin-top:16px;">
            <x-activity-tracker::card>
                <p class="at-text-muted" style="margin:0;">{{ $unavailableReason }}</p>
            </x-activity-tracker::card>
        </div>
    @endif

    @if ($channelType === 'presence')
        <x-activity-tracker::card header="Presence members" class="at-mt-16">
            @if ($members === null)
                <x-activity-tracker::empty-state
                    title="Presence members unavailable"
                    :description="$unavailableReason ?? 'Presence member visibility is disabled or unsupported for the configured driver.'"
                />
            @elseif (empty($members))
                <x-activity-tracker::empty-state title="No members currently present" description="Nobody is currently connected to this presence channel." />
            @else
                <div class="at-table-wrap" style="border:none;">
                    <table class="at-table">
                        <thead><tr><th>User ID</th><th>Name</th></tr></thead>
                        <tbody>
                        @foreach ($members as $member)
                            <tr>
                                <td>{{ $member['user_id'] ?? '—' }}</td>
                                <td>{{ $member['name'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-activity-tracker::card>
    @endif

    <div class="at-stat-grid at-mt-16">
        <x-activity-tracker::card>
            <div class="at-stat-label">Successful broadcasts</div>
            <div class="at-stat-value">{{ number_format($history['success']) }}</div>
        </x-activity-tracker::card>
        <x-activity-tracker::card>
            <div class="at-stat-label">Failed broadcasts</div>
            <div class="at-stat-value">{{ number_format($history['failed']) }}</div>
        </x-activity-tracker::card>
        <x-activity-tracker::card>
            <div class="at-stat-label">Avg. duration</div>
            <div class="at-stat-value">{{ $history['avg_duration_ms'] ? DurationFormatter::format((float) $history['avg_duration_ms']) : '—' }}</div>
        </x-activity-tracker::card>
        <x-activity-tracker::card>
            <div class="at-stat-label">Last broadcast</div>
            <div class="at-stat-value" style="font-size:14px;">{{ $history['last_broadcast_at'] ? \Illuminate\Support\Carbon::parse($history['last_broadcast_at'])->diffForHumans() : '—' }}</div>
        </x-activity-tracker::card>
    </div>
</x-activity-tracker::layout>
