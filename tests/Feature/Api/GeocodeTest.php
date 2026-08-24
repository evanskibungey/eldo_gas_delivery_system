<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeocodeTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $customer = Customer::factory()->create();
        $this->token = $customer->createToken('mobile')->plainTextToken;
        Cache::flush();
    }

    private function upstream(): string
    {
        return rtrim(config('geocoding.nominatim.url'), '/') . '/*';
    }

    public function test_search_requires_authentication(): void
    {
        $this->getJson('/api/v1/geocode/search?q=Zion')->assertUnauthorized();
    }

    public function test_search_returns_normalised_places(): void
    {
        Http::fake([$this->upstream() => Http::response([
            [
                'place_id' => 42,
                'display_name' => 'Zion Mall, Uganda Road, Eldoret, Uasin Gishu, Kenya',
                'lat' => '0.5210',
                'lon' => '35.2755',
            ],
        ])]);

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/search?q=Zion Mall')
            ->assertOk()
            ->assertJsonPath('data.0.short', 'Zion Mall, Uganda Road')
            ->assertJsonPath('data.0.lat', 0.5210)
            ->assertJsonPath('data.0.lon', 35.2755);
    }

    public function test_search_is_bounded_to_the_service_area(): void
    {
        Http::fake([$this->upstream() => Http::response([])]);

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/search?q=Moi Avenue')
            ->assertOk();

        // Without bounded=1 a common street name returns hits hundreds of
        // kilometres away that the customer cannot tell apart from local ones.
        Http::assertSent(function ($request) {
            return $request['bounded'] === 1
                && ! empty($request['viewbox'])
                && $request['countrycodes'] === 'ke';
        });
    }

    public function test_repeated_searches_hit_the_cache_not_the_upstream(): void
    {
        Http::fake([$this->upstream() => Http::response([])]);

        for ($i = 0; $i < 3; $i++) {
            $this->withToken($this->token)
                ->getJson('/api/v1/geocode/search?q=Zion Mall')
                ->assertOk();
        }

        // The whole point of proxying: thousands of devices become one
        // upstream identity, and repeated lookups cost nothing.
        Http::assertSentCount(1);
    }

    public function test_nearby_reverse_lookups_share_a_cache_entry(): void
    {
        Http::fake([$this->upstream() => Http::response([
            'place_id' => 7,
            'display_name' => 'Kapsoya, Eldoret, Uasin Gishu, Kenya',
            'lat' => '0.52100',
            'lon' => '35.27550',
        ])]);

        // Two pins about 2 m apart resolve to the same address.
        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/reverse?lat=0.521001&lng=35.275501')
            ->assertOk()
            ->assertJsonPath('data.short', 'Kapsoya, Eldoret');

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/reverse?lat=0.521004&lng=35.275502')
            ->assertOk();

        Http::assertSentCount(1);
    }

    public function test_upstream_failure_reports_unavailable_rather_than_empty(): void
    {
        Http::fake([$this->upstream() => Http::response('rate limited', 429)]);

        // An empty list reads as "no such place", and the app would keep
        // showing the previous pin's address as if it still applied.
        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/reverse?lat=0.5210&lng=35.2755')
            ->assertStatus(503);
    }

    public function test_a_failed_lookup_is_not_cached(): void
    {
        // The client retries twice on failure, so the first attempt burns
        // three responses before the request is reported as failed.
        Http::fake([$this->upstream() => Http::sequence()
            ->push('boom', 500)
            ->push('boom', 500)
            ->push('boom', 500)
            ->push([
                'place_id' => 7,
                'display_name' => 'Kapsoya, Eldoret, Uasin Gishu, Kenya',
                'lat' => '0.5210',
                'lon' => '35.2755',
            ], 200),
        ]);

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/reverse?lat=0.5210&lng=35.2755')
            ->assertStatus(503);

        // A cached failure would pin "unavailable" in front of every customer
        // for the whole TTL, long after the upstream recovered.
        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/reverse?lat=0.5210&lng=35.2755')
            ->assertOk()
            ->assertJsonPath('data.short', 'Kapsoya, Eldoret');
    }

    public function test_a_malformed_coordinate_is_dropped_not_coerced_to_zero(): void
    {
        Http::fake([$this->upstream() => Http::response([
            ['place_id' => 1, 'display_name' => 'Broken', 'lat' => 'abc', 'lon' => 'xyz'],
            [
                'place_id' => 2,
                'display_name' => 'Zion Mall, Uganda Road, Eldoret',
                'lat' => '0.5210',
                'lon' => '35.2755',
            ],
        ])]);

        // Coercing to 0 silently pins the Gulf of Guinea.
        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/search?q=Zion')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.place_id', '2');
    }

    public function test_search_rejects_a_too_short_query(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/search?q=Z')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    }

    public function test_reverse_rejects_an_impossible_coordinate(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/reverse?lat=91&lng=200')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lat', 'lng']);
    }
}
