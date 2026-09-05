<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Services;

use Abdulbaset\ActivityTracker\Contracts\BroadcastChannelMonitorInterface;
use Abdulbaset\ActivityTracker\Models\Activity;
use Illuminate\Support\Collection;

/**
 * Combines two independent data sources for the Broadcast Monitoring
 * dashboard:
 *
 * - LIVE state (known channels, connection counts, presence members) from
 *   whichever BroadcastChannelMonitorInterface is bound — honestly empty/
 *   unavailable for unsupported drivers, never fabricated.
 * - HISTORICAL activity (recent broadcasts, failure counts) from the
 *   Activity table, populated by ActivityTrackerBroadcastTracker observing
 *   queued broadcast jobs — available regardless of provider, since it
 *   doesn't depend on a management API at all.
 */
final class ActivityTrackerBroadcastStatisticsService
{
    public function __construct(private readonly BroadcastChannelMonitorInterface $monitor)
    {
    }

    public function provider(): string
    {
        return $this->monitor->provider();
    }

    public function supportsChannelDiscovery(): bool
    {
        return $this->monitor->supportsChannelDiscovery();
    }

    public function supportsConnectionCounts(): bool
    {
        return $this->monitor->supportsConnectionCounts();
    }

    public function unavailableReason(): ?string
    {
        return $this->monitor->unavailableReason();
    }

    /**
     * @return array<int, array{name: string, type: string, connections: int|null, status: string}>
     */
    public function channels(): array
    {
        return $this->monitor->channels();
    }

    public function presenceMembers(string $channel): ?array
    {
        if (! config('activity-tracker.broadcast_monitoring.show_presence_members', true)) {
            return null;
        }

        return $this->monitor->presenceMembers($channel);
    }

    public function knownChannelsCount(): int
    {
        return count($this->channels());
    }

    public function activeChannelsCount(): int
    {
        return count(array_filter($this->channels(), static fn (array $c) => $c['status'] === 'active'));
    }

    public function totalConnections(): ?int
    {
        if (! $this->supportsConnectionCounts()) {
            return null;
        }

        $known = array_filter($this->channels(), static fn (array $c) => $c['connections'] !== null);

        if ($known === []) {
            return null;
        }

        return array_sum(array_column($known, 'connections'));
    }

    public function presenceChannelsCount(): int
    {
        return count(array_filter($this->channels(), static fn (array $c) => $c['type'] === 'presence'));
    }

    public function recentBroadcastsCount(int $days = 7): int
    {
        return Activity::query()->broadcasts()
            ->where('created_at', '>=', now()->subDays($days))
            ->count();
    }

    public function broadcastFailuresCount(int $days = 7): int
    {
        return Activity::query()->broadcasts()
            ->where('broadcast_status', 'failed')
            ->where('created_at', '>=', now()->subDays($days))
            ->count();
    }

    /**
     * @return Collection<int, Activity>
     */
    public function recentBroadcasts(int $limit = 10): Collection
    {
        return Activity::query()->broadcasts()->latest('id')->limit($limit)->get();
    }

    /**
     * @return array{success: int, failed: int, avg_duration_ms: float|null, last_broadcast_at: ?\Illuminate\Support\Carbon}
     */
    public function channelHistorySummary(string $channel): array
    {
        $query = Activity::query()->broadcasts()->where('broadcast_channel', $channel);

        return [
            'success' => (clone $query)->where('broadcast_status', 'sent')->count(),
            'failed' => (clone $query)->where('broadcast_status', 'failed')->count(),
            'avg_duration_ms' => (clone $query)->whereNotNull('duration_ms')->avg('duration_ms'),
            'last_broadcast_at' => (clone $query)->latest('id')->value('created_at'),
        ];
    }
}
