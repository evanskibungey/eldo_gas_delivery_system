<?php

namespace App\Support;

use App\Models\SystemSetting;

/**
 * What a rider is paid.
 *
 * A rider earns a FLAT FEE PER DELIVERED ORDER. It has nothing to do with the
 * value of the gas: a KSh 3,000 cylinder and a KSh 1,200 refill pay the same,
 * because the work is the same trip.
 *
 * This exists because three places each computed it differently, and all three
 * were wrong:
 *
 *   - RiderService::statsFor()  paid `total_amount × (1 − commission)`, i.e.
 *                               90% of the cylinder's sale price
 *   - EarningsController        paid the full order value
 *   - ProfileController         paid the full order value
 *
 * All three now come through here, so the rider app, the admin rider page and
 * the profile tile can no longer disagree about what someone earned.
 */
final class RiderEarnings
{
    public const SETTING_KEY = 'rider_earning_per_delivery';

    public const DEFAULT_PER_DELIVERY = 100;

    /** Flat fee in whole KSh, as money is stored throughout this app. */
    public static function perDelivery(): int
    {
        return (int) SystemSetting::get(
            self::SETTING_KEY,
            (string) self::DEFAULT_PER_DELIVERY,
        );
    }

    /** Pay for a number of completed deliveries. */
    public static function forDeliveries(int $deliveredCount): int
    {
        return max(0, $deliveredCount) * self::perDelivery();
    }
}
