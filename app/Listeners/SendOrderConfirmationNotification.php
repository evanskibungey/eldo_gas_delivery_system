<?php

namespace App\Listeners;

use App\Events\OrderPlacedEvent;
use App\Jobs\SendSmsJob;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendOrderConfirmationNotification implements ShouldQueue
{
    public string $queue = 'high';

    public function handle(OrderPlacedEvent $event): void
    {
        $order    = $event->order->load(['customer', 'size', 'brand']);
        $customer = $order->customer;

        if (! $customer?->phone) {
            return;
        }

        // "Your order has been received" makes no sense to somebody standing at
        // the counter holding the cylinder. A walk-in gets one message — the
        // receipt sent by AdminOrderCreator — instead of this plus a delivery
        // thank-you.
        if ($order->isWalkIn()) {
            return;
        }

        SendSmsJob::dispatch(
            $customer->phone,
            app(SmsTemplateService::class)->orderConfirmation($order),
            'order_placed',
            'customer',
            $customer->id,
        );
    }
}
