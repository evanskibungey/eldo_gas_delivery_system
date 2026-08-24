<?php

namespace Database\Factories;

use App\Models\CustomerAddress;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    /**
     * Eldoret CBD — the centre of the only area this business delivers to.
     * Keep generated addresses near it: the previous box spanned roughly
     * 220 km and put most fixtures well outside the service area (a longitude
     * of 36.5 is Nairobi), which is not a shape any real customer record has.
     */
    private const CENTRE_LAT = 0.5143;
    private const CENTRE_LNG = 35.2698;

    public function definition(): array
    {
        return [
            'customer_id' => \App\Models\Customer::factory(),
            'label'       => fake()->randomElement(['Home', 'Office', 'Shop']),
            // ±0.09° is roughly ±10 km, comfortably inside the service area.
            'latitude'    => fake()->randomFloat(6, self::CENTRE_LAT - 0.09, self::CENTRE_LAT + 0.09),
            'longitude'   => fake()->randomFloat(6, self::CENTRE_LNG - 0.09, self::CENTRE_LNG + 0.09),
            'description' => fake()->streetAddress(),
            'is_default'  => false,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }

    /** Mombasa — far outside anywhere riders go. */
    public function outsideServiceArea(): static
    {
        return $this->state([
            'latitude'  => -4.0435,
            'longitude' => 39.6682,
        ]);
    }
}
