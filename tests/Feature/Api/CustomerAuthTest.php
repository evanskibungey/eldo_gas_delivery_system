<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\OtpToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_otp_accepts_valid_phone(): void
    {
        $response = $this->postJson('/api/v1/auth/request-otp', ['phone' => '0712345678']);

        $response->assertOk()->assertJson(['message' => 'OTP sent.']);
        $this->assertDatabaseHas('otp_tokens', ['phone' => '+254712345678']);
    }

    public function test_request_otp_normalises_phone_with_plus254(): void
    {
        $this->postJson('/api/v1/auth/request-otp', ['phone' => '+254712345678'])->assertOk();

        $this->assertDatabaseHas('otp_tokens', ['phone' => '+254712345678']);
    }

    public function test_request_otp_requires_phone(): void
    {
        $this->postJson('/api/v1/auth/request-otp', [])->assertUnprocessable()->assertJsonValidationErrors(['phone']);
    }

    public function test_verify_otp_returns_token_for_valid_code(): void
    {
        $phone = '+254712345678';
        OtpToken::factory()->create(['phone' => $phone, 'token' => '1234']);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => $phone,
            'token' => '1234',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_at',
                'customer' => ['id', 'name', 'phone', 'gaspoints', 'referral_code', 'profile_complete', 'is_active'],
            ])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('customer.is_active', true);

        $this->assertNotNull($response->json('expires_at'));
        $this->assertDatabaseHas('customers', ['phone' => $phone]);
    }

    public function test_verify_otp_creates_customer_on_first_login(): void
    {
        $phone = '+254799999999';
        OtpToken::factory()->create(['phone' => $phone, 'token' => '5678']);

        $this->assertDatabaseMissing('customers', ['phone' => $phone]);

        $this->postJson('/api/v1/auth/verify-otp', ['phone' => $phone, 'token' => '5678'])->assertOk();

        $this->assertDatabaseHas('customers', ['phone' => $phone, 'is_active' => true]);
    }

    public function test_verify_otp_rejects_inactive_customer(): void
    {
        $phone = '+254711000111';
        Customer::factory()->create([
            'phone' => $phone,
            'is_active' => false,
        ]);
        OtpToken::factory()->create(['phone' => $phone, 'token' => '4321']);

        $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => $phone,
            'token' => '4321',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.phone.0', 'Your account is currently inactive. Please contact support.');
    }

    public function test_verify_otp_rejects_wrong_code(): void
    {
        $phone = '+254712345678';
        OtpToken::factory()->create(['phone' => $phone, 'token' => '1234']);

        $this->postJson('/api/v1/auth/verify-otp', ['phone' => $phone, 'token' => '0000'])->assertUnprocessable();
    }

    public function test_verify_otp_rejects_expired_code(): void
    {
        $phone = '+254712345678';
        OtpToken::factory()->expired()->create(['phone' => $phone, 'token' => '1234']);

        $this->postJson('/api/v1/auth/verify-otp', ['phone' => $phone, 'token' => '1234'])->assertUnprocessable();
    }

    public function test_verify_otp_rejects_already_used_code(): void
    {
        $phone = '+254712345678';
        OtpToken::factory()->used()->create(['phone' => $phone, 'token' => '1234']);

        $this->postJson('/api/v1/auth/verify-otp', ['phone' => $phone, 'token' => '1234'])->assertUnprocessable();
    }

    public function test_logout_revokes_current_token(): void
    {
        $customer = Customer::factory()->create();
        $token = $customer->createToken('mobile')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_all_revokes_all_customer_tokens(): void
    {
        $customer = Customer::factory()->create();
        $token = $customer->createToken('mobile')->plainTextToken;
        $customer->createToken('tablet');

        $this->withToken($token)->postJson('/api/v1/auth/logout-all')->assertOk()->assertJson([
            'message' => 'Logged out from all devices.',
        ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_inactive_customer_token_is_rejected_and_revoked(): void
    {
        $customer = Customer::factory()->create(['is_active' => false]);
        $token = $customer->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/orders')
            ->assertForbidden()
            ->assertJson(['message' => 'Your account is inactive.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_protected_endpoint_requires_token(): void
    {
        $this->getJson('/api/v1/orders')->assertUnauthorized();
    }

    public function test_protected_endpoint_rejects_invalid_token(): void
    {
        $this->withToken('invalid-token')->getJson('/api/v1/orders')->assertUnauthorized();
    }
}