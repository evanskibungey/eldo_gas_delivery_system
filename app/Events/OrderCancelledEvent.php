<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;

class OrderCancelledEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Order  $order,
        public readonly string $reason,
    ) {}
}
