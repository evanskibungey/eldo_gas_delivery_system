<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\CylinderSize;
use App\Models\GasBrand;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An order is a basket, and every admin surface that names "the cylinder" is
 * naming whichever one happened to lead it.
 *
 * The detail page is the packing list a rider loads from, and the orders
 * report is what the shop reads its volumes off. Both used to see one line of
 * a three-line order.
 */
class OrderBasketVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private CylinderSize $six;
    private CylinderSize $thirteen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
        $this->six = CylinderSize::factory()->create(['name' => '6kg']);
        $this->thirteen = CylinderSize::factory()->create(['name' => '13kg']);
    }

    private function actingAsAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /** An order whose lead cylinder is the 13kg, with a 6kg behind it. */
    private function basket(): Order
    {
        $order = Order::factory()->create([
            'size_id' => $this->thirteen->id,
            'status' => 'pending',
        ]);
        $brand = GasBrand::factory()->create(['name' => 'ProGas']);

        OrderItem::factory()->for($order)->create([
            'size_id' => $this->thirteen->id,
            'brand_id' => $brand->id,
            'order_type' => 'swap',
            'quantity' => 2,
            'line_total' => 6400,
        ]);
        OrderItem::factory()->for($order)->create([
            'size_id' => $this->six->id,
            'brand_id' => $brand->id,
            'order_type' => 'swap',
            'quantity' => 1,
            'line_total' => 1350,
        ]);

        return $order->fresh();
    }

    public function test_the_detail_page_carries_every_line_the_rider_loads(): void
    {
        $order = $this->basket();

        $this->actingAsAdmin()
            ->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->has('order.items', 2)
                    ->where('order.cylinder_count', 3)
                    // Each line prices itself. The page shows a per-line
                    // total, and a line with no price of its own would have
                    // to borrow the order's.
                    ->where('order.items.0.quantity', 2)
                    ->where('order.items.0.line_total', 6400)
                    ->where('order.items.1.size_name', '6kg')
            );
    }

    public function test_a_basket_is_found_by_a_size_that_is_not_its_first(): void
    {
        $basket = $this->basket();

        $response = $this->actingAsAdmin()
            ->get('/admin/reports/orders?size_id=' . $this->six->id)
            ->assertOk();

        // The order's own size_id is the 13kg. Filtering on that column alone
        // hid this basket from every 6kg total the shop reads.
        $response->assertInertia(
            fn ($page) => $page
                ->has('orders.data', 1)
                ->where('orders.data.0.id', $basket->id)
        );
    }

    public function test_an_order_without_that_size_is_still_excluded(): void
    {
        $this->basket();

        $other = CylinderSize::factory()->create(['name' => '50kg']);

        $this->actingAsAdmin()
            ->get('/admin/reports/orders?size_id=' . $other->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('orders.data', 0));
    }

    public function test_an_order_predating_order_items_is_still_findable(): void
    {
        // Its cylinder lives in the column, not in a row.
        $legacy = Order::factory()->create(['size_id' => $this->six->id]);

        $this->actingAsAdmin()
            ->get('/admin/reports/orders?size_id=' . $this->six->id)
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->has('orders.data', 1)
                    ->where('orders.data.0.id', $legacy->id)
            );
    }

    public function test_the_export_lists_the_basket_in_its_own_column(): void
    {
        $this->basket();

        $csv = $this->actingAsAdmin()
            ->get('/admin/reports/orders/export')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Items,Cylinders', $csv);
        $this->assertStringContainsString('13kg', $csv);
        $this->assertStringContainsString('6kg', $csv);
        // Quoted, because a summary of two cylinders contains a comma and an
        // unquoted one would shift every column after it.
        $this->assertStringContainsString('"13kg · ProGas ×2, 6kg · ProGas"', $csv);
    }
}
