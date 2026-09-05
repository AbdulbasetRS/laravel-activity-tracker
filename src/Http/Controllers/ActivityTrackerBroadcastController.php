<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Http\Controllers;

use Abdulbaset\ActivityTracker\Services\ActivityTrackerBroadcastStatisticsService;
use Abdulbaset\ActivityTracker\Support\TrackingContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class ActivityTrackerBroadcastController extends Controller
{
    public function __construct(
        private readonly ActivityTrackerBroadcastStatisticsService $stats,
        private readonly TrackingContext $trackingContext,
    ) {
    }

    public function index(Request $request): View|JsonResponse
    {
        $data = $this->overviewData();

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'html' => view('activity-tracker::broadcasts._overview', $data)->render(),
                ],
            ]);
        }

        return view('activity-tracker::broadcasts.index', $data);
    }

    public function channel(string $channel): View
    {
        $data = $this->trackingContext->withoutTracking(fn () => [
            'channel' => $channel,
            'channelType' => $this->channelType($channel),
            'provider' => $this->stats->provider(),
            'supportsConnectionCounts' => $this->stats->supportsConnectionCounts(),
            'liveInfo' => collect($this->stats->channels())->firstWhere('name', $channel),
            'members' => $this->stats->presenceMembers($channel),
            'history' => $this->stats->channelHistorySummary($channel),
            'unavailableReason' => $this->stats->unavailableReason(),
        ]);

        return view('activity-tracker::broadcasts.channel', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function overviewData(): array
    {
        return $this->trackingContext->withoutTracking(fn () => [
            'provider' => $this->stats->provider(),
            'supportsChannelDiscovery' => $this->stats->supportsChannelDiscovery(),
            'supportsConnectionCounts' => $this->stats->supportsConnectionCounts(),
            'unavailableReason' => $this->stats->unavailableReason(),
            'channels' => $this->stats->channels(),
            'knownChannels' => $this->stats->knownChannelsCount(),
            'activeChannels' => $this->stats->activeChannelsCount(),
            'totalConnections' => $this->stats->totalConnections(),
            'presenceChannels' => $this->stats->presenceChannelsCount(),
            'recentBroadcastsCount' => $this->stats->recentBroadcastsCount(),
            'broadcastFailures' => $this->stats->broadcastFailuresCount(),
            'recentBroadcasts' => $this->stats->recentBroadcasts(),
            'autoRefresh' => (bool) config('activity-tracker.broadcast_monitoring.auto_refresh', true),
            'refreshInterval' => (int) config('activity-tracker.broadcast_monitoring.refresh_interval', 10000),
        ]);
    }

    private function channelType(string $channel): string
    {
        return match (true) {
            str_starts_with($channel, 'presence-') => 'presence',
            str_starts_with($channel, 'private-') => 'private',
            default => 'public',
        };
    }
}
