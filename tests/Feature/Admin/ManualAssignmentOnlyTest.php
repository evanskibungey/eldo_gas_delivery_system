<?php

namespace Tests\Feature\Admin;

use App\Events\OrderPlacedEvent;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Rider;
use App\Support\OrderLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Dispatch is manual: an order waits in `pending` until an admin assigns it.
 *
 * Nothing may quietly claim an order on the admin's behalf, and a rider handing
 * one back must return it to the queue rather than starting another automatic
 * round of offers.
 */
class ManualAssignmentOnlyTest extends TestCase
{
    use RefreshDatabase;

    private function availableRiderNearby(Order $order): Rider
    {
        // Everything auto-assign used to look for: active, available, idle,
        // and a recent GPS fix right next to the drop.
        return Rider::factory()->create([
            'is_active' => true,
            'is_available' => true,
            'current_latitude' => $order->delivery_lat,
            'current_longitude' => $order->delivery_lng,
            'location_updated_at' => now(),
        ]);
    }

    public function test_a_new_order_stays_pending_even_with_an_ideal_rider_free(): void
    {
        $order = Order::factory()->create(['status' => OrderLifecycle::STATUS_PENDING]);
        $this->availableRiderNearby($order);

        // Runs every listener registered for the event, exactly as placing an
        // order does. Nothing should touch the assignment.
        event(new OrderPlacedEvent($order));

        $order->refresh();

        $this->assertSame(OrderLifecycle::STATUS_PENDING, $order->status);
        $this->assertNull($order->rider_id, 'The order was assigned without an admin.');
        $this->assertNull($order->rider_acceptance_deadline);
    }

    public function test_an_admin_can_still_assign_by_hand(): void
    {
        $order = Order::factory()->create(['status' => OrderLifecycle::STATUS_PENDING]);
        $rider = $this->availableRiderNearby($order);
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.orders.assign', $order), ['rider_id' => $rider->id])
            ->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'rider_id' => $rider->id,
            'status' => OrderLifecycle::STATUS_RIDER_ASSIGNED,
        ]);
    }

    public function test_an_admin_assignment_carries_no_acceptance_deadline(): void
    {
        $order = Order::factory()->create(['status' => OrderLifecycle::STATUS_PENDING]);
        $rider = $this->availableRiderNearby($order);
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.orders.assign', $order), ['rider_id' => $rider->id]);

        // A deadline is what let the old sweeper take an order back off a rider
        // who had not tapped Accept. The admin assigned it having seen them at
        // the shop, so that decision stands.
        $this->assertNull($order->fresh()->rider_acceptance_deadline);
    }

    public function test_a_decline_returns_the_order_to_the_queue_without_re_offering_it(): void
    {
        Event::fake([OrderPlacedEvent::class]);

        $rider = Rider::factory()->create();
        $order = Order::factory()->create([
            'status' => OrderLifecycle::STATUS_RIDER_ASSIGNED,
            'rider_id' => $rider->id,
            'rider_assigned_at' => now(),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$rider->createToken('test')->plainTextToken])
            ->postJson("/api/v1/rider/orders/{$order->id}/decline")
            ->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderLifecycle::STATUS_PENDING,
            'rider_id' => null,
        ]);

        // Re-firing OrderPlacedEvent was how the old auto-assign loop handed the
        // order to the next rider. It also made the admin panel announce a
        // known order as brand new.
        Event::assertNotDispatched(OrderPlacedEvent::class);
    }
}
