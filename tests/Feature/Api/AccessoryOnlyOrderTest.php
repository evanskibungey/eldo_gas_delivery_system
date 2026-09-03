<?php

namespace Tests\Feature\Api;

use App\Models\AddonGroup;
use App\Models\AddonItem;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CylinderPrice;
use App\Models\CylinderSize;
use App\Models\Order;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ordering accessories with no gas attached.
 *
 * Before this, an accessory belonged to exactly one cylinder size and an
 * order had to name a cylinder, so "just a hose" was unrepresentable at both
 * ends. These cover the new path and, as importantly, that the old one is
 * untouched — the app in production still posts gas orders the old way.
 */
class AccessoryOnlyOrderTest extends TestCase
{
    use RefreshDatabase;

    private function seedCatalogue(): array
    {
        $size = CylinderSize::factory()->create(['name' => '13kg', 'is_active' => true]);
        CylinderPrice::factory()->create([
            'size_id' => $size->id,
            'gas_refill_price' => 3200,
            'new_cylinder_price' => 4500,
            'new_gas_fill_price' => 3400,
            'delivery_fee' => 150,
        ]);

        // size_id null: belongs to no cylinder, sold on its own.
        $group = AddonGroup::factory()->create([
            'size_id' => null,
            'name' => 'Hoses',
            'is_active' => true,
        ]);
        $hose = AddonItem::factory()->create([
            'group_id' => $group->id,
            'name' => 'Hose 1.5m',
            'price' => 700,
            'is_active' => true,
        ]);

        return [$size, $group, $hose];
    }

    private function actor(): array
    {
        $customer = Customer::factory()->create(['is_active' => true]);
        $address = CustomerAddress::factory()->create(['customer_id' => $customer->id]);

        return [$customer, $address, $customer->createToken('mobile')->plainTextToken];
    }

    public function test_a_customer_can_order_an_accessory_with_no_cylinder(): void
    {
        [, , $hose] = $this->seedCatalogue();
        [$customer, $address, $token] = $this->actor();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/orders', [
                'order_type' => 'accessory',
                'address_id' => $address->id,
                'addon_ids' => [$hose->id],
                'payment_method' => 'mpesa',
            ])
            ->assertSuccessful();

        $order = Order::where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame('accessory', $order->order_type);
        $this->assertNull($order->size_id);
        $this->assertNull($order->brand_id);
        $this->assertSame(0, (int) $order->gas_price);
        $this->assertSame(0, (int) $order->cylinder_price);
        $this->assertSame(700, (int) $order->addons_total);
    }

    public function test_the_rider_is_still_paid_for_the_trip(): void
    {
        [, , $hose] = $this->seedCatalogue();
        [, $address, $token] = $this->actor();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/orders', [
                'order_type' => 'accessory',
                'address_id' => $address->id,
                'addon_ids' => [$hose->id],
                'payment_method' => 'cash',
            ])
            ->assertSuccessful();

        // delivery_base_fee defaults to '0.00', so without a deliberate floor
        // every accessory delivery would quietly be free. It falls back to
        // the cheapest cylinder's fee instead.
        $order = Order::firstOrFail();
        $this->assertSame(150, (int) $order->delivery_fee);
        $this->assertSame(850, (int) $order->total_amount);
    }

    public function test_an_explicit_setting_overrides_the_fallback_fee(): void
    {
        [, , $hose] = $this->seedCatalogue();
        [, $address, $token] = $this->actor();
        SystemSetting::updateOrCreate(
            ['key' => 'accessory_delivery_fee'],
            ['value' => '80'],
        );

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/orders', [
                'order_type' => 'accessory',
                'address_id' => $address->id,
                'addon_ids' => [$hose->id],
                'payment_method' => 'cash',
            ])
            ->assertSuccessful();

        $this->assertSame(80, (int) Order::firstOrFail()->delivery_fee);
    }

    public function test_an_accessory_order_must_actually_contain_one(): void
    {
        $this->seedCatalogue();
        [, $address, $token] = $this->actor();

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/orders', [
                'order_type' => 'accessory',
                'address_id' => $address->id,
                'payment_method' => 'mpesa',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('addon_ids');
    }

    public function test_a_gas_order_still_has_to_name_a_cylinder(): void
    {
        $this->seedCatalogue();
        [, $address, $token] = $this->actor();

        // The requirement was lifted for accessories only. Dropping it for
        // everything would let a swap through with no size and no price.
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/orders', [
                'order_type' => 'swap',
                'address_id' => $address->id,
                'payment_method' => 'mpesa',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['size_id', 'brand_id']);
    }

    public function test_universal_accessories_appear_under_every_size(): void
    {
        [$size] = $this->seedCatalogue();
        [, , $token] = $this->actor();

        $body = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/catalogue')
            ->assertOk()
            ->json();

        // Same group reachable both ways: attached to the size for the addons
        // step of a gas order, and standing alone for the accessories page.
        $groupNames = collect($body['data'])
            ->firstWhere('id', $size->id)['addon_groups'];
        $this->assertContains('Hoses', collect($groupNames)->pluck('name')->all());
        $this->assertContains('Hoses', collect($body['accessories'])->pluck('name')->all());
    }
}
