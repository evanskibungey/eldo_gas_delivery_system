<?php

namespace Database\Factories;

use App\Models\AddonGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AddonGroup>
 */
class AddonGroupFactory extends Factory
{
    protected $model = AddonGroup::class;

    public function definition(): array
    {
        static $order = 1;

        return [
            // Left null by default: a group belongs to a cylinder size only
            // when a test says so. Universal is the simpler shape.
            'size_id'        => null,
            'name'           => fake()->randomElement(['Hoses', 'Regulators', 'Burners']),
            'selection_type' => 'multi',
            'sort_order'     => $order++,
            'is_active'      => true,
        ];
    }

    /** Scoped to one cylinder size, the way the catalogue used to require. */
    public function forSize(int $sizeId): static
    {
        return $this->state(fn () => ['size_id' => $sizeId]);
    }
}
