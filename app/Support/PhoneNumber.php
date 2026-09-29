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

    /**
     * Nigerian mobile number in E.164 (+234XXXXXXXXXX), or null when the value is not one.
     * For SMS sending only — stored numbers are never rewritten.
     *
     * Accepts 0XXXXXXXXXX, 234…, +234…, +2340…, 00234…, and the 10-digit national number
     * without its leading 0, with spaces, dashes, dots or brackets. The national number
     * must be 10 digits in an NCC mobile range (070, 080, 081, 090, 091). Anything else —
     * letters, two numbers in one field, landlines, foreign numbers — is null.
     */
    public static function toE164(?string $raw): ?string
    {
        $value = trim((string) $raw);

        if ($value === '' || ! preg_match('/^\+?[\d\s\-.()]+$/', $value)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value);

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '234')) {
            $digits = substr($digits, 3);
        }
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^(70|80|81|90|91)\d{8}$/', $digits) ? '+234'.$digits : null;
    }

    /**
     * Strip formatting (spaces, dashes, dots, brackets) typed into a phone field, keeping
     * the digits and any leading '+'. A local 0-prefixed number stays 0-prefixed.
     */
    public static function stripFormatting(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        return preg_replace('/[\s\-.()]/', '', trim($raw));
    }

    /**
     * stripFormatting() over the phone fields present in form input, ready to merge back
     * into the request before validation.
     */
    public static function stripFormattingFields(array $input, array $fields = ['telephone1', 'telephone2']): array
    {
        $out = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $input) && is_string($input[$field])) {
                $out[$field] = self::stripFormatting($input[$field]);
            }
        }

        return $out;
    }
}
