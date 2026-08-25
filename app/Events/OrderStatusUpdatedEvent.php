<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("orders.{$this->order->id}"),
            // The admin dispatch board needs every transition, not just new
            // orders — without this the table is stale from the moment an
            // order is placed until someone hits Refresh.
            new PrivateChannel('admin.orders'),
        ];

        if ($this->order->rider_id) {
            $channels[] = new PrivateChannel("rider.{$this->order->rider_id}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'order.status_updated';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status,
            'payment_status' => $this->order->payment_status,
            'has_issue' => (bool) $this->order->has_issue,
            'issue_type' => $this->order->issue_type,
            'rider_name' => $this->order->rider?->name,
            'updated_at' => $this->order->updated_at?->toISOString(),
        ];
    }
}
