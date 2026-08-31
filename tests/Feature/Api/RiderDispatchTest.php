<?php

namespace Tests\Feature\Api;

use App\Console\Commands\ExpireRiderAcceptance;
use App\Events\RiderLocationUpdated;
use App\Jobs\SendRiderPushJob;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderRiderDecline;
use App\Models\Rider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Covers the backend <-> rider-app contract: device registration, the
 * cumulative decline exclusion, and location broadcast throttling.
 */
class RiderDispatchTest extends TestCase
{
    use RefreshDatabase;

    private Rider $rider;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rider = Rider::factory()->create();
        $this->token = $this->rider->createToken('rider-mobile')->plainTextToken;
    }

    private function authed(): self
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token);

        return $this;
    }

    // ─── Device registration ────────────────────────────────────────────────

    public function test_rider_can_register_a_push_device(): void
    {
        $this->authed()->postJson('/api/v1/rider/devices', [
            'token'       => 'fcm-token-abc',
            'platform'    => 'android',
            'app_version' => '1.2.0',
        ])->assertCreated();

        $this->assertDatabaseHas('devices', [
            'rider_id'    => $this->rider->id,
            'customer_id' => null,
            'token'       => 'fcm-token-abc',
        ]);
    }

    public function test_registering_a_device_claims_it_from_a_previous_customer_owner(): void
    {
        $customer = \App\Models\Customer::factory()->create();
        Device::create([
            'customer_id' => $customer->id,
            'token'       => 'shared-handset-token',
            'platform'    => 'android',
        ]);

        $this->authed()->postJson('/api/v1/rider/devices', [
            'token' => 'shared-handset-token',
        ])->assertCreated();

        // Ownership is single-sided: claiming for the rider clears the customer.
        $this->assertDatabaseHas('devices', [
            'token'       => 'shared-handset-token',
            'rider_id'    => $this->rider->id,
            'customer_id' => null,
        ]);
        $this->assertSame(1, Device::where('token', 'shared-handset-token')->count());
    }

    public function test_rider_can_unregister_only_their_own_device(): void
    {
        $otherRider = Rider::factory()->create();
        Device::create(['rider_id' => $otherRider->id, 'token' => 'not-mine', 'platform' => 'android']);

        $this->authed()->deleteJson('/api/v1/rider/devices/not-mine')
            ->assertOk()
            ->assertJson(['message' => 'Device not found.']);

        $this->assertDatabaseHas('devices', ['token' => 'not-mine']);
    }

    public function test_device_registration_requires_a_rider_token(): void
    {
        $this->postJson('/api/v1/rider/devices', ['token' => 'x'])->assertUnauthorized();
    }

    // ─── Assignment push ────────────────────────────────────────────────────

    public function test_assignment_queues_a_push_to_the_rider(): void
    {
        Queue::fake();

        $order = Order::factory()->create([
            'rider_id'                  => $this->rider->id,
            'status'                    => 'rider_assigned',
            'rider_acceptance_deadline' => now()->addSeconds(60),
        ]);

        (new \App\Listeners\NotifyRiderOfNewOrder)->handle(
            new \App\Events\RiderAssignedEvent($order, $this->rider),
        );

        Queue::assertPushed(SendRiderPushJob::class, function (SendRiderPushJob $job): bool {
            return $job->riderId === $this->rider->id
                && $job->data['type'] === 'order.assigned';
        });
    }

    // ─── Cumulative decline exclusion ───────────────────────────────────────

    public function test_declining_records_a_persistent_exclusion(): void
    {
        Event::fake();

        $order = Order::factory()->create([
            'rider_id'                  => $this->rider->id,
            'status'                    => 'rider_assigned',
            'rider_acceptance_deadline' => now()->addSeconds(60),
        ]);

        $this->authed()->postJson("/api/v1/rider/orders/{$order->id}/decline")->assertOk();

        $this->assertDatabaseHas('order_rider_declines', [
            'order_id' => $order->id,
            'rider_id' => $this->rider->id,
            'reason'   => 'declined',
        ]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending', 'rider_id' => null]);
    }

    public function test_an_assignment_is_not_taken_back_when_the_rider_stays_quiet(): void
    {
        Event::fake();

        // An admin assigns only after seeing the rider at the shop, so the
        // system must not undo that because no button was tapped. A rider who
        // truly cannot take it declines; silence is chased by
        // CheckRiderDelaysJob after 15 minutes, and the admin decides.
        $order = Order::factory()->create([
            'rider_id'           => $this->rider->id,
            'status'             => 'rider_assigned',
            'rider_assigned_at'  => now()->subHour(),
            'rider_accepted_at'  => null,
        ]);

        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertDatabaseHas('orders', [
            'id'       => $order->id,
            'rider_id' => $this->rider->id,
            'status'   => 'rider_assigned',
        ]);

        $this->assertDatabaseMissing('order_rider_declines', [
            'order_id' => $order->id,
            'reason'   => 'acceptance_expired',
        ]);
    }

    public function test_decline_records_are_idempotent(): void
    {
        $order = Order::factory()->create();

        OrderRiderDecline::record($order->id, $this->rider->id, 'declined');
        OrderRiderDecline::record($order->id, $this->rider->id, 'acceptance_expired');

        $this->assertSame(1, OrderRiderDecline::where('order_id', $order->id)->count());
    }

    // ─── Location broadcast throttling ──────────────────────────────────────

    public function test_first_location_ping_broadcasts(): void
    {
        Event::fake([RiderLocationUpdated::class]);
        Cache::flush();

        $this->authed()->putJson('/api/v1/rider/location', [
            'latitude'  => -0.2833,
            'longitude' => 35.2697,
        ])->assertOk();

        Event::assertDispatched(RiderLocationUpdated::class);
    }

    public function test_a_near_identical_ping_is_not_rebroadcast(): void
    {
        Event::fake([RiderLocationUpdated::class]);
        Cache::flush();

        $payload = ['latitude' => -0.2833, 'longitude' => 35.2697];

        $this->authed()->putJson('/api/v1/rider/location', $payload)->assertOk();
        $this->authed()->putJson('/api/v1/rider/location', $payload)->assertOk();

        // Second ping is inside the rate cap and has not moved — one only.
        Event::assertDispatchedTimes(RiderLocationUpdated::class, 1);
    }

    public function test_the_position_is_still_persisted_when_the_broadcast_is_skipped(): void
    {
        Event::fake([RiderLocationUpdated::class]);
        Cache::flush();

        $this->authed()->putJson('/api/v1/rider/location', [
            'latitude' => -0.2833, 'longitude' => 35.2697,
        ])->assertOk();

        // Tiny movement — suppressed on the wire, but auto-assignment still
        // needs the fresh fix, so the write must happen regardless.
        $this->authed()->putJson('/api/v1/rider/location', [
            'latitude' => -0.28331, 'longitude' => 35.26971,
        ])->assertOk();

        Event::assertDispatchedTimes(RiderLocationUpdated::class, 1);

        $this->rider->refresh();
        $this->assertEqualsWithDelta(-0.28331, $this->rider->current_latitude, 0.000001);
    }

    public function test_meaningful_movement_rebroadcasts(): void
    {
        Event::fake([RiderLocationUpdated::class]);
        Cache::flush();

        $this->authed()->putJson('/api/v1/rider/location', [
            'latitude' => -0.2833, 'longitude' => 35.2697,
        ])->assertOk();

        // Past both the rate cap and the distance threshold (~1.1 km).
        $this->travel(6)->seconds();

        $this->authed()->putJson('/api/v1/rider/location', [
            'latitude' => -0.2933, 'longitude' => 35.2697,
        ])->assertOk();

        Event::assertDispatchedTimes(RiderLocationUpdated::class, 2);
    }
}
