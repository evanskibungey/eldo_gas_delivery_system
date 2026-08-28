<?php

namespace App\Support;

use App\Models\Rider;

/**
 * The single definition of what a rider's map/roster badge says.
 *
 * This lived in two places (RiderService and RiderTrackingService), both with
 * the same inverted logic: `is_available` was treated as proof the rider was
 * idle, so a rider mid-delivery showed as 'available' and a rider who had
 * clocked off showed as 'on_delivery'. Availability is only the rider's own
 * on/off switch — assignment gates on busy-status orders, and never clears it —
 * so actual work decides first and availability only breaks the remaining tie.
 */
final class RiderStatus
{
    public const OFFLINE = 'offline';
    public const AVAILABLE = 'available';
    public const ON_DELIVERY = 'on_delivery';

    /**
     * @param  bool|null  $hasActiveOrder  Pass a precomputed value where the
     *                                     caller has already loaded it (see the
     *                                     withExists() in RiderTrackingService)
     *                                     to avoid one query per rider.
     */
    public static function for(Rider $rider, ?bool $hasActiveOrder = null): string
    {
        if (! $rider->is_active) {
            return self::OFFLINE;
        }

        $hasActiveOrder ??= self::resolveHasActiveOrder($rider);

        if ($hasActiveOrder) {
            return self::ON_DELIVERY;
        }

        return $rider->is_available ? self::AVAILABLE : self::OFFLINE;
    }

    private static function resolveHasActiveOrder(Rider $rider): bool
    {
        // Set by a withExists('orders as has_active_order') on the query.
        $preloaded = $rider->getAttribute('has_active_order');

        if ($preloaded !== null) {
            return (bool) $preloaded;
        }

        return $rider->orders()
            ->whereIn('status', OrderLifecycle::riderBusyStatuses())
            ->exists();
    }
}
