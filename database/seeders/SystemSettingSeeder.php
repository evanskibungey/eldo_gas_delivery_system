<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'app_name'            => 'EldoGas',
            'shop_always_open'    => '1',
            'shop_open_time'      => '07:00',
            'shop_close_time'     => '21:00',
            'delivery_fee_mode'   => 'per_size',
            'delivery_base_fee'   => '0.00',
            'delivery_per_km_fee' => '0.00',
            'shop_lat'            => '',
            'shop_lng'            => '',
            'commission_rate'     => '10.00',
        ];

        foreach ($defaults as $key => $value) {
            SystemSetting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
