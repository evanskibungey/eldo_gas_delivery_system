<?php

namespace Database\Factories;

use App\Models\CylinderSize;
use Illuminate\Database\Eloquent\Factories\Factory;

class CylinderSizeFactory extends Factory
{
    protected $model = CylinderSize::class;

    public function definition(): array
    {
        static $order = 1;

        return [
            'name'          => fake()->randomElement(['6kg', '13kg', '22.5kg', '50kg']),
            'weight_kg'     => fake()->randomFloat(1, 6, 50),
            'sort_order'    => $order++,
            'is_commercial' => false,
            'is_active'     => true,
        ];
    }
}
