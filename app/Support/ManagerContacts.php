<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Single source of truth for the shop manager alert list.
 *
 * SHOP_MANAGER_PHONES is a comma-separated list, and the resolve-and-
 * normalize logic had been copy-pasted into three listeners/jobs while four
 * other call sites read a `shop.manager_phone` key that was never defined —
 * so those four silently paged nobody, including the P0 SOS alert.
 */
class ManagerContacts
{
    /**
     * Every configured manager number, normalized to +254 E.164.
     *
     * @return array<int, string>
     */
    public static function phones(): array
    {
        $raw = (string) config('shop.manager_phones', '');

        if (trim($raw) === '') {
            return [];
        }

        return Collection::make(explode(',', $raw))
            ->map(static fn (string $phone): string => static::normalize($phone))
            ->filter(static fn (string $phone): bool => $phone !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Local 07... numbers become +2547...; anything already in international
     * form is left as-is.
     */
    public static function normalize(string $phone): string
    {
        $phone = trim($phone);

        if ($phone === '') {
            return '';
        }

        return str_starts_with($phone, '0')
            ? '+254' . substr($phone, 1)
            : $phone;
    }
}
