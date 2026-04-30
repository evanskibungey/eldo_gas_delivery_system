<?php

namespace App\Listeners;

use App\Events\StockRestoredEvent;

class HandleStockRestored
{
    public function handle(StockRestoredEvent $event): void
    {
        $size = $event->stockLevel->size;

        if ($size && ! $size->is_active) {
            $size->update(['is_active' => true]);
        }
    }
}
