<?php

namespace Database\Factories;

use App\Models\Rider;
use Illuminate\Database\Eloquent\Factories\Factory;

class RiderFactory extends Factory
{
    protected $model = Rider::class;

    public function definition(): array
    {
        return [
            'name'                => fake()->name(),
            'phone'               => '+2547' . fake()->numerify('########'),
            'national_id'         => fake()->numerify('########'),
            'is_safety_certified' => true,
            'certification_date'  => now()->subMonths(6),
            'is_active'           => true,
            'is_available'        => true,
            'avg_rating'          => 4.5,
            'total_deliveries'    => 0,
            'incident_count'      => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function unavailable(): static
    {
        return $this->state(['is_available' => false]);
    }
}
