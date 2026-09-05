<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Services\Broadcasting;

use Abdulbaset\ActivityTracker\Contracts\BroadcastChannelMonitorInterface;
use Throwable;

/**
 * Live channel/connection stats for the "pusher" driver, and for "reverb"
 * (Laravel Reverb implements the Pusher HTTP protocol, so the same SDK and
 * API calls work against it when pointed at Reverb's host/port).
 *
 * Requires the OPTIONAL `pusher/pusher-php-server` package — never a hard
 * dependency of this package. If it isn't installed, or the client can't be
 * constructed, or any API call fails, this degrades to reporting
 * unavailability rather than throwing — a third-party provider outage must
 * never break the host application.
 */
final class PusherBroadcastChannelMonitor implements BroadcastChannelMonitorInterface
{
    private readonly ?object $client;

    private ?string $lastError = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly string $driver, array $config)
    {
        $this->client = $this->makeClient($config);
    }

    public function provider(): string
    {
        return $this->driver;
    }

    public function supportsChannelDiscovery(): bool
    {
        return $this->client !== null;
    }

    public function supportsConnectionCounts(): bool
    {
        return $this->client !== null;
    }

    public function channels(): array
    {
        if ($this->client === null) {
            return [];
        }

        try {
            $response = $this->client->getChannels(['info' => 'user_count']);
            $channels = $this->toArray($response)['channels'] ?? [];

            $result = [];

            foreach ($channels as $name => $info) {
                $infoArray = $this->toArray($info);

                $result[] = [
                    'name' => (string) $name,
                    'type' => $this->classify((string) $name),
                    'connections' => isset($infoArray['user_count']) ? (int) $infoArray['user_count'] : null,
                    'status' => 'active',
                ];
            }

            return $result;
        } catch (Throwable $e) {
            $this->lastError = 'Broadcasting provider API error: '.$e->getMessage();

            return [];
        }
    }

    public function presenceMembers(string $channel): ?array
    {
        if ($this->client === null || $this->classify($channel) !== 'presence') {
            return null;
        }

        try {
            $response = $this->client->getChannelUsers($channel);
            $users = $this->toArray($response)['users'] ?? [];

            $result = [];

            foreach ($users as $user) {
                $userArray = $this->toArray($user);

                $result[] = [
                    'user_id' => $userArray['id'] ?? null,
                    'name' => $userArray['name'] ?? ($userArray['user_info']['name'] ?? null),
                ];
            }

            return $result;
        } catch (Throwable $e) {
            $this->lastError = 'Broadcasting provider API error: '.$e->getMessage();

            return null;
        }
    }

    public function unavailableReason(): ?string
    {
        if ($this->client === null) {
            return $this->lastError ?? sprintf(
                'The "pusher/pusher-php-server" package is not installed, so live connection statistics are unavailable for the "%s" driver. Run `composer require pusher/pusher-php-server` to enable them.',
                $this->driver
            );
        }

        return $this->lastError;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function makeClient(array $config): ?object
    {
        if (! class_exists(\Pusher\Pusher::class)) {
            return null;
        }

        try {
            $options = $config['options'] ?? [];

            return new \Pusher\Pusher(
                (string) ($config['key'] ?? ''),
                (string) ($config['secret'] ?? ''),
                (string) ($config['app_id'] ?? ''),
                is_array($options) ? $options : []
            );
        } catch (Throwable $e) {
            $this->lastError = 'Failed to initialize the broadcasting provider client: '.$e->getMessage();

            return null;
        }
    }

    private function classify(string $name): string
    {
        return match (true) {
            str_starts_with($name, 'presence-') => 'presence',
            str_starts_with($name, 'private-') => 'private',
            default => 'public',
        };
    }

    /**
     * Normalizes an SDK response (stdClass, nested stdClass, or array —
     * varies across pusher/pusher-php-server versions) into a plain array,
     * without needing to know the exact shape in advance.
     *
     * @return array<mixed>
     */
    private function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            return json_decode((string) json_encode($value), true) ?? [];
        }

        return [];
    }
}
