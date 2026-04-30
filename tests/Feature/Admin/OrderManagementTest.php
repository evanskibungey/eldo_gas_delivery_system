<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Order;
use App\Models\Rider;
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

    // ── index ──────────────────────────────────────────────────────────────────

    public function test_admin_can_view_orders_list(): void
    {
        Order::factory()->count(3)->create();

        $this->actingAsAdmin()
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Admin/Orders/Index'));
    }

    public function test_guest_is_redirected_from_orders(): void
    {
        $this->get(route('admin.orders.index'))
            ->assertRedirect();
    }

    // ── assign rider ───────────────────────────────────────────────────────────

    public function test_admin_can_assign_rider_to_pending_order(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);
        $rider = Rider::factory()->create();

        $this->actingAsAdmin()
            ->post(route('admin.orders.assign', $order), ['rider_id' => $rider->id])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id'       => $order->id,
            'rider_id' => $rider->id,
            'status'   => 'rider_assigned',
        ]);
    }

    // ── status update ──────────────────────────────────────────────────────────

    public function test_admin_can_update_order_status(): void
    {
        $order = Order::factory()->withRider()->create();

        $this->actingAsAdmin()
            ->post(route('admin.orders.status', $order), ['status' => 'picked_up'])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'picked_up']);
    }

    // ── cancel ─────────────────────────────────────────────────────────────────

    public function test_admin_can_cancel_order(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);

        $this->actingAsAdmin()
            ->post(route('admin.orders.cancel', $order), ['reason' => 'Test cancel'])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
    }
}
