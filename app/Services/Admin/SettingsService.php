<?php

namespace App\Services\Admin;

use App\Models\Admin;
use App\Models\SystemSetting;
use App\Services\GasPointsService;
use Illuminate\Support\Facades\Auth;

class SettingsService
{
    public function __construct(private readonly GasPointsService $gasPoints) {}

    public function getAll(): array
    {
        $keys = [
            'app_name',
            'shop_always_open',
            'shop_open_time',
            'shop_close_time',
            'delivery_fee_mode',
            'delivery_base_fee',
            'delivery_per_km_fee',
            'shop_lat',
            'shop_lng',
            'commission_rate',
            'gaspoints_enabled',
            'gaspoints_earn_new_cylinder',
            'gaspoints_earn_swap',
            'gaspoints_earn_large_cylinder',
            'gaspoints_earn_welcome',
            'gaspoints_earn_review',
            'gaspoints_earn_referral',
            'gaspoints_earn_referral_third_order',
            'gaspoints_expiry_days',
            'gaspoints_min_order_amount',
            'gaspoints_referral_apply_window_days',
            'gaspoints_referral_reward_window_days',
            'gaspoints_referral_min_order_amount',
            'gaspoints_max_balance',
            'gaspoints_redemption_tiers',
        ];

        $stored = SystemSetting::getMany($keys);

        $merged = array_merge([
            'app_name' => 'EldoGas',
            'shop_always_open' => '1',
            'shop_open_time' => '07:00',
            'shop_close_time' => '21:00',
            'delivery_fee_mode' => 'per_size',
            'delivery_base_fee' => '0.00',
            'delivery_per_km_fee' => '0.00',
            'shop_lat' => '',
            'shop_lng' => '',
            'commission_rate' => '10.00',
            'gaspoints_enabled' => '1',
            'gaspoints_earn_new_cylinder' => '150',
            'gaspoints_earn_swap' => '100',
            'gaspoints_earn_large_cylinder' => '200',
            'gaspoints_earn_welcome' => '250',
            'gaspoints_earn_review' => '25',
            'gaspoints_earn_referral' => '250',
            'gaspoints_earn_referral_third_order' => '100',
            'gaspoints_expiry_days' => '365',
            'gaspoints_min_order_amount' => '0',
            'gaspoints_referral_apply_window_days' => '14',
            'gaspoints_referral_reward_window_days' => '90',
            'gaspoints_referral_min_order_amount' => '0',
            'gaspoints_max_balance' => '0',
            'gaspoints_redemption_tiers' => json_encode($this->gasPoints->redemptionTiersMap()),
        ], $stored);

        $tiers = json_decode((string) $merged['gaspoints_redemption_tiers'], true);
        if (! is_array($tiers) || empty($tiers)) {
            $tiers = $this->gasPoints->redemptionTiersMap();
        }

        $merged['gaspoints_redemption_tiers'] = collect($tiers)
            ->map(fn ($kes, $points) => ['points' => (int) $points, 'kes' => (int) $kes])
            ->values()
            ->sortBy('points')
            ->values()
            ->all();

        return $merged;
    }

    public function updateGeneral(array $data): void
    {
        SystemSetting::set('app_name', $data['app_name']);
    }

    public function updateShopHours(array $data): void
    {
        $alwaysOpen = (bool) ($data['always_open'] ?? false);
        SystemSetting::set('shop_always_open', $alwaysOpen ? '1' : '0');

        if (! $alwaysOpen) {
            SystemSetting::set('shop_open_time', $data['shop_open_time']);
            SystemSetting::set('shop_close_time', $data['shop_close_time']);
        }
    }

    public function updateDelivery(array $data): void
    {
        SystemSetting::set('delivery_fee_mode', $data['delivery_fee_mode']);
        SystemSetting::set('delivery_base_fee', $data['delivery_base_fee']);
        SystemSetting::set('delivery_per_km_fee', $data['delivery_per_km_fee']);
        SystemSetting::set('shop_lat', $data['shop_lat'] ?? '');
        SystemSetting::set('shop_lng', $data['shop_lng'] ?? '');
    }

    public function updateCommission(array $data): void
    {
        SystemSetting::set('commission_rate', $data['commission_rate']);
    }

    public function updatePoints(array $data): void
    {
        SystemSetting::set('gaspoints_enabled', ! empty($data['gaspoints_enabled']) ? '1' : '0');
        SystemSetting::set('gaspoints_earn_new_cylinder', (string) $data['gaspoints_earn_new_cylinder']);
        SystemSetting::set('gaspoints_earn_swap', (string) $data['gaspoints_earn_swap']);
        SystemSetting::set('gaspoints_earn_large_cylinder', (string) $data['gaspoints_earn_large_cylinder']);
        SystemSetting::set('gaspoints_earn_welcome', (string) $data['gaspoints_earn_welcome']);
        SystemSetting::set('gaspoints_earn_review', (string) $data['gaspoints_earn_review']);
        SystemSetting::set('gaspoints_earn_referral', (string) $data['gaspoints_earn_referral']);
        SystemSetting::set('gaspoints_earn_referral_third_order', (string) $data['gaspoints_earn_referral_third_order']);
        SystemSetting::set('gaspoints_expiry_days', (string) $data['gaspoints_expiry_days']);
        SystemSetting::set('gaspoints_min_order_amount', (string) $data['gaspoints_min_order_amount']);
        SystemSetting::set('gaspoints_referral_apply_window_days', (string) $data['gaspoints_referral_apply_window_days']);
        SystemSetting::set('gaspoints_referral_reward_window_days', (string) $data['gaspoints_referral_reward_window_days']);
        SystemSetting::set('gaspoints_referral_min_order_amount', (string) $data['gaspoints_referral_min_order_amount']);
        SystemSetting::set('gaspoints_max_balance', (string) $data['gaspoints_max_balance']);

        $tiers = collect($data['gaspoints_redemption_tiers'])
            ->mapWithKeys(fn ($tier) => [(int) $tier['points'] => (int) $tier['kes']])
            ->sortKeys()
            ->all();

        SystemSetting::set('gaspoints_redemption_tiers', json_encode($tiers));
    }

    public function updateAccount(array $data): void
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();
        $payload = [
            'name' => $data['name'],
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
            'name' => $admin->name,
            'email' => $admin->email,
        ];
    }
}
