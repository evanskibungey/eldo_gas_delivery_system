<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A synthetic new-order broadcast, for proving out the alert chain without
 * placing a real order.
 *
 * Broadcasts as `order.placed` on `admin.orders`, so the admin panel handles it
 * through exactly the same code path a real order takes: chime, banner, desktop
 * notification. Nothing is written to the database.
 *
 * @see \App\Console\Commands\TestOrderAlert
 */
class TestOrderAlertEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(public readonly array $payload) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.orders')];
    }

    public function broadcastAs(): string
    {
        return 'order.placed';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
