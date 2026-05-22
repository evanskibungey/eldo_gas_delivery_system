<?php

namespace App\Listeners;

use App\Events\OrderDeliveredEvent;
use App\Models\Customer;
use App\Services\GamificationService;
use App\Services\GasPointsService;
use Illuminate\Contracts\Queue\ShouldQueue;

class AwardGasPointsOnDelivery implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(
        private readonly GasPointsService    $gasPoints,
        private readonly GamificationService $gamification,
    ) {}

    public function handle(OrderDeliveredEvent $event): void
    {
        $order = $event->order->load(['customer', 'size']);

        if (! $order->customer) {
            return;
        }

        // 1. Award GasPoints for the delivery
        $this->gasPoints->awardForOrder($order);

        // 2. Update streak + check for newly earned badges
        $this->gamification->updateOnDelivery($order->customer->fresh(), $order);

        // 3. Referral bonuses
        $customer = $order->customer;
        if ($customer->referred_by) {
            $deliveredCount = $customer->orders()->where('status', 'delivered')->count();

            if ($deliveredCount === 1) {
                $referrer = Customer::find($customer->referred_by);
                if ($referrer) {
                    $this->gasPoints->awardReferralBonus($referrer, $customer);
                    $this->gamification->checkReferralBadge($referrer);
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
