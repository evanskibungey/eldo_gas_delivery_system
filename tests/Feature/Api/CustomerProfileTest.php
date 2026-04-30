<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\CustomerAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProfileTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::factory()->create(['name' => 'Jane Doe']);
        $this->token    = $this->customer->createToken('mobile')->plainTextToken;
    }

    // ── profile ────────────────────────────────────────────────────────────────

    public function test_can_fetch_profile(): void
    {
        $this->withToken($this->token)->getJson('/api/v1/profile')
            ->assertOk()
            ->assertJsonPath('name', 'Jane Doe')
            ->assertJsonStructure(['id', 'name', 'phone', 'gaspoints', 'referral_code']);
    }

    public function test_can_update_name(): void
    {
        $this->withToken($this->token)->putJson('/api/v1/profile', ['name' => 'Jane Updated'])
            ->assertOk();

        $this->assertDatabaseHas('customers', ['id' => $this->customer->id, 'name' => 'Jane Updated']);
    }

    public function test_name_update_requires_non_empty_string(): void
    {
        $this->withToken($this->token)->putJson('/api/v1/profile', ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    // ── addresses ──────────────────────────────────────────────────────────────

    public function test_can_list_addresses(): void
    {
        CustomerAddress::factory()->count(2)->create(['customer_id' => $this->customer->id]);
        CustomerAddress::factory()->create(); // another customer

        $this->withToken($this->token)->getJson('/api/v1/addresses')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_can_create_address(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/addresses', [
            'label'       => 'Home',
            'latitude'    => -0.2833,
            'longitude'   => 35.2697,
            'description' => 'My house',
            'is_default'  => true,
        ])->assertCreated();

        $this->assertDatabaseHas('customer_addresses', [
            'customer_id' => $this->customer->id,
            'label'       => 'Home',
            'is_default'  => true,
        ]);
    }

    public function test_creating_default_address_clears_previous_default(): void
    {
        $old = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_default'  => true,
        ]);

        $this->withToken($this->token)->postJson('/api/v1/addresses', [
            'label'      => 'Office',
            'latitude'   => -0.28,
            'longitude'  => 35.27,
            'is_default' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('customer_addresses', ['id' => $old->id, 'is_default' => false]);
    }

    public function test_can_delete_own_address(): void
    {
        $address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);

        $this->withToken($this->token)->deleteJson("/api/v1/addresses/{$address->id}")
            ->assertOk();

        $this->assertDatabaseMissing('customer_addresses', ['id' => $address->id]);
    }

    public function test_cannot_delete_another_customers_address(): void
    {
        $address = CustomerAddress::factory()->create();

        $this->withToken($this->token)->deleteJson("/api/v1/addresses/{$address->id}")
            ->assertNotFound();
    }
}
