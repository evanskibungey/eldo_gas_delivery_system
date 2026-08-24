<?php

namespace App\Services\Geocoding;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Server-side geocoding, so the app never calls a geocoder directly.
 *
 * The customer app used to hit nominatim.openstreetmap.org from every device:
 * keystroke autocomplete on search, plus a reverse lookup every time the map
 * settled. Nominatim's usage policy forbids both against the public instance,
 * and the failure mode was silent — a 429 surfaced as an empty catch, so the
 * app kept showing whatever address was on screen before.
 *
 * Routing through here buys three things the app could not have on its own:
 * a shared cache (reverse lookups cluster hard around the same few places),
 * one upstream identity instead of thousands, and a provider that can be
 * swapped by changing config rather than shipping a release.
 */
class GeocodingService
{
    /** A cached null is indistinguishable from a miss, so null gets a stand-in. */
    private const NULL_SENTINEL = '__geo_null__';
    private const MISS = '__geo_miss__';

    public function __construct(private readonly GeocodingProvider $provider) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, ?array $viewbox = null): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $key = 'geo:search:' . md5(mb_strtolower($query) . '|' . json_encode($viewbox));

        return $this->remember($key, $this->searchTtl(), fn () => $this->provider->search($query, $viewbox));
    }

    public function reverse(float $lat, float $lng): ?array
    {
        // Round to ~11 m before keying. Two pins a few metres apart resolve to
        // the same address, so keying on raw coordinates would make the cache
        // almost always miss.
        $key = sprintf('geo:reverse:%.4f,%.4f', $lat, $lng);

        return $this->remember($key, $this->reverseTtl(), fn () => $this->provider->reverse($lat, $lng));
    }

    /**
     * Cache successes only — including a legitimately empty result, which is
     * a real answer ("nothing matches that") and worth remembering.
     *
     * A failure throws out of $resolve before anything is written, so a wrong
     * or empty answer can never be pinned in front of every customer for the
     * whole TTL. Null needs a sentinel because a cached null is
     * indistinguishable from a cache miss.
     */
    private function remember(string $key, int $ttl, callable $resolve): mixed
    {
        $cached = Cache::get($key, self::MISS);
        if ($cached !== self::MISS) {
            return $cached === self::NULL_SENTINEL ? null : $cached;
        }

        try {
            $value = $resolve();
        } catch (RuntimeException $e) {
            Log::warning('[Geocoding] upstream failed: ' . $e->getMessage());
            throw $e;
        }

        Cache::put($key, $value ?? self::NULL_SENTINEL, $ttl);

        return $value;
    }

    private function searchTtl(): int
    {
        return (int) config('geocoding.cache.search_ttl', 86400);
    }

    private function reverseTtl(): int
    {
        return (int) config('geocoding.cache.reverse_ttl', 604800);
    }

    /** Shared HTTP client so every provider gets the same timeouts. */
    public static function client(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout((int) config('geocoding.timeout', 8))
            ->retry(2, 250, throw: false);
    }
}
