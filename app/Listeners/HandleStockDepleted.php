<?php

namespace App\Listeners;

use App\Events\StockDepletedEvent;

class HandleStockDepleted
{
    public function handle(StockDepletedEvent $event): void
    {
        $size = $event->stockLevel->size;

        if ($size && $size->is_active) {
            $size->update(['is_active' => false]);
        }
    }
}
