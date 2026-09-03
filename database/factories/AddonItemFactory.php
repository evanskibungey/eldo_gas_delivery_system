<?php

namespace Database\Factories;

use App\Models\AddonItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AddonItem>
 */
class AddonItemFactory extends Factory
{
    protected $model = AddonItem::class;

    public function definition(): array
    {
        static $order = 1;

        return [
            'name'       => fake()->randomElement(['Hose 1.5m', 'Regulator', 'Burner head']),
            'price'      => fake()->numberBetween(300, 2000),
            'sort_order' => $order++,
            'is_active'  => true,
        ];
    }
}
