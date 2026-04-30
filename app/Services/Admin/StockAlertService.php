<?php

namespace App\Services\Admin;

use App\Events\CriticalStockAlertEvent;
use App\Events\LowStockAlertEvent;
use App\Events\StockDepletedEvent;
use App\Models\StockLevel;

class StockAlertService
{
    public function check(): void
    {
        StockLevel::with('size')->get()->each(function (StockLevel $level) {
            if ($level->isEmpty()) {
                event(new StockDepletedEvent($level));
            } elseif ($level->isCritical()) {
                event(new CriticalStockAlertEvent($level));
            } elseif ($level->isLow()) {
                event(new LowStockAlertEvent($level));
            }
        });
    }
}
