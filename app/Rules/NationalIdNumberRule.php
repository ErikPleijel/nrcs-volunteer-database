<?php

namespace App\Rules;

use App\Models\User;
use App\Support\NationalIdNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

/**
 * Format + uniqueness for users.national_id_number. Rule::unique can't be
 * used: the column is encrypted (every ciphertext differs), so uniqueness
 * is checked on national_id_number_hash instead.
 *
 * Not implicit, so it only runs when a value was given. The NIN is
 * optional on every form (NIMC coverage is far from universal among the
 * people NRCS serves), so pair it with 'nullable'.
 */
class NationalIdNumberRule implements ValidationRule
{
    public const FORMAT_MESSAGE = 'The National ID number (NIN) must be exactly 11 digits.';

    public const TAKEN_MESSAGE = 'This National ID number (NIN) is already registered to another account.';

    public const ARCHIVED_MESSAGE = 'This National ID number (NIN) belongs to an account that has been deactivated. '
        .'If it is yours, contact your branch administrator to reactivate it instead of registering again.';

    /**
     * @param  int|null  $ignoreUserId  the user being edited, so their own unchanged NIN passes
     * @param  bool  $explainArchived  public registration: say so when the match is an archived account
     */
    public function __construct(
        private ?int $ignoreUserId = null,
        private bool $explainArchived = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value)) {
            $fail(self::FORMAT_MESSAGE);

            return;
        }

        $normalized = NationalIdNumber::normalize((string) $value);

        if (! NationalIdNumber::isValid($normalized)) {
            $fail(self::FORMAT_MESSAGE);

            return;
        }

        $existing = User::where('national_id_number_hash', NationalIdNumber::hash($normalized))
            ->when($this->ignoreUserId, fn ($q, $id) => $q->whereKeyNot($id))
            ->first(['id', 'lifecycle_status']);

        if (! $existing) {
            return;
        }

        $fail($this->explainArchived && $existing->lifecycle_status === 'archived'
            ? self::ARCHIVED_MESSAGE
            : self::TAKEN_MESSAGE);
    }

    /**
     * Turn the unique-index violation on national_id_number_hash — two
     * near-simultaneous submissions that both passed validation — into the
     * same friendly error. Any other query exception is left for the caller
     * to rethrow.
     */
    public static function rethrowIfDuplicate(QueryException $e): void
    {
        if (($e->errorInfo[0] ?? null) === '23000' && str_contains($e->getMessage(), 'national_id_number_hash')) {
            throw ValidationException::withMessages([
                'national_id_number' => [self::TAKEN_MESSAGE],
            ]);
        }
    }
}
