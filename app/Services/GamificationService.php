<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerBadge;
use App\Models\CustomerStreak;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GamificationService
{
    // Keys must match BadgeInfo.all in the Flutter client.
    private const ORDER_BADGES = [
        'first_order' => 1,
        'order_5'     => 5,
        'order_10'    => 10,
        'order_25'    => 25,
        'order_50'    => 50,
    ];

    private const STREAK_BADGES = [
        'streak_3' => 3,
        'streak_6' => 6,
    ];

    private const POINTS_BADGES = [
        'points_1000' => 1000,
        'points_5000' => 5000,
    ];

    /**
     * Called after every delivered order.
     * Updates streak counters and awards any newly-unlocked badges.
     */
    public function updateOnDelivery(Customer $customer, Order $order): void
    {
        DB::transaction(function () use ($customer, $order) {
            $streak = CustomerStreak::firstOrCreate(
                ['customer_id' => $customer->id],
                ['current_streak' => 0, 'longest_streak' => 0, 'order_count' => 0]
            );

            // Increment total delivered count
            $streak->increment('order_count');
            $streak->refresh();

            // Determine which calendar month this delivery belongs to
            $deliveredAt  = $order->delivered_at ?? now();
            $orderMonth   = Carbon::parse($deliveredAt)->startOfMonth()->toDateString();

            if ($streak->last_order_month === null) {
                // First ever delivery
                $streak->current_streak = 1;
            } else {
                $lastMonth         = Carbon::parse($streak->last_order_month);
                $thisMonth         = Carbon::parse($orderMonth);
                $expectedNextMonth = $lastMonth->copy()->addMonthNoOverflow()->startOfMonth();

                if ($thisMonth->isSameMonth($lastMonth)) {
                    // Another delivery in the same calendar month — streak unchanged
                } elseif ($thisMonth->isSameDay($expectedNextMonth)) {
                    // Consecutive month — extend streak
                    $streak->current_streak++;
                } else {
                    // Gap: reset streak to 1
                    $streak->current_streak = 1;
                }
            }

            $streak->last_order_month = $orderMonth;
            $streak->longest_streak   = max((int) $streak->longest_streak, (int) $streak->current_streak);
            $streak->save();

            // Check badges after updating streak
            $customer->refresh();
            $this->checkAndAwardBadges($customer, $streak);
        });
    }

    /**
     * Call after a referral is successfully used (ReferralController).
     */
    public function checkReferralBadge(Customer $customer): void
    {
        $this->awardBadgeIfMissing($customer, 'first_referral');
    }

    /**
     * Rebuild streak/badge state for a customer (admin utility / backfill).
     */
    public function recalculate(Customer $customer): void
    {
        $deliveredOrders = $customer->orders()
            ->where('status', 'delivered')
            ->orderBy('delivered_at')
            ->get();

        DB::transaction(function () use ($customer, $deliveredOrders) {
            CustomerStreak::where('customer_id', $customer->id)->delete();
            CustomerBadge::where('customer_id', $customer->id)->delete();

            foreach ($deliveredOrders as $order) {
                $this->updateOnDelivery($customer->fresh(), $order);
            }
        });
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function checkAndAwardBadges(Customer $customer, CustomerStreak $streak): void
    {
        $existing = CustomerBadge::where('customer_id', $customer->id)
            ->pluck('badge_key')
            ->flip()
            ->toArray();

        // Order count badges
        foreach (self::ORDER_BADGES as $key => $required) {
            if ($streak->order_count >= $required && ! isset($existing[$key])) {
                $this->awardBadgeIfMissing($customer, $key);
            }
        }

        // Streak badges
        foreach (self::STREAK_BADGES as $key => $required) {
            if ($streak->current_streak >= $required && ! isset($existing[$key])) {
                $this->awardBadgeIfMissing($customer, $key);
            }
        }

        // Points balance badges
        foreach (self::POINTS_BADGES as $key => $required) {
            if ($customer->gaspoints_balance >= $required && ! isset($existing[$key])) {
                $this->awardBadgeIfMissing($customer, $key);
            }
        }
    }

    private function awardBadgeIfMissing(Customer $customer, string $key): void
    {
        $created = CustomerBadge::firstOrCreate(
            ['customer_id' => $customer->id, 'badge_key' => $key],
            ['earned_at'   => now()]
        );

        if ($created->wasRecentlyCreated) {
            Log::info("[GAMIFICATION] Badge '{$key}' earned by customer #{$customer->id}");
        }
    }
}
