<?php

namespace App\Support;

use RuntimeException;

/**
 * Single source of truth for Nigerian National ID Numbers (NIMC NIN:
 * exactly 11 digits). Validation, the stored value and the uniqueness hash
 * all go through normalize(), so '12345678901' and '123 456 789-01' are
 * one and the same NIN.
 */
class NationalIdNumber
{
    /**
     * Strip everything people type around the digits: all whitespace
     * (including non-breaking and thin spaces), any dash character, and dots.
     * Does not check the result — see isValid().
     */
    public static function normalize(?string $raw): string
    {
        $raw = trim((string) $raw);

        // \p{Z} = Unicode spaces (NBSP, U+2009…); \p{Pd} = every dash (-, –, —, …).
        $normalized = preg_replace('/[\s\p{Z}\p{Pd}.]+/u', '', $raw);

        // null = invalid UTF-8: return it untouched so isValid() rejects it.
        return $normalized ?? $raw;
    }

    /**
     * Exactly 11 ASCII digits. [0-9], not \d: under /u PHP's \d also
     * matches non-ASCII digits (same reasoning as DbNumber::parse()).
     */
    public static function isValid(string $normalized): bool
    {
        return preg_match('/^[0-9]{11}$/', $normalized) === 1;
    }

    /**
     * Keyed hash (HMAC-SHA256) of a normalized NIN, stored in
     * users.national_id_number_hash to enforce uniqueness — the encrypted
     * column can't be compared, since every encryption differs. Keyed, not a
     * plain SHA-256: with only 10^11 possible NINs, an unkeyed hash could be
     * reversed by brute force. The key (NIN_HASH_KEY) must never change once
     * hashes exist, or every stored hash stops matching.
     */
    public static function hash(string $normalized): string
    {
        $key = (string) config('app.nin_hash_key');

        if ($key === '') {
            throw new RuntimeException(
                'NIN_HASH_KEY is not set. Add a long random secret to .env '
                .'(e.g. the output of: php -r "echo bin2hex(random_bytes(32));") '
                .'before saving or migrating National ID numbers.'
            );
        }

        return hash_hmac('sha256', $normalized, $key);
    }
}
