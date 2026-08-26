<?php

namespace App\Listeners;

use App\Events\OrderDeliveredEvent;
use App\Jobs\SendSmsJob;
use App\Services\GasPointsService;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendDeliveryThankYou implements ShouldQueue
{
    public string $queue = 'default';

    /**
     * AwardGasPointsOnDelivery listens to the same event on the same queue, so
     * without a delay this can run first and report a balance that has not been
     * credited yet. A short wait lets the award land; the customer cannot tell
     * the difference, and earnedForOrder() returning 0 makes the message omit
     * the points sentence rather than print a wrong one if it still has not.
     */
    public int $delay = 15;

    public function handle(OrderDeliveredEvent $event): void
    {
        $order    = $event->order->load('customer');
        $customer = $order->customer;

        if (! $customer?->phone) {
            return;
        }

        $gasPoints = app(GasPointsService::class);

        // Read both from the database at send time. The event's copy of the
        // customer predates the award and would carry a stale balance.
        $earned  = $gasPoints->earnedForOrder($order);
        $balance = $gasPoints->getBalance($customer->fresh());

        SendSmsJob::dispatch(
            $customer->phone,
            app(SmsTemplateService::class)->deliveryThankYou($order, $earned, $balance),
            'order_delivered',
            'customer',
            $customer->id,
        );
    }
}
