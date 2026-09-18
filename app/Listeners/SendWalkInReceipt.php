<?php

namespace App\Listeners;

use App\Events\OrderDeliveredEvent;
use App\Jobs\SendSmsJob;
use App\Services\GasPointsService;
use App\Services\Sms\SmsTemplateService;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * The single message a counter-sale customer receives.
 *
 * Its job is conversion: somebody who bought over the counter has no account,
 * and the GasPoints they just earned can only be spent in the app. That is the
 * reason to install it, so the receipt has to be able to name them.
 *
 * SendOrderConfirmationNotification and SendDeliveryThankYou both return early
 * for this channel, so one transaction produces one text rather than three.
 * The safety tip still goes out — somebody carrying a cylinder home needs it
 * just as much as somebody who had it delivered.
 */
class SendWalkInReceipt implements ShouldQueue
{
    public string $queue = 'default';

    /**
     * AwardGasPointsOnDelivery listens to the same event on the same queue, so
     * without a delay this can win the race and report nothing earned. The
     * template drops the points sentence rather than printing "0", so a lost
     * race would quietly cost the message its entire reason for existing.
     */
    public int $delay = 15;

    public function handle(OrderDeliveredEvent $event): void
    {
        $order = $event->order->load(['customer', 'items.size', 'items.brand']);
        $customer = $order->customer;

        if (! $customer?->phone || ! $order->isWalkIn()) {
            return;
        }

        SendSmsJob::dispatch(
            $customer->phone,
            app(SmsTemplateService::class)->walkInReceipt(
                $order,
                app(GasPointsService::class)->earnedForOrder($order),
            ),
            'walk_in_receipt',
            'customer',
            $customer->id,
        );
    }
}
