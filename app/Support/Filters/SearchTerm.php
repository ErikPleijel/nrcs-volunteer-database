<?php

namespace App\Support\Filters;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * The users-list search term, kept private when it is a phone number or
 * National ID number.
 *
 * Every term ParsedSearch reads as a phone or NIN (10, 11 or 13 digits) is
 * treated alike, since an 11-digit NIN and a phone number (080…) can't be
 * told apart: on screen it is shown masked, with a generic label, and in a
 * saved campaign (filter_json) it is stored encrypted under ENCRYPTED_KEY in
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
     * A phone number or NIN (see ParsedSearch::isSensitive()).
     */
    public static function isSensitive(string $search): bool
    {
        return ParsedSearch::parse($search)->isSensitive();
    }

    /**
     * Label for the filter summary: 'Search: ●●●●●●●●901' for a phone or
     * NIN term, 'Search: "…"' otherwise.
     */
    public static function label(string $search): string
    {
        $parsed = ParsedSearch::parse($search);

        if ($parsed->isSensitive()) {
            return 'Search: '.str_repeat('●', 8).substr($parsed->value, -3);
        }

        return 'Search: "'.$search.'"';
    }

    /**
     * Filters ready to be stored as campaign filter_json: a phone or NIN
     * 'search' is swapped for its encrypted copy.
     */
    public static function forStorage(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search === '' || ! self::isSensitive($search)) {
            return $filters;
        }

        unset($filters['search']);
        $filters[self::ENCRYPTED_KEY] = Crypt::encryptString($search);

        return $filters;
    }
}
