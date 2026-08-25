<?php

namespace App\Events;

use App\Models\Order;
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
        $this->order->loadMissing(['customer:id,name,phone', 'size:id,name', 'brand:id,name']);

        return [
            'id'             => $this->order->id,
            'order_number'   => $this->order->order_number,
            'status'         => $this->order->status,
            'order_type'     => $this->order->order_type,
            'total_amount'   => $this->order->total_amount,
            'payment_method' => $this->order->payment_method,
            'size_name'      => $this->order->size?->name,
            'brand_name'     => $this->order->brand?->name,
            'customer_name'  => $this->order->customer?->name,
            'customer_phone' => $this->order->customer?->phone,
            'address'        => $this->order->delivery_address ?: $this->order->delivery_label,
            'created_ago'    => $this->order->created_at->diffForHumans(),
            // The order-declined path re-fires this event to trigger another
            // auto-assign hop. Without this flag the admin board announces an
            // order it has already been showing for minutes as brand new.
            'is_reoffer'     => $this->excludeRiderIds !== [],
        ];
    }
}
