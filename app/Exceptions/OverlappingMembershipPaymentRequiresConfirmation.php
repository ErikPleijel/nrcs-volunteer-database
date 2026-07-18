<?php

namespace App\Exceptions;

use App\Models\MembershipPayment;
use RuntimeException;

/**
 * Thrown by MembershipPaymentController::store()/update() when the
 * submitted payment/expiry date range overlaps another existing personal
 * membership payment for the same user, and the caller has not explicitly
 * confirmed that intent.
 *
 * The caller can catch this, re-prompt the submitter with the conflicting
 * payment's dates, and retry with confirm_overlap = true.
 */
class OverlappingMembershipPaymentRequiresConfirmation extends RuntimeException
{
    public function __construct(
        public readonly MembershipPayment $conflictingPayment,
    ) {
        parent::__construct(
            "This payment's date range overlaps membership payment #{$conflictingPayment->id} "
            .'for the same user (valid '.$conflictingPayment->payment_date->toDateString()
            .' to '.$conflictingPayment->expiry_date->toDateString().'). '
            .'Re-submit with confirmation to proceed.'
        );
    }
}
