<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RiderLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<int>  $broadcastOrderIds  IDs of any active orders this rider
     *                                        is currently assigned to. Each one
     *                                        gets its own private-orders.{id}
     *                                        channel so the customer Flutter app
     *                                        can render the live rider dot.
     */
    public function __construct(
        public readonly int     $riderId,
        public readonly string  $riderName,
        public readonly float   $lat,
        public readonly float   $lng,
        public readonly string  $status,    // 'on_delivery' | 'available' | 'offline'
        public readonly ?int    $heading,   // 0-359 degrees, null if stationary
        public readonly ?string $orderId,   // active order short ID e.g. '#1042'
        public readonly ?string $location,  // human-readable area e.g. 'Langas'
        public readonly array   $broadcastOrderIds = [],
    ) {}

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('admin.riders')];
        foreach ($this->broadcastOrderIds as $id) {
            $channels[] = new PrivateChannel("orders.{$id}");
        }
        return $channels;
    }

    /**
     * Broadcast as a dotted event name so the frontend can listen with:
     * Echo.private('admin.riders').listen('.rider.location.updated', callback)
     */
    public function broadcastAs(): string
    {
        return 'rider.location.updated';
    }
}
