<?php

namespace App\Listeners;

use App\Events\RiderAssignedEvent;
use Illuminate\Support\Facades\Log;

class NotifyRiderOfNewOrder
{
    public function handle(RiderAssignedEvent $event): void
    {
        // Real-time delivery is handled by RiderAssignedEvent broadcasting to
        // private-rider.{riderId} via Reverb WebSocket (pusher_channels_flutter).
        // This listener is reserved for FCM background push (Phase 2).
        Log::info("[RIDER] Order #{$event->order->order_number} broadcast to rider.{$event->rider->id}");
    }
}
