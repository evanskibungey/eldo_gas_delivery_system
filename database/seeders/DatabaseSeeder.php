<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            CylinderSizeSeeder::class,
            GasBrandSeeder::class,
            CylinderPriceSeeder::class,
            StockLevelSeeder::class,
            AddonGroupSeeder::class,
            SystemSettingSeeder::class,
        ]);
    }
}
