<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Order;
use App\Models\Rider;
use App\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::factory()->create();
    }

    private function actingAsAdmin(): static
    {
        return $this->actingAs($this->admin, 'admin');
    }

    public function test_admin_can_view_orders_list(): void
    {
        Order::factory()->count(3)->create();

        $this->actingAsAdmin()
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Orders/Index'));
    }

    public function test_guest_is_redirected_from_orders(): void
    {
        $this->get(route('admin.orders.index'))->assertRedirect();
    }

    public function test_admin_can_assign_rider_to_pending_order(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);
        $rider = Rider::factory()->create();

        $this->actingAsAdmin()
            ->post(route('admin.orders.assign', $order), ['rider_id' => $rider->id])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'rider_id' => $rider->id,
            'status' => 'rider_assigned',
        ]);
    }

    public function test_admin_can_update_order_status(): void
    {
        $order = Order::factory()->withRider()->create();

        $this->actingAsAdmin()
            ->post(route('admin.orders.status', $order), ['status' => 'picked_up'])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'picked_up']);
    }

    public function test_admin_can_cancel_order(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);

        $this->actingAsAdmin()
            ->post(route('admin.orders.cancel', $order), ['reason' => 'Test cancel'])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    }

    public function test_admin_cancelling_picked_up_order_does_not_restore_inventory_by_default(): void
    {
        $stock = StockLevel::factory()->create(['filled_count' => 3]);
        $order = Order::factory()->create([
            'size_id' => $stock->size_id,
            'status' => 'picked_up',
        ]);

        $this->actingAsAdmin()
            ->post(route('admin.orders.cancel', $order), ['reason' => 'Customer no longer available'])
            ->assertRedirect();

        $stock->refresh();

        $this->assertSame(3, $stock->filled_count);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    }

    public function test_admin_can_explicitly_restore_inventory_when_cancelling_picked_up_order(): void
    {
        $stock = StockLevel::factory()->create(['filled_count' => 3]);
        $order = Order::factory()->create([
            'size_id' => $stock->size_id,
            'status' => 'picked_up',
        ]);

        $this->actingAsAdmin()
            ->post(route('admin.orders.cancel', $order), [
                'reason' => 'Cylinder returned to shop',
                'inventory_returned' => true,
            ])
            ->assertRedirect();

        $stock->refresh();

        $this->assertSame(4, $stock->filled_count);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    }
}