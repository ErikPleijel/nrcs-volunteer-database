<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonInterface;

/**
 * The facts behind the "Include ID card" box on a person's own membership
 * payment, and its default. Used by the online payment page
 * (PaystackPaymentController::show()) and the staff payment form (via
 * MembershipPaymentController::getCurrentMembership()), so the two follow the
 * same rule. Advice only: nothing here blocks a payment, and the price is
 * still worked out server side in initiate()/store().
 *
 * Default (Decisions.md 2026-10-22): ON for a volunteer (User::isVolunteer(),
 * so it follows the card that will print) who has no printed card valid for
 * more than OnlinePaymentEligibility::RENEWAL_WINDOW_DAYS days; OFF for
 * everyone else, including people who left their unit.
 */
final class IdCardOrderAdvice
{
    private function __construct(
        public readonly bool $isVolunteer,
        public readonly ?CarbonInterface $validUntil,
        public readonly bool $expiresSoon,
        public readonly bool $hasPhoto,
        public readonly bool $hasSignature,
        public readonly bool $photoRejected,
        public readonly bool $signatureRejected,
        public readonly bool $hasNin,
        public readonly ?string $branchName,
    ) {}

    public static function for(User $user): self
    {
        // Same card as User::hasValidIdCardPrinted(): the latest print, valid
        // while its expiry date is in the future.
        $expiry = $user->idCardPrints()->latest('printed_at')->first()?->expiry_date;
        $validUntil = $expiry?->isFuture() ? $expiry : null;

        return new self(
            isVolunteer: $user->isVolunteer(),
            validUntil: $validUntil,
            expiresSoon: $validUntil !== null
                && $validUntil->lte(now()->addDays(OnlinePaymentEligibility::RENEWAL_WINDOW_DAYS)),
            hasPhoto: $user->hasProfilePhoto(),
            hasSignature: $user->hasSignature(),
            photoRejected: $user->needsPictureReupload(),
            signatureRejected: $user->needsSignatureReupload(),
            hasNin: $user->hasNationalIdNumber(),
            branchName: $user->branch?->name,
        );
    }

    public function defaultOn(): bool
    {
        return $this->isVolunteer && ($this->validUntil === null || $this->expiresSoon);
    }

    /** Something the card needs is missing or was rejected. */
    public function hasMissingItems(): bool
    {
        return ! $this->hasPhoto || ! $this->hasSignature || $this->photoRejected
            || $this->signatureRejected || ! $this->hasNin;
    }

    /** For the staff form's JS (current-membership endpoint). */
    public function toArray(): array
    {
        return [
            'is_volunteer' => $this->isVolunteer,
            'default_on' => $this->defaultOn(),
            'valid_until_display' => $this->validUntil?->format('d M Y'),
            'expires_soon' => $this->expiresSoon,
            'has_photo' => $this->hasPhoto,
            'has_signature' => $this->hasSignature,
            'photo_rejected' => $this->photoRejected,
            'signature_rejected' => $this->signatureRejected,
            'has_nin' => $this->hasNin,
        ];
    }
}
