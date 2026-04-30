<?php

namespace Database\Factories;

use App\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    public function definition(): array
    {
        return [
            'customer_id' => \App\Models\Customer::factory(),
            'label'       => fake()->randomElement(['Home', 'Office', 'Shop']),
            'latitude'    => fake()->latitude(-1.5, 0.5),
            'longitude'   => fake()->longitude(35.5, 36.5),
            'description' => fake()->streetAddress(),
            'is_default'  => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
