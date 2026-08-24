<?php

namespace App\Services\Geocoding;

/**
 * One geocoding backend. Implementations must return the same shape so the
 * app never learns which provider is in use and swapping one is a config
 * change rather than an app release.
 *
 * Result shape:
 *   [
 *     'place_id'     => string,
 *     'display_name' => string,   // full, disambiguating
 *     'short'        => string,   // one-line label for a list row
 *     'lat'          => float,
 *     'lon'          => float,
 *   ]
 */
interface GeocodingProvider
{
    /**
     * @param  array{west: float, south: float, east: float, north: float}|null  $viewbox
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, ?array $viewbox = null): array;

    /** @return array<string, mixed>|null */
    public function reverse(float $lat, float $lng): ?array;
}
