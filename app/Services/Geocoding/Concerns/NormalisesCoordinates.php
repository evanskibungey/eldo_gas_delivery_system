<?php

namespace App\Services\Geocoding\Concerns;

trait NormalisesCoordinates
{
    /**
     * Validate a coordinate pair from an upstream response.
     *
     * Returns null for anything unusable rather than coercing to a number.
     * Coercing a malformed value to 0 silently pins the Gulf of Guinea, and
     * a pin at 0,0 looks exactly as confident on the map as a real one.
     *
     * @return array{0: float, 1: float}|null
     */
    protected function validCoordinates(mixed $lat, mixed $lon): ?array
    {
        $lat = filter_var($lat, FILTER_VALIDATE_FLOAT);
        $lon = filter_var($lon, FILTER_VALIDATE_FLOAT);

        if ($lat === false || $lon === false) {
            return null;
        }
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            return null;
        }

        return [$lat, $lon];
    }

    /** First two comma-separated parts of a long display name. */
    protected function shortenDisplayName(string $displayName): string
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $displayName))));

        if (count($parts) <= 2) {
            return $displayName;
        }

        return implode(', ', array_slice($parts, 0, 2));
    }
}
