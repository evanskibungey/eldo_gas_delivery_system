<?php

namespace App\Services\Geocoding;

use App\Services\Geocoding\Concerns\NormalisesCoordinates;
use RuntimeException;

/**
 * Google Geocoding and Places.
 *
 * Google's address and POI coverage in Kenya is considerably better than
 * OpenStreetMap's, which is the point of using it: "which building is this"
 * is the question the whole delivery-location flow is trying to answer.
 *
 * Called from the server so the key stays out of the APK and results stay
 * cached. That does forgo Places session-token pricing, which requires the
 * autocomplete and the detail fetch to be correlated client-side — the
 * trade is deliberate: caching removes far more requests than session
 * pricing would have discounted.
 */
class GoogleProvider implements GeocodingProvider
{
    use NormalisesCoordinates;

    private const PLACES_BASE = 'https://maps.googleapis.com/maps/api/place';
    private const GEOCODE_BASE = 'https://maps.googleapis.com/maps/api/geocode';

    public function search(string $query, ?array $viewbox = null): array
    {
        $params = [
            'query' => $query,
            'key' => $this->key(),
        ];

        if ($region = config('geocoding.country')) {
            $params['region'] = $region;
        }

        if ($viewbox !== null) {
            // Text Search takes a circle, not a rectangle. Use the box's
            // centre and the radius that reaches its corner, so nothing
            // inside the delivery area is excluded.
            [$lat, $lng, $radius] = $this->circleFor($viewbox);
            $params['location'] = "{$lat},{$lng}";
            $params['radius'] = (int) $radius;
        }

        $rows = $this->request(self::PLACES_BASE . '/textsearch/json', $params);

        return array_values(array_filter(array_map(
            fn ($row) => is_array($row) ? $this->normalisePlace($row) : null,
            $rows
        )));
    }

    public function reverse(float $lat, float $lng): ?array
    {
        $rows = $this->request(self::GEOCODE_BASE . '/json', [
            'latlng' => "{$lat},{$lng}",
            'key' => $this->key(),
        ]);

        $first = $rows[0] ?? null;

        return is_array($first) ? $this->normaliseGeocode($first) : null;
    }

    /** @return array{0: float, 1: float, 2: float} lat, lng, radius in metres */
    private function circleFor(array $viewbox): array
    {
        $lat = ($viewbox['south'] + $viewbox['north']) / 2;
        $lng = ($viewbox['west'] + $viewbox['east']) / 2;

        $latSpanKm = ($viewbox['north'] - $viewbox['south']) * 110.574;
        $lngSpanKm = ($viewbox['east'] - $viewbox['west'])
            * 111.320 * max(0.01, abs(cos(deg2rad($lat))));

        // Half-diagonal of the box, capped at Google's 50km maximum.
        $radiusKm = sqrt(($latSpanKm / 2) ** 2 + ($lngSpanKm / 2) ** 2);

        return [$lat, $lng, min(50000, $radiusKm * 1000)];
    }

    private function key(): string
    {
        $key = (string) config('geocoding.google.key');

        if ($key === '') {
            throw new RuntimeException(
                'GOOGLE_MAPS_API_KEY is not set but the google driver is selected.'
            );
        }

        return $key;
    }

    /**
     * Google signals failure in the body with HTTP 200, so the status field
     * has to be checked explicitly or a quota error reads as "no results".
     *
     * @return array<int, mixed>
     */
    private function request(string $url, array $params): array
    {
        $response = GeocodingService::client()
            ->withHeaders(['Accept' => 'application/json'])
            ->get($url, $params);

        if ($response->failed()) {
            throw new RuntimeException("Google geocoding returned {$response->status()}");
        }

        $status = (string) $response->json('status');

        if ($status === 'ZERO_RESULTS') {
            return [];
        }

        if ($status !== 'OK') {
            $detail = $response->json('error_message') ?? $status;
            throw new RuntimeException("Google geocoding failed: {$detail}");
        }

        return $response->json('results') ?? [];
    }

    /** Places Text Search row. */
    private function normalisePlace(array $row): ?array
    {
        $coordinates = $this->validCoordinates(
            $row['geometry']['location']['lat'] ?? null,
            $row['geometry']['location']['lng'] ?? null,
        );
        if ($coordinates === null) {
            return null;
        }

        [$lat, $lon] = $coordinates;
        $name = trim((string) ($row['name'] ?? ''));
        $formatted = (string) ($row['formatted_address'] ?? '');

        // The place name is the label a person recognises ("Zion Mall"); the
        // formatted address is the disambiguating detail beneath it.
        return [
            'place_id' => (string) ($row['place_id'] ?? ''),
            'display_name' => $formatted !== '' ? $formatted : $name,
            'short' => $name !== '' ? $name : $this->shortenDisplayName($formatted),
            'lat' => $lat,
            'lon' => $lon,
        ];
    }

    /** Geocoding API row (reverse). */
    private function normaliseGeocode(array $row): ?array
    {
        $coordinates = $this->validCoordinates(
            $row['geometry']['location']['lat'] ?? null,
            $row['geometry']['location']['lng'] ?? null,
        );
        if ($coordinates === null) {
            return null;
        }

        [$lat, $lon] = $coordinates;
        $formatted = (string) ($row['formatted_address'] ?? '');

        return [
            'place_id' => (string) ($row['place_id'] ?? ''),
            'display_name' => $formatted,
            'short' => $this->shortenDisplayName($formatted),
            'lat' => $lat,
            'lon' => $lon,
        ];
    }
}
