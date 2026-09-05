<?php

declare(strict_types=1);

namespace Abdulbaset\ActivityTracker\Tests\Fixtures;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

final class TestBroadcastableEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public string $message = 'hello')
    {
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('presence-support'),
            new PrivateChannel('private-orders'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'test.broadcast.event';
    }
}
