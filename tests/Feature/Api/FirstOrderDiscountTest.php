<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CylinderPrice;
use App\Models\CylinderSize;
use App\Models\GasBrand;
use App\Models\Order;
use App\Support\OrderLifecycle;
use App\Models\StockLevel;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Money off a customer's first order through the app, applied without them
 * asking for it.
 *
 * The decision is the server's alone: the app is told the offer exists so it
 * can show the line, but what comes off is worked out again here, against the
 * orders table, at the moment the order is written.
 */
class FirstOrderDiscountTest extends TestCase
{
    use RefreshDatabase;

    private CylinderSize $size;
    private GasBrand $brand;
    private Customer $customer;
    private CustomerAddress $address;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->size = CylinderSize::factory()->create(['is_active' => true]);
        $this->brand = GasBrand::factory()->create();
        $this->size->brands()->attach($this->brand->id);

        CylinderPrice::factory()->create([
            'size_id' => $this->size->id,
            'gas_refill_price' => 3200,
            'new_cylinder_price' => 4500,
            'new_gas_fill_price' => 3400,
            'delivery_fee' => 0,
        ]);
        StockLevel::factory()->create([
            'size_id' => $this->size->id,
            'filled_count' => 50,
        ]);

        $this->customer = Customer::factory()->create(['is_active' => true]);
        $this->address = CustomerAddress::factory()->create([
            'customer_id' => $this->customer->id,
        ]);
        $this->token = $this->customer->createToken('mobile')->plainTextToken;
    }

    private function placeOrder(int $quantity = 1): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/orders', [
                'order_type' => 'swap',
                'address_id' => $this->address->id,
                'payment_method' => 'mpesa',
                'items' => [[
                    'size_id' => $this->size->id,
                    'brand_id' => $this->brand->id,
                    'order_type' => 'swap',
                    'quantity' => $quantity,
                ]],
            ]);
    }

    public function test_a_first_app_order_is_discounted_without_being_asked(): void
    {
        $this->placeOrder()->assertSuccessful();

        $order = Order::firstOrFail();

        $this->assertSame(100, (int) $order->first_order_discount);
        // 3200 of gas, free delivery, less the hundred.
        $this->assertSame(3100.0, (float) $order->total_amount);
    }

    public function test_the_second_order_pays_full_price(): void
    {
        $this->placeOrder()->assertSuccessful();
        $this->placeOrder()->assertSuccessful();

        $second = Order::latest('id')->firstOrFail();

        $this->assertSame(0, (int) $second->first_order_discount);
        $this->assertSame(3200.0, (float) $second->total_amount);
    }

    public function test_an_order_below_the_minimum_gets_nothing(): void
    {
        SystemSetting::set('first_order_discount_min_order', '5000');

        $this->placeOrder()->assertSuccessful();

        // A new account must not be the cheap way to buy one small thing.
        $this->assertSame(0, (int) Order::firstOrFail()->first_order_discount);
    }

    public function test_a_cancelled_first_order_leaves_the_offer_intact(): void
    {
        $this->placeOrder()->assertSuccessful();
        Order::firstOrFail()->update(['status' => OrderLifecycle::STATUS_CANCELLED]);

        $this->placeOrder()->assertSuccessful();

        // Nobody gains by cancelling — no gas arrives either way — so a first
        // order that fell through should not cost the customer the offer.
        $this->assertSame(
            100,
            (int) Order::latest('id')->firstOrFail()->first_order_discount,
        );
    }

    public function test_an_order_the_shop_took_by_phone_neither_earns_nor_spends_it(): void
    {
        // Placed for the customer over the counter, before they ever used the
        // app. The offer is for app orders, so this one pays full price...
        Order::factory()->create([
            'customer_id' => $this->customer->id,
            'channel' => 'phone',
            'status' => OrderLifecycle::STATUS_DELIVERED,
            'first_order_discount' => 0,
        ]);

        $this->placeOrder()->assertSuccessful();

        // ...and their first app order still gets it.
        $appOrder = Order::where('channel', 'app')->firstOrFail();
        $this->assertSame(100, (int) $appOrder->first_order_discount);
    }

    public function test_the_home_screen_advertises_it_until_it_is_used(): void
    {
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/home')
            ->assertSuccessful()
            ->assertJsonPath('first_order_discount.amount', 100)
            ->assertJsonPath('first_order_discount.min_order', 1000);

        $this->placeOrder()->assertSuccessful();

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/home')
            ->assertSuccessful()
            ->assertJsonPath('first_order_discount.amount', 0);
    }

    public function test_switching_the_promotion_off_stops_it(): void
    {
        SystemSetting::set('first_order_discount', '0');

        $this->placeOrder()->assertSuccessful();

        $this->assertSame(0, (int) Order::firstOrFail()->first_order_discount);
        $this->assertSame(3200.0, (float) Order::firstOrFail()->total_amount);
    }
}
