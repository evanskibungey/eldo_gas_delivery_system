<?php

namespace App\Events;

use App\Models\StockLevel;
use Illuminate\Foundation\Events\Dispatchable;

class StockRestoredEvent
{
    use Dispatchable;

    public function __construct(public readonly StockLevel $stockLevel) {}
}
