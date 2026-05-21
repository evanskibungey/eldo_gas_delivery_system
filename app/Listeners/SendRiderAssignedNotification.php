<?php

namespace App\Listeners;

use App\Events\RiderAssignedEvent;
use App\Jobs\SendSmsJob;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendRiderAssignedNotification implements ShouldQueue
{
    public string $queue = 'high';

    public function handle(RiderAssignedEvent $event): void
    {
        $order    = $event->order->load('customer');
        $customer = $order->customer;

        if (! $customer?->phone) {
            return;
        }

        SendSmsJob::dispatch(
            $customer->phone,
            app(SmsTemplateService::class)->riderAssigned($order, $event->rider),
            'rider_assigned',
            'customer',
            $customer->id,
        );
    }
}
