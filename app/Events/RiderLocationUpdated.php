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

    public function __construct(
        public readonly int     $riderId,
        public readonly string  $riderName,
        public readonly float   $lat,
        public readonly float   $lng,
        public readonly string  $status,    // 'on_delivery' | 'available' | 'offline'
        public readonly ?int    $heading,   // 0-359 degrees, null if stationary
        public readonly ?string $orderId,   // active order short ID e.g. '#1042'
        public readonly ?string $location,  // human-readable area e.g. 'Langas'
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.riders')];
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
