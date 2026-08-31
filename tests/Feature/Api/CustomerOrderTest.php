<?php

namespace Tests\Feature\Api;

use App\Models\AddonGroup;
use App\Models\AddonItem;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CylinderPrice;
use App\Models\CylinderSize;
use App\Models\GasBrand;
use App\Models\Order;
use App\Models\Rider;
use App\Models\StockLevel;
use App\Models\SystemSetting;
use Carbon\Carbon;
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
        $this->token = $this->customer->createToken('mobile')->plainTextToken;
    }

    private function authed(): static
    {
        return $this->withToken($this->token);
    }

    private function createOrderingPrerequisites(): array
    {
        $size = CylinderSize::factory()->create();
        $brand = GasBrand::factory()->create();
        $address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);

        CylinderPrice::factory()->create([
            'size_id' => $size->id,
            'gas_refill_price' => 1800,
            'delivery_fee' => 200,
        ]);
        StockLevel::factory()->create(['size_id' => $size->id, 'filled_count' => 10]);
        $size->brands()->attach($brand->id);

        return compact('size', 'brand', 'address');
    }

    private function payload(CylinderSize $size, GasBrand $brand, CustomerAddress $address, array $overrides = []): array
    {
        return array_merge([
            'order_type' => 'swap',
            'size_id' => $size->id,
            'brand_id' => $brand->id,
            'address_id' => $address->id,
            'payment_method' => 'cash',
        ], $overrides);
    }

    public function test_customer_can_list_own_orders(): void
    {
        Order::factory()->count(3)->create(['customer_id' => $this->customer->id]);
        Order::factory()->create();

        $this->authed()->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    public function test_order_history_includes_reorder_and_payment_flags(): void
    {
        $delivered = Order::factory()->delivered()->create([
            'customer_id' => $this->customer->id,
            'payment_status' => 'collected',
        ]);
        $pending = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $this->authed()->getJson('/api/v1/orders')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $delivered->id,
                'can_reorder' => true,
                'payment_status' => 'collected',
            ])
            ->assertJsonFragment([
                'id' => $pending->id,
                'can_reorder' => false,
                'payment_status' => 'pending',
            ]);
    }

    public function test_customer_can_view_own_order(): void
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'payment_status' => 'disputed',
        ]);

        $this->authed()->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('id', $order->id)
            ->assertJsonPath('payment_status', 'disputed');
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $order = Order::factory()->create();

        $this->authed()->getJson("/api/v1/orders/{$order->id}")->assertNotFound();
    }

    public function test_customer_can_place_order(): void
    {
        ['size' => $size, 'brand' => $brand, 'address' => $address] = $this->createOrderingPrerequisites();

        $response = $this->authed()->postJson('/api/v1/orders', $this->payload($size, $brand, $address));

        $response->assertCreated()->assertJsonStructure(['order_number', 'total_amount', 'payment_status']);
        $this->assertDatabaseHas('orders', ['customer_id' => $this->customer->id, 'order_type' => 'swap']);
    }

    public function test_customer_cannot_place_order_when_shop_is_closed(): void
    {
        SystemSetting::set('shop_open_time', '07:00');
        SystemSetting::set('shop_close_time', '21:00');
        $this->travelTo(Carbon::create(2026, 1, 1, 22, 0, 0, config('app.timezone')));

        ['size' => $size, 'brand' => $brand, 'address' => $address] = $this->createOrderingPrerequisites();

        $this->authed()->postJson('/api/v1/orders', $this->payload($size, $brand, $address))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Shop is closed right now.')
            ->assertJsonPath('shop_status.open', false);

        $this->assertDatabaseCount('orders', 0);
        $this->travelBack();
    }

    public function test_place_order_is_idempotent_for_same_key(): void
    {
        ['size' => $size, 'brand' => $brand, 'address' => $address] = $this->createOrderingPrerequisites();

        $payload = $this->payload($size, $brand, $address);
        $headers = ['Idempotency-Key' => 'order-test-key-123'];

        $first = $this->authed()->withHeaders($headers)->postJson('/api/v1/orders', $payload);
        $second = $this->authed()->withHeaders($headers)->postJson('/api/v1/orders', $payload);

        $first->assertCreated();
        $second->assertCreated();
        $this->assertSame($first->json('order_id'), $second->json('order_id'));
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_place_order_rejects_redemption_points_outside_configured_tiers(): void
    {
        ['size' => $size, 'brand' => $brand, 'address' => $address] = $this->createOrderingPrerequisites();

        $this->authed()->postJson('/api/v1/orders', $this->payload($size, $brand, $address, [
            'redemption_points' => 999,
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.redemption_points.0', 'Invalid redemption amount.');
    }

    public function test_place_order_rejects_multiple_addons_from_single_select_group(): void
    {
        ['size' => $size, 'brand' => $brand, 'address' => $address] = $this->createOrderingPrerequisites();

        $group = AddonGroup::create([
            'size_id' => $size->id,
            'name' => 'Accessories',
            'selection_type' => 'single',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $itemA = AddonItem::create([
            'group_id' => $group->id,
            'name' => 'Valve A',
            'price' => 100,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $itemB = AddonItem::create([
            'group_id' => $group->id,
            'name' => 'Valve B',
            'price' => 120,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->authed()->postJson('/api/v1/orders', $this->payload($size, $brand, $address, [
            'addon_ids' => [$itemA->id, $itemB->id],
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.addon_ids.0', 'Only one item can be selected from Accessories.');
    }

    public function test_order_fails_when_out_of_stock(): void
    {
        $size = CylinderSize::factory()->create();
        $brand = GasBrand::factory()->create();
        $address = CustomerAddress::factory()->create(['customer_id' => $this->customer->id]);

        CylinderPrice::factory()->create(['size_id' => $size->id]);
        StockLevel::factory()->outOfStock()->create(['size_id' => $size->id]);
        $size->brands()->attach($brand->id);

        $this->authed()->postJson('/api/v1/orders', $this->payload($size, $brand, $address))->assertUnprocessable();
    }

    public function test_customer_cannot_use_another_customers_address(): void
    {
        $size = CylinderSize::factory()->create();
        $brand = GasBrand::factory()->create();
        $otherAddress = CustomerAddress::factory()->create();

        CylinderPrice::factory()->create(['size_id' => $size->id]);
        StockLevel::factory()->create(['size_id' => $size->id, 'filled_count' => 10]);
        $size->brands()->attach($brand->id);

        $this->authed()->postJson('/api/v1/orders', $this->payload($size, $brand, $otherAddress))->assertUnprocessable();
    }

    public function test_customer_can_report_rider_no_show(): void
    {
        $rider = Rider::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'rider_id' => $rider->id,
            'status' => 'rider_assigned',
        ]);

        $this->authed()->postJson("/api/v1/orders/{$order->id}/report-issue", [
            'issue_type' => 'rider_no_show',
        ])->assertCreated();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'has_issue' => true,
            'issue_type' => 'rider_no_show',
        ]);
    }

    public function test_customer_can_cancel_pending_order(): void
    {
        $order = Order::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'pending',
        ]);

        $this->authed()->postJson("/api/v1/orders/{$order->id}/cancel", ['reason' => 'Changed my mind'])->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    }

    public function test_customer_cannot_cancel_delivered_order(): void
    {
        $order = Order::factory()->delivered()->create(['customer_id' => $this->customer->id]);

        $this->authed()->postJson("/api/v1/orders/{$order->id}/cancel")->assertUnprocessable();
    }

    public function test_customer_cannot_cancel_another_customers_order(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);

        $this->authed()->postJson("/api/v1/orders/{$order->id}/cancel")->assertNotFound();
    }

    // ── service area ───────────────────────────────────────────────────────────

    public function test_order_outside_the_service_area_is_rejected(): void
    {
        ['size' => $size, 'brand' => $brand] = $this->createOrderingPrerequisites();
        $farAway = CustomerAddress::factory()->outsideServiceArea()->create([
            'customer_id' => $this->customer->id,
        ]);

        $this->authed()
            ->postJson('/api/v1/orders', $this->payload($size, $brand, $farAway))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['address_id']);

        // Nothing may be created: auto-assignment is radius-gated, so an order
        // out here would sit at pending forever with no rider ever matching.
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_inside_the_service_area_is_accepted(): void
    {
        ['size' => $size, 'brand' => $brand] = $this->createOrderingPrerequisites();
        $nearby = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'latitude' => 0.5200,
            'longitude' => 35.2800,
        ]);

        $this->authed()
            ->postJson('/api/v1/orders', $this->payload($size, $brand, $nearby))
            ->assertCreated();
    }

    public function test_service_area_radius_can_be_widened_at_runtime(): void
    {
        ['size' => $size, 'brand' => $brand] = $this->createOrderingPrerequisites();
        // ~55 km north of Eldoret: outside the default 25 km, inside 100 km.
        $address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'latitude' => 1.0100,
            'longitude' => 35.2698,
        ]);

        $this->authed()
            ->postJson('/api/v1/orders', $this->payload($size, $brand, $address))
            ->assertUnprocessable();

        SystemSetting::set('service_area_radius_km', 100);

        $this->authed()
            ->postJson('/api/v1/orders', $this->payload($size, $brand, $address))
            ->assertCreated();
    }

    public function test_the_address_text_is_snapshotted_onto_the_order(): void
    {
        ['size' => $size, 'brand' => $brand] = $this->createOrderingPrerequisites();
        $address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'label' => 'Home',
            'description' => 'Kapsoya, opposite Zion Mall',
        ]);

        $this->authed()
            ->postJson('/api/v1/orders', $this->payload($size, $brand, $address))
            ->assertCreated();

        // The rider used to get a pin and nothing else; the landmark the
        // customer typed never left customer_addresses.
        $this->assertDatabaseHas('orders', [
            'customer_id' => $this->customer->id,
            'delivery_label' => 'Home',
            'delivery_address' => 'Kapsoya, opposite Zion Mall',
        ]);
    }

    public function test_editing_an_address_does_not_rewrite_a_past_order(): void
    {
        ['size' => $size, 'brand' => $brand] = $this->createOrderingPrerequisites();
        $address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'label' => 'Home',
            'description' => 'Kapsoya, opposite Zion Mall',
        ]);

        $this->authed()
            ->postJson('/api/v1/orders', $this->payload($size, $brand, $address))
            ->assertCreated();

        $this->authed()->putJson("/api/v1/addresses/{$address->id}", [
            'label' => 'Office',
            'latitude' => 0.5300,
            'longitude' => 35.2900,
            'description' => 'Somewhere else entirely',
        ])->assertOk();

        // Snapshotted, not joined — where a past order went is history.
        $this->assertDatabaseHas('orders', [
            'delivery_label' => 'Home',
            'delivery_address' => 'Kapsoya, opposite Zion Mall',
        ]);
    }

    public function test_the_customer_can_read_back_where_an_order_is_going(): void
    {
        ['size' => $size, 'brand' => $brand] = $this->createOrderingPrerequisites();
        $address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
            'label' => 'Home',
            'description' => 'Kapsoya, opposite Zion Mall',
        ]);

        $response = $this->authed()
            ->postJson('/api/v1/orders', $this->payload($size, $brand, $address))
            ->assertCreated();

        $this->authed()
            ->getJson('/api/v1/orders/' . $response->json('order_id'))
            ->assertOk()
            ->assertJsonPath('delivery_label', 'Home')
            ->assertJsonPath('delivery_address', 'Kapsoya, opposite Zion Mall');
    }
}
