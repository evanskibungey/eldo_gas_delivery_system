<?php

namespace Database\Factories;

use App\Models\CylinderPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

class CylinderPriceFactory extends Factory
{
    protected $model = CylinderPrice::class;

    public function definition(): array
    {
        return [
            'size_id'            => \App\Models\CylinderSize::factory(),
            'gas_refill_price'   => fake()->numberBetween(1000, 5000),
            'new_cylinder_price' => fake()->numberBetween(3000, 8000),
            'new_gas_fill_price' => fake()->numberBetween(1000, 5000),
            'delivery_fee'       => 200,
        ];
    }
}
