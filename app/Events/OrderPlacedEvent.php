<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderPlacedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param array<int> $excludeRiderIds Rider IDs that declined and must be skipped during re-assignment.
     */
    public function __construct(
        public readonly Order $order,
        public readonly array $excludeRiderIds = [],
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.orders'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.placed';
    }

    public function broadcastWith(): array
    {
        return [
            'id'           => $this->order->id,
            'order_number' => $this->order->order_number,
            'status'       => $this->order->status,
            'order_type'   => $this->order->order_type,
            'total_amount' => $this->order->total_amount,
            'size_name'    => $this->order->size?->name,
            'created_ago'  => $this->order->created_at->diffForHumans(),
        ];
    }
}
