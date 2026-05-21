<?php

namespace App\Listeners;

use App\Events\OrderDeliveredEvent;
use App\Jobs\SendSmsJob;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendDeliveryThankYou implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(OrderDeliveredEvent $event): void
    {
        $order    = $event->order->load('customer');
        $customer = $order->customer;

        if (! $customer?->phone) {
            return;
        }

        SendSmsJob::dispatch(
            $customer->phone,
            app(SmsTemplateService::class)->deliveryThankYou($order),
            'order_delivered',
            'customer',
            $customer->id,
        );
    }
}
