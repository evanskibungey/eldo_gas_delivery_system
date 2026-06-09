<?php

namespace App\Services;

use App\Models\SystemSetting;
use Carbon\Carbon;

class ShopHoursService
{
    public function status(?Carbon $now = null): array
    {
        if ($this->isAlwaysOpen()) {
            return [
                'open'        => true,
                'opens_at'    => '00:00',
                'closes_at'   => '23:59',
                'next_open'   => null,
                'always_open' => true,
            ];
        }

        $openTime  = SystemSetting::get('shop_open_time', '07:00');
        $closeTime = SystemSetting::get('shop_close_time', '21:00');

        $timezone = config('app.timezone', 'Africa/Nairobi');
        $now = $now?->copy()->setTimezone($timezone) ?? Carbon::now($timezone);

        [$openHour, $openMinute]   = array_map('intval', explode(':', $openTime));
        [$closeHour, $closeMinute] = array_map('intval', explode(':', $closeTime));

        $opensAtToday  = $now->copy()->setTime($openHour, $openMinute);
        $closesAtToday = $now->copy()->setTime($closeHour, $closeMinute);

        $isOpen = $now->betweenIncluded($opensAtToday, $closesAtToday);

        $nextOpen = null;
        if (! $isOpen) {
            $nextOpen = $now->lessThan($opensAtToday)
                ? $opensAtToday
                : $opensAtToday->copy()->addDay();
        }

        return [
            'open'        => $isOpen,
            'opens_at'    => $openTime,
            'closes_at'   => $closeTime,
            'next_open'   => $nextOpen?->toIso8601String(),
            'always_open' => false,
        ];
    }

    public function isOpen(?Carbon $now = null): bool
    {
        if ($this->isAlwaysOpen()) return true;

        return $this->status($now)['open'];
    }

    private function isAlwaysOpen(): bool
    {
        return (bool) SystemSetting::get('shop_always_open', '1');
    }
}
