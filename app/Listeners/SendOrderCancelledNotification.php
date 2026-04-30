<?php

namespace App\Listeners;

use App\Events\OrderCancelledEvent;
use App\Jobs\SendSmsJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOrderCancelledNotification implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(OrderCancelledEvent $event): void
    {
        $order    = $event->order->load('customer');
        $customer = $order->customer;

        if (! $customer?->phone) {
            return;
        }

        $reason  = $event->reason ?: 'No reason provided';
        $message = "EldoGas: Order #{$order->order_number} has been cancelled. "
            . "Reason: {$reason}. "
            . "Contact us at +254 700 000000 for assistance.";

        SendSmsJob::dispatch(
            $customer->phone,
            $message,
            'order_cancelled',
            'customer',
            $customer->id,
        );
    }
}
