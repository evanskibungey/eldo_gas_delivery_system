<?php

namespace App\Listeners;

use App\Events\PaymentDisputeEvent;
use Illuminate\Support\Facades\Log;

class HandlePaymentDispute
{
    public function handle(PaymentDisputeEvent $event): void
    {
        $order = $event->order;
        Log::warning("[DISPUTE] Payment dispute flagged — order #{$order->order_number} · customer {$order->customer_id}");
        // Phase 10: Alert admin dashboard with payment dispute badge
        // Phase 10: Send admin SMS for unresolved disputes
    }
}
