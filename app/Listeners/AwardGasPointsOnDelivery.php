<?php

namespace App\Listeners;

use App\Events\OrderDeliveredEvent;
use App\Models\Customer;
use App\Services\GasPointsService;
use Illuminate\Contracts\Queue\ShouldQueue;

class AwardGasPointsOnDelivery implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private readonly GasPointsService $gasPoints) {}

    public function handle(OrderDeliveredEvent $event): void
    {
        $order = $event->order->load(['customer', 'size']);

        if (! $order->customer) {
            return;
        }

        $this->gasPoints->awardForOrder($order);

        // Check for referral: if this is the friend's first delivered order, reward the referrer
        $customer = $order->customer;
        if ($customer->referred_by) {
            $deliveredCount = $customer->orders()->where('status', 'delivered')->count();

            if ($deliveredCount === 1) {
                $referrer = Customer::find($customer->referred_by);
                if ($referrer) {
                    $this->gasPoints->awardReferralBonus($referrer, $customer);
                }
            }

            if ($deliveredCount === 3) {
                $referrer = Customer::find($customer->referred_by);
                if ($referrer) {
                    $this->gasPoints->awardReferralThirdOrderBonus($referrer, $customer);
                }
            }
        }
    }
}
