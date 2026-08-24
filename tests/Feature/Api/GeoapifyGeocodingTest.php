<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Services\Geocoding\GeoapifyProvider;
use App\Services\Geocoding\GeocodingProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GeoapifyGeocodingTest extends TestCase
{
    use RefreshDatabase;

    private const UPSTREAM = 'https://api.geoapify.com/*';

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('geocoding.driver', 'geoapify');
        config()->set('geocoding.geoapify.key', 'test-key');

        $customer = Customer::factory()->create();
        $this->token = $customer->createToken('mobile')->plainTextToken;
        Cache::flush();
    }

    public function test_the_configured_driver_is_geoapify(): void
    {
        $this->assertInstanceOf(GeoapifyProvider::class, app(GeocodingProvider::class));
    }

    public function test_search_returns_normalised_places(): void
    {
        Http::fake([self::UPSTREAM => Http::response([
            'results' => [[
                'place_id' => 'abc123',
                'formatted' => 'Zion Mall, Uganda Road, Eldoret, Kenya',
                'address_line1' => 'Zion Mall',
                'address_line2' => 'Uganda Road, Eldoret, Kenya',
                'lat' => 0.5210,
                'lon' => 35.2755,
            ]],
        ])]);

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/search?q=Zion Mall')
            ->assertOk()
            // Geoapify supplies a purpose-built one-line label, so there is no
            // need to guess one by chopping up the formatted address.
            ->assertJsonPath('data.0.short', 'Zion Mall')
            ->assertJsonPath('data.0.display_name', 'Zion Mall, Uganda Road, Eldoret, Kenya')
            ->assertJsonPath('data.0.lat', 0.5210)
            ->assertJsonPath('data.0.lon', 35.2755);
    }

    public function test_search_is_bounded_and_biased_to_the_service_area(): void
    {
        Http::fake([self::UPSTREAM => Http::response(['results' => []])]);

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/search?q=Moi Avenue')
            ->assertOk();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/geocode/autocomplete')
                && str_starts_with($request['filter'], 'rect:')
                && str_starts_with($request['bias'], 'proximity:')
                && $request['apiKey'] === 'test-key';
        });
    }

    public function test_the_api_key_never_reaches_the_client(): void
    {
        Http::fake([self::UPSTREAM => Http::response([
            'results' => [[
                'place_id' => 'abc123',
                'formatted' => 'Zion Mall, Uganda Road, Eldoret, Kenya',
                'address_line1' => 'Zion Mall',
                'lat' => 0.5210,
                'lon' => 35.2755,
            ]],
        ])]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/geocode/search?q=Zion Mall')
            ->assertOk();

        // The whole reason lookups are proxied rather than called from the app.
        $this->assertStringNotContainsString('test-key', $response->getContent());
        $this->assertStringNotContainsString('apiKey', $response->getContent());
    }

    public function test_reverse_returns_the_first_result(): void
    {
        Http::fake([self::UPSTREAM => Http::response([
            'results' => [[
                'place_id' => 'xyz',
                'formatted' => 'Kapsoya, Eldoret, Kenya',
                'address_line1' => 'Kapsoya',
                'lat' => 0.5210,
                'lon' => 35.2755,
            ]],
        ])]);

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/reverse?lat=0.5210&lng=35.2755')
            ->assertOk()
            ->assertJsonPath('data.short', 'Kapsoya');
    }

    public function test_reverse_with_no_match_returns_null_not_an_error(): void
    {
        Http::fake([self::UPSTREAM => Http::response(['results' => []])]);

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/reverse?lat=0.5210&lng=35.2755')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_a_malformed_coordinate_is_dropped_not_coerced_to_zero(): void
    {
        Http::fake([self::UPSTREAM => Http::response([
            'results' => [
                ['place_id' => '1', 'formatted' => 'Broken', 'lat' => null, 'lon' => 'xyz'],
                [
                    'place_id' => '2',
                    'formatted' => 'Zion Mall, Uganda Road, Eldoret',
                    'address_line1' => 'Zion Mall',
                    'lat' => 0.5210,
                    'lon' => 35.2755,
                ],
            ],
        ])]);

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/search?q=Zion')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.place_id', '2');
    }

    public function test_upstream_failure_reports_unavailable(): void
    {
        Http::fake([self::UPSTREAM => Http::response('quota exceeded', 429)]);

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/search?q=Zion Mall')
            ->assertStatus(503);
    }

    public function test_results_are_cached_across_requests(): void
    {
        Http::fake([self::UPSTREAM => Http::response(['results' => []])]);

        for ($i = 0; $i < 3; $i++) {
            $this->withToken($this->token)
                ->getJson('/api/v1/geocode/search?q=Zion Mall')
                ->assertOk();
        }

        // Credits are the cost model here, so a cache hit is money saved.
        Http::assertSentCount(1);
    }

    public function test_a_missing_key_fails_loudly_rather_than_calling_out(): void
    {
        config()->set('geocoding.geoapify.key', '');
        Http::fake();

        $this->expectException(RuntimeException::class);
        (new GeoapifyProvider())->reverse(0.5210, 35.2755);
    }
}
