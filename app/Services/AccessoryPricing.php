<?php

namespace App\Services;

use App\Models\CylinderPrice;
use App\Models\SystemSetting;

/**
 * What an accessory-only order costs to deliver.
 *
 * Two callers need the same answer: PlaceOrderAction, which charges it, and
 * the catalogue endpoint, which tells the app what to show before the
 * customer commits. A second copy of this arithmetic would be a quote that
 * quietly stops matching the bill.
 */
class AccessoryPricing
{
    /**
     * A rider still rides for a hose, so this is never free by accident.
     *
     * The usual per-size fee is unavailable — such an order names no size —
     * and delivery_base_fee defaults to '0.00', so falling back to it alone
     * would give away every accessory delivery on a shop that never set it.
     * Explicit setting first, then the flat base fee if the shop uses one,
     * then the cheapest cylinder's fee as a floor. Zero happens only when
     * someone chooses zero.
     */
    public function deliveryFee(): float
    {
        $explicit = SystemSetting::get('accessory_delivery_fee');
        if ($explicit !== null && $explicit !== '') {
            return (float) $explicit;
        }

        $feeMode = SystemSetting::get('delivery_fee_mode', 'per_size');
        if (in_array($feeMode, ['flat_rate', 'per_km'], true)) {
            $base = (float) SystemSetting::get('delivery_base_fee', '0.00');
            if ($base > 0) {
                return $base;
            }
        }

        return (float) (CylinderPrice::min('delivery_fee') ?? 0);
    }
}
