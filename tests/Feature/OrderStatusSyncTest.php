<?php

namespace Tests\Feature;

use App\Actions\CancelOrderAction;
use App\Actions\ResolvePaymentDisputeAction;
use App\Actions\RiderNoShowAction;
use App\Events\OrderStatusUpdatedEvent;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Rider;
use App\Services\Admin\OrderService;
use App\Support\OrderLifecycle;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Order status has to stay in step across three surfaces: the admin board, the
 * rider app and the customer's tracking page. They all learn about a change the
 * same way — an OrderStatusUpdatedEvent broadcast on their channel.
 *
 * The failure this guards against is silent and one-directional: a mutation
 * that updates the database and skips the broadcast. Nothing errors, and the
 * screen that missed it simply shows stale state until someone reloads. A rider
 * can be chasing payment for an order already marked paid, or an admin can be
 * looking at "assigned" for an order already delivered.
 *
 * Every path that writes status, payment_status or has_issue therefore has to
 * announce it, which is what these tests assert.
 */
class OrderStatusSyncTest extends TestCase
{
    use RefreshDatabase;

    private function assignedOrder(?Rider $rider = null): Order
    {
        $rider ??= Rider::factory()->create();

        return Order::factory()->create([
            'status' => OrderLifecycle::STATUS_RIDER_ASSIGNED,
            'rider_id' => $rider->id,
            'rider_assigned_at' => now(),
            'rider_accepted_at' => now(),
        ]);
    }

    private function riderHeaders(Rider $rider): array
    {
        return ['Authorization' => 'Bearer '.$rider->createToken('test')->plainTextToken];
    }

    /** @return string[] */
    private function channelsFor(Order $order): array
    {
        return array_map(
            fn (PrivateChannel $channel) => $channel->name,
            (new OrderStatusUpdatedEvent($order))->broadcastOn(),
        );
    }

    // ── Routing: one change has to reach all three surfaces ───────────────────

    public function test_a_change_reaches_the_admin_board_the_rider_and_the_customer(): void
    {
        $order = $this->assignedOrder();

        $channels = $this->channelsFor($order);

        $this->assertContains('private-admin.orders', $channels, 'The admin board would not update.');
        $this->assertContains("private-rider.{$order->rider_id}", $channels, 'The rider app would not update.');
        $this->assertContains("private-orders.{$order->id}", $channels, 'Customer tracking would not update.');
    }

    public function test_an_unassigned_order_still_reaches_the_admin_and_the_customer(): void
    {
        $order = Order::factory()->create([
            'status' => OrderLifecycle::STATUS_PENDING,
            'rider_id' => null,
        ]);

        $channels = $this->channelsFor($order);

        $this->assertContains('private-admin.orders', $channels);
        $this->assertContains("private-orders.{$order->id}", $channels);
        // No rider channel to address, and nothing should invent one.
        $this->assertCount(2, $channels);
    }

    // ── Admin acts -> the rider app must hear about it ────────────────────────

    public function test_admin_advancing_status_is_announced(): void
    {
        Event::fake([OrderStatusUpdatedEvent::class]);

        $order = $this->assignedOrder();
        app(OrderService::class)->advanceStatus($order, OrderLifecycle::STATUS_PICKED_UP);

        Event::assertDispatched(OrderStatusUpdatedEvent::class);
    }

    public function test_admin_collecting_payment_is_announced(): void
    {
        Event::fake([OrderStatusUpdatedEvent::class]);

        $order = Order::factory()->create([
            'status' => OrderLifecycle::STATUS_DELIVERED,
            'payment_status' => 'pending',
            'rider_id' => Rider::factory()->create()->id,
        ]);

        app(OrderService::class)->collectPayment($order);

        Event::assertDispatched(OrderStatusUpdatedEvent::class);
    }

    public function test_admin_cancelling_is_announced_to_the_rider_carrying_it(): void
    {
        $order = $this->assignedOrder();

        Event::fake([OrderStatusUpdatedEvent::class]);
        app(CancelOrderAction::class)->execute($order, 'Customer changed mind', 'admin', 1, true);

        // The rider must not keep delivering a cancelled order, so the channel
        // has to survive the cancellation.
        Event::assertDispatched(
            OrderStatusUpdatedEvent::class,
            fn (OrderStatusUpdatedEvent $e) => in_array(
                "private-rider.{$order->rider_id}",
                array_map(fn ($c) => $c->name, $e->broadcastOn()),
                true,
            ),
        );
    }

    public function test_flagging_a_payment_dispute_is_announced(): void
    {
        $order = $this->assignedOrder();
        $admin = Admin::factory()->create();

        Event::fake([OrderStatusUpdatedEvent::class]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.orders.issues.payment-dispute', $order), ['note' => 'Short payment'])
            ->assertRedirect();

        Event::assertDispatched(OrderStatusUpdatedEvent::class);
    }

    public function test_resolving_a_payment_dispute_is_announced(): void
    {
        $order = $this->assignedOrder();
        $order->update(['payment_status' => 'disputed', 'has_issue' => true]);

        Event::fake([OrderStatusUpdatedEvent::class]);
        app(ResolvePaymentDisputeAction::class)->execute($order, 'paid', 1);

        Event::assertDispatched(OrderStatusUpdatedEvent::class);
    }

    // ── Rider acts -> the admin board must hear about it ──────────────────────

    public function test_rider_advancing_status_is_announced_to_the_admin_board(): void
    {
        $rider = Rider::factory()->create();
        $order = $this->assignedOrder($rider);

        Event::fake([OrderStatusUpdatedEvent::class]);

        $this->withHeaders($this->riderHeaders($rider))
            ->putJson("/api/v1/rider/orders/{$order->id}/status", ['status' => 'picked_up'])
            ->assertOk();

        Event::assertDispatched(
            OrderStatusUpdatedEvent::class,
            fn (OrderStatusUpdatedEvent $e) => in_array(
                'private-admin.orders',
                array_map(fn ($c) => $c->name, $e->broadcastOn()),
                true,
            ),
        );
    }

    public function test_rider_accepting_is_announced(): void
    {
        $rider = Rider::factory()->create();
        $order = Order::factory()->create([
            'status' => OrderLifecycle::STATUS_RIDER_ASSIGNED,
            'rider_id' => $rider->id,
            'rider_assigned_at' => now(),
            'rider_accepted_at' => null,
        ]);

        Event::fake([OrderStatusUpdatedEvent::class]);

        $this->withHeaders($this->riderHeaders($rider))
            ->postJson("/api/v1/rider/orders/{$order->id}/accept")
            ->assertOk();

        Event::assertDispatched(OrderStatusUpdatedEvent::class);
    }

    public function test_rider_declining_is_announced(): void
    {
        $rider = Rider::factory()->create();
        $order = $this->assignedOrder($rider);

        Event::fake([OrderStatusUpdatedEvent::class]);

        $this->withHeaders($this->riderHeaders($rider))
            ->postJson("/api/v1/rider/orders/{$order->id}/decline")
            ->assertOk();

        Event::assertDispatched(OrderStatusUpdatedEvent::class);
    }

    // ── Third parties -> both apps must hear about it ─────────────────────────

    public function test_a_rider_no_show_report_is_announced(): void
    {
        $order = $this->assignedOrder();

        Event::fake([OrderStatusUpdatedEvent::class]);
        app(RiderNoShowAction::class)->execute($order, 'customer', $order->customer_id);

        Event::assertDispatched(OrderStatusUpdatedEvent::class);
    }

    public function test_a_customer_reporting_a_general_issue_is_announced(): void
    {
        $customer = Customer::factory()->create();
        $rider = Rider::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'rider_id' => $rider->id,
            'status' => OrderLifecycle::STATUS_ON_THE_WAY,
        ]);

        Event::fake([OrderStatusUpdatedEvent::class]);

        $this->withHeaders(['Authorization' => 'Bearer '.$customer->createToken('test')->plainTextToken])
            ->postJson("/api/v1/orders/{$order->id}/report-issue", [
                'issue_type' => 'other',
                'description' => 'Rider took a wrong turn',
            ])
            ->assertCreated();

        Event::assertDispatched(OrderStatusUpdatedEvent::class);
    }

    public function test_an_mpesa_confirmation_is_announced(): void
    {
        $order = $this->assignedOrder();
        $order->update([
            'payment_method' => 'mpesa',
            'payment_status' => 'pending',
            'mpesa_checkout_request_id' => 'ws_CO_TEST_123',
        ]);

        Event::fake([OrderStatusUpdatedEvent::class]);

        // Safaricom confirms out of band. Without a broadcast the rider keeps
        // showing "payment pending" and chases a customer who has paid.
        $this->postJson('/api/v1/webhooks/mpesa/callback', [
            'Body' => ['stkCallback' => [
                'MerchantRequestID' => 'mr-test',
                'CheckoutRequestID' => 'ws_CO_TEST_123',
                'ResultCode' => 0,
                'ResultDesc' => 'The service request is processed successfully.',
            ]],
        ])->assertOk();

        $this->assertSame('collected', $order->fresh()->payment_status);
        Event::assertDispatched(OrderStatusUpdatedEvent::class);
    }
}
