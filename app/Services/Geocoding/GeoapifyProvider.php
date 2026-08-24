<?php

namespace App\Services\Geocoding;

use App\Services\Geocoding\Concerns\NormalisesCoordinates;
use RuntimeException;

/**
 * Geoapify geocoding.
 *
 * The key lives in the server's environment and never reaches the APK, which
 * is the whole reason lookups go through our API rather than straight out of
 * the app.
 *
 * Uses /geocode/autocomplete for search because that is what the field
 * actually is — someone typing a place name a few characters at a time. It
 * handles complete queries just as well as partial ones.
 */
class GeoapifyProvider implements GeocodingProvider
{
    use NormalisesCoordinates;

    private const BASE = 'https://api.geoapify.com/v1/geocode';

    public function search(string $query, ?array $viewbox = null): array
    {
        $params = [
            'text' => $query,
            'format' => 'json',
            'limit' => 6,
            'apiKey' => $this->key(),
        ];

        if ($viewbox !== null) {
            // A rectangle is strictly narrower than the country filter, so it
            // replaces rather than joins it.
            $params['filter'] = sprintf(
                'rect:%F,%F,%F,%F',
                $viewbox['west'], $viewbox['south'], $viewbox['east'], $viewbox['north']
            );
            // Rank by closeness to the middle of the delivery area, so the
            // nearest match wins rather than an arbitrary in-box one.
            $params['bias'] = sprintf(
                'proximity:%F,%F',
                ($viewbox['west'] + $viewbox['east']) / 2,
                ($viewbox['south'] + $viewbox['north']) / 2
            );
        } elseif ($country = config('geocoding.country')) {
            $params['filter'] = 'countrycode:' . $country;
        }

        $rows = $this->request('/autocomplete', $params);

        return array_values(array_filter(array_map(
            fn ($row) => is_array($row) ? $this->normalise($row) : null,
            $rows
        )));
    }

    public function reverse(float $lat, float $lng): ?array
    {
        $rows = $this->request('/reverse', [
            'lat' => $lat,
            'lon' => $lng,
            'format' => 'json',
            'limit' => 1,
            'apiKey' => $this->key(),
        ]);

        $first = $rows[0] ?? null;

        return is_array($first) ? $this->normalise($first) : null;
    }

    private function key(): string
    {
        $key = (string) config('geocoding.geoapify.key');

        if ($key === '') {
            // Fail loudly at the first call rather than sending a request that
            // is guaranteed to come back 401.
            throw new RuntimeException(
                'GEOAPIFY_API_KEY is not set but the geoapify driver is selected.'
            );
        }

        return $key;
    }

    /** @return array<int, mixed> */
    private function request(string $path, array $params): array
    {
        $response = GeocodingService::client()
            ->withHeaders(['Accept' => 'application/json'])
            ->get(self::BASE . $path, $params);

        if ($response->failed()) {
            throw new RuntimeException(
                "Geoapify {$path} returned {$response->status()}"
            );
        }

        return $response->json('results') ?? [];
    }

    private function normalise(array $row): ?array
    {
        $coordinates = $this->validCoordinates($row['lat'] ?? null, $row['lon'] ?? null);
        if ($coordinates === null) {
            return null;
        }

        [$lat, $lon] = $coordinates;
        $formatted = (string) ($row['formatted'] ?? '');

        // Geoapify gives a purpose-built one-line label; fall back to trimming
        // the formatted address only when it is missing.
        $short = trim((string) ($row['address_line1'] ?? ''));
        if ($short === '') {
            $short = $this->shortenDisplayName($formatted);
        }

        return [
            'place_id' => (string) ($row['place_id'] ?? ''),
            'display_name' => $formatted,
            'short' => $short,
            'lat' => $lat,
            'lon' => $lon,
        ];
    }
}
