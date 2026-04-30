<?php

namespace Database\Seeders;

use App\Models\CylinderSize;
use Illuminate\Database\Seeder;

class CylinderSizeSeeder extends Seeder
{
    public function run(): void
    {
        $sizes = [
            ['name' => '3kg',  'weight_kg' => 3.0,  'sort_order' => 1, 'is_commercial' => false],
            ['name' => '6kg',  'weight_kg' => 6.0,  'sort_order' => 2, 'is_commercial' => false],
            ['name' => '13kg', 'weight_kg' => 13.0, 'sort_order' => 3, 'is_commercial' => false],
            ['name' => '25kg', 'weight_kg' => 25.0, 'sort_order' => 4, 'is_commercial' => true],
            ['name' => '50kg', 'weight_kg' => 50.0, 'sort_order' => 5, 'is_commercial' => true],
        ];

        foreach ($sizes as $size) {
            CylinderSize::firstOrCreate(['name' => $size['name']], $size + ['is_active' => true]);
        }
    }
}
