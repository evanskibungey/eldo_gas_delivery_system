<?php

namespace App\Services;

use App\Models\Order;

class EtaService
{
    /** Average riding speed in km/h for urban LPG delivery. */
    private const RIDER_KMH = 25;

    /** Time to load and prepare the order at the shop (minutes). */
    private const PICKUP_BUFFER = 5;

    /** Added per pending order ahead in the queue (minutes). */
    private const QUEUE_MINUTES_PER_ORDER = 4;

    /** Absolute bounds regardless of queue / distance. */
    private const MIN_MINUTES = 15;
    private const MAX_MINUTES = 90;

    /**
     * Estimate delivery ETA in minutes.
     *
     * Uses three inputs:
     *   1. Active order queue depth (how many orders the shop is currently
     *      handling — each adds a small wait).
     *   2. Straight-line distance from the shop to the customer's last known
     *      delivery address (from their most recent order), converted to travel
     *      time at average rider speed.
     *   3. A fixed pickup buffer for loading.
     *
     * @param  float|null  $customerLat  Last known delivery latitude (null → no distance factor)
     * @param  float|null  $customerLng  Last known delivery longitude
     */
    public function estimate(?float $customerLat = null, ?float $customerLng = null): int
    {
        $activeOrders = Order::whereNotIn('status', ['delivered', 'cancelled'])->count();

        $queueMinutes    = $activeOrders * self::QUEUE_MINUTES_PER_ORDER;
        $distanceMinutes = $this->travelMinutes($customerLat, $customerLng);

        $eta = self::PICKUP_BUFFER + $queueMinutes + $distanceMinutes;

        return (int) max(self::MIN_MINUTES, min(self::MAX_MINUTES, $eta));
    }

    private function travelMinutes(?float $lat, ?float $lng): int
    {
        if ($lat === null || $lng === null) {
            return 10; // fallback when location unknown
        }

        $shopLat = (float) config('services.shop.latitude',  -0.2833); // default: Eldoret
        $shopLng = (float) config('services.shop.longitude', 35.2833);

        $km = $this->haversineKm($shopLat, $shopLng, $lat, $lng);

        return (int) ceil(($km / self::RIDER_KMH) * 60);
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r    = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $r * 2 * asin(sqrt($a));
    }
}
