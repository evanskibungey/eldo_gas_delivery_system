<?php

namespace Database\Seeders;

use App\Models\CylinderSize;
use App\Models\StockLevel;
use Illuminate\Database\Seeder;

class StockLevelSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            '3kg'  => ['filled_count' => 20, 'empty_count' => 5,  'low_stock_threshold' => 5],
            '6kg'  => ['filled_count' => 15, 'empty_count' => 3,  'low_stock_threshold' => 5],
            '13kg' => ['filled_count' => 10, 'empty_count' => 2,  'low_stock_threshold' => 3],
            '25kg' => ['filled_count' => 5,  'empty_count' => 1,  'low_stock_threshold' => 2],
            '50kg' => ['filled_count' => 3,  'empty_count' => 0,  'low_stock_threshold' => 2],
        ];

        foreach ($defaults as $sizeName => $data) {
            $size = CylinderSize::where('name', $sizeName)->first();
            if ($size) {
                StockLevel::updateOrCreate(['size_id' => $size->id], $data);
            }
        }
    }
}
