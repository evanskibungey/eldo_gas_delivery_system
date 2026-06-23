<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\GasPointsTransaction;
use App\Models\Order;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GasPointsService
{
    // Configurable milestone thresholds
    private array $milestones = [500, 1000, 2000, 5000];

    private const REDEMPTION_TIERS_DEFAULT = [500 => 50, 1000 => 100, 2000 => 200, 5000 => 500];

    public function isEnabled(): bool
    {
        return SystemSetting::get('gaspoints_enabled', '1') === '1';
    }

    private function rate(string $key, int $default): int
    {
        return (int) SystemSetting::get($key, (string) $default);
    }

    /**
     * Admin-configurable points-to-KES redemption map: [points => kes].
     * Single source of truth — used by checkout validation and by the
     * customer-facing config endpoint/pages.
     */
    public function redemptionTiersMap(): array
    {
        $json  = SystemSetting::get('gaspoints_redemption_tiers');
        $tiers = $json ? json_decode($json, true) : null;

        return is_array($tiers) && ! empty($tiers) ? $tiers : self::REDEMPTION_TIERS_DEFAULT;
    }

    /**
     * Full GasPoints configuration for clients (web + Flutter): whether the
     * program is active, current earn rates, and redemption tiers with
     * positional labels/descriptions for display.
     */
    public function config(): array
    {
        $tierLabels = ['Bronze', 'Silver', 'Gold', 'Platinum'];

        $tiers = collect($this->redemptionTiersMap())
            ->map(fn ($kes, $points) => ['points' => (int) $points, 'kes' => (int) $kes])
            ->values()
            ->sortBy('points')
            ->values()
            ->map(fn ($tier, $i) => $tier + [
                'label'       => $tierLabels[$i] ?? ('Tier ' . ($i + 1)),
                'description' => "KES {$tier['kes']} off your next order",
            ])
            ->all();

        return [
            'enabled' => $this->isEnabled(),
            'earn_rates' => [
                'new_cylinder'         => $this->rate('gaspoints_earn_new_cylinder', 150),
                'swap'                 => $this->rate('gaspoints_earn_swap', 100),
                'large_cylinder'       => $this->rate('gaspoints_earn_large_cylinder', 200),
                'welcome'              => $this->rate('gaspoints_earn_welcome', 250),
                'review'               => $this->rate('gaspoints_earn_review', 25),
                'referral'             => $this->rate('gaspoints_earn_referral', 250),
                'referral_third_order' => $this->rate('gaspoints_earn_referral_third_order', 100),
            ],
            'redemption_tiers' => $tiers,
        ];
    }

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
        if (! $this->isEnabled()) {
            return;
        }

        $customer = $order->customer;
        if (! $customer) {
            return;
        }

        // Large commercial cylinders get flat bonus
        if (in_array($order->size?->name, ['25kg', '50kg'])) {
            $points = $this->rate('gaspoints_earn_large_cylinder', 200);
            $this->award($customer, $points, 'earned', "Delivery bonus — {$order->size->name} order #{$order->order_number}", $order->id);
            return;
        }

        // First order welcome bonus
        $isFirst = $customer->orders()->where('status', 'delivered')->where('id', '!=', $order->id)->doesntExist();
        if ($isFirst) {
            $points = $this->rate('gaspoints_earn_welcome', 250);
            $this->award($customer, $points, 'bonus', "Welcome bonus — first order #{$order->order_number}", $order->id);
            return;
        }

        $points = $order->order_type === 'new_cylinder'
            ? $this->rate('gaspoints_earn_new_cylinder', 150)
            : $this->rate('gaspoints_earn_swap', 100);
        $label  = $order->order_type === 'new_cylinder' ? 'New cylinder' : 'Gas refill';
        $this->award($customer, $points, 'earned', "{$label} — order #{$order->order_number}", $order->id);
    }

    /**
     * Award points for submitting a review.
     */
    public function awardForRating(Order $order): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $customer = $order->customer;
        if (! $customer) {
            return;
        }

        $points = $this->rate('gaspoints_earn_review', 25);
        $this->award($customer, $points, 'earned', "Review bonus — order #{$order->order_number}", $order->id);
    }

    /**
     * Award referral bonus to the referrer when their friend places their first order.
     */
    public function awardReferralBonus(Customer $referrer, Customer $newCustomer): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $points = $this->rate('gaspoints_earn_referral', 250);
        $this->award($referrer, $points, 'referral', "Referral bonus — {$newCustomer->name} placed their first order");
    }

    /**
     * Award referral third-order bonus.
     */
    public function awardReferralThirdOrderBonus(Customer $referrer, Customer $friend): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $points = $this->rate('gaspoints_earn_referral_third_order', 100);
        $this->award($referrer, $points, 'referral_bonus', "Referral loyalty bonus — {$friend->name}'s 3rd order");
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
