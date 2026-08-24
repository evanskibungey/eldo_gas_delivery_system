<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rate limiting on the public API. The OTP controllers already limit per
 * phone number; these group throttles cover the case that limiter cannot
 * see — one client walking many different numbers.
 */
class ApiThrottleTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::factory()->create();
        $this->token = $this->customer->createToken('mobile')->plainTextToken;
    }

    public function test_otp_requests_are_capped_per_ip_across_different_phone_numbers(): void
    {
        // Each call uses a fresh number, so the controller's per-phone
        // limiter never fires — only the IP throttle can stop this.
        for ($i = 0; $i < 30; $i++) {
            $phone = '+2547' . str_pad((string) $i, 8, '0', STR_PAD_LEFT);

            $this->postJson('/api/v1/auth/request-otp', ['phone' => $phone])
                ->assertStatus(200);
        }

        $this->postJson('/api/v1/auth/request-otp', ['phone' => '+254799999999'])
            ->assertStatus(429);
    }

    public function test_rider_otp_requests_are_capped_per_ip(): void
    {
        // Unknown numbers are rejected 422 by the controller, but they still
        // consume the IP budget — otherwise enumeration would be free.
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/v1/rider/auth/request-otp', [
                'phone' => '+2547' . str_pad((string) $i, 8, '0', STR_PAD_LEFT),
            ]);
        }

        $this->postJson('/api/v1/rider/auth/request-otp', ['phone' => '+254799999999'])
            ->assertStatus(429);
    }

    public function test_authenticated_routes_are_throttled_per_customer_not_per_ip(): void
    {
        $first = $this->withToken($this->token)->getJson('/api/v1/profile')->assertOk();
        $second = $this->withToken($this->token)->getJson('/api/v1/profile')->assertOk();

        $this->assertSame('119', $first->headers->get('X-RateLimit-Remaining'));
        $this->assertSame('118', $second->headers->get('X-RateLimit-Remaining'));

        // A different customer from the same IP starts with a full budget.
        $other = Customer::factory()->create();
        $otherToken = $other->createToken('mobile')->plainTextToken;

        // The guard caches its resolved user for the lifetime of the
        // container, and the test container is shared across requests within
        // one test method — without this the second token would still be
        // attributed to the first customer.
        $this->app['auth']->forgetGuards();

        $this->withToken($otherToken)->getJson('/api/v1/profile')
            ->assertOk()
            ->assertHeader('X-RateLimit-Remaining', '119');
    }

    public function test_sos_has_its_own_tighter_budget(): void
    {
        $this->withToken($this->token)->getJson('/api/v1/profile')
            ->assertHeader('X-RateLimit-Limit', '120');

        // Separate bucket: spending the SOS allowance must not eat into the
        // general one, and vice versa.
        $this->withToken($this->token)->postJson('/api/v1/sos/trigger', [
            'lat' => -0.2833,
            'lng' => 35.2697,
        ])->assertOk()->assertHeader('X-RateLimit-Limit', '10');
    }

    public function test_sos_remains_available_well_beyond_any_genuine_emergency_need(): void
    {
        // A panic button must not be the request that gets refused, so the
        // cap sits far above real use — five in a row still succeed.
        for ($i = 0; $i < 5; $i++) {
            $this->withToken($this->token)->postJson('/api/v1/sos/trigger', [
                'lat' => -0.2833,
                'lng' => 35.2697,
            ])->assertOk();
        }
    }
}
