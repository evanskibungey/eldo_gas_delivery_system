<?php

namespace App\Listeners;

use App\Events\RiderAssignedEvent;
use App\Jobs\SendSmsJob;
use App\Models\SystemSetting;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendRiderAssignedNotification implements ShouldQueue
{
    public string $queue = 'high';

    public function handle(RiderAssignedEvent $event): void
    {
        $order    = $event->order->load('customer');
        $rider    = $event->rider;
        $customer = $order->customer;

        if (! $customer?->phone) {
            return;
        }

        $appName = SystemSetting::get('app_name', 'EldoGas');
        $message = "{$appName}: {$rider->name} is on the way with your gas! "
            . "Order #{$order->order_number}. "
            . "Expected in ~25 mins. Call {$rider->phone} if needed.";

        SendSmsJob::dispatch(
            $customer->phone,
            $message,
            'rider_assigned',
            'customer',
            $customer->id,
        );
    }
}
