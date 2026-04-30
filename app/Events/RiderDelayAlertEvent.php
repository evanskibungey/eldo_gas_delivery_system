<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RiderDelayAlertEvent
{
    use Dispatchable, SerializesModels;

    // alertType: 'pickup_delay' | 'delivery_delay' | 'no_show'
    public function __construct(
        public readonly Order  $order,
        public readonly string $alertType,
    ) {}
}
