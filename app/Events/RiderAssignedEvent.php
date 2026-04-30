<?php

namespace App\Events;

use App\Models\Order;
use App\Models\Rider;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RiderAssignedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Order $order,
        public readonly Rider $rider,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("orders.{$this->order->id}"),
            new PrivateChannel("rider.{$this->rider->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'rider.assigned';
    }

    public function broadcastWith(): array
    {
        return [
            'status'      => $this->order->status,
            'rider_name'  => $this->rider->name,
            'rider_phone' => $this->rider->phone,
            'rider_lat'   => $this->rider->current_latitude,
            'rider_lng'   => $this->rider->current_longitude,
            'rider_rated' => $this->rider->avg_rating,
        ];
    }
}
