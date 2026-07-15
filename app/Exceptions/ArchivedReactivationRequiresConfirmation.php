<?php

namespace App\Exceptions;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown by Approvable::approve() when approving a record would reactivate an
 * ARCHIVED member and the caller has not explicitly confirmed that intent.
 *
 * The approval transaction is rolled back, so the UI can catch this, re-prompt
 * the approver, and retry with $allowArchivedReactivation = true.
 */
class ArchivedReactivationRequiresConfirmation extends RuntimeException
{
    public function __construct(
        public readonly User $member,
        public readonly Model $record,
    ) {
        parent::__construct(
            "Approving this record would reactivate archived member #{$member->id}. "
            .'Re-submit with confirmation to proceed.'
        );
    }
}
