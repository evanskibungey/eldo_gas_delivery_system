<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use App\Models\SystemSetting;
use App\Support\OrderLifecycle;

/**
 * A fixed amount off a customer's first order placed through the app.
 *
 * One rule, in one place, because two of them ask: the home screen, to say
 * the offer exists, and order placement, to actually take it off. A customer
 * shown "KES 100 off" who is then charged full price would be worse than
 * never showing it.
 *
 * Nothing here trusts the app. The amount is read from settings and the
 * eligibility from the orders table, both at the moment the order is written.
 */
class FirstOrderDiscount
{
    /// The channel that earns it. Orders the shop takes by phone or over the
    /// counter are not app orders and neither earn nor consume the offer.
    public const CHANNEL = 'app';

    public function amount(): int
    {
        return max(0, (int) SystemSetting::get('first_order_discount', 100));
    }

    /// Below this the discount is not applied, so a new account cannot be a
    /// way to get a cheap accessory rather than a first cylinder.
    public function minimumOrder(): int
    {
        return max(0, (int) SystemSetting::get('first_order_discount_min_order', 1000));
    }

    /// True when this customer has never had an app order stand.
    ///
    /// Cancelled orders do not count, so a first order the shop could not
    /// fill leaves the offer intact. Nobody gains by cancelling: no gas
    /// arrives either way.
    public function isEligible(Customer $customer): bool
    {
        if ($this->amount() <= 0) {
            return false;
        }

        return ! Order::where('customer_id', $customer->id)
            ->where('channel', self::CHANNEL)
            ->where('status', '!=', OrderLifecycle::STATUS_CANCELLED)
            ->exists();
    }

    /// What comes off an order of this size, or zero.
    ///
    /// [subtotal] is the order before any discount — items, delivery and
    /// accessories — so the minimum is judged on what the order is worth
    /// rather than on what is left after GasPoints.
    public function forOrder(Customer $customer, float $subtotal, string $channel): int
    {
        if ($channel !== self::CHANNEL) {
            return 0;
        }
        if ($subtotal < $this->minimumOrder()) {
            return 0;
        }
        if (! $this->isEligible($customer)) {
            return 0;
        }

        return $this->amount();
    }
}
