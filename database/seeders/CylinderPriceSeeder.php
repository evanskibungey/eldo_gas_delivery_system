<?php

namespace Database\Seeders;

use App\Models\CylinderPrice;
use App\Models\CylinderSize;
use Illuminate\Database\Seeder;

class CylinderPriceSeeder extends Seeder
{
    public function run(): void
    {
        // Placeholder prices in KES — Admin will update these before launch
        $prices = [
            '3kg'  => ['gas_refill_price' => 750,   'new_cylinder_price' => 2000,  'new_gas_fill_price' => 750,   'delivery_fee' => 100],
            '6kg'  => ['gas_refill_price' => 1400,  'new_cylinder_price' => 3500,  'new_gas_fill_price' => 1400,  'delivery_fee' => 100],
            '13kg' => ['gas_refill_price' => 2800,  'new_cylinder_price' => 7000,  'new_gas_fill_price' => 2800,  'delivery_fee' => 150],
            '25kg' => ['gas_refill_price' => 5500,  'new_cylinder_price' => 13000, 'new_gas_fill_price' => 5500,  'delivery_fee' => 200],
            '50kg' => ['gas_refill_price' => 11000, 'new_cylinder_price' => 25000, 'new_gas_fill_price' => 11000, 'delivery_fee' => 300],
        ];

        foreach ($prices as $sizeName => $priceData) {
            $size = CylinderSize::where('name', $sizeName)->first();
            if ($size) {
                CylinderPrice::updateOrCreate(
                    ['size_id' => $size->id],
                    $priceData
                );
            }
        }
    }
}
