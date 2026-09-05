<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Contracts;

/**
 * Provider-specific adapter for live broadcast channel/connection state.
 *
 * This is deliberately NOT assumed to be available — most broadcasting
 * drivers (redis, log, null, ably without extra setup) expose no
 * management API for "which channels currently exist" or "how many
 * clients are connected". Implementations MUST report that honestly via
 * supportsChannelDiscovery()/supportsConnectionCounts() rather than
 * fabricating data, and MUST NEVER let a provider API failure propagate —
 * every method degrades to an empty/unavailable result on error.
 */
interface BroadcastChannelMonitorInterface
{
    /**
     * A short, human-readable name for the active broadcasting driver,
     * e.g. "pusher", "reverb", "redis", "log", "null".
     */
    public function provider(): string;

    public function supportsChannelDiscovery(): bool;

    public function supportsConnectionCounts(): bool;

    /**
     * @return array<int, array{name: string, type: string, connections: int|null, status: string}>
     */
    public function channels(): array;

    /**
     * Members currently present in a presence channel, or null when
     * unsupported/unavailable/not a presence channel. Each member is a
     * plain array with whatever the provider actually exposes — never
     * fabricated, never includes credentials/tokens/session data.
     *
     * @return array<int, array{user_id: mixed, name: string|null}>|null
     */
    public function presenceMembers(string $channel): ?array;

    /**
     * A human-readable explanation for why live stats aren't available,
     * or null when they are.
     */
    public function unavailableReason(): ?string;
}
