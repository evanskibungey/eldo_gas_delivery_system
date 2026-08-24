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

    // ── address updates ────────────────────────────────────────────────────────

    public function test_set_as_default_sends_only_the_changed_field(): void
    {
        $other = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'is_default'  => true,
        ]);
        $address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'latitude'    => 0.51430000,
            'longitude'   => 35.26980000,
            'is_default'  => false,
        ]);

        // This is exactly what UpdateAddressDto(isDefault: true) now serialises to.
        $this->withToken($this->token)
            ->putJson("/api/v1/addresses/{$address->id}", ['is_default' => true])
            ->assertOk();

        $this->assertDatabaseHas('customer_addresses', ['id' => $address->id, 'is_default' => true]);
        $this->assertDatabaseHas('customer_addresses', ['id' => $other->id, 'is_default' => false]);

        // The coordinates must survive a partial update untouched.
        $fresh = $address->fresh();
        $this->assertEquals(0.51430000, (float) $fresh->latitude);
        $this->assertEquals(35.26980000, (float) $fresh->longitude);
    }

    public function test_a_null_coordinate_can_never_blank_out_a_delivery_pin(): void
    {
        $address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'latitude'    => 0.51430000,
            'longitude'   => 35.26980000,
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v1/addresses/{$address->id}", [
                'label'     => null,
                'latitude'  => null,
                'longitude' => null,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['label', 'latitude', 'longitude']);

        $fresh = $address->fresh();
        $this->assertEquals(0.51430000, (float) $fresh->latitude);
        $this->assertEquals(35.26980000, (float) $fresh->longitude);
    }

    public function test_latitude_and_longitude_must_move_together(): void
    {
        $address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);

        $this->withToken($this->token)
            ->putJson("/api/v1/addresses/{$address->id}", ['latitude' => 0.6])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['longitude']);
    }

    public function test_can_move_an_address_to_new_coordinates(): void
    {
        $address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'latitude'    => 0.51430000,
            'longitude'   => 35.26980000,
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v1/addresses/{$address->id}", [
                'label'       => 'Office',
                'latitude'    => 0.52210000,
                'longitude'   => 35.28150000,
                'description' => 'Zion Mall, Uganda Road',
            ])
            ->assertOk();

        $fresh = $address->fresh();
        $this->assertSame('Office', $fresh->label);
        $this->assertEquals(0.52210000, (float) $fresh->latitude);
        $this->assertEquals(35.28150000, (float) $fresh->longitude);
        $this->assertSame('Zion Mall, Uganda Road', $fresh->description);
    }

    public function test_an_empty_landmark_is_stored_as_no_landmark(): void
    {
        $address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'description' => 'Old landmark',
        ]);

        $this->withToken($this->token)
            ->putJson("/api/v1/addresses/{$address->id}", ['description' => '   '])
            ->assertOk();

        $this->assertNull($address->fresh()->description);
    }

    public function test_cannot_update_another_customers_address(): void
    {
        $address = CustomerAddress::factory()->create();

        $this->withToken($this->token)
            ->putJson("/api/v1/addresses/{$address->id}", ['is_default' => true])
            ->assertNotFound();
    }
}
