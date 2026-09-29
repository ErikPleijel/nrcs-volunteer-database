<?php

namespace App\Campaigns\Recipients;

use App\Models\User;

/**
 * How one user can be reached by one campaign: opt-outs applied, phone converted to
 * E.164 (telephone1, else telephone2). Shared by CampaignRecipientBuilder and
 * SmsNumberPlanner so recipient rows and shared-number winners use the same rules.
 */
final class RecipientContact
{
    private function __construct(
        public readonly ?string $effectiveEmail,
        public readonly RecipientPhone $phone,
        /** E.164 number SMS may go to (null when opted out of SMS or no valid number). */
        public readonly ?string $effectivePhone,
        /** Every channel this campaign uses is opted out: no row at all. */
        public readonly bool $optedOutOfAll,
        /** Reachable only by SMS, has a number, none valid. */
        public readonly bool $invalidNumber,
        /** This campaign would send this user an SMS (shared-number deduplication pool). */
        public readonly bool $getsSms,
    ) {}

    /**
     * @param  User|\stdClass  $user  a User, or a plain row with the SmsNumberPlanner::USER_COLUMNS
     */
    public static function for(object $user, string $channel): self
    {
        $usesEmail = in_array($channel, ['email', 'both', 'email_fallback_sms'], true);
        $usesSms = in_array($channel, ['sms', 'both', 'email_fallback_sms'], true);

        $emailOptOut = (bool) $user->email_opt_out;
        $smsOptOut = (bool) $user->sms_opt_out;

        $email = trim((string) $user->email) ?: null;
        $phone = RecipientPhone::pick($user->telephone1, $user->telephone2);

        $effectiveEmail = ($usesEmail && $emailOptOut) ? null : $email;
        $effectivePhone = ($usesSms && ! $smsOptOut) ? $phone->e164 : null;

        $optedOutOfAll = ($channel === 'email' && $emailOptOut)
            || ($channel === 'sms' && $smsOptOut)
            || (in_array($channel, ['both', 'email_fallback_sms'], true) && $emailOptOut && $smsOptOut);

        $needsSms = RecipientPhone::needsSms($channel, $effectiveEmail);

        return new self(
            effectiveEmail: $effectiveEmail,
            phone: $phone,
            effectivePhone: $effectivePhone,
            optedOutOfAll: $optedOutOfAll,
            invalidNumber: $usesSms && ! $smsOptOut && $phone->invalid && $needsSms,
            // "both" texts everyone with a number; fallback only those without usable email.
            getsSms: $effectivePhone !== null && ($channel === 'both' || $needsSms),
        );
    }
}
