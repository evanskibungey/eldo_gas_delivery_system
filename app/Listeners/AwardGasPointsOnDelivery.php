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
        private readonly GasPointsService $gasPoints,
        private readonly GamificationService $gamification,
    ) {}

    public function handle(OrderDeliveredEvent $event): void
    {
        $order = $event->order->load(['customer', 'size']);

        if (! $order->customer) {
            return;
        }

        $this->gasPoints->awardForOrder($order);
        $this->gamification->updateOnDelivery($order->customer->fresh(), $order);

        $customer = $order->customer;
        if ($customer->referred_by) {
            $deliveredCount = $customer->orders()->where('status', 'delivered')->count();
            $referrer = Customer::find($customer->referred_by);

            if ($referrer && $deliveredCount === 1) {
                $this->gasPoints->awardReferralBonus($referrer, $customer, $order);
                $this->gamification->checkReferralBadge($referrer);
            }

            if ($referrer && $deliveredCount === 3) {
                $this->gasPoints->awardReferralThirdOrderBonus($referrer, $customer, $order);
            }
        }
    }
}
