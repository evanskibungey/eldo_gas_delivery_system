<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RiderOrderRemovedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $riderId,
        public readonly int $orderId,
        public readonly string $reason,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("rider.{$this->riderId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.removed';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'reason' => $this->reason,
        ];
    }
}
