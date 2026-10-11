<?php

namespace App\Support;

/**
 * Formats an amount in kobo as naira with two decimals ("₦12,345.67"),
 * using integer arithmetic only, so a sum of kobo never picks up float
 * rounding on the way to the screen or a CSV.
 */
class Naira
{
    public static function format(int $kobo, bool $symbol = true): string
    {
        $sign = $kobo < 0 ? '-' : '';
        $kobo = abs($kobo);

        $naira = number_format(intdiv($kobo, 100)).'.'.str_pad((string) ($kobo % 100), 2, '0', STR_PAD_LEFT);

        return $sign.($symbol ? '₦' : '').$naira;
    }

    /**
     * A plain decimal for CSV cells ("12345.67"): no symbol and no
     * thousands separators, so spreadsheets read it as a number.
     */
    public static function decimal(int $kobo): string
    {
        $sign = $kobo < 0 ? '-' : '';
        $kobo = abs($kobo);

        return $sign.intdiv($kobo, 100).'.'.str_pad((string) ($kobo % 100), 2, '0', STR_PAD_LEFT);
    }
}
