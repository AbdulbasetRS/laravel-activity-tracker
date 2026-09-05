<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Services\Broadcasting;

use Abdulbaset\ActivityTracker\Contracts\BroadcastChannelMonitorInterface;

/**
 * Honest fallback for any broadcasting driver this package has no
 * management-API integration for (redis, log, null, ably, or Pusher/Reverb
 * without the optional pusher/pusher-php-server SDK installed). Never
 * fabricates a channel list or a connection count — every method reports
 * plainly that the information isn't available and why.
 */
final class NullBroadcastChannelMonitor implements BroadcastChannelMonitorInterface
{
    public function __construct(private readonly string $driver)
    {
    }

    public function provider(): string
    {
        return $this->driver;
    }

    public function supportsChannelDiscovery(): bool
    {
        return false;
    }

    public function supportsConnectionCounts(): bool
    {
        return false;
    }

    public function channels(): array
    {
        return [];
    }

    public function presenceMembers(string $channel): ?array
    {
        return null;
    }

    public function unavailableReason(): ?string
    {
        return sprintf(
            'Live connection statistics unavailable for the configured broadcasting driver (%s).',
            $this->driver
        );
    }
}
