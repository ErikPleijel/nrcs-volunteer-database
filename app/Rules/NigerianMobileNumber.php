<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Nigerian mobile number (see PhoneNumber::toE164), local 0-prefixed or +234.
 *
 * Pass the currently stored value when editing an existing user: an unchanged value
 * always passes, so a legacy invalid number never blocks an unrelated profile edit.
 * Empty values pass — combine with required/nullable as usual.
 */
class NigerianMobileNumber implements ValidationRule
{
    public function __construct(private readonly ?string $current = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = is_scalar($value) ? (string) $value : '';

        if (trim($value) === '') {
            return;
        }

        if ($this->current !== null && PhoneNumber::stripFormatting($value) === PhoneNumber::stripFormatting($this->current)) {
            return;
        }

        if (PhoneNumber::toE164($value) === null) {
            $fail('Enter a Nigerian mobile number, for example 0803 123 4567 or +234 803 123 4567. Foreign and landline numbers are not accepted.');
        }
    }
}
