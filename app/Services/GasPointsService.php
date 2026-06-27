<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\GasPointsTransaction;
use App\Models\Order;
use App\Models\SystemSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GasPointsService
{
    private const REDEMPTION_TIERS_DEFAULT = [500 => 50, 1000 => 100, 2000 => 200, 5000 => 500];

    private const MILESTONES = [500, 1000, 2000, 5000];

    private const DEFAULTS = [
        'gaspoints_earn_new_cylinder' => 150,
        'gaspoints_earn_swap' => 100,
        'gaspoints_earn_large_cylinder' => 200,
        'gaspoints_earn_welcome' => 250,
        'gaspoints_earn_review' => 25,
        'gaspoints_earn_referral' => 250,
        'gaspoints_earn_referral_third_order' => 100,
        'gaspoints_expiry_days' => 365,
        'gaspoints_min_order_amount' => 0,
        'gaspoints_referral_apply_window_days' => 14,
        'gaspoints_referral_reward_window_days' => 90,
        'gaspoints_referral_min_order_amount' => 0,
        'gaspoints_max_balance' => 0,
    ];

    public function isEnabled(): bool
    {
        return SystemSetting::get('gaspoints_enabled', '1') === '1';
    }

    private function rate(string $key, int $default): int
    {
        return (int) SystemSetting::get($key, (string) $default);
    }

    public function intSetting(string $key): int
    {
        return (int) SystemSetting::get($key, (string) (self::DEFAULTS[$key] ?? 0));
    }

    public function redemptionTiersMap(): array
    {
        $json = SystemSetting::get('gaspoints_redemption_tiers');
        $tiers = $json ? json_decode($json, true) : null;

        $map = is_array($tiers) && ! empty($tiers)
            ? $tiers
            : self::REDEMPTION_TIERS_DEFAULT;

        return collect($map)
            ->mapWithKeys(fn ($kes, $points) => [(int) $points => (int) $kes])
            ->sortKeys()
            ->all();
    }

    public function config(): array
    {
        $tierLabels = ['Bronze', 'Silver', 'Gold', 'Platinum'];

        $tiers = collect($this->redemptionTiersMap())
            ->map(fn ($kes, $points) => ['points' => (int) $points, 'kes' => (int) $kes])
            ->values()
            ->map(fn (array $tier, int $index) => $tier + [
                'label' => $tierLabels[$index] ?? ('Tier ' . ($index + 1)),
                'description' => "KES {$tier['kes']} off your next order",
            ])
            ->all();

        return [
            'enabled' => $this->isEnabled(),
            'earn_rates' => [
                'new_cylinder' => $this->rate('gaspoints_earn_new_cylinder', self::DEFAULTS['gaspoints_earn_new_cylinder']),
                'swap' => $this->rate('gaspoints_earn_swap', self::DEFAULTS['gaspoints_earn_swap']),
                'large_cylinder' => $this->rate('gaspoints_earn_large_cylinder', self::DEFAULTS['gaspoints_earn_large_cylinder']),
                'welcome' => $this->rate('gaspoints_earn_welcome', self::DEFAULTS['gaspoints_earn_welcome']),
                'review' => $this->rate('gaspoints_earn_review', self::DEFAULTS['gaspoints_earn_review']),
                'referral' => $this->rate('gaspoints_earn_referral', self::DEFAULTS['gaspoints_earn_referral']),
                'referral_third_order' => $this->rate('gaspoints_earn_referral_third_order', self::DEFAULTS['gaspoints_earn_referral_third_order']),
            ],
            'redemption_tiers' => $tiers,
            'rules' => [
                'expiry_days' => $this->intSetting('gaspoints_expiry_days'),
                'min_order_amount' => $this->intSetting('gaspoints_min_order_amount'),
                'referral_apply_window_days' => $this->intSetting('gaspoints_referral_apply_window_days'),
                'referral_reward_window_days' => $this->intSetting('gaspoints_referral_reward_window_days'),
                'referral_min_order_amount' => $this->intSetting('gaspoints_referral_min_order_amount'),
                'max_balance' => $this->intSetting('gaspoints_max_balance'),
            ],
        ];
    }

    public function award(
        Customer $customer,
        int $points,
        string $type,
        string $description,
        ?int $orderId = null,
        ?string $rewardKey = null,
        ?string $eventCode = null,
        ?Carbon $expiresAt = null,
    ): int {
        if ($points <= 0) {
            return 0;
        }

        if ($rewardKey) {
            $existing = GasPointsTransaction::where('reward_key', $rewardKey)->first();
            if ($existing) {
                return max(0, (int) $existing->points);
            }
        }

        $awarded = DB::transaction(function () use ($customer, $points, $type, $description, $orderId, $rewardKey, $eventCode, $expiresAt) {
            $lockedCustomer = Customer::lockForUpdate()->find($customer->id);
            if (! $lockedCustomer) {
                return 0;
            }

            $this->expireEligiblePointsForCustomer($lockedCustomer);
            $lockedCustomer->refresh();

            $maxBalance = $this->intSetting('gaspoints_max_balance');
            $awardablePoints = $points;

            if ($maxBalance > 0) {
                $room = $maxBalance - (int) $lockedCustomer->gaspoints_balance;
                if ($room <= 0) {
                    return 0;
                }
                $awardablePoints = min($awardablePoints, $room);
            }

            if ($awardablePoints <= 0) {
                return 0;
            }

            $newBalance = (int) $lockedCustomer->gaspoints_balance + $awardablePoints;
            $earnedExpiry = $expiresAt ?? $this->defaultExpiryDate();

            GasPointsTransaction::create([
                'customer_id' => $lockedCustomer->id,
                'order_id' => $orderId,
                'reward_key' => $rewardKey,
                'type' => $type,
                'event_code' => $eventCode,
                'points' => $awardablePoints,
                'remaining_points' => $awardablePoints,
                'balance_after' => $newBalance,
                'description' => $description,
                'created_at' => now(),
                'expires_at' => $earnedExpiry,
            ]);

            $lockedCustomer->update(['gaspoints_balance' => $newBalance]);
            $customer->refresh();

            $this->checkMilestones($lockedCustomer->id, $newBalance);

            return $awardablePoints;
        });

        if ($awarded > 0) {
            $context = $eventCode ?? $type;
            Log::info("[GASPOINTS] +{$awarded} pts to customer #{$customer->id} ({$context})");
        }

        return $awarded;
    }

    public function redeem(
        Customer $customer,
        int $points,
        string $description,
        ?int $orderId = null,
        ?string $rewardKey = null,
        ?string $eventCode = 'checkout_redemption',
    ): bool {
        if ($points <= 0) {
            return false;
        }

        if ($rewardKey && GasPointsTransaction::where('reward_key', $rewardKey)->exists()) {
            return true;
        }

        return DB::transaction(function () use ($customer, $points, $description, $orderId, $rewardKey, $eventCode) {
            $lockedCustomer = Customer::lockForUpdate()->find($customer->id);
            if (! $lockedCustomer) {
                return false;
            }

            $this->expireEligiblePointsForCustomer($lockedCustomer);
            $lockedCustomer->refresh();

            if ((int) $lockedCustomer->gaspoints_balance < $points) {
                return false;
            }

            $availableBuckets = GasPointsTransaction::where('customer_id', $lockedCustomer->id)
                ->where('points', '>', 0)
                ->where('remaining_points', '>', 0)
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();

            $remaining = $points;
            /** @var GasPointsTransaction $bucket */
            foreach ($availableBuckets as $bucket) {
                if ($remaining <= 0) {
                    break;
                }

                $take = min((int) $bucket->remaining_points, $remaining);
                if ($take <= 0) {
                    continue;
                }

                $bucket->update([
                    'remaining_points' => (int) $bucket->remaining_points - $take,
                ]);
                $remaining -= $take;
            }

            if ($remaining > 0) {
                return false;
            }

            $newBalance = (int) $lockedCustomer->gaspoints_balance - $points;

            GasPointsTransaction::create([
                'customer_id' => $lockedCustomer->id,
                'order_id' => $orderId,
                'reward_key' => $rewardKey,
                'type' => 'redeemed',
                'event_code' => $eventCode,
                'points' => -$points,
                'remaining_points' => 0,
                'balance_after' => $newBalance,
                'description' => $description,
                'created_at' => now(),
            ]);

            $lockedCustomer->update(['gaspoints_balance' => $newBalance]);
            $customer->refresh();

            return true;
        });
    }

    public function refundRedemption(Order $order): int
    {
        $order->loadMissing('customer');

        if (! $order->customer || (int) $order->gaspoints_redeemed <= 0) {
            return 0;
        }

        return $this->award(
            customer: $order->customer,
            points: (int) $order->gaspoints_redeemed,
            type: 'earned',
            description: "Refund - order #{$order->order_number} cancelled",
            orderId: $order->id,
            rewardKey: "refund:redemption:order:{$order->id}",
            eventCode: 'redemption_refund',
            expiresAt: null,
        );
    }

    public function getBalance(Customer $customer): int
    {
        return (int) $customer->gaspoints_balance;
    }

    public function awardForOrder(Order $order): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $order->loadMissing(['customer', 'size']);
        if (! $order->customer) {
            return 0;
        }

        if ((int) $order->total_amount < $this->intSetting('gaspoints_min_order_amount')) {
            return 0;
        }

        $customer = $order->customer;
        $awarded = 0;

        $basePoints = $order->order_type === 'new_cylinder'
            ? $this->rate('gaspoints_earn_new_cylinder', self::DEFAULTS['gaspoints_earn_new_cylinder'])
            : $this->rate('gaspoints_earn_swap', self::DEFAULTS['gaspoints_earn_swap']);
        $baseLabel = $order->order_type === 'new_cylinder' ? 'New cylinder' : 'Gas refill';

        $awarded += $this->award(
            customer: $customer,
            points: $basePoints,
            type: 'earned',
            description: "{$baseLabel} - order #{$order->order_number}",
            orderId: $order->id,
            rewardKey: "order:base:{$order->id}",
            eventCode: 'delivery_base',
        );

        $weightKg = (int) ($order->size?->weight_kg ?? 0);
        if ($weightKg >= 25) {
            $bonus = $this->rate('gaspoints_earn_large_cylinder', self::DEFAULTS['gaspoints_earn_large_cylinder']);
            $awarded += $this->award(
                customer: $customer,
                points: $bonus,
                type: 'earned',
                description: "Large cylinder bonus - {$order->size?->name} order #{$order->order_number}",
                orderId: $order->id,
                rewardKey: "order:large_bonus:{$order->id}",
                eventCode: 'delivery_large_bonus',
            );
        }

        $isFirstDelivered = $customer->orders()
            ->where('status', 'delivered')
            ->where('id', '!=', $order->id)
            ->doesntExist();

        if ($isFirstDelivered) {
            $welcome = $this->rate('gaspoints_earn_welcome', self::DEFAULTS['gaspoints_earn_welcome']);
            $awarded += $this->award(
                customer: $customer,
                points: $welcome,
                type: 'bonus',
                description: "Welcome bonus - first order #{$order->order_number}",
                orderId: $order->id,
                rewardKey: "order:welcome:{$order->id}",
                eventCode: 'delivery_welcome',
            );
        }

        return $awarded;
    }

    public function awardForRating(Order $order): int
    {
        if (! $this->isEnabled()) {
            return 0;
        }

        $order->loadMissing('customer');
        if (! $order->customer) {
            return 0;
        }

        return $this->award(
            customer: $order->customer,
            points: $this->rate('gaspoints_earn_review', self::DEFAULTS['gaspoints_earn_review']),
            type: 'earned',
            description: "Review bonus - order #{$order->order_number}",
            orderId: $order->id,
            rewardKey: "rating:{$order->id}",
            eventCode: 'rating_review',
        );
    }

    public function awardReferralBonus(Customer $referrer, Customer $newCustomer, Order $order): int
    {
        if (! $this->isEnabled() || ! $this->isReferralRewardEligible($newCustomer, $order)) {
            return 0;
        }

        return $this->award(
            customer: $referrer,
            points: $this->rate('gaspoints_earn_referral', self::DEFAULTS['gaspoints_earn_referral']),
            type: 'referral',
            description: "Referral bonus - {$newCustomer->name} placed their first order",
            orderId: $order->id,
            rewardKey: "referral:first:friend:{$newCustomer->id}",
            eventCode: 'referral_first_order',
        );
    }

    public function awardReferralThirdOrderBonus(Customer $referrer, Customer $friend, Order $order): int
    {
        if (! $this->isEnabled() || ! $this->isReferralRewardEligible($friend, $order)) {
            return 0;
        }

        return $this->award(
            customer: $referrer,
            points: $this->rate('gaspoints_earn_referral_third_order', self::DEFAULTS['gaspoints_earn_referral_third_order']),
            type: 'referral_bonus',
            description: "Referral loyalty bonus - {$friend->name}'s 3rd order",
            orderId: $order->id,
            rewardKey: "referral:third:friend:{$friend->id}",
            eventCode: 'referral_third_order',
        );
    }

    public function canApplyReferral(Customer $customer): ?string
    {
        if ($customer->referred_by) {
            return 'You have already used a referral code.';
        }

        if ($customer->orders()->exists()) {
            return 'Referral codes must be applied before your first order.';
        }

        $windowDays = $this->intSetting('gaspoints_referral_apply_window_days');
        if ($windowDays > 0 && $customer->created_at->lt(now()->subDays($windowDays))) {
            return "Referral codes must be applied within {$windowDays} days of registration.";
        }

        return null;
    }

    public function expireAllEligiblePoints(): int
    {
        $expiredPoints = 0;

        Customer::query()
            ->where('gaspoints_balance', '>', 0)
            ->chunkById(100, function (Collection $customers) use (&$expiredPoints) {
                foreach ($customers as $customer) {
                    $expiredPoints += $this->expireEligiblePointsForCustomer($customer);
                }
            });

        return $expiredPoints;
    }

    private function isReferralRewardEligible(Customer $friend, Order $order): bool
    {
        if ((int) $order->total_amount < $this->intSetting('gaspoints_referral_min_order_amount')) {
            return false;
        }

        if (! $friend->referral_applied_at) {
            return false;
        }

        $windowDays = $this->intSetting('gaspoints_referral_reward_window_days');
        if ($windowDays > 0 && $friend->referral_applied_at->copy()->addDays($windowDays)->lt($order->delivered_at ?? now())) {
            return false;
        }

        return true;
    }

    private function defaultExpiryDate(): ?Carbon
    {
        $days = $this->intSetting('gaspoints_expiry_days');

        return $days > 0 ? now()->addDays($days) : null;
    }

    private function expireEligiblePointsForCustomer(Customer $customer): int
    {
        $expiredPoints = 0;

        $expiredBuckets = GasPointsTransaction::where('customer_id', $customer->id)
            ->where('points', '>', 0)
            ->where('remaining_points', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereNull('expired_at')
            ->lockForUpdate()
            ->get();

        /** @var GasPointsTransaction $bucket */
        foreach ($expiredBuckets as $bucket) {
            $pointsToExpire = (int) $bucket->remaining_points;
            if ($pointsToExpire <= 0) {
                continue;
            }

            $newBalance = max(0, (int) $customer->gaspoints_balance - $pointsToExpire);

            GasPointsTransaction::create([
                'customer_id' => $customer->id,
                'order_id' => $bucket->order_id,
                'reward_key' => "expiry:{$bucket->id}",
                'type' => 'redeemed',
                'event_code' => 'expiry',
                'points' => -$pointsToExpire,
                'remaining_points' => 0,
                'balance_after' => $newBalance,
                'description' => 'GasPoints expired',
                'created_at' => now(),
            ]);

            $bucket->update([
                'remaining_points' => 0,
                'expired_at' => now(),
            ]);

            $customer->update(['gaspoints_balance' => $newBalance]);
            $expiredPoints += $pointsToExpire;
        }

        return $expiredPoints;
    }

    private function checkMilestones(int $customerId, int $newBalance): void
    {
        foreach (self::MILESTONES as $threshold) {
            if ($newBalance >= $threshold) {
                Log::info("[GASPOINTS] Customer #{$customerId} reached milestone: {$threshold} pts");
            }
        }
    }
}
