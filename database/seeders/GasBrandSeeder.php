<?php

namespace Database\Seeders;

use App\Models\CylinderSize;
use App\Models\GasBrand;
use Illuminate\Database\Seeder;

class GasBrandSeeder extends Seeder
{
    public function run(): void
    {
        $brands = ['Total', 'Rubis', 'Lake Gas', 'Hashi'];
        $sizes  = CylinderSize::all();

        foreach ($brands as $name) {
            $brand = GasBrand::firstOrCreate(['name' => $name], ['is_active' => true]);
            // All brands available in all sizes by default
            $brand->sizes()->sync($sizes->pluck('id'));
        }
    }
}
