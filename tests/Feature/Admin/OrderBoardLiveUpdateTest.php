<?php

namespace Tests\Feature\Admin;

use App\Events\OrderPlacedEvent;
use App\Events\OrderStatusUpdatedEvent;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Rider;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\AlwaysProp;
use Tests\TestCase;

/**
 * The admin dispatch board is driven by the `admin.orders` broadcast channel and
 * by the tab counts. Both used to drift out of sync with reality: only new
 * orders reached the channel, and the On the Way badge counted a status its own
 * filter excluded.
 */
class OrderBoardLiveUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function channelNames(object $event): array
    {
        return array_map(
            fn (PrivateChannel $channel) => $channel->name,
            $event->broadcastOn(),
        );
    }

    public function test_status_updates_broadcast_to_the_admin_board(): void
    {
        $rider = Rider::factory()->create();
        $order = Order::factory()->create([
            'status' => 'rider_assigned',
            'rider_id' => $rider->id,
        ]);

        $channels = $this->channelNames(new OrderStatusUpdatedEvent($order));

        $this->assertContains('private-admin.orders', $channels);
        $this->assertContains("private-orders.{$order->id}", $channels);
        $this->assertContains("private-rider.{$rider->id}", $channels);
    }

    public function test_status_broadcast_payload_carries_what_the_board_renders(): void
    {
        $order = Order::factory()->create([
            'status' => 'correction_in_progress',
            'has_issue' => true,
            'issue_type' => 'wrong_cylinder',
        ]);

        $payload = (new OrderStatusUpdatedEvent($order))->broadcastWith();

        $this->assertSame($order->id, $payload['order_id']);
        $this->assertSame('correction_in_progress', $payload['status']);
        $this->assertTrue($payload['has_issue']);
        $this->assertSame('wrong_cylinder', $payload['issue_type']);
    }

    public function test_new_order_payload_carries_the_details_the_alert_shows(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);

        $payload = (new OrderPlacedEvent($order))->broadcastWith();

        $this->assertSame($order->order_number, $payload['order_number']);
        $this->assertSame($order->total_amount, $payload['total_amount']);
        $this->assertArrayHasKey('customer_name', $payload);
        $this->assertArrayHasKey('customer_phone', $payload);
        $this->assertArrayHasKey('address', $payload);
        $this->assertFalse($payload['is_reoffer']);
    }

    public function test_a_rider_decline_reoffer_is_flagged_so_it_is_not_announced_as_new(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);
        $declined = Rider::factory()->create();

        $payload = (new OrderPlacedEvent($order, [$declined->id]))->broadcastWith();

        $this->assertTrue($payload['is_reoffer']);
    }

    public function test_on_the_way_tab_lists_the_corrections_its_badge_counts(): void
    {
        Order::factory()->create(['status' => 'on_the_way']);
        Order::factory()->create(['status' => 'correction_in_progress']);

        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.orders.index', ['status' => 'on_the_way']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Orders/Index')
                ->where('counts.on_the_way', 2)
                ->has('orders.data', 2));
    }

    public function test_stale_pending_is_counted_across_the_whole_table(): void
    {
        // Off the first page of any filtered view, and outside the pending tab —
        // the old client-side count missed both of these.
        Order::factory()->create([
            'status' => 'pending',
            'created_at' => now()->subMinutes(30),
        ]);
        Order::factory()->create([
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.orders.index', ['status' => 'delivered']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('stale_pending', 1));
    }

    public function test_sidebar_badges_are_always_props_so_partial_reloads_refresh_them(): void
    {
        Order::factory()->count(3)->create(['status' => 'pending']);

        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');

        $shared = (new HandleInertiaRequests())->share(Request::create('/admin/orders'));

        // The board refreshes itself with router.reload({ only: [...] }). A
        // plain closure prop is filtered out of those responses and the badge
        // freezes; only an AlwaysProp survives the filter.
        $this->assertInstanceOf(AlwaysProp::class, $shared['pending_orders_count']);
        $this->assertInstanceOf(AlwaysProp::class, $shared['low_stock_count']);

        $this->assertSame(3, ($shared['pending_orders_count'])());
    }
}
