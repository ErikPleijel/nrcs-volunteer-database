<?php

namespace App\Support\Filters;

use App\Support\DbNumber;

/**
 * What a users-list search term is, decided by its shape (see parse()):
 *
 * - DB code:   1–9 digits, or a 'DB'/'DB-' prefix → users.id, exact.
 * - Phone:     10 digits starting 7/8/9 (no trunk 0), or 234… / +234…
 *              (13 digits) → telephone1/2, exact after normalising.
 * - Phone/NIN: 11 digits → NIN (keyed hash) and telephone1/2, exact.
 * - Bad number: any other run of digits → matches nobody.
 * - Email:     contains '@' → email, matching from the start.
 * - Text:      anything else → name parts only, never email.
 *
 * Spaces, dashes, dots and brackets inside a number are ignored.
 */
final class ParsedSearch
{
    public const DB_CODE = 'db_code';

    public const PHONE = 'phone';

    public const PHONE_OR_NIN = 'phone_or_nin';

    public const BAD_NUMBER = 'bad_number';

    public const EMAIL = 'email';

    public const TEXT = 'text';

    /**
     * @param  string  $value  the id (DB code), the digits (phone, phone/NIN),
     *                         or the trimmed term (everything else)
     */
    private function __construct(public readonly string $type, public readonly string $value) {}

    public static function parse(string $raw): self
    {
        $term = trim($raw);

        // 'DB-2246', 'DB 22 46', 'db2246', 'DB-2246-BRANCH-DIV' (card forms).
        if (preg_match('/^DB/i', $term) && ($id = DbNumber::parse($term)) !== null) {
            return new self(self::DB_CODE, (string) $id);
        }

        // \p{Z} = Unicode spaces (NBSP, thin space); \p{Pd} = every dash.
        $compact = preg_replace('/[\s\p{Z}\p{Pd}.()]+/u', '', $term);

        // [0-9], not \d: under /u PHP's \d also matches non-ASCII digits.
        if ($compact !== null && preg_match('/^(\+?)([0-9]+)$/', $compact, $m)) {
            [, $plus, $digits] = $m;
            $length = strlen($digits);

            if ($plus === '' && $length <= 9) {
                return (int) $digits > 0
                    ? new self(self::DB_CODE, (string) (int) $digits)
                    : new self(self::BAD_NUMBER, $digits);
            }
            if ($plus === '' && $length === 10 && preg_match('/^[789]/', $digits)) {
                return new self(self::PHONE, $digits);
            }
            if ($plus === '' && $length === 11) {
                return new self(self::PHONE_OR_NIN, $digits);
            }
            if ($length === 13 && str_starts_with($digits, '234')) {
                return new self(self::PHONE, $digits);
            }

            return new self(self::BAD_NUMBER, $digits);
        }

        return new self(str_contains($term, '@') ? self::EMAIL : self::TEXT, $term);
    }

    /**
     * A phone number or NIN: shown masked and stored encrypted (see SearchTerm).
     */
    public function isSensitive(): bool
    {
        return $this->type === self::PHONE || $this->type === self::PHONE_OR_NIN;
    }

    /**
     * Every way the number may sit in telephone1/2 once spaces, dashes, dots,
     * '+', commas and brackets are stripped from the stored value: 0803…,
     * 803…, 234803… and 2340803…. An 11-digit term without a leading 0 can
     * only be a NIN, or a phone stored exactly as typed.
     *
     * @return list<string>
     */
    public function phoneForms(): array
    {
        $digits = $this->value;

        $national = match (true) {
            $this->type === self::PHONE && strlen($digits) === 13 => substr($digits, 3),
            $this->type === self::PHONE => $digits,
            $this->type === self::PHONE_OR_NIN && str_starts_with($digits, '0') => substr($digits, 1),
            $this->type === self::PHONE_OR_NIN => null,
            default => throw new \LogicException('Not a phone search.'),
        };

        if ($national === null) {
            return [$digits];
        }

        return ['0'.$national, $national, '234'.$national, '2340'.$national];
    }
}
