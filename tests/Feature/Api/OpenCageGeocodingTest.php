<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Services\Geocoding\GeocodingProvider;
use App\Services\Geocoding\OpenCageProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class OpenCageGeocodingTest extends TestCase
{
    use RefreshDatabase;

    private const UPSTREAM = 'https://api.opencagedata.com/*';

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('geocoding.driver', 'opencage');
        config()->set('geocoding.opencage.key', 'test-key');

        $customer = Customer::factory()->create();
        $this->token = $customer->createToken('mobile')->plainTextToken;
        Cache::flush();
    }

    private function payload(array $components, string $formatted = 'Somewhere, Kenya'): array
    {
        return [
            'status' => ['code' => 200, 'message' => 'OK'],
            'results' => [[
                'formatted' => $formatted,
                'components' => $components,
                'geometry' => ['lat' => 0.51726, 'lng' => 35.31139],
            ]],
        ];
    }

    public function test_the_configured_driver_is_opencage(): void
    {
        $this->assertInstanceOf(OpenCageProvider::class, app(GeocodingProvider::class));
    }

    public function test_reverse_names_the_place_instead_of_returning_coordinates(): void
    {
        Http::fake([self::UPSTREAM => Http::response($this->payload(
            ['neighbourhood' => 'Kapsoya', 'city' => 'Eldoret', 'country' => 'Kenya'],
            'Kapsoya, Eldoret, Uasin Gishu, Kenya',
        ))]);

        // The whole point: the delivery card had nothing to show but
        // "0.51726, 35.31139" whenever this lookup came back empty.
        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/reverse?lat=0.51726&lng=35.31139')
            ->assertOk()
            ->assertJsonPath('data.short', 'Kapsoya, Eldoret')
            ->assertJsonPath('data.display_name', 'Kapsoya, Eldoret, Uasin Gishu, Kenya');
    }

    public function test_reverse_sends_the_coordinates_as_the_query(): void
    {
        Http::fake([self::UPSTREAM => Http::response($this->payload(['city' => 'Eldoret']))]);

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/reverse?lat=0.51726&lng=35.31139')
            ->assertOk();

        Http::assertSent(function ($request) {
            return str_starts_with($request['q'], '0.517')
                && str_contains($request['q'], ',')
                && $request['key'] === 'test-key'
                && $request['no_annotations'] == 1;
        });
    }

    public function test_the_label_falls_back_through_the_component_hierarchy(): void
    {
        // Kenyan addresses often have no street number and frequently no road
        // name, so the estate or suburb is what carries the meaning.
        $cases = [
            [['suburb' => 'Langas', 'town' => 'Eldoret'], 'Langas, Eldoret'],
            [['road' => 'Uganda Road', 'city' => 'Eldoret'], 'Uganda Road, Eldoret'],
            [['village' => 'Kesses', 'county' => 'Uasin Gishu'], 'Kesses, Uasin Gishu'],
            [['city' => 'Eldoret'], 'Eldoret'],
        ];

        // One sequence rather than re-faking per iteration: a second
        // Http::fake() does not replace the first, it merges, and the earliest
        // matching stub keeps answering.
        $sequence = Http::sequence();
        foreach ($cases as [$components, $_]) {
            $sequence->push($this->payload($components), 200);
        }
        Http::fake([self::UPSTREAM => $sequence]);

        foreach ($cases as $index => [$_, $expected]) {
            // Distinct coordinates so each lookup is its own cache entry.
            $lat = 0.51726 + ($index / 1000);

            $this->withToken($this->token)
                ->getJson("/api/v1/geocode/reverse?lat={$lat}&lng=35.31139")
                ->assertOk()
                ->assertJsonPath('data.short', $expected);
        }
    }

    public function test_a_duplicated_component_is_not_repeated(): void
    {
        Http::fake([self::UPSTREAM => Http::response($this->payload(
            ['suburb' => 'Eldoret', 'city' => 'Eldoret'],
        ))]);

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/reverse?lat=0.51726&lng=35.31139')
            ->assertOk()
            ->assertJsonPath('data.short', 'Eldoret');
    }

    public function test_with_no_usable_components_it_trims_the_formatted_address(): void
    {
        Http::fake([self::UPSTREAM => Http::response($this->payload(
            ['country' => 'Kenya'],
            'Some Long Place, Uasin Gishu, Rift Valley, Kenya',
        ))]);

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/reverse?lat=0.51726&lng=35.31139')
            ->assertOk()
            ->assertJsonPath('data.short', 'Some Long Place, Uasin Gishu');
    }

    public function test_search_is_bounded_to_the_service_area(): void
    {
        Http::fake([self::UPSTREAM => Http::response(['status' => ['code' => 200], 'results' => []])]);

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/search?q=Zion Mall')
            ->assertOk();

        Http::assertSent(function ($request) {
            return ! empty($request['bounds'])
                && ! empty($request['proximity'])
                && $request['countrycode'] === 'ke';
        });
    }

    public function test_a_quota_failure_is_reported_not_read_as_no_results(): void
    {
        // 402 is the free-tier wall. Silently returning nothing would put
        // coordinates back on the card with no explanation.
        Http::fake([self::UPSTREAM => Http::response(
            ['status' => ['code' => 402, 'message' => 'quota exceeded']],
            402,
        )]);

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/reverse?lat=0.51726&lng=35.31139')
            ->assertStatus(503);
    }

    public function test_an_invalid_key_is_reported(): void
    {
        Http::fake([self::UPSTREAM => Http::response(
            ['status' => ['code' => 403, 'message' => 'invalid API key']],
            403,
        )]);

        $this->withToken($this->token)
            ->getJson('/api/v1/geocode/search?q=Zion Mall')
            ->assertStatus(503);
    }

    public function test_the_key_never_reaches_the_client(): void
    {
        Http::fake([self::UPSTREAM => Http::response($this->payload(['city' => 'Eldoret']))]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/geocode/reverse?lat=0.51726&lng=35.31139')
            ->assertOk();

        $this->assertStringNotContainsString('test-key', $response->getContent());
    }

    public function test_results_are_cached(): void
    {
        Http::fake([self::UPSTREAM => Http::response($this->payload(['city' => 'Eldoret']))]);

        for ($i = 0; $i < 3; $i++) {
            $this->withToken($this->token)
                ->getJson('/api/v1/geocode/reverse?lat=0.51726&lng=35.31139')
                ->assertOk();
        }

        // OpenCage's free tier is a daily request count, so cache hits are
        // the difference between comfortably inside it and hitting the wall.
        Http::assertSentCount(1);
    }

    public function test_a_missing_key_fails_loudly(): void
    {
        config()->set('geocoding.opencage.key', '');
        Http::fake();

        $this->expectException(RuntimeException::class);
        (new OpenCageProvider())->reverse(0.51726, 35.31139);
    }
}
