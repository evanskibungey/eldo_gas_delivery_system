<?php

namespace App\Services\Geocoding;

use App\Services\Geocoding\Concerns\NormalisesCoordinates;
use RuntimeException;

/**
 * Nominatim, either the public instance or a self-hosted one.
 *
 * Point `GEOCODING_NOMINATIM_URL` at your own instance to lift the fair-use
 * restrictions entirely — the public host forbids the autocomplete and
 * per-map-settle reverse lookups this app makes, and will rate-limit or block
 * an app that generates real traffic against it.
 */
class NominatimProvider implements GeocodingProvider
{
    use NormalisesCoordinates;

    public function search(string $query, ?array $viewbox = null): array
    {
        $params = [
            'q' => $query,
            'format' => 'json',
            'addressdetails' => 1,
            'countrycodes' => config('geocoding.country', 'ke'),
            'limit' => 6,
        ];

        if ($viewbox !== null) {
            // Nominatim wants west,north,east,south.
            $params['viewbox'] = implode(',', [
                $viewbox['west'], $viewbox['north'], $viewbox['east'], $viewbox['south'],
            ]);
            $params['bounded'] = 1;
        }

        $response = $this->request('/search', $params);
        $rows = is_array($response) ? $response : [];

        return array_values(array_filter(array_map(
            fn ($row) => is_array($row) ? $this->normalise($row) : null,
            $rows
        )));
    }

    public function reverse(float $lat, float $lng): ?array
    {
        $row = $this->request('/reverse', [
            'lat' => $lat,
            'lon' => $lng,
            'format' => 'json',
            'addressdetails' => 1,
        ]);

        if (! is_array($row) || empty($row) || isset($row['error'])) {
            return null;
        }

        return $this->normalise($row);
    }

    private function request(string $path, array $params): mixed
    {
        $base = rtrim((string) config('geocoding.nominatim.url'), '/');

        $response = GeocodingService::client()
            ->withHeaders([
                // Nominatim's policy mandates a contactable User-Agent.
                'User-Agent' => (string) config('geocoding.user_agent'),
                'Accept' => 'application/json',
            ])
            ->get($base . $path, $params);

        if ($response->failed()) {
            throw new RuntimeException(
                "Nominatim {$path} returned {$response->status()}"
            );
        }

        return $response->json();
    }

    /** Nominatim sends coordinates as strings. */
    private function normalise(array $row): ?array
    {
        $coordinates = $this->validCoordinates($row['lat'] ?? null, $row['lon'] ?? null);
        if ($coordinates === null) {
            return null;
        }

        [$lat, $lon] = $coordinates;
        $displayName = (string) ($row['display_name'] ?? '');

        return [
            'place_id' => (string) ($row['place_id'] ?? ''),
            'display_name' => $displayName,
            'short' => $this->shortenDisplayName($displayName),
            'lat' => $lat,
            'lon' => $lon,
        ];
    }
}
