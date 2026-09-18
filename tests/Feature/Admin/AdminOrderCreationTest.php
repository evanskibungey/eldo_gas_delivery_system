<?php

namespace Tests\Feature\Admin;

use App\Events\OrderDeliveredEvent;
use App\Jobs\SafetyTipJob;
use App\Jobs\SendSmsJob;
use App\Listeners\SendDeliveryThankYou;
use App\Listeners\SendOrderConfirmationNotification;
use App\Listeners\SendSafetyTipAfterDelivery;
use App\Listeners\SendWalkInReceipt;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CylinderPrice;
use App\Models\CylinderSize;
use App\Models\GasBrand;
use App\Models\Order;
use App\Models\StockLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Orders an admin creates for someone who did not use the app.
 *
 * Two shapes. A phone order is an ordinary delivery that happened to be taken
 * by voice, so it must behave exactly like an app order once it exists. A
 * counter sale is money already in the till and a cylinder already gone, so
 * recording it as pending would put a fictional job on the dispatch board.
 *
 * The trap this file exists to hold shut is the SMS count: a counter sale
 * travels the whole placed-then-delivered path in one action, which fires
 * three customer messages unless two of them stand down.
 */
class AdminOrderCreationTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
    }

    /** @return array{0: CylinderSize, 1: GasBrand} */
    private function cylinder(string $name = '13kg', int $refill = 3200, int $fee = 150, int $stock = 20): array
    {
        $size = CylinderSize::factory()->create(['name' => $name, 'is_active' => true]);
        $brand = GasBrand::factory()->create();
        $size->brands()->attach($brand->id);

        CylinderPrice::factory()->create([
            'size_id' => $size->id,
            'gas_refill_price' => $refill,
            'new_cylinder_price' => 4500,
            'new_gas_fill_price' => $refill + 200,
            'delivery_fee' => $fee,
        ]);
        StockLevel::factory()->create(['size_id' => $size->id, 'filled_count' => $stock]);

        return [$size, $brand];
    }

    private function line(CylinderSize $size, GasBrand $brand, int $quantity = 1, string $type = 'swap'): array
    {
        return [
            'size_id' => $size->id,
            'brand_id' => $brand->id,
            'order_type' => $type,
            'quantity' => $quantity,
        ];
    }

    private function createOrder(array $payload)
    {
        return $this->actingAs($this->admin, 'admin')->post('/admin/orders', $payload);
    }

    // ── Counter sales ─────────────────────────────────────────────────────────

    public function test_a_counter_sale_is_closed_and_paid_on_creation(): void
    {
        [$size, $brand] = $this->cylinder();

        $this->createOrder([
            'channel' => 'walk_in',
            'customer_name' => 'Mercy Chebet',
            'customer_phone' => '0712345678',
            'items' => [$this->line($size, $brand)],
            'payment_method' => 'cash',
        ])->assertRedirect();

        $order = Order::firstOrFail();

        $this->assertSame('delivered', $order->status);
        $this->assertSame('collected', $order->payment_status);
        $this->assertNotNull($order->delivered_at);
        // Nothing travelled, so nothing is charged for travel — and nobody was
        // sent, so no rider can be waiting on this.
        $this->assertSame(0, (int) $order->delivery_fee);
        $this->assertNull($order->rider_id);
        $this->assertSame(3200, (int) $order->total_amount);
    }

    public function test_a_counter_sale_deducts_stock(): void
    {
        [$size, $brand] = $this->cylinder(stock: 5);

        $this->createOrder([
            'channel' => 'walk_in',
            'customer_name' => 'Mercy Chebet',
            'customer_phone' => '0712345678',
            'items' => [$this->line($size, $brand, 2)],
            'payment_method' => 'cash',
        ])->assertRedirect();

        $this->assertSame(3, (int) StockLevel::where('size_id', $size->id)->value('filled_count'));
    }

    public function test_a_counter_sale_cannot_sell_off_an_empty_shelf(): void
    {
        [$size, $brand] = $this->cylinder(stock: 1);

        $this->createOrder([
            'channel' => 'walk_in',
            'customer_name' => 'Mercy Chebet',
            'customer_phone' => '0712345678',
            'items' => [$this->line($size, $brand, 3)],
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, Order::count());
        // Refused, not partly applied.
        $this->assertSame(1, (int) StockLevel::where('size_id', $size->id)->value('filled_count'));
    }

    /**
     * The three-SMS trap.
     *
     * A counter sale is placed and delivered in one action, so it fires
     * OrderPlacedEvent and OrderDeliveredEvent back to back. Left alone that is
     * "your order has been received" to somebody standing at the counter, then
     * a thank-you for a delivery that never happened, then the receipt — three
     * billed messages for one transaction, two of them nonsense.
     */
    public function test_a_counter_sale_sends_one_receipt_and_not_the_delivery_pair(): void
    {
        [$size, $brand] = $this->cylinder();
        $customer = Customer::factory()->create(['name' => 'Mercy Chebet', 'phone' => '+254712345678']);

        Queue::fake();

        $this->createOrder([
            'channel' => 'walk_in',
            'customer_id' => $customer->id,
            'items' => [$this->line($size, $brand)],
            'payment_method' => 'cash',
        ])->assertRedirect();

        $order = Order::firstOrFail();

        // Drive the listeners by hand: under Queue::fake() they are parked as
        // CallQueuedListener and never run, so this asserts what each listener
        // decides rather than what a worker happened to get to.
        Queue::assertNotPushed(SendSmsJob::class);

        app(SendOrderConfirmationNotification::class)->handle(new \App\Events\OrderPlacedEvent($order));
        Queue::assertNotPushed(SendSmsJob::class);

        app(SendDeliveryThankYou::class)->handle(new OrderDeliveredEvent($order));
        Queue::assertNotPushed(SendSmsJob::class);

        app(SendWalkInReceipt::class)->handle(new OrderDeliveredEvent($order));
        Queue::assertPushed(SendSmsJob::class, 1);

        // The safety tip still goes out. Somebody carrying a cylinder home
        // needs it at least as much as somebody who had it delivered. It goes
        // via its own delayed job rather than SendSmsJob directly.
        app(SendSafetyTipAfterDelivery::class)->handle(new OrderDeliveredEvent($order));
        Queue::assertPushed(SendSmsJob::class, 1);
        Queue::assertPushed(SafetyTipJob::class, 1);
    }

    public function test_the_walk_in_receipt_carries_the_points_and_the_app_link(): void
    {
        [$size, $brand] = $this->cylinder();
        $customer = Customer::factory()->create(['name' => 'Mercy Chebet', 'phone' => '+254712345678']);

        $this->createOrder([
            'channel' => 'walk_in',
            'customer_id' => $customer->id,
            'items' => [$this->line($size, $brand)],
            'payment_method' => 'cash',
        ])->assertRedirect();

        $order = Order::firstOrFail();

        Queue::fake();
        app(SendWalkInReceipt::class)->handle(new OrderDeliveredEvent($order));

        Queue::assertPushed(SendSmsJob::class, function (SendSmsJob $job) {
            $message = (string) (fn () => $this->message)->call($job);

            // The points are the reason to install the app — they cannot be
            // spent anywhere else. A receipt without them is just a receipt.
            return str_contains($message, 'GasPoints')
                && str_contains($message, '/get');
        });
    }

    public function test_a_counter_sale_awards_gaspoints(): void
    {
        [$size, $brand] = $this->cylinder();
        $customer = Customer::factory()->create(['gaspoints_balance' => 0]);

        $this->createOrder([
            'channel' => 'walk_in',
            'customer_id' => $customer->id,
            'items' => [$this->line($size, $brand)],
            'payment_method' => 'cash',
        ])->assertRedirect();

        $this->assertGreaterThan(
            0,
            (int) $customer->fresh()->gaspoints_balance,
            'A counter sale earned no GasPoints, which removes the only reason a walk-in has to install the app.',
        );
    }

    // ── Phone orders ──────────────────────────────────────────────────────────

    public function test_a_phone_order_behaves_like_an_app_order(): void
    {
        [$size, $brand] = $this->cylinder();
        $customer = Customer::factory()->create();
        $address = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

        $this->createOrder([
            'channel' => 'phone',
            'customer_id' => $customer->id,
            'address_id' => $address->id,
            'items' => [$this->line($size, $brand)],
            'payment_method' => 'cash',
        ])->assertRedirect();

        $order = Order::firstOrFail();

        $this->assertSame('pending', $order->status);
        $this->assertNull($order->rider_id, 'Assignment is manual — a phone order must wait for an admin.');
        // A delivery is charged for, unlike a counter sale.
        $this->assertSame(150, (int) $order->delivery_fee);
        $this->assertSame((float) $address->latitude, (float) $order->delivery_lat);
        $this->assertSame('phone', $order->channel);
    }

    public function test_a_phone_order_needs_an_address(): void
    {
        [$size, $brand] = $this->cylinder();
        $customer = Customer::factory()->create();

        $this->createOrder([
            'channel' => 'phone',
            'customer_id' => $customer->id,
            'items' => [$this->line($size, $brand)],
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('address_id');

        $this->assertSame(0, Order::count());
    }

    public function test_a_phone_order_cannot_borrow_someone_elses_address(): void
    {
        [$size, $brand] = $this->cylinder();
        $customer = Customer::factory()->create();
        $stranger = CustomerAddress::factory()->create([
            'customer_id' => Customer::factory()->create()->id,
        ]);

        // The id exists, so `exists:` is satisfied. Only an ownership check
        // stops the rider being sent to a different house entirely.
        $this->createOrder([
            'channel' => 'phone',
            'customer_id' => $customer->id,
            'address_id' => $stranger->id,
            'items' => [$this->line($size, $brand)],
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('address_id');

        $this->assertSame(0, Order::count());
    }

    // ── Customers ─────────────────────────────────────────────────────────────

    public function test_an_inline_customer_is_created_unverified(): void
    {
        [$size, $brand] = $this->cylinder();

        $this->createOrder([
            'channel' => 'walk_in',
            'customer_name' => 'Mercy Chebet',
            'customer_phone' => '0712345678',
            'items' => [$this->line($size, $brand)],
            'payment_method' => 'cash',
        ])->assertRedirect();

        $customer = Customer::firstOrFail();

        $this->assertSame('Mercy Chebet', $customer->name);
        // 07… normalised, so their later app login lands on this same row
        // rather than colliding with the UNIQUE phone column.
        $this->assertSame('+254712345678', $customer->phone);
        // Typing a number in does not prove it. This null IS the conversion
        // signal the walk-in badge and the campaign filter both read.
        $this->assertNull($customer->phone_verified_at);
        $this->assertSame('admin', $customer->created_via);
        $this->assertNotEmpty($customer->referral_code);
    }

    public function test_a_repeat_walk_in_reuses_the_customer(): void
    {
        [$size, $brand] = $this->cylinder();
        $existing = Customer::factory()->create([
            'name' => 'Mercy Chebet',
            'phone' => '+254712345678',
        ]);

        // Same person, entered the local way this time. A second insert would
        // hit the UNIQUE phone column and 500 at the counter.
        $this->createOrder([
            'channel' => 'walk_in',
            'customer_name' => 'Mercy',
            'customer_phone' => '0712345678',
            'items' => [$this->line($size, $brand)],
            'payment_method' => 'cash',
        ])->assertRedirect();

        $this->assertSame(1, Customer::count());
        // A rushed counter entry must not overwrite the name they chose.
        $this->assertSame('Mercy Chebet', $existing->fresh()->name);
        $this->assertSame($existing->id, Order::firstOrFail()->customer_id);
    }

    public function test_the_order_records_the_admin_as_the_actor(): void
    {
        [$size, $brand] = $this->cylinder();

        $this->createOrder([
            'channel' => 'phone',
            'customer_name' => 'Mercy Chebet',
            'customer_phone' => '0712345678',
            'address_id' => CustomerAddress::factory()->create([
                'customer_id' => Customer::factory()->create(['phone' => '+254712345678'])->id,
            ])->id,
            'items' => [$this->line($size, $brand)],
            'payment_method' => 'cash',
        ])->assertRedirect();

        $history = Order::firstOrFail()->statusHistory()->where('status', 'pending')->firstOrFail();

        // The history is the record of who did what, and an admin taking a call
        // is not the customer placing an order.
        $this->assertSame('admin', $history->actor_type);
        $this->assertSame($this->admin->id, (int) $history->actor_id);
    }

    // ── Basket rules ──────────────────────────────────────────────────────────

    public function test_duplicate_lines_merge_into_a_quantity(): void
    {
        [$size, $brand] = $this->cylinder();

        $this->createOrder([
            'channel' => 'walk_in',
            'customer_name' => 'Mercy Chebet',
            'customer_phone' => '0712345678',
            'items' => [
                $this->line($size, $brand, 1),
                $this->line($size, $brand, 2),
            ],
            'payment_method' => 'cash',
        ])->assertRedirect();

        // order_items carries unique(order_id, size_id, brand_id, order_type),
        // so two rows for the same cylinder is a constraint violation rather
        // than a bigger order.
        $order = Order::with('items')->firstOrFail();
        $this->assertCount(1, $order->items);
        $this->assertSame(3, (int) $order->items->first()->quantity);
    }

    public function test_a_brand_not_sold_in_that_size_is_refused(): void
    {
        [$size, $brand] = $this->cylinder();
        $foreign = GasBrand::factory()->create();

        $this->createOrder([
            'channel' => 'walk_in',
            'customer_name' => 'Mercy Chebet',
            'customer_phone' => '0712345678',
            'items' => [$this->line($size, $foreign)],
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('items.0.brand_id');

        $this->assertSame(0, Order::count());
        $this->assertNotNull($brand);
    }

    public function test_an_order_needs_a_customer(): void
    {
        [$size, $brand] = $this->cylinder();

        $this->createOrder([
            'channel' => 'walk_in',
            'items' => [$this->line($size, $brand)],
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('customer_phone');

        $this->assertSame(0, Order::count());
    }

    public function test_the_app_channel_cannot_be_claimed_from_here(): void
    {
        [$size, $brand] = $this->cylinder();

        // `app` means the customer placed it themselves. Accepting it here
        // would also set the full-screen new-order alarm off at whoever just
        // typed the order in.
        $this->createOrder([
            'channel' => 'app',
            'customer_name' => 'Mercy Chebet',
            'customer_phone' => '0712345678',
            'items' => [$this->line($size, $brand)],
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('channel');
    }

    // ── Access ────────────────────────────────────────────────────────────────

    public function test_a_guest_cannot_create_an_order(): void
    {
        [$size, $brand] = $this->cylinder();

        $this->post('/admin/orders', [
            'channel' => 'walk_in',
            'customer_name' => 'Mercy Chebet',
            'customer_phone' => '0712345678',
            'items' => [$this->line($size, $brand)],
            'payment_method' => 'cash',
        ])->assertRedirect();

        $this->assertSame(0, Order::count());
    }

    public function test_the_composer_loads_with_the_catalogue(): void
    {
        [$size] = $this->cylinder();

        $this->actingAs($this->admin, 'admin')
            ->get('/admin/orders/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Orders/Create')
                ->has('catalogue.sizes', 1)
                ->where('catalogue.sizes.0.id', $size->id)
                // The count, not a boolean: somebody asking for four needs to
                // be told there are two before the order is built.
                ->where('catalogue.sizes.0.filled_count', 20)
                ->where('catalogue.sizes.0.swap_price', 3200)
                ->has('catalogue.brands_by_size.'.$size->id, 1));
    }

    public function test_the_create_route_is_not_swallowed_as_an_order_id(): void
    {
        // `orders/create` sits above `orders/{order}`. Declared the other way
        // round it resolves as an id and 404s.
        $this->actingAs($this->admin, 'admin')
            ->get('/admin/orders/create')
            ->assertOk();

        $this->actingAs($this->admin, 'admin')
            ->getJson('/admin/orders/catalogue')
            ->assertOk()
            ->assertJsonStructure(['sizes', 'brands_by_size', 'addons_by_size']);
    }
}
