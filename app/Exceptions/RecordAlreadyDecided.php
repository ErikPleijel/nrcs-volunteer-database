<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown by Approvable::approve()/reject() when the record is no longer
 * pending: someone else (or a double-click) decided it first. $record holds
 * the freshly locked row, so the caller can say who decided it and when.
 */
class RecordAlreadyDecided extends RuntimeException
{
    public function __construct(public readonly Model $record)
    {
        parent::__construct(sprintf(
            '%s #%s is not pending approval (status: %s).',
            ucfirst($record->approvalModuleKey()),
            $record->getKey(),
            $record->approval_status
        ));
    }
}
