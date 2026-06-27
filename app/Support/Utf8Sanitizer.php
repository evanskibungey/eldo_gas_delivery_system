<?php

namespace App\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

class Utf8Sanitizer
{
    public static function clean(mixed $value): mixed
    {
        if ($value instanceof Collection) {
            return $value->map(fn ($item) => self::clean($item))->all();
        }

        if ($value instanceof Arrayable) {
            return self::clean($value->toArray());
        }

        if (is_array($value)) {
            $cleaned = [];

            foreach ($value as $key => $item) {
                $cleaned[self::cleanArrayKey($key)] = self::clean($item);
            }

            return $cleaned;
        }

        if (is_string($value)) {
            return self::cleanString($value);
        }

        return $value;
    }

    private static function cleanArrayKey(mixed $key): mixed
    {
        return is_string($key) ? self::cleanString($key) : $key;
    }

    private static function cleanString(string $value): string
    {
        if ($value === '' || preg_match('//u', $value) === 1) {
            return $value;
        }

        $cleanedUtf8 = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
        if (is_string($cleanedUtf8) && $cleanedUtf8 !== '' && preg_match('//u', $cleanedUtf8) === 1) {
            return $cleanedUtf8;
        }

        foreach (['Windows-1252', 'ISO-8859-1'] as $encoding) {
            $converted = @mb_convert_encoding($value, 'UTF-8', $encoding);

            if (is_string($converted) && $converted !== '' && preg_match('//u', $converted) === 1) {
                return $converted;
            }
        }

        return '';
    }
}