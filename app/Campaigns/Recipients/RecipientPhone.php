<?php

namespace App\Campaigns\Recipients;

use App\Support\PhoneNumber;

/**
 * The SMS number a campaign recipient row gets, picked from a user's telephone1 and
 * telephone2. Shared by both recipient builders (CampaignAdminController::buildRecipients
 * and campaigns:build-recipients) so they agree. Stored user numbers are never changed.
 */
final class RecipientPhone
{
    public const INVALID_NUMBER_ERROR = 'No valid Nigerian mobile number in telephone1 or telephone2.';

    private function __construct(
        /** +234XXXXXXXXXX, or null when neither number is a valid Nigerian mobile. */
        public readonly ?string $e164,
        /** 'telephone1' | 'telephone2' — which field $e164 came from. */
        public readonly ?string $source,
        /** At least one number is filled in, but none is valid. */
        public readonly bool $invalid,
    ) {}

    /**
     * telephone1 when valid, else telephone2 when valid.
     */
    public static function pick(?string $telephone1, ?string $telephone2): self
    {
        foreach (['telephone1' => $telephone1, 'telephone2' => $telephone2] as $field => $raw) {
            if ($e164 = PhoneNumber::toE164($raw)) {
                return new self($e164, $field, false);
            }
        }

        $anyFilled = trim((string) $telephone1) !== '' || trim((string) $telephone2) !== '';

        return new self(null, null, $anyFilled);
    }

    /**
     * Whether this recipient can only be reached by SMS: always on an "sms" campaign, and
     * on "both" / "email_fallback_sms" when there is no usable email address.
     */
    public static function needsSms(string $channel, ?string $effectiveEmail): bool
    {
        return match ($channel) {
            'sms' => true,
            'both', 'email_fallback_sms' => trim((string) $effectiveEmail) === '',
            default => false,
        };
    }
}
