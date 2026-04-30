<?php

namespace Tests\Unit;

use App\Models\Customer;
use App\Models\OtpToken;
use App\Services\Customer\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OtpServiceTest extends TestCase
{
    use RefreshDatabase;

    private OtpService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OtpService::class);
    }

    public function test_generate_creates_otp_token(): void
    {
        $otp = $this->service->generate('+254711000000');

        $this->assertNotNull($otp->token);
        $this->assertEquals(4, strlen($otp->token));
        $this->assertDatabaseHas('otp_tokens', ['phone' => '+254711000000']);
    }

    public function test_generate_deletes_previous_unused_tokens(): void
    {
        OtpToken::factory()->create(['phone' => '+254711000000', 'token' => '1111']);
        OtpToken::factory()->create(['phone' => '+254711000000', 'token' => '2222']);

        $this->service->generate('+254711000000');

        // Old unused tokens should be gone
        $this->assertDatabaseMissing('otp_tokens', ['phone' => '+254711000000', 'token' => '1111']);
        $this->assertDatabaseMissing('otp_tokens', ['phone' => '+254711000000', 'token' => '2222']);
    }

    public function test_verify_returns_customer_for_valid_token(): void
    {
        OtpToken::factory()->create(['phone' => '+254711000000', 'token' => '4321']);

        $customer = $this->service->verify('+254711000000', '4321');

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('+254711000000', $customer->phone);
    }

    public function test_verify_marks_token_as_used(): void
    {
        $otp = OtpToken::factory()->create(['phone' => '+254711000000', 'token' => '4321']);

        $this->service->verify('+254711000000', '4321');

        $this->assertNotNull($otp->fresh()->used_at);
    }

    public function test_verify_throws_for_wrong_token(): void
    {
        OtpToken::factory()->create(['phone' => '+254711000000', 'token' => '4321']);

        $this->expectException(ValidationException::class);

        $this->service->verify('+254711000000', '0000');
    }

    public function test_verify_throws_for_expired_token(): void
    {
        OtpToken::factory()->expired()->create(['phone' => '+254711000000', 'token' => '4321']);

        $this->expectException(ValidationException::class);

        $this->service->verify('+254711000000', '4321');
    }

    public function test_verify_creates_customer_on_first_login(): void
    {
        $phone = '+254799111222';
        OtpToken::factory()->create(['phone' => $phone, 'token' => '5555']);

        $this->assertDatabaseMissing('customers', ['phone' => $phone]);

        $this->service->verify($phone, '5555');

        $this->assertDatabaseHas('customers', ['phone' => $phone]);
    }
}
