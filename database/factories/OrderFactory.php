<?php

namespace Database\Factories;

use App\Models\CylinderSize;
use App\Models\GasBrand;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_number'   => 'ORD-' . strtoupper(fake()->lexify('??????')),
            'customer_id'    => \App\Models\Customer::factory(),
            'size_id'        => CylinderSize::factory(),
            'brand_id'       => GasBrand::factory(),
            'order_type'     => 'swap',
            'status'         => 'pending',
            'gas_price'      => 1800,
            'cylinder_price' => 0,
            'delivery_fee'   => 200,
            'addons_total'   => 0,
            'total_amount'   => 2000,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'delivery_lat'   => -0.2833,
            'delivery_lng'   => 35.2697,
        ];
    }

    public function delivered(): static
    {
        return $this->state([
            'status'       => 'delivered',
            'delivered_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }

    public function withRider(): static
    {
        return $this->state([
            'rider_id'           => \App\Models\Rider::factory(),
            'status'             => 'rider_assigned',
            'rider_assigned_at'  => now(),
        ]);
    }
}
