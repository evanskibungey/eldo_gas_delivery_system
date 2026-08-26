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

    // ── In-app deletion (DELETE /api/v1/profile) ────────────────────────────
    // Google Play requires this alongside the web form above. Both paths run
    // the same AccountDeletionService, and these assert that they agree.

    public function test_in_app_deletion_purges_pii_and_revokes_every_token(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '+254712345678',
            'name' => 'Jane Doe',
            'is_active' => true,
        ]);
        $token = $customer->createToken('mobile')->plainTextToken;
        // A second device, to prove deletion is not limited to the caller.
        $customer->createToken('tablet');

        $this->assertDatabaseCount('personal_access_tokens', 2);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/profile')
            ->assertOk()
            ->assertJson(['message' => 'Account deleted.']);

        $customer->refresh();
        $this->assertSame('', $customer->name);
        $this->assertStringStartsWith('deleted_', $customer->phone);
        $this->assertFalse((bool) $customer->is_active);
        $this->assertNull($customer->phone_verified_at);
        $this->assertSame(0, (int) $customer->gaspoints_balance);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_in_app_deletion_requires_authentication(): void
    {
        $this->deleteJson('/api/v1/profile')->assertUnauthorized();

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_deleted_token_cannot_be_reused(): void
    {
        $customer = Customer::factory()->create(['phone' => '+254712345678']);
        $token = $customer->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/profile')
            ->assertOk();

        // The guard caches its resolved user for the lifetime of the app
        // instance, which a test reuses across requests but production never
        // does. Without this the stale customer answers the second call and
        // the assertion below passes (403, inactive) for the wrong reason.
        $this->app['auth']->forgetGuards();

        // The purge revoked the very token that authorised it, so the app
        // cannot keep making calls while it finishes signing itself out.
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/profile')
            ->assertUnauthorized();
    }

    public function test_in_app_deletion_frees_the_phone_number_for_signup(): void
    {
        $phone = '+254712345678';
        $customer = Customer::factory()->create(['phone' => $phone]);
        $token = $customer->createToken('mobile')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/profile')
            ->assertOk();

        // Anonymising rather than deleting the row keeps retained orders
        // referentially valid, but it must not lock the number out of a
        // fresh signup afterwards.
        $this->assertDatabaseMissing('customers', ['phone' => $phone]);

        $this->postJson('/api/v1/auth/request-otp', ['phone' => $phone])
            ->assertSuccessful();
    }
}
