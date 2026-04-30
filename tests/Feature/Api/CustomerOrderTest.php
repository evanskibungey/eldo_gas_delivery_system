<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CylinderPrice;
use App\Models\CylinderSize;
use App\Models\GasBrand;
use App\Models\Order;
use App\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerOrderTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::factory()->create();
        $this->token    = $this->customer->createToken('mobile')->plainTextToken;
    }

    private function authed(): static
    {
        return $this->withToken($this->token);
    }

    // ── index ──────────────────────────────────────────────────────────────────

    public function test_customer_can_list_own_orders(): void
    {
        Order::factory()->count(3)->create(['customer_id' => $this->customer->id]);
        Order::factory()->create(); // another customer's order

        $this->authed()->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('total', 3);
    }

    // ── show ───────────────────────────────────────────────────────────────────

    public function test_customer_can_view_own_order(): void
    {
        $order = Order::factory()->create(['customer_id' => $this->customer->id]);

        $this->authed()->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('id', $order->id);
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $order = Order::factory()->create(); // different customer

        $this->authed()->getJson("/api/v1/orders/{$order->id}")
            ->assertNotFound();
    }

    // ── store ──────────────────────────────────────────────────────────────────

    public function test_customer_can_place_order(): void
    {
        $size    = CylinderSize::factory()->create();
        $brand   = GasBrand::factory()->create();
        $address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);

        CylinderPrice::factory()->create([
            'size_id'          => $size->id,
            'gas_refill_price' => 1800,
            'delivery_fee'     => 200,
        ]);

        StockLevel::factory()->create(['size_id' => $size->id, 'filled_count' => 10]);

        $size->brands()->attach($brand->id);

        $response = $this->authed()->postJson('/api/v1/orders', [
            'order_type'     => 'swap',
            'size_id'        => $size->id,
            'brand_id'       => $brand->id,
            'address_id'     => $address->id,
            'payment_method' => 'cash',
        ]);

        $response->assertCreated()->assertJsonStructure(['order_number', 'total_amount']);
        $this->assertDatabaseHas('orders', ['customer_id' => $this->customer->id, 'order_type' => 'swap']);
    }

    public function test_order_fails_when_out_of_stock(): void
    {
        $size    = CylinderSize::factory()->create();
        $brand   = GasBrand::factory()->create();
        $address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);

        CylinderPrice::factory()->create(['size_id' => $size->id]);
        StockLevel::factory()->outOfStock()->create(['size_id' => $size->id]);
        $size->brands()->attach($brand->id);

        $this->authed()->postJson('/api/v1/orders', [
            'order_type'     => 'swap',
            'size_id'        => $size->id,
            'brand_id'       => $brand->id,
            'address_id'     => $address->id,
            'payment_method' => 'cash',
        ])->assertUnprocessable();
    }

    public function test_customer_cannot_use_another_customers_address(): void
    {
        $size         = CylinderSize::factory()->create();
        $brand        = GasBrand::factory()->create();
        $otherAddress = CustomerAddress::factory()->create(); // different customer

        CylinderPrice::factory()->create(['size_id' => $size->id]);
        StockLevel::factory()->create(['size_id' => $size->id, 'filled_count' => 10]);

        $this->authed()->postJson('/api/v1/orders', [
            'order_type'     => 'swap',
            'size_id'        => $size->id,
            'brand_id'       => $brand->id,
            'address_id'     => $otherAddress->id,
            'payment_method' => 'cash',
        ])->assertUnprocessable();
    }

    // ── cancel ─────────────────────────────────────────────────────────────────

    public function test_customer_can_cancel_pending_order(): void
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'status'      => 'pending',
        ]);

        $this->authed()->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Changed my mind'])
            ->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    }

    public function test_customer_cannot_cancel_delivered_order(): void
    {
        $order = Order::factory()->delivered()->create(['customer_id' => $this->customer->id]);

        $this->authed()->postJson("/api/v1/orders/{$order->id}/cancel")
            ->assertUnprocessable();
    }

    public function test_customer_cannot_cancel_another_customers_order(): void
    {
        $order = Order::factory()->create(['status' => 'pending']); // different customer

        $this->authed()->postJson("/api/v1/orders/{$order->id}/cancel")
            ->assertNotFound();
    }
}
