<?php

namespace App\Support;

/**
 * How many SMS a message will actually be billed as.
 *
 * SMS is priced per segment, and the segment size depends on the alphabet:
 *
 *   GSM-7  : 160 characters alone, 153 each once a message is split
 *   UCS-2  :  70 characters alone,  67 each once a message is split
 *
 * A message drops to UCS-2 the moment it contains ONE character outside the
 * GSM-7 set. An em dash, a curly quote, an emoji — the sort of thing that
 * arrives by paste from a word processor — more than halves the capacity and
 * can turn a one-segment message into four. Nothing warns you; the bill just
 * grows.
 *
 * This is the authority for what a campaign costs before it is sent.
 */
final class SmsSegments
{
    /** GSM 03.38 basic character set. */
    private const GSM7 =
        "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡"
        ."ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";

    /** In GSM-7, but each costs two septets. */
    private const GSM7_EXTENDED = "^{}\\[~]|€";

    /**
     * @return array{
     *     encoding: string,
     *     units: int,
     *     segments: int,
     *     limit: int,
     *     remaining: int,
     *     offenders: array<int, string>
     * }
     */
    public static function measure(string $message): array
    {
        $basic = preg_split('//u', self::GSM7, -1, PREG_SPLIT_NO_EMPTY);
        $extended = preg_split('//u', self::GSM7_EXTENDED, -1, PREG_SPLIT_NO_EMPTY);
        $chars = preg_split('//u', $message, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $units = 0;
        $offenders = [];

        foreach ($chars as $char) {
            if (in_array($char, $basic, true)) {
                $units++;
            } elseif (in_array($char, $extended, true)) {
                $units += 2;
            } else {
                $offenders[$char] = $char;
            }
        }

        $gsm7 = $offenders === [];

        if (! $gsm7) {
            // The whole message is re-encoded, so it is measured in UTF-16 code
            // units — and anything above the BMP (every emoji) costs two.
            $units = 0;
            foreach ($chars as $char) {
                $units += mb_ord($char, 'UTF-8') > 0xFFFF ? 2 : 1;
            }
        }

        $single = $gsm7 ? 160 : 70;
        $perPart = $gsm7 ? 153 : 67;

        $segments = $units === 0 ? 0 : ($units <= $single ? 1 : (int) ceil($units / $perPart));
        $limit = $segments <= 1 ? $single : $segments * $perPart;

        return [
            'encoding' => $gsm7 ? 'GSM-7' : 'UCS-2',
            'units' => $units,
            'segments' => $segments,
            'limit' => $limit,
            'remaining' => max(0, $limit - $units),
            'offenders' => array_values($offenders),
        ];
    }

    public static function segments(string $message): int
    {
        return self::measure($message)['segments'];
    }

    /** Total segments billed for sending this message to this many people. */
    public static function totalSegments(string $message, int $recipients): int
    {
        return self::segments($message) * max(0, $recipients);
    }
}
