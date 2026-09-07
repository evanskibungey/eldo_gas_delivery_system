/**
 * Client-side SMS segment counter, for live feedback as a message is typed.
 *
 * Mirrors App\Support\SmsSegments. The PHP class is authoritative — it is what
 * the server measures at send time and what the admin's confirmation is checked
 * against. This exists only so the count updates per keystroke instead of per
 * round trip. Keep the two in step; the composer also re-checks with the server
 * before anything is sent.
 *
 * SMS is billed per segment, and the segment size depends on the alphabet:
 *   GSM-7 : 160 alone, 153 each once split
 *   UCS-2 :  70 alone,  67 each once split
 *
 * One character outside GSM-7 — an em dash, a curly quote, an emoji — re-encodes
 * the whole message and more than halves its capacity.
 */

const GSM7 =
    '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?¡' +
    'ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';

/** In GSM-7, but each costs two septets. */
const GSM7_EXTENDED = '^{}\\[~]|€';

const BASIC = new Set(Array.from(GSM7));
const EXTENDED = new Set(Array.from(GSM7_EXTENDED));

export interface SmsMeasure {
    encoding:  'GSM-7' | 'UCS-2';
    units:     number;
    segments:  number;
    limit:     number;
    remaining: number;
    /** Characters that forced UCS-2, so the composer can name them. */
    offenders: string[];
}

export function measureSms(message: string): SmsMeasure {
    // Array.from splits by code point, so an emoji counts as one character
    // here rather than two stray surrogates.
    const chars = Array.from(message);

    let units = 0;
    const offenders = new Set<string>();

    for (const char of chars) {
        if (BASIC.has(char)) {
            units += 1;
        } else if (EXTENDED.has(char)) {
            units += 2;
        } else {
            offenders.add(char);
        }
    }

    const gsm7 = offenders.size === 0;

    if (!gsm7) {
        // Re-encoded whole. UCS-2 counts UTF-16 units, so anything above the
        // BMP (every emoji) costs two.
        units = 0;
        for (const char of chars) {
            units += (char.codePointAt(0) ?? 0) > 0xffff ? 2 : 1;
        }
    }

    const single = gsm7 ? 160 : 70;
    const perPart = gsm7 ? 153 : 67;

    const segments = units === 0 ? 0 : units <= single ? 1 : Math.ceil(units / perPart);
    const limit = segments <= 1 ? single : segments * perPart;

    return {
        encoding: gsm7 ? 'GSM-7' : 'UCS-2',
        units,
        segments,
        limit,
        remaining: Math.max(0, limit - units),
        offenders: [...offenders],
    };
}
