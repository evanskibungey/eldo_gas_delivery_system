<?php

namespace Database\Factories;

use App\Models\StockLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockLevelFactory extends Factory
{
    protected $model = StockLevel::class;

    public function definition(): array
    {
        return [
            'size_id'             => \App\Models\CylinderSize::factory(),
            'filled_count'        => fake()->numberBetween(10, 100),
            'empty_count'         => fake()->numberBetween(0, 20),
            'low_stock_threshold' => 5,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(['filled_count' => 0]);
    }
}
