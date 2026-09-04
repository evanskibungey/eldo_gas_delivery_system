<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CylinderPrice;
use App\Models\CylinderSize;
use App\Models\GasBrand;
use App\Models\Order;
use App\Models\StockLevel;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ordering several cylinders at once.
 *
 * The endpoint takes two shapes: an items[] basket, and the bare size_id and
 * brand_id the app in production posts. Both have to keep working, so most of
 * what follows is about the two agreeing.
 */
class MultiItemOrderTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: CylinderSize, 1: GasBrand} */
    private function cylinder(string $name, int $refill, int $fee, int $stock = 20): array
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
        StockLevel::factory()->create([
            'size_id' => $size->id,
            'filled_count' => $stock,
        ]);

        return [$size, $brand];
    }

    /** @return array{0: CustomerAddress, 1: string} */
    private function actor(): array
    {
        $customer = Customer::factory()->create(['is_active' => true]);
        $address = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

        return [$address, $customer->createToken('mobile')->plainTextToken];
    }

    public function test_a_basket_of_different_cylinders_becomes_one_order(): void
    {
        [$small, $smallBrand] = $this->cylinder('6kg', 1500, 100);
        [$large, $largeBrand] = $this->cylinder('13kg', 3200, 150);
        [$address, $token] = $this->actor();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/orders', [
                'order_type' => 'swap',
                'address_id' => $address->id,
                'payment_method' => 'mpesa',
                'items' => [
                    ['size_id' => $small->id, 'brand_id' => $smallBrand->id, 'order_type' => 'swap', 'quantity' => 1],
                    ['size_id' => $large->id, 'brand_id' => $largeBrand->id, 'order_type' => 'swap', 'quantity' => 2],
                ],
            ])
            ->assertSuccessful();

        $order = Order::with('items')->firstOrFail();
        $this->assertCount(2, $order->items);
        $this->assertSame(3, $order->cylinderCount());

        // 1500 + (3200 x 2) = 7900 of gas, and one delivery fee for the trip
        // rather than one per cylinder. Under per-size pricing the basket
        // takes the dearest line's fee, so a 6kg never makes a 13kg cheaper
        // to deliver.
        $this->assertSame(150, (int) $order->delivery_fee);
        $this->assertSame(8050, (int) $order->total_amount);
    }

    public function test_the_same_cylinder_twice_becomes_one_line(): void
    {
        [$size, $brand] = $this->cylinder('13kg', 3200, 150);
        [$address, $token] = $this->actor();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/orders', [
                'order_type' => 'swap',
                'address_id' => $address->id,
                'payment_method' => 'cash',
                'items' => [
                    ['size_id' => $size->id, 'brand_id' => $brand->id, 'order_type' => 'swap', 'quantity' => 1],
                    ['size_id' => $size->id, 'brand_id' => $brand->id, 'order_type' => 'swap', 'quantity' => 2],
                ],
            ])
            ->assertSuccessful();

        // Merged, not repeated. The unique key would reject the second row,
        // but a customer should get EldoGas 13kg x3 rather than an error.
        $order = Order::with('items')->firstOrFail();
        $this->assertCount(1, $order->items);
        $this->assertSame(3, (int) $order->items->first()->quantity);
    }

    public function test_the_same_size_in_two_brands_stays_two_lines(): void
    {
        [$size, $eldo] = $this->cylinder('13kg', 3200, 150);
        $total = GasBrand::factory()->create(['name' => 'Total']);
        $size->brands()->attach($total->id);
        [$address, $token] = $this->actor();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/orders', [
                'order_type' => 'swap',
                'address_id' => $address->id,
                'payment_method' => 'cash',
                'items' => [
                    ['size_id' => $size->id, 'brand_id' => $eldo->id, 'order_type' => 'swap'],
                    ['size_id' => $size->id, 'brand_id' => $total->id, 'order_type' => 'swap'],
                ],
            ])
            ->assertSuccessful();

        $this->assertCount(2, Order::with('items')->firstOrFail()->items);
    }

    public function test_one_basket_can_mix_a_refill_and_a_new_cylinder(): void
    {
        [$size, $brand] = $this->cylinder('13kg', 3200, 150);
        [$address, $token] = $this->actor();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/orders', [
                'order_type' => 'swap',
                'address_id' => $address->id,
                'payment_method' => 'cash',
                'items' => [
                    ['size_id' => $size->id, 'brand_id' => $brand->id, 'order_type' => 'swap'],
                    ['size_id' => $size->id, 'brand_id' => $brand->id, 'order_type' => 'new_cylinder'],
                ],
            ])
            ->assertSuccessful();

        // order_type lives on the line, which is the whole reason this is
        // expressible. The new cylinder carries its deposit; the refill does
        // not: 3200 + (3400 + 4500) + 150 delivery.
        $order = Order::with('items')->firstOrFail();
        $this->assertCount(2, $order->items);
        $this->assertSame(11250, (int) $order->total_amount);
    }

    public function test_a_line_is_refused_when_the_shop_is_short(): void
    {
        [$size, $brand] = $this->cylinder('13kg', 3200, 150, stock: 2);
        [$address, $token] = $this->actor();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/orders', [
                'order_type' => 'swap',
                'address_id' => $address->id,
                'payment_method' => 'cash',
                'items' => [
                    ['size_id' => $size->id, 'brand_id' => $brand->id, 'order_type' => 'swap', 'quantity' => 3],
                ],
            ])
            ->assertStatus(422);

        // Names the cylinder and the number left. "This cylinder size is out
        // of stock" leaves a customer with four lines guessing which.
        $this->assertStringContainsString('13kg', $response->json('message'));
        $this->assertStringContainsString('2 left', $response->json('message'));
        $this->assertSame(0, Order::count());
    }

    public function test_a_brand_must_be_sold_in_the_cylinder_it_is_paired_with(): void
    {
        [$size, $brand] = $this->cylinder('13kg', 3200, 150);
        [$other] = $this->cylinder('6kg', 1500, 100);
        [$address, $token] = $this->actor();

        // Checking only the lead line would let a basket smuggle this past.
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/orders', [
                'order_type' => 'swap',
                'address_id' => $address->id,
                'payment_method' => 'cash',
                'items' => [
                    ['size_id' => $size->id, 'brand_id' => $brand->id, 'order_type' => 'swap'],
                    ['size_id' => $other->id, 'brand_id' => $brand->id, 'order_type' => 'swap'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('brand_id');
    }

    public function test_the_old_single_cylinder_shape_still_works(): void
    {
        [$size, $brand] = $this->cylinder('13kg', 3200, 150);
        [$address, $token] = $this->actor();

        // What the build in production posts, and will keep posting until
        // everyone updates. It has to stay a first-class shape, not a
        // deprecated one.
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/orders', [
                'order_type' => 'swap',
                'size_id' => $size->id,
                'brand_id' => $brand->id,
                'address_id' => $address->id,
                'payment_method' => 'mpesa',
            ])
            ->assertSuccessful();

        $order = Order::with('items')->firstOrFail();
        $this->assertCount(1, $order->items);
        $this->assertSame(1, (int) $order->items->first()->quantity);
        $this->assertSame(3350, (int) $order->total_amount);

        // And the legacy columns still mirror it, because most of the
        // codebase still reads them.
        $this->assertSame($size->id, $order->size_id);
        $this->assertSame(3200, (int) $order->gas_price);
    }

    public function test_a_flat_rate_is_charged_once_for_the_whole_basket(): void
    {
        [$small, $smallBrand] = $this->cylinder('6kg', 1500, 100);
        [$large, $largeBrand] = $this->cylinder('13kg', 3200, 150);
        [$address, $token] = $this->actor();

        SystemSetting::updateOrCreate(['key' => 'delivery_fee_mode'], ['value' => 'flat_rate']);
        SystemSetting::updateOrCreate(['key' => 'delivery_base_fee'], ['value' => '0']);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/orders', [
                'order_type' => 'swap',
                'address_id' => $address->id,
                'payment_method' => 'cash',
                'items' => [
                    ['size_id' => $small->id, 'brand_id' => $smallBrand->id, 'order_type' => 'swap'],
                    ['size_id' => $large->id, 'brand_id' => $largeBrand->id, 'order_type' => 'swap'],
                ],
            ])
            ->assertSuccessful();

        // A basket is one journey, charged once — and a shop that names zero
        // has chosen free delivery rather than forgotten to set a fee.
        $order = Order::firstOrFail();
        $this->assertSame(0, (int) $order->delivery_fee);
        $this->assertSame(4700, (int) $order->total_amount);
    }
}
