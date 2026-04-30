<?php

namespace Database\Factories;

use App\Models\GasBrand;
use Illuminate\Database\Eloquent\Factories\Factory;

class GasBrandFactory extends Factory
{
    protected $model = GasBrand::class;

    public function definition(): array
    {
        return [
            'name'      => fake()->company() . ' Gas',
            'is_active' => true,
        ];
    }
}
