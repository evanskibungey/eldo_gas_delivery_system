<?php

namespace App\Services;

use App\Models\SystemSetting;
use Carbon\Carbon;

class ShopHoursService
{
    public function status(?Carbon $now = null): array
    {
        $openTime = SystemSetting::get('shop_open_time', '07:00');
        $closeTime = SystemSetting::get('shop_close_time', '21:00');

        $timezone = config('app.timezone', 'Africa/Nairobi');
        $now = $now?->copy()->setTimezone($timezone) ?? Carbon::now($timezone);

        [$openHour, $openMinute] = array_map('intval', explode(':', $openTime));
        [$closeHour, $closeMinute] = array_map('intval', explode(':', $closeTime));

        $opensAtToday = $now->copy()->setTime($openHour, $openMinute);
        $closesAtToday = $now->copy()->setTime($closeHour, $closeMinute);

        $isOpen = $now->betweenIncluded($opensAtToday, $closesAtToday);

        $nextOpen = null;
        if (! $isOpen) {
            $nextOpen = $now->lessThan($opensAtToday)
                ? $opensAtToday
                : $opensAtToday->copy()->addDay();
        }

        return [
            'open' => $isOpen,
            'opens_at' => $openTime,
            'closes_at' => $closeTime,
            'next_open' => $nextOpen?->toIso8601String(),
        ];
    }

    public function isOpen(?Carbon $now = null): bool
    {
        return $this->status($now)['open'];
    }
}
