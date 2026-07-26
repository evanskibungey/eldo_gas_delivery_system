<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\OtpToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletion_page_loads(): void
    {
        $this->get('/account-deletion')
            ->assertOk()
            ->assertSee('Delete your EldoGas account');
    }

    public function test_valid_code_purges_pii_and_deactivates_account(): void
    {
        $phone = '+254712345678';
        $customer = Customer::factory()->create([
            'phone' => $phone,
            'name' => 'Jane Doe',
            'is_active' => true,
        ]);
        $customer->createToken('mobile');
        OtpToken::factory()->create(['phone' => $phone, 'token' => '1234']);

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->post('/account-deletion', ['phone' => $phone, 'token' => '1234'])
            ->assertRedirect(route('account-deletion'))
            ->assertSessionHas('deleted', true);

        $customer->refresh();
        $this->assertSame('', $customer->name);
        $this->assertStringStartsWith('deleted_', $customer->phone);
        $this->assertFalse((bool) $customer->is_active);
        $this->assertNull($customer->phone_verified_at);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_wrong_code_leaves_account_untouched(): void
    {
        $phone = '+254712345678';
        Customer::factory()->create(['phone' => $phone, 'name' => 'Jane Doe']);
        OtpToken::factory()->create(['phone' => $phone, 'token' => '1234']);

        $this->post('/account-deletion', ['phone' => $phone, 'token' => '0000'])
            ->assertRedirect(route('account-deletion'))
            ->assertSessionHasErrors('token');

        $this->assertDatabaseHas('customers', ['phone' => $phone, 'name' => 'Jane Doe']);
    }

    public function test_deletion_requires_existing_account(): void
    {
        // Valid-looking token row but no customer → still rejected, no crash.
        OtpToken::factory()->create(['phone' => '+254700111222', 'token' => '1234']);

        $this->post('/account-deletion', ['phone' => '+254700111222', 'token' => '1234'])
            ->assertSessionHasErrors('token');
    }
}
