<?php

namespace App\Services;

use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Single source of truth for whether a person may pay their OWN membership
 * fee online, and which fees they may choose. Used by the profile page's
 * membership button and by PaystackPaymentController::show()/initiate(), so
 * the three can never disagree.
 *
 * Organisation and Red Cross Unit payments are not covered here beyond the
 * global on/off switch (isAvailable()); donations likewise only use the
 * switch, never this class's personal-membership rules.
 */
final class OnlinePaymentEligibility
{
    public const NOT_AVAILABLE_MESSAGE = 'Online payment is not available yet.';

    /** Days before expiry from which early renewal is offered. */
    public const RENEWAL_WINDOW_DAYS = 28;

    private const MESSAGES = [
        'archived' => 'Your account is archived. Please contact your branch or Red Cross Unit directly to renew your membership.',
        'no_email' => 'Please add an email address to your profile before making a payment.',
        'volunteer_contact_branch' => 'You have registered interest in volunteering. Please contact your branch first, so they can place you in a Red Cross Unit.',
        'pending_approval' => 'A payment is awaiting approval at your branch.',
        'membership_valid' => 'Your membership is still valid. You can renew online from '.self::RENEWAL_WINDOW_DAYS.' days before it expires.',
        'payments_disabled' => self::NOT_AVAILABLE_MESSAGE,
    ];

    /**
     * @param  string|null  $reason  null when $canPay, otherwise a key of MESSAGES
     * @param  string  $subcase  new | lapsed | expiring_soon | valid
     */
    private function __construct(
        public readonly bool $canPay,
        public readonly ?string $reason,
        public readonly string $subcase,
        public readonly ?MembershipPayment $currentPayment,
        public readonly Collection $allowedFees,
    ) {}

    /**
     * The global on/off switch: config flag on AND both Paystack keys set.
     */
    public static function isAvailable(): bool
    {
        return (bool) config('paystack.enabled')
            && filled(config('paystack.public_key'))
            && filled(config('paystack.secret_key'));
    }

    public static function for(User $user): self
    {
        // Personal, approved, not deleted, expiry_date >= today.
        $currentPayment = $user->currentMembershipPayment()
            ->personal()
            ->with('membershipFee')
            ->first();

        if (! $currentPayment) {
            $hasEverPaid = $user->membershipPayments()
                ->personal()
                ->where('is_deleted', false)
                ->exists();
            $subcase = $hasEverPaid ? 'lapsed' : 'new';
        } elseif ($currentPayment->expiresSoon(self::RENEWAL_WINDOW_DAYS)) {
            $subcase = 'expiring_soon';
        } else {
            $subcase = 'valid';
        }

        $reason = match (true) {
            $user->lifecycle_status === 'archived' => 'archived',
            blank($user->email) => 'no_email',
            $user->lifecycle_status === 'pending_engagement'
                && $user->can_contribute_volunteering
                && ! $user->can_contribute_member => 'volunteer_contact_branch',
            self::hasPendingManualPayment($user) => 'pending_approval',
            $subcase === 'valid' => 'membership_valid',
            ! self::isAvailable() => 'payments_disabled',
            default => null,
        };

        return new self(
            canPay: $reason === null,
            reason: $reason,
            subcase: $subcase,
            currentPayment: $currentPayment,
            allowedFees: $reason === null ? self::allowedFeesFor($user) : new Collection,
        );
    }

    /**
     * Personal fees this user may choose: every active personal fee for a
     * Red Cross Unit member, otherwise only the non-volunteer fees.
     */
    public static function allowedFeesFor(User $user): Collection
    {
        return MembershipFee::active()
            ->forPersons()
            ->when($user->red_cross_unit_id === null, fn ($q) => $q->where('is_volunteer_fee', false))
            ->orderBy('validity_years')
            ->orderBy('amount')
            ->get();
    }

    public function allowsFee(int|string|null $feeId): bool
    {
        return $feeId !== null && $this->allowedFees->contains('id', (int) $feeId);
    }

    public function message(): ?string
    {
        return $this->reason ? self::MESSAGES[$this->reason] : null;
    }

    /**
     * A personal payment entered at the branch and still awaiting the
     * four-eyes decision (rejected and deleted ones don't count).
     */
    private static function hasPendingManualPayment(User $user): bool
    {
        return MembershipPayment::pendingApproval()
            ->where('user_id', $user->id)
            ->personal()
            ->where('is_deleted', false)
            ->exists();
    }
}
