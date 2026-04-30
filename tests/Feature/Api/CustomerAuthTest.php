<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\OtpToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    // ── request-otp ────────────────────────────────────────────────────────────

    public function test_request_otp_accepts_valid_phone(): void
    {
        $response = $this->postJson('/api/v1/auth/request-otp', ['phone' => '0712345678']);

        $response->assertOk()->assertJson(['message' => 'OTP sent.']);
        $this->assertDatabaseHas('otp_tokens', ['phone' => '+254712345678']);
    }

    public function test_request_otp_normalises_phone_with_plus254(): void
    {
        $this->postJson('/api/v1/auth/request-otp', ['phone' => '+254712345678'])
            ->assertOk();

        $this->assertDatabaseHas('otp_tokens', ['phone' => '+254712345678']);
    }

    public function test_request_otp_requires_phone(): void
    {
        $this->postJson('/api/v1/auth/request-otp', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    // ── verify-otp ─────────────────────────────────────────────────────────────

    public function test_verify_otp_returns_token_for_valid_code(): void
    {
        $phone = '+254712345678';

        OtpToken::factory()->create(['phone' => $phone, 'token' => '1234']);

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => $phone,
            'token' => '1234',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'customer'])
            ->assertJsonPath('token_type', 'Bearer');

        $this->assertDatabaseHas('customers', ['phone' => $phone]);
    }

    public function test_verify_otp_creates_customer_on_first_login(): void
    {
        $phone = '+254799999999';
        OtpToken::factory()->create(['phone' => $phone, 'token' => '5678']);

        $this->assertDatabaseMissing('customers', ['phone' => $phone]);

        $this->postJson('/api/v1/auth/verify-otp', ['phone' => $phone, 'token' => '5678'])
            ->assertOk();

        $this->assertDatabaseHas('customers', ['phone' => $phone]);
    }

    public function test_verify_otp_rejects_wrong_code(): void
    {
        $phone = '+254712345678';
        OtpToken::factory()->create(['phone' => $phone, 'token' => '1234']);

        $this->postJson('/api/v1/auth/verify-otp', ['phone' => $phone, 'token' => '0000'])
            ->assertUnprocessable();
    }

    public function test_verify_otp_rejects_expired_code(): void
    {
        $phone = '+254712345678';
        OtpToken::factory()->expired()->create(['phone' => $phone, 'token' => '1234']);

        $this->postJson('/api/v1/auth/verify-otp', ['phone' => $phone, 'token' => '1234'])
            ->assertUnprocessable();
    }

    public function test_verify_otp_rejects_already_used_code(): void
    {
        $phone = '+254712345678';
        OtpToken::factory()->used()->create(['phone' => $phone, 'token' => '1234']);

        $this->postJson('/api/v1/auth/verify-otp', ['phone' => $phone, 'token' => '1234'])
            ->assertUnprocessable();
    }

    // ── logout ─────────────────────────────────────────────────────────────────

    public function test_logout_revokes_token(): void
    {
        $customer = Customer::factory()->create();
        $token    = $customer->createToken('mobile')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        // Token row should be deleted from the database
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    // ── auth guard ─────────────────────────────────────────────────────────────

    public function test_protected_endpoint_requires_token(): void
    {
        $this->getJson('/api/v1/orders')->assertUnauthorized();
    }

    public function test_protected_endpoint_rejects_invalid_token(): void
    {
        $this->withToken('invalid-token')
            ->getJson('/api/v1/orders')
            ->assertUnauthorized();
    }
}
