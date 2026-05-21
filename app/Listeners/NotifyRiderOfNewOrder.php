<?php

namespace App\Listeners;

use App\Events\RiderAssignedEvent;
use App\Jobs\SendSmsJob;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyRiderOfNewOrder implements ShouldQueue
{
    public string $queue = 'high';

    public function handle(RiderAssignedEvent $event): void
    {
        $order = $event->order->load(['customer', 'size', 'brand']);
        $rider = $event->rider;

        if (! $rider->phone) {
            return;
        }

        SendSmsJob::dispatch(
            $rider->phone,
            app(SmsTemplateService::class)->riderOrderDetails($order, $rider),
            'rider_assigned',
            'rider',
            $rider->id,
        );
    }
}
