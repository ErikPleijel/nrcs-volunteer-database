<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * Canonicalise a phone number for exact comparison: strip formatting,
     * then strip AT MOST ONE of a leading '234' country code or a single
     * leading '0' trunk prefix. Deliberately not repeated/unbounded —
     * unlike ltrim($digits, '0'), this won't eat digits that just happen
     * to start with zero, and won't let a longer/unrelated number collide
     * on a shared digit suffix.
     *
     * Shared by phone login (LoginController) and the Telephone1 duplicate
     * report, so both agree on what "the same number" means.
     */
    public static function normalize(string $raw): string
    {
        $digits = preg_replace('/\D/', '', $raw);

        if (str_starts_with($digits, '234')) {
            return substr($digits, 3);
        }

        if (str_starts_with($digits, '0')) {
            return substr($digits, 1);
        }

        return $digits;
    }
}
