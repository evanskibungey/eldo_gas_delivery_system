<?php

namespace Database\Factories;

use App\Models\OtpToken;
use Illuminate\Database\Eloquent\Factories\Factory;

class OtpTokenFactory extends Factory
{
    protected $model = OtpToken::class;

    public function definition(): array
    {
        return [
            'phone'      => '+2547' . fake()->numerify('########'),
            'token'      => str_pad((string) fake()->numberBetween(0, 9999), 4, '0', STR_PAD_LEFT),
            'expires_at' => now()->addMinutes(10),
            'used_at'    => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subMinutes(1)]);
    }

    public function used(): static
    {
        return $this->state(['used_at' => now()->subMinutes(1)]);
    }
}
