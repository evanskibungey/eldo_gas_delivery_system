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

    /**
     * A box around the service area, for bounding a geocoder search.
     *
     * Without it a common street name returns a match several hundred
     * kilometres away that nobody choosing from a list can tell apart from the
     * local one.
     *
     * @return array{west: float, south: float, east: float, north: float}
     */
    public function viewbox(): array
    {
        [$lat, $lng] = $this->centre();
        $radiusKm = $this->radiusKm();

        $latDelta = $radiusKm / 110.574;
        // Longitude degrees shrink towards the poles; guard the divisor so a
        // pathological configured latitude cannot divide by zero.
        $cosLat = max(0.01, abs(cos(deg2rad($lat))));
        $lngDelta = $radiusKm / (111.320 * $cosLat);

        return [
            'west' => $lng - $lngDelta,
            'south' => $lat - $latDelta,
            'east' => $lng + $lngDelta,
            'north' => $lat + $latDelta,
        ];
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
