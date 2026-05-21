<?php

namespace App\Listeners;

use App\Events\OrderStatusUpdatedEvent;
use App\Jobs\SendOrderStatusPushJob;

class SendCustomerOrderPush
{
    public function handle(OrderStatusUpdatedEvent $event): void
    {
        // Skip the very first 'pending' transition — the customer just
        // tapped Place Order and is already looking at Confirmation.
        if ($event->order->status === 'pending') {
            return;
        }

        SendOrderStatusPushJob::dispatch($event->order->id);
    }
}
