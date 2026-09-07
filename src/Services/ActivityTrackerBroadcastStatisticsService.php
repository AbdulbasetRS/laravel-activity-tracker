<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Services;

use Abdulbaset\ActivityTracker\Contracts\BroadcastChannelMonitorInterface;
use Abdulbaset\ActivityTracker\Models\Activity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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
 *
 * PERFORMANCE: a single dashboard render needs the live channel list for
 * several independent numbers (known/active/connections/presence counts).
 * channels() is memoized per-instance so those don't each make their own
 * provider API call, AND wrapped in a short, configurable cache
 * (`broadcast_monitoring.cache_seconds`) so concurrent requests (multiple
 * admins with the dashboard open, or auto-refresh ticks landing close
 * together) don't each hit the provider either. The provider API is never
 * called anywhere outside this service — ordinary activity tracking/
 * browsing never touches it.
 */
final class ActivityTrackerBroadcastStatisticsService
{
    /**
     * @var array<int, array{name: string, type: string, connections: int|null, status: string}>|null
     */
    private ?array $channelsCache = null;

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
        if ($this->channelsCache !== null) {
            return $this->channelsCache;
        }

        $ttl = (int) config('activity-tracker.broadcast_monitoring.cache_seconds', 5);

        return $this->channelsCache = $ttl > 0
            ? Cache::remember('activity-tracker:broadcast-channels:'.$this->monitor->provider(), $ttl, fn () => $this->monitor->channels())
            : $this->monitor->channels();
    }

    public function presenceMembers(string $channel): ?array
    {
        if (! config('activity-tracker.broadcast_monitoring.show_presence_members', true)) {
            return null;
        }

        $ttl = (int) config('activity-tracker.broadcast_monitoring.cache_seconds', 5);

        if ($ttl <= 0) {
            return $this->monitor->presenceMembers($channel);
        }

        $key = 'activity-tracker:broadcast-presence:'.$this->monitor->provider().':'.$channel;

        // Cache::remember() can't distinguish "cached null" from "not yet
        // cached" — so a null result (unsupported/unavailable) is never
        // cached; only an actual member list (even an empty one) is.
        if (Cache::has($key)) {
            return Cache::get($key);
        }

        $members = $this->monitor->presenceMembers($channel);

        if ($members !== null) {
            Cache::put($key, $members, $ttl);
        }

        return $members;
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

    /**
     * "Defined Channels" — the channel PATTERNS registered in the
     * application via `Broadcast::channel(...)` (e.g. `orders.{orderId}`)
     * — a completely different concept from "Active Provider Channels"
     * (channels() above, which are currently-live channels reported by the
     * broadcasting provider itself). A definition existing does NOT mean
     * any client is currently subscribed to a channel matching it; this
     * package never conflates the two.
     *
     * Best-effort only: Laravel does not expose registered channel
     * patterns through any public API, so this reads the (undocumented,
     * version-dependent) internal registry via reflection and degrades to
     * an empty list — never an error — if that shape ever changes.
     *
     * @return array<int, string>
     */
    public function definedChannelPatterns(): array
    {
        try {
            $broadcaster = app(\Illuminate\Contracts\Broadcasting\Factory::class)->connection();
            $reflection = new \ReflectionObject($broadcaster);

            if (! $reflection->hasProperty('channels')) {
                return [];
            }

            $property = $reflection->getProperty('channels');
            $property->setAccessible(true);

            $channels = $property->getValue($broadcaster);

            return is_array($channels) ? array_values(array_map('strval', array_keys($channels))) : [];
        } catch (\Throwable) {
            return [];
        }
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
