<?php

namespace App\Services;

use App\Models\SystemSetting;

/**
 * Decides whether a delivery pin is somewhere riders actually go.
 *
 * The customer app enforces the same rule at the point the pin is chosen, but
 * this is the authority — the app's copy exists only so the customer finds out
 * while they can still move the pin.
 */
class ServiceAreaService
{
    private const EARTH_RADIUS_KM = 6371;

    public function name(): string
    {
        return (string) config('delivery.service_area.name', 'Eldoret');
    }

    public function centre(): array
    {
        return [
            (float) config('delivery.service_area.latitude'),
            (float) config('delivery.service_area.longitude'),
        ];
    }

    /** Runtime-overridable via the `service_area_radius_km` system setting. */
    public function radiusKm(): float
    {
        return (float) SystemSetting::get(
            'service_area_radius_km',
            config('delivery.service_area.radius_km', 25)
        );
    }

    public function distanceKm(float $lat, float $lng): float
    {
        [$centreLat, $centreLng] = $this->centre();

        $dLat = deg2rad($lat - $centreLat);
        $dLng = deg2rad($lng - $centreLng);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($centreLat)) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * asin(min(1.0, sqrt($a)));
    }

    public function contains(float $lat, float $lng): bool
    {
        return $this->distanceKm($lat, $lng) <= $this->radiusKm();
    }

    public function rejectionMessage(float $lat, float $lng): string
    {
        $km = (int) round($this->distanceKm($lat, $lng));

        return "That delivery location is about {$km} km from {$this->name()}, "
            . 'outside our delivery area.';
    }
}
