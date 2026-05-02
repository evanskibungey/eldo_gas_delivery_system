<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Auth;

class SettingsService
{
    public function getAll(): array
    {
        $keys = [
            'app_name',
            'shop_open_time',
            'shop_close_time',
            'delivery_fee_mode',
            'delivery_base_fee',
            'delivery_per_km_fee',
            'shop_lat',
            'shop_lng',
            'commission_rate',
        ];

        $stored = SystemSetting::getMany($keys);

        return array_merge([
            'app_name'            => 'EldoGas',
            'shop_open_time'      => '07:00',
            'shop_close_time'     => '21:00',
            'delivery_fee_mode'   => 'per_size',
            'delivery_base_fee'   => '0.00',
            'delivery_per_km_fee' => '0.00',
            'shop_lat'            => '',
            'shop_lng'            => '',
            'commission_rate'     => '10.00',
        ], $stored);
    }

    public function updateGeneral(array $data): void
    {
        SystemSetting::set('app_name', $data['app_name']);
    }

    public function updateShopHours(array $data): void
    {
        SystemSetting::set('shop_open_time',  $data['shop_open_time']);
        SystemSetting::set('shop_close_time', $data['shop_close_time']);
    }

    public function updateDelivery(array $data): void
    {
        SystemSetting::set('delivery_fee_mode',   $data['delivery_fee_mode']);
        SystemSetting::set('delivery_base_fee',   $data['delivery_base_fee']);
        SystemSetting::set('delivery_per_km_fee', $data['delivery_per_km_fee']);
        SystemSetting::set('shop_lat',            $data['shop_lat'] ?? '');
        SystemSetting::set('shop_lng',            $data['shop_lng'] ?? '');
    }

    public function updateCommission(array $data): void
    {
        SystemSetting::set('commission_rate', $data['commission_rate']);
    }

    public function updateAccount(array $data): void
    {
        /** @var Admin $admin */
        $admin   = Auth::guard('admin')->user();
        $payload = [
            'name'  => $data['name'],
            'email' => $data['email'],
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $admin->update($payload);
    }

    public function currentAdmin(): array
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();
        return [
            'name'  => $admin->name,
            'email' => $admin->email,
        ];
    }
}
