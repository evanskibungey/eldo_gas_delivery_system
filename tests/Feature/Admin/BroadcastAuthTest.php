<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Echo cannot subscribe to a private channel until POST /broadcasting/auth
 * returns a signature. If that call fails, the WebSocket still connects and
 * still pings — so the panel looks healthy and shows no "offline" indicator —
 * but no `pusher:subscribe` frame is ever sent and not one broadcast arrives.
 *
 * The failure is completely silent from the outside, which is why it needs a
 * test rather than a browser check.
 *
 * Two traps make a naive version of this test pass against a broken app:
 *
 *  1. phpunit.xml sets BROADCAST_CONNECTION=null, and NullBroadcaster::auth()
 *     returns an empty 200 for everyone, authorised or not.
 *  2. actingAs($user, $guard) calls Auth::shouldUse($guard), making that guard
 *     the DEFAULT for the test. Production never does that — the default stays
 *     'web' — so actingAs() resolves users the real request cannot.
 *
 * Both are worked around below.
 */
class BroadcastAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('broadcasting.default', 'reverb');
        config()->set('broadcasting.connections.reverb', [
            'driver' => 'reverb',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => 'test-app',
            'options' => [
                'host' => '127.0.0.1',
                'port' => 8080,
                'scheme' => 'http',
                'useTLS' => false,
            ],
        ]);

        // Broadcast::channel() proxies to whichever driver is active when it is
        // called. AppServiceProvider registered the channels during boot, while
        // the null driver was still selected — so the reverb driver created
        // above starts with no channels and refuses everything. Re-register.
        require base_path('routes/channels.php');
    }

    /**
     * Authenticate a guard the way a real session does: without promoting it to
     * the default guard. Using actingAs() here would defeat the test.
     */
    private function loginAs(Authenticatable $user, string $guard): void
    {
        Auth::guard($guard)->setUser($user);
    }

    public function test_an_authenticated_admin_can_authorise_the_orders_channel(): void
    {
        $this->loginAs(Admin::factory()->create(), 'admin');

        $response = $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-admin.orders',
        ]);

        $response->assertOk();

        // Echo sends no pusher:subscribe frame without this key in the response,
        // and Reverb rejects the subscribe without the signature it carries.
        $this->assertArrayHasKey('auth', $response->json());
    }

    public function test_a_guest_cannot_authorise_the_orders_channel(): void
    {
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-admin.orders',
        ])->assertForbidden();
    }

    public function test_an_admin_can_authorise_an_individual_order_channel(): void
    {
        $order = Order::factory()->create();
        $this->loginAs(Admin::factory()->create(), 'admin');

        // The order detail page subscribes to this for live status updates.
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-orders.{$order->id}",
        ])->assertOk();
    }

    public function test_a_customer_can_authorise_their_own_order(): void
    {
        $customer = Customer::factory()->create();
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        $this->loginAs($customer, 'customer');

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-orders.{$order->id}",
        ])->assertOk();
    }

    public function test_a_customer_cannot_authorise_someone_elses_order(): void
    {
        $customer = Customer::factory()->create();
        $theirs = Order::factory()->create();

        $this->loginAs($customer, 'customer');

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-orders.{$theirs->id}",
        ])->assertForbidden();
    }

    public function test_a_customer_cannot_authorise_the_admin_channel(): void
    {
        $this->loginAs(Customer::factory()->create(), 'customer');

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-admin.orders',
        ])->assertForbidden();
    }
}
