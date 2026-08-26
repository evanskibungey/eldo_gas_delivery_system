<?php

namespace App\Services\Geocoding;

use App\Services\Geocoding\Concerns\NormalisesCoordinates;
use RuntimeException;

/**
 * OpenCage geocoding.
 *
 * Reverse geocoding is the half that matters here: the app has no map and no
 * picker, so the delivery card has nothing to show but whatever name we can
 * put to the phone's coordinates. When that lookup fails the customer sees
 * "0.51726, 35.31139", which tells them nothing about whether the rider is
 * coming to the right place.
 *
 * OpenCage returns structured `components` alongside the formatted address,
 * which is what makes a short label possible: "Kapsoya, Eldoret" rather than
 * the full postal string or an arbitrary truncation of it.
 */
class OpenCageProvider implements GeocodingProvider
{
    use NormalisesCoordinates;

    private const BASE = 'https://api.opencagedata.com/geocode/v1/json';

    public function search(string $query, ?array $viewbox = null): array
    {
        $params = [
            'q' => $query,
            'limit' => 6,
        ];

        if ($country = config('geocoding.country')) {
            $params['countrycode'] = $country;
        }

        if ($viewbox !== null) {
            // OpenCage wants min_lon,min_lat,max_lon,max_lat.
            $params['bounds'] = sprintf(
                '%F,%F,%F,%F',
                $viewbox['west'], $viewbox['south'], $viewbox['east'], $viewbox['north']
            );
            // Rank by closeness to the middle of the delivery area so the
            // nearest match wins rather than an arbitrary in-bounds one.
            $params['proximity'] = sprintf(
                '%F,%F',
                ($viewbox['south'] + $viewbox['north']) / 2,
                ($viewbox['west'] + $viewbox['east']) / 2
            );
        }

        $rows = $this->request($params);

        return array_values(array_filter(array_map(
            fn ($row) => is_array($row) ? $this->normalise($row) : null,
            $rows
        )));
    }

    public function reverse(float $lat, float $lng): ?array
    {
        // Reverse is the same endpoint with "lat,lng" as the query.
        $rows = $this->request([
            'q' => sprintf('%F,%F', $lat, $lng),
            'limit' => 1,
        ]);

        $first = $rows[0] ?? null;

        return is_array($first) ? $this->normalise($first) : null;
    }

    private function key(): string
    {
        $key = (string) config('geocoding.opencage.key');

        if ($key === '') {
            throw new RuntimeException(
                'OPENCAGE_API_KEY is not set but the opencage driver is selected.'
            );
        }

        return $key;
    }

    /** @return array<int, mixed> */
    private function request(array $params): array
    {
        $response = GeocodingService::client()
            ->withHeaders(['Accept' => 'application/json'])
            ->get(self::BASE, array_merge($params, [
                'key' => $this->key(),
                // Annotations are timezone/currency/what3words extras we never
                // read; asking for them just makes the payload bigger.
                'no_annotations' => 1,
                'language' => 'en',
            ]));

        if ($response->failed()) {
            // 402 is the quota wall and 403 a bad or suspended key. Both are
            // worth naming, because "no results" is what they look like from
            // the app otherwise.
            $detail = $response->json('status.message') ?? $response->status();
            throw new RuntimeException("OpenCage returned {$detail}");
        }

        return $response->json('results') ?? [];
    }

    private function normalise(array $row): ?array
    {
        $coordinates = $this->validCoordinates(
            $row['geometry']['lat'] ?? null,
            $row['geometry']['lng'] ?? null,
        );
        if ($coordinates === null) {
            return null;
        }

        [$lat, $lon] = $coordinates;
        $formatted = (string) ($row['formatted'] ?? '');
        $components = is_array($row['components'] ?? null) ? $row['components'] : [];

        return [
            'place_id' => (string) ($row['annotations']['geohash'] ?? md5("{$lat},{$lon}")),
            'display_name' => $formatted,
            'short' => $this->shortLabel($components, $formatted),
            'lat' => $lat,
            'lon' => $lon,
        ];
    }

    /**
     * A label a person would actually use to describe where they are.
     *
     * Kenyan addresses often have no street number and frequently no road
     * name, so the estate or suburb carries the meaning: "Kapsoya, Eldoret"
     * is useful, "Uasin Gishu, Kenya" is not, and the full formatted string
     * is too long for a card.
     */
    private function shortLabel(array $components, string $formatted): string
    {
        $locality = $this->firstOf($components, [
            'neighbourhood',
            'suburb',
            'village',
            'hamlet',
            'residential',
            'city_district',
            'road',
        ]);

        $place = $this->firstOf($components, ['city', 'town', 'municipality', 'county']);

        $parts = array_values(array_unique(array_filter([$locality, $place])));

        if ($parts !== []) {
            return implode(', ', $parts);
        }

        return $this->shortenDisplayName($formatted);
    }

    private function firstOf(array $components, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = trim((string) ($components[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
