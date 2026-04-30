<?php

namespace App\Listeners;

use App\Events\WrongCylinderReportedEvent;
use Illuminate\Support\Facades\Log;

class HandleWrongCylinder
{
    public function handle(WrongCylinderReportedEvent $event): void
    {
        $order = $event->order;
        Log::warning("[ISSUE] Wrong cylinder reported — order #{$order->order_number} · rider {$order->rider_id}");
        // Phase 10: Notify admin dashboard immediately
        // Phase 14: Push notification to rider app with correction instructions
    }
}
