<?php

namespace App\Support\Filters;

use App\Support\NationalIdNumber;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * The users-list search term, kept private when it may be a National ID
 * number.
 *
 * Any 11-digit term (after stripping spaces, dashes and dots) is treated
 * alike, since a NIN and a Nigerian phone number (080…) can't be told
 * apart: on screen it is shown masked, with a generic label, and in a saved
 * campaign (filter_json) it is stored encrypted under ENCRYPTED_KEY in
 * place of the plain 'search', so the audience can still be rebuilt with
 * the exact same search without the full number sitting in
 * messaging_campaigns.
 */
class SearchTerm
{
    public const ENCRYPTED_KEY = 'search_encrypted';

    /**
     * The search term a filter array stands for: the plain 'search', or
     * the decrypted copy a saved campaign keeps instead. '' when none.
     */
    public static function from(array $filters): string
    {
        $plain = trim((string) ($filters['search'] ?? ''));
        if ($plain !== '') {
            return $plain;
        }

        $encrypted = $filters[self::ENCRYPTED_KEY] ?? null;
        if (! is_string($encrypted) || $encrypted === '') {
            return '';
        }

        try {
            return trim(Crypt::decryptString($encrypted));
        } catch (DecryptException) {
            return '';
        }
    }

    /**
     * 11 digits once separators are stripped — a NIN or a phone number.
     * Same normalization and digit check as a NIN, but no claim that it is one.
     */
    public static function isElevenDigits(string $search): bool
    {
        return NationalIdNumber::isValid(NationalIdNumber::normalize($search));
    }

    /**
     * Label for the filter summary: 'Search: ●●●●●●●●901' for an
     * 11-digit term, 'Search: "…"' otherwise.
     */
    public static function label(string $search): string
    {
        if (self::isElevenDigits($search)) {
            $normalized = NationalIdNumber::normalize($search);

            return 'Search: '.str_repeat('●', 8).substr($normalized, -3);
        }

        return 'Search: "'.$search.'"';
    }

    /**
     * Filters ready to be stored as campaign filter_json: an 11-digit
     * 'search' is swapped for its encrypted copy.
     */
    public static function forStorage(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search === '' || ! self::isElevenDigits($search)) {
            return $filters;
        }

        unset($filters['search']);
        $filters[self::ENCRYPTED_KEY] = Crypt::encryptString($search);

        return $filters;
    }
}
