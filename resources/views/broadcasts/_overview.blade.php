@php
    use Abdulbaset\ActivityTracker\Support\DurationFormatter;
@endphp
<div class="at-stat-grid">
    <x-activity-tracker::card>
        <div class="at-stat-label">Known channels</div>
        <div class="at-stat-value">{{ $supportsChannelDiscovery ? number_format($knownChannels) : '—' }}</div>
    </x-activity-tracker::card>
    <x-activity-tracker::card>
        <div class="at-stat-label">Active channels</div>
        <div class="at-stat-value">{{ $supportsChannelDiscovery ? number_format($activeChannels) : '—' }}</div>
    </x-activity-tracker::card>
    <x-activity-tracker::card>
        <div class="at-stat-label">Connections</div>
        <div class="at-stat-value">{{ $totalConnections !== null ? number_format($totalConnections) : '—' }}</div>
    </x-activity-tracker::card>
    <x-activity-tracker::card>
        <div class="at-stat-label">Presence channels</div>
        <div class="at-stat-value">{{ $supportsChannelDiscovery ? number_format($presenceChannels) : '—' }}</div>
    </x-activity-tracker::card>
</div>

<div class="at-stat-grid">
    <x-activity-tracker::card>
        <div class="at-stat-label">Recent broadcasts (7d)</div>
        <div class="at-stat-value">{{ number_format($recentBroadcastsCount) }}</div>
    </x-activity-tracker::card>
    <x-activity-tracker::card>
        <div class="at-stat-label">Broadcast failures (7d)</div>
        <div class="at-stat-value">{{ number_format($broadcastFailures) }}</div>
    </x-activity-tracker::card>
    <x-activity-tracker::card>
        <div class="at-stat-label">Provider</div>
        <div class="at-stat-value" style="font-size:16px;">{{ strtoupper($provider) }}</div>
    </x-activity-tracker::card>
</div>

@if ($unavailableReason)
    <div style="margin-bottom:16px;">
        <x-activity-tracker::card>
            <p class="at-text-muted" style="margin:0;">{{ $unavailableReason }}</p>
        </x-activity-tracker::card>
    </div>
@endif

<x-activity-tracker::card header="Broadcast channels">
    @if (! $supportsChannelDiscovery)
        <x-activity-tracker::empty-state
            title="Channel discovery unavailable"
            :description="$unavailableReason ?? 'The configured broadcasting driver does not expose a channel-listing API.'"
        />
    @elseif (empty($channels))
        <x-activity-tracker::empty-state title="No known channels" description="No channels were reported by the broadcasting provider right now." />
    @else
        <div class="at-table-wrap" style="border:none;">
            <table class="at-table">
                <thead>
                <tr>
                    <th>Channel</th>
                    <th>Type</th>
                    <th>Connections</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($channels as $channel)
                    <tr>
                        <td><a href="{{ route('activity-tracker.broadcasts.channel', ['channel' => $channel['name']]) }}">{{ $channel['name'] }}</a></td>
                        <td><span class="at-badge at-badge-neutral">{{ ucfirst($channel['type']) }}</span></td>
                        <td>{{ $channel['connections'] !== null ? number_format($channel['connections']) : '—' }}</td>
                        <td>
                            <span class="at-badge {{ $channel['status'] === 'active' ? 'at-badge-success' : 'at-badge-neutral' }}">{{ ucfirst($channel['status']) }}</span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-activity-tracker::card>

<x-activity-tracker::card header="Recent broadcast activity" class="at-mt-16">
    @if ($recentBroadcasts->isEmpty())
        <x-activity-tracker::empty-state title="No broadcast activity yet" description="Queued broadcasts (events implementing ShouldBroadcast) will appear here once one runs." />
    @else
        <div class="at-table-wrap" style="border:none;">
            <table class="at-table">
                <thead>
                <tr>
                    <th>Event</th>
                    <th>Channel</th>
                    <th>Status</th>
                    <th>Duration</th>
                    <th>Date</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($recentBroadcasts as $broadcast)
                    <tr>
                        <td><a href="{{ route('activity-tracker.activities.show', $broadcast) }}">{{ $broadcast->broadcast_event ?? '—' }}</a></td>
                        <td>{{ $broadcast->broadcast_channel ?? '—' }}</td>
                        <td>
                            <span class="at-badge {{ $broadcast->broadcast_status === 'sent' ? 'at-badge-success' : 'at-badge-danger' }}">{{ ucfirst($broadcast->broadcast_status ?? 'unknown') }}</span>
                        </td>
                        <td>{{ DurationFormatter::format($broadcast->duration_ms) ?? '—' }}</td>
                        <td title="{{ $broadcast->created_at }}">{{ $broadcast->created_at?->diffForHumans() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-activity-tracker::card>

<p class="at-text-muted" style="margin-top:12px;">
    "Sent" means the queued broadcast operation completed without error — it does not confirm any browser actually received it. Laravel has no built-in client-acknowledgement mechanism to observe that.
</p>
