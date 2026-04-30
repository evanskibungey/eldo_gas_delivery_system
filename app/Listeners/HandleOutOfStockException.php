<?php

namespace App\Listeners;

use App\Events\OutOfStockExceptionEvent;
use Illuminate\Support\Facades\Log;

class HandleOutOfStockException
{
    public function handle(OutOfStockExceptionEvent $event): void
    {
        $order = $event->order;
        Log::warning("[ISSUE] Out of stock — order #{$order->order_number} cancelled · customer {$order->customer_id}");
        // Phase 10: SMS customer "We're sorry — out of stock" with re-order options
        // Phase 10: Push urgent alert to admin dashboard
    }
}
