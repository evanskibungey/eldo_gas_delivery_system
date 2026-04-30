<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\GasPointsTransaction;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GasPointsService
{
    // Configurable milestone thresholds
    private array $milestones = [500, 1000, 2000, 5000];

    /**
     * Award points to a customer.
     * Creates the transaction record and increments the balance atomically.
     */
    public function award(
        Customer $customer,
        int      $points,
        string   $type,
        string   $description,
        ?int     $orderId = null
    ): void {
        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($customer, $points, $type, $description, $orderId) {
            $newBalance = $customer->gaspoints_balance + $points;

            GasPointsTransaction::create([
                'customer_id'   => $customer->id,
                'order_id'      => $orderId,
                'type'          => $type,
                'points'        => $points,
                'balance_after' => $newBalance,
                'description'   => $description,
                'created_at'    => now(),
            ]);

            $customer->increment('gaspoints_balance', $points);
            $customer->refresh();

            $this->checkMilestones($customer, $newBalance);
        });

        Log::info("[GASPOINTS] +{$points} pts to customer #{$customer->id} ({$type}) — new balance: " . ($customer->gaspoints_balance));
    }

    /**
     * Redeem points from a customer's balance.
     * Returns false if balance is insufficient.
     */
    public function redeem(Customer $customer, int $points, string $description, ?int $orderId = null): bool
    {
        if ($points <= 0 || $customer->gaspoints_balance < $points) {
            return false;
        }

        DB::transaction(function () use ($customer, $points, $description, $orderId) {
            $newBalance = $customer->gaspoints_balance - $points;

            GasPointsTransaction::create([
                'customer_id'   => $customer->id,
                'order_id'      => $orderId,
                'type'          => 'redeemed',
                'points'        => -$points,
                'balance_after' => $newBalance,
                'description'   => $description,
                'created_at'    => now(),
            ]);

            $customer->decrement('gaspoints_balance', $points);
        });

        return true;
    }

    public function getBalance(Customer $customer): int
    {
        return (int) $customer->gaspoints_balance;
    }

    /**
     * Award points for a delivered order based on business rules.
     */
    public function awardForOrder(Order $order): void
    {
        $customer = $order->customer;
        if (! $customer) {
            return;
        }

        // Large commercial cylinders get flat bonus
        if (in_array($order->size?->name, ['25kg', '50kg'])) {
            $this->award($customer, 200, 'earned', "Delivery bonus — {$order->size->name} order #{$order->order_number}", $order->id);
            return;
        }

        // First order welcome bonus
        $isFirst = $customer->orders()->where('status', 'delivered')->where('id', '!=', $order->id)->doesntExist();
        if ($isFirst) {
            $this->award($customer, 250, 'bonus', "Welcome bonus — first order #{$order->order_number}", $order->id);
            return;
        }

        $points = $order->order_type === 'new_cylinder' ? 150 : 100;
        $label  = $order->order_type === 'new_cylinder' ? 'New cylinder' : 'Gas refill';
        $this->award($customer, $points, 'earned', "{$label} — order #{$order->order_number}", $order->id);
    }

    /**
     * Award points for submitting a review.
     */
    public function awardForRating(Order $order): void
    {
        $customer = $order->customer;
        if (! $customer) {
            return;
        }

        $this->award($customer, 25, 'earned', "Review bonus — order #{$order->order_number}", $order->id);
    }

    /**
     * Award referral bonus to the referrer when their friend places their first order.
     */
    public function awardReferralBonus(Customer $referrer, Customer $newCustomer): void
    {
        $this->award($referrer, 250, 'referral', "Referral bonus — {$newCustomer->name} placed their first order");
    }

    /**
     * Award referral third-order bonus.
     */
    public function awardReferralThirdOrderBonus(Customer $referrer, Customer $friend): void
    {
        $this->award($referrer, 100, 'referral_bonus', "Referral loyalty bonus — {$friend->name}'s 3rd order");
    }

    /**
     * Fire milestone notifications if the customer crossed a threshold.
     */
    private function checkMilestones(Customer $customer, int $newBalance): void
    {
        $oldBalance = $newBalance - ($customer->gaspoints_balance - $customer->fresh()->gaspoints_balance ?? 0);

        foreach ($this->milestones as $threshold) {
            if ($newBalance >= $threshold) {
                Log::info("[GASPOINTS] Customer #{$customer->id} reached milestone: {$threshold} pts");
                // Phase 10 hook: dispatch GasPointsMilestoneNotification when notification system is wired
            }
        }
    }
}
