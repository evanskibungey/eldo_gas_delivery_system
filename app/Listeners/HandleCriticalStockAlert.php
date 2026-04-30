<?php

namespace App\Listeners;

use App\Events\CriticalStockAlertEvent;
use Illuminate\Support\Facades\Log;

class HandleCriticalStockAlert
{
    public function handle(CriticalStockAlertEvent $event): void
    {
        $size  = $event->stockLevel->size;
        $count = $event->stockLevel->filled_count;

        Log::critical("[STOCK] Critical stock: {$size->name} — only {$count} cylinder(s) left");

        // Phase 10: broadcast urgent alert to admin dashboard + SMS
    }
}
