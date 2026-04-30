<?php

namespace App\Listeners;

use App\Events\RiderDelayAlertEvent;
use Illuminate\Support\Facades\Log;

class HandleRiderDelayAlert
{
    public function handle(RiderDelayAlertEvent $event): void
    {
        $order = $event->order;
        Log::warning("[DELAY] {$event->alertType} — order #{$order->order_number} · rider {$order->rider_id}");
        // Phase 10: Alert admin via WebSocket + push notification
        // Phase 10: SMS customer ETA update for delivery_delay
    }
}
