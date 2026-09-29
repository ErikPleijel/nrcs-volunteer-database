<?php

namespace App\Support;

/**
 * How many SMS pages ("segments") a text needs.
 *
 * GSM-7 (the standard SMS alphabet): 160 characters in one page, 153 per page when split.
 * Characters from the GSM extension table (^ { } \ [ ] ~ | € and form feed) take two.
 * Any other character — Hausa letters such as ƙ ɗ ɓ, most accented letters, emoji — makes
 * the whole message UCS-2 (Unicode): 70 per page, 67 per page when split, counted in UTF-16
 * units (an emoji is 2).
 *
 * Approximation: a GSM extension character is never split across two pages by real
 * phones; that edge case is ignored.
 */
final class SmsSegments
{
    private const GSM_BASIC = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?"
        .'¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';

    private const GSM_EXTENSION = "^{}\\[~]|€\f";

    /**
     * @return array{encoding: string, chars: int, units: int, segments: int, per_segment: int, non_gsm: array<int, string>}
     */
    public static function analyse(string $text): array
    {
        static $basic = null, $extension = null;
        $basic ??= array_flip(mb_str_split(self::GSM_BASIC));
        $extension ??= array_flip(mb_str_split(self::GSM_EXTENSION));

        $chars = mb_str_split($text);
        $gsmUnits = 0;
        $nonGsm = [];

        foreach ($chars as $char) {
            if (isset($basic[$char])) {
                $gsmUnits++;
            } elseif (isset($extension[$char])) {
                $gsmUnits += 2;
            } else {
                $nonGsm[$char] = true;
            }
        }

        if ($nonGsm === []) {
            return self::result('GSM-7', count($chars), $gsmUnits, 160, 153, []);
        }

        $utf16Units = intdiv(strlen(mb_convert_encoding($text, 'UTF-16BE', 'UTF-8')), 2);

        return self::result('UCS-2', count($chars), $utf16Units, 70, 67, array_keys($nonGsm));
    }

    public static function isUnicode(string $text): bool
    {
        return self::analyse($text)['encoding'] === 'UCS-2';
    }

    private static function result(string $encoding, int $chars, int $units, int $single, int $multi, array $nonGsm): array
    {
        $segments = match (true) {
            $units === 0 => 0,
            $units <= $single => 1,
            default => (int) ceil($units / $multi),
        };

        return [
            'encoding' => $encoding,
            'chars' => $chars,
            'units' => $units,
            'segments' => $segments,
            'per_segment' => $segments > 1 ? $multi : $single,
            'non_gsm' => $nonGsm,
        ];
    }
}
