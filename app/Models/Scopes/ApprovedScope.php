<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope enforcing the core invariant: only APPROVED records are "real".
 *
 * Applied by the Approvable trait. Pending and rejected records are hidden from
 * every default query (lists, totals, reports, relations) unless a caller opts
 * out via withAnyApprovalStatus() / pendingApproval() / rejectedOnly().
 */
class ApprovedScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Qualify the column so the scope is join-safe.
        $builder->where($model->getTable().'.approval_status', 'approved');
    }
}
