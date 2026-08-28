<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\OtpToken;
use App\Models\Rider;
use App\Services\Sms\SmsServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class RiderApiTest extends TestCase
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

    public function test_rider_otp_request_rejected_for_unknown_phone(): void
    {
        $this->postJson('/api/v1/rider/auth/request-otp', ['phone' => '0700000000'])->assertUnprocessable();
    }

    public function test_rider_otp_request_accepted_for_known_active_rider(): void
    {
        $this->postJson('/api/v1/rider/auth/request-otp', ['phone' => $this->rider->phone])->assertOk();

        $this->assertDatabaseHas('otp_tokens', ['phone' => $this->rider->phone]);
    }

    public function test_rider_otp_request_returns_service_unavailable_when_sms_delivery_fails(): void
    {
        $this->mock(SmsServiceInterface::class, function (MockInterface $mock): void {
            $mock->shouldReceive('send')->once()->andReturnFalse();
        });

        $this->postJson('/api/v1/rider/auth/request-otp', ['phone' => $this->rider->phone])
            ->assertStatus(503)
            ->assertJson([
                'message' => "We couldn't send a verification code right now. Please try again.",
            ]);

        $this->assertDatabaseMissing('otp_tokens', ['phone' => $this->rider->phone]);
    }

    public function test_rider_verify_otp_returns_token(): void
    {
        OtpToken::factory()->create(['phone' => $this->rider->phone, 'token' => '9999']);

        $this->postJson('/api/v1/rider/auth/verify-otp', [
            'phone' => $this->rider->phone,
            'token' => '9999',
        ])
            ->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_at', 'rider']);
    }

    public function test_inactive_rider_cannot_request_otp(): void
    {
        $inactive = Rider::factory()->inactive()->create();

        $this->postJson('/api/v1/rider/auth/request-otp', ['phone' => $inactive->phone])->assertUnprocessable();
    }

    public function test_rider_can_logout_from_all_devices(): void
    {
        $token = $this->rider->createToken('secondary-device')->plainTextToken;
        $this->rider->createToken('tertiary-device');

        $this->withToken($token)
            ->postJson('/api/v1/rider/auth/logout-all')
            ->assertOk()
            ->assertJson(['message' => 'Logged out from all devices.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_rider_can_list_active_orders(): void
    {
        Order::factory()->withRider()->create(['rider_id' => $this->rider->id]);
        Order::factory()->delivered()->create(['rider_id' => $this->rider->id]);
        Order::factory()->withRider()->create();

        $response = $this->withToken($this->token)->getJson('/api/v1/rider/orders');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_rider_can_view_own_order(): void
    {
        $order = Order::factory()->withRider()->create(['rider_id' => $this->rider->id]);

        $this->withToken($this->token)->getJson("/api/v1/rider/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('id', $order->id);
    }

    public function test_rider_cannot_view_another_riders_order(): void
    {
        $order = Order::factory()->withRider()->create();

        $this->withToken($this->token)->getJson("/api/v1/rider/orders/{$order->id}")->assertNotFound();
    }

    public function test_rider_can_advance_order_status(): void
    {
        $order = Order::factory()->create([
            'rider_id' => $this->rider->id,
            'status' => 'rider_assigned',
        ]);

        $this->withToken($this->token)->putJson("/api/v1/rider/orders/{$order->id}/status", [
            'status' => 'picked_up',
        ])->assertOk();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'picked_up']);
    }

    public function test_rider_cannot_skip_status_steps(): void
    {
        $order = Order::factory()->create([
            'rider_id' => $this->rider->id,
            'status' => 'rider_assigned',
        ]);

        $this->withToken($this->token)->putJson("/api/v1/rider/orders/{$order->id}/status", [
            'status' => 'delivered',
        ])->assertUnprocessable();
    }

    public function test_rider_can_mark_cash_payment_collected_on_delivery(): void
    {
        $order = Order::factory()->create([
            'rider_id' => $this->rider->id,
            'status' => 'on_the_way',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ]);

        $this->withToken($this->token)->putJson("/api/v1/rider/orders/{$order->id}/status", [
            'status' => 'delivered',
            'payment_collected' => true,
        ])
            ->assertOk()
            ->assertJsonPath('payment_status', 'collected');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'delivered',
            'payment_status' => 'collected',
        ]);
    }

    public function test_rider_can_update_location(): void
    {
        $this->withToken($this->token)->putJson('/api/v1/rider/location', [
            'latitude' => -0.2833,
            'longitude' => 35.2697,
            'heading' => 90.0,
        ])->assertOk();

        $this->assertDatabaseHas('riders', [
            'id' => $this->rider->id,
            'current_latitude' => -0.2833,
        ]);
    }

    public function test_rider_can_toggle_availability(): void
    {
        $this->rider->update(['is_available' => true]);

        $response = $this->withToken($this->token)->postJson('/api/v1/rider/location/toggle-availability');

        $response->assertOk()->assertJsonPath('is_available', false);
        $this->assertDatabaseHas('riders', ['id' => $this->rider->id, 'is_available' => false]);
    }

    public function test_rider_can_set_availability_explicitly(): void
    {
        $this->rider->update(['is_available' => true]);

        // Idempotent: sending the same value twice must not flip it back.
        foreach ([false, false] as $_) {
            $this->withToken($this->token)
                ->putJson('/api/v1/rider/location/availability', ['is_available' => false])
                ->assertOk()
                ->assertJsonPath('is_available', false);
        }

        $this->assertDatabaseHas('riders', ['id' => $this->rider->id, 'is_available' => false]);
    }

    public function test_location_ping_accepts_android_unknown_heading(): void
    {
        // Android sends -1 when it has no bearing. This used to 422 and throw
        // away the position along with it.
        $this->withToken($this->token)->putJson('/api/v1/rider/location', [
            'latitude' => 0.5143,
            'longitude' => 35.2698,
            'heading' => -1,
        ])->assertOk();

        $this->rider->refresh();
        $this->assertNull($this->rider->heading);
        $this->assertNotNull($this->rider->location_updated_at);
    }

    public function test_rider_history_returns_only_completed_orders(): void
    {
        Order::factory()->delivered()->create(['rider_id' => $this->rider->id]);
        Order::factory()->withRider()->create(['rider_id' => $this->rider->id]);
        Order::factory()->delivered()->create();

        $response = $this->withToken($this->token)->getJson('/api/v1/rider/orders/history');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertSame('delivered', $response->json('data.0.status'));
    }

    public function test_active_order_awaiting_acceptance_is_flagged_for_the_app(): void
    {
        // The app can only offer Accept when the payload says so; without this
        // the button existed solely inside the assignment WebSocket sheet.
        $order = Order::factory()->create([
            'rider_id' => $this->rider->id,
            'status' => 'rider_assigned',
            'rider_accepted_at' => null,
            'rider_acceptance_deadline' => now()->addSeconds(60),
        ]);

        $this->withToken($this->token)->getJson('/api/v1/rider/orders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $order->id)
            ->assertJsonPath('data.0.needs_acceptance', true);
    }

    public function test_accepting_clears_the_deadline_and_stops_re_queueing(): void
    {
        $order = Order::factory()->create([
            'rider_id' => $this->rider->id,
            'status' => 'rider_assigned',
            'rider_accepted_at' => null,
            'rider_acceptance_deadline' => now()->addSeconds(60),
        ]);

        $this->withToken($this->token)
            ->postJson("/api/v1/rider/orders/{$order->id}/accept")
            ->assertOk();

        $order->refresh();
        $this->assertNotNull($order->rider_accepted_at);
        $this->assertNull($order->rider_acceptance_deadline);
    }

    public function test_accepting_an_order_already_re_queued_conflicts(): void
    {
        // The sweeper got there first: rider_id is cleared and the status is
        // back to pending. Accepting must not resurrect the assignment.
        $order = Order::factory()->create([
            'rider_id' => $this->rider->id,
            'status' => 'rider_assigned',
        ]);

        $order->update(['status' => 'pending']);

        $this->withToken($this->token)
            ->postJson("/api/v1/rider/orders/{$order->id}/accept")
            ->assertStatus(422);
    }

    public function test_rider_endpoints_require_rider_token(): void
    {
        $customer = \App\Models\Customer::factory()->create();
        $customerToken = $customer->createToken('mobile')->plainTextToken;

        $this->withToken($customerToken)->getJson('/api/v1/rider/orders')->assertUnauthorized();
    }
}
