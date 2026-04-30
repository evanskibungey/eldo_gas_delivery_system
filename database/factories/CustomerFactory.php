<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name'               => fake()->name(),
            'phone'              => '+2547' . fake()->numerify('########'),
            'phone_verified_at'  => now(),
            'gaspoints_balance'  => 0,
            'referral_code'      => strtoupper(fake()->lexify('????????')),
            'is_active'          => true,
        ];
    }

    public function unverified(): static
    {
        return $this->state(['phone_verified_at' => null]);
    }
}
