<?php

namespace Tests\Feature;

use App\Models\GasBrand;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The order_items table — phase one of turning an order from a cylinder into
 * a container of them.
 *
 * Nothing reads these rows yet. What these cover is that the shape holds and
 * that the legacy columns are untouched, which is what makes the later phases
 * safe to do one file at a time.
 */
class OrderItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_order_can_hold_several_different_cylinders(): void
    {
        $order = Order::factory()->create();
        $eldo = GasBrand::factory()->create(['name' => 'EldoGas']);
        $total = GasBrand::factory()->create(['name' => 'Total']);

        OrderItem::factory()->for($order)->create([
            'brand_id' => $eldo->id,
            'order_type' => 'swap',
            'quantity' => 2,
            'gas_price' => 3200,
            'cylinder_price' => 0,
            'line_total' => 6400,
        ]);
        OrderItem::factory()->for($order)->create([
            'brand_id' => $total->id,
            'order_type' => 'new_cylinder',
            'quantity' => 1,
            'gas_price' => 3400,
            'cylinder_price' => 4500,
            'line_total' => 7900,
        ]);

        $order->load('items');
        $this->assertCount(2, $order->items);
        $this->assertSame(3, $order->cylinderCount());
        $this->assertSame(14300, (int) $order->items->sum('line_total'));
    }

    public function test_the_same_configuration_cannot_be_added_twice(): void
    {
        $order = Order::factory()->create();
        $brand = GasBrand::factory()->create();

        $config = [
            'brand_id' => $brand->id,
            'order_type' => 'swap',
        ];

        $first = OrderItem::factory()->for($order)->create($config);

        // The merge rule is a database guarantee, not a UI convention: adding
        // the same brand, size and type again can only raise a quantity.
        $this->expectException(QueryException::class);
        OrderItem::factory()->for($order)->create(
            $config + ['size_id' => $first->size_id],
        );
    }

    public function test_a_different_brand_is_a_different_line(): void
    {
        $order = Order::factory()->create();
        $first = OrderItem::factory()->for($order)->create([
            'brand_id' => GasBrand::factory()->create(['name' => 'EldoGas'])->id,
            'order_type' => 'swap',
        ]);

        // Same cylinder, different brand — a separate row, because the unique
        // key differs. This is the EldoGas 13kg vs Total 13kg case.
        OrderItem::factory()->for($order)->create([
            'size_id' => $first->size_id,
            'brand_id' => GasBrand::factory()->create(['name' => 'Total'])->id,
            'order_type' => 'swap',
        ]);

        $this->assertCount(2, $order->refresh()->items);
    }

    public function test_line_total_is_per_unit_prices_times_quantity(): void
    {
        $item = OrderItem::factory()->create([
            'quantity' => 3,
            'gas_price' => 3200,
            'cylinder_price' => 0,
            'line_total' => 9600,
        ]);

        // Stored and computed must agree. They diverge only if something
        // wrote the column by hand.
        $this->assertSame(9600, $item->computedTotal());
        $this->assertSame($item->line_total, $item->computedTotal());
    }

    public function test_items_are_removed_with_their_order(): void
    {
        $order = Order::factory()->create();
        OrderItem::factory()->for($order)->count(2)->create();

        $order->delete();

        $this->assertSame(0, OrderItem::count());
    }

    public function test_a_label_reads_as_a_person_would_say_it(): void
    {
        $item = OrderItem::factory()->create(['quantity' => 2]);
        $item->load(['size', 'brand']);

        $this->assertStringEndsWith('×2', $item->label());

        // A single cylinder says nothing about quantity — "13kg · ProGas ×1"
        // is how a machine writes it, not a person.
        $single = OrderItem::factory()->create(['quantity' => 1]);
        $this->assertStringNotContainsString('×', $single->load(['size', 'brand'])->label());
    }

    public function test_the_legacy_columns_are_left_alone(): void
    {
        // Phase one adds a table and changes nothing else. Every read path in
        // the codebase still goes through these, and will until they are
        // moved across one at a time.
        $order = Order::factory()->create();

        $this->assertNotNull($order->size_id);
        $this->assertNotNull($order->gas_price);
        $this->assertContains($order->order_type, ['swap', 'new_cylinder', 'accessory']);
    }
}
