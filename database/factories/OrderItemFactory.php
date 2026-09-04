<?php

namespace Database\Factories;

use App\Models\CylinderSize;
use App\Models\GasBrand;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        $gas = fake()->numberBetween(1500, 4000);
        $cylinder = 0;

        return [
            'order_id' => Order::factory(),
            'size_id' => CylinderSize::factory(),
            'brand_id' => GasBrand::factory(),
            'order_type' => 'swap',
            'quantity' => 1,
            'gas_price' => $gas,
            'cylinder_price' => $cylinder,
            'line_total' => $gas + $cylinder,
        ];
    }
}
