<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Services\Geocoding\GeocodingProvider;
use App\Services\Geocoding\GoogleProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GoogleGeocodingTest extends TestCase
{
    use RefreshDatabase;

    private const UPSTREAM = 'https://maps.googleapis.com/*';

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('geocoding.driver', 'google');
        config()->set('geocoding.google.key', 'test-key');

        $customer = Customer::factory()->create();
        $this->token = $customer->createToken('mobile')->plainTextToken;
        Cache::flush();
    }

    private function place(array $overrides = []): array
    {
        return array_merge([
            'place_id' => 'ChIJabc123',
            'name' => 'Zion Mall',
            'formatted_address' => 'Uganda Road, Eldoret, Kenya',
            'geometry' => ['location' => ['lat' => 0.5210, 'lng' => 35.2755]],
        ], $overrides);
    }

    public function test_the_configured_driver_is_google(): void
    {
        $this->assertInstanceOf(GoogleProvider::class, app(GeocodingProvider::class));
    }

    public function test_search_uses_the_place_name_as_the_label(): void
    {
        Http::fake([self::UPSTREAM => Http::response([
            'status' => 'OK',
            'results' => [$this->place()],
        ])]);

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/search?q=Zion Mall')
            ->assertOk()
            // The name is what a person recognises; the formatted address is
            // the disambiguating detail beneath it.
            ->assertJsonPath('data.0.short', 'Zion Mall')
            ->assertJsonPath('data.0.display_name', 'Uganda Road, Eldoret, Kenya')
            ->assertJsonPath('data.0.lat', 0.5210)
            ->assertJsonPath('data.0.lon', 35.2755);
    }

    public function test_search_is_confined_to_the_service_area(): void
    {
        Http::fake([self::UPSTREAM => Http::response(['status' => 'OK', 'results' => []])]);

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/search?q=Moi Avenue')
            ->assertOk();

        Http::assertSent(function ($request) {
            // Text Search takes a circle rather than a rectangle, and Google
            // caps the radius at 50km.
            return str_contains($request->url(), '/place/textsearch/json')
                && ! empty($request['location'])
                && (int) $request['radius'] > 0
                && (int) $request['radius'] <= 50000;
        });
    }

    public function test_reverse_returns_the_formatted_address(): void
    {
        Http::fake([self::UPSTREAM => Http::response([
            'status' => 'OK',
            'results' => [[
                'place_id' => 'ChIJxyz',
                'formatted_address' => 'Kapsoya, Eldoret, Uasin Gishu, Kenya',
                'geometry' => ['location' => ['lat' => 0.5210, 'lng' => 35.2755]],
            ]],
        ])]);

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/reverse?lat=0.5210&lng=35.2755')
            ->assertOk()
            ->assertJsonPath('data.short', 'Kapsoya, Eldoret');
    }

    public function test_zero_results_is_an_empty_answer_not_a_failure(): void
    {
        Http::fake([self::UPSTREAM => Http::response(['status' => 'ZERO_RESULTS'])]);

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/reverse?lat=0.5210&lng=35.2755')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_a_quota_error_is_reported_rather_than_read_as_no_results(): void
    {
        // Google signals failure in the body with HTTP 200. Without checking
        // `status`, an exhausted quota would look exactly like "nowhere
        // matches", and the app would keep the previous pin's address on
        // screen as if it still applied.
        Http::fake([self::UPSTREAM => Http::response([
            'status' => 'OVER_QUERY_LIMIT',
            'error_message' => 'You have exceeded your daily request quota.',
        ])]);

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/search?q=Zion Mall')
            ->assertStatus(503);
    }

    public function test_a_request_denied_error_is_reported(): void
    {
        Http::fake([self::UPSTREAM => Http::response([
            'status' => 'REQUEST_DENIED',
            'error_message' => 'The provided API key is invalid.',
        ])]);

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/search?q=Zion Mall')
            ->assertStatus(503);
    }

    public function test_the_api_key_never_reaches_the_client(): void
    {
        Http::fake([self::UPSTREAM => Http::response([
            'status' => 'OK',
            'results' => [$this->place()],
        ])]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/geocode/search?q=Zion Mall')
            ->assertOk();

        $this->assertStringNotContainsString('test-key', $response->getContent());
    }

    public function test_a_malformed_coordinate_is_dropped(): void
    {
        Http::fake([self::UPSTREAM => Http::response([
            'status' => 'OK',
            'results' => [
                $this->place([
                    'place_id' => 'broken',
                    'geometry' => ['location' => ['lat' => null, 'lng' => 'xyz']],
                ]),
                $this->place(['place_id' => 'good']),
            ],
        ])]);

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/search?q=Zion')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.place_id', 'good');
    }

    public function test_results_are_cached(): void
    {
        Http::fake([self::UPSTREAM => Http::response(['status' => 'OK', 'results' => []])]);

        for ($i = 0; $i < 3; $i++) {
            $this->withToken($this->token)
                ->getJson('/api/v1/geocode/search?q=Zion Mall')
                ->assertOk();
        }

        // Google bills per request, so a cache hit is money saved.
        Http::assertSentCount(1);
    }

    public function test_a_missing_key_fails_loudly(): void
    {
        config()->set('geocoding.google.key', '');
        Http::fake();

        $this->expectException(RuntimeException::class);
        (new GoogleProvider())->reverse(0.5210, 35.2755);
    }
}
