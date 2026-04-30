<?php

namespace App\Listeners;

use App\Events\OrderPlacedEvent;
use App\Jobs\SendSmsJob;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOrderConfirmationNotification implements ShouldQueue
{
    public string $queue = 'high';

    public function handle(OrderPlacedEvent $event): void
    {
        $order    = $event->order->load('customer');
        $customer = $order->customer;

        if (! $customer?->phone) {
            return;
        }

        $message = "EldoGas: Order #{$order->order_number} confirmed! "
            . "We are preparing your delivery. "
            . "Track your order in the app. Total: KES " . number_format($order->total_amount);

        SendSmsJob::dispatch(
            $customer->phone,
            $message,
            'order_placed',
            'customer',
            $customer->id,
        );
    }
}
