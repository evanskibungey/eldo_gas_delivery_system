<?php

namespace App\Listeners;

use App\Events\LowStockAlertEvent;
use Illuminate\Support\Facades\Log;

class HandleLowStockAlert
{
    public function handle(LowStockAlertEvent $event): void
    {
        $size  = $event->stockLevel->size;
        $count = $event->stockLevel->filled_count;

        Log::warning("[STOCK] Low stock: {$size->name} — {$count} cylinders remaining");

        // Phase 10: broadcast to admin dashboard + send SMS to shop manager
    }
}
