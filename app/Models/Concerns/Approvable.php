<?php

namespace App\Models\Concerns;

use App\Exceptions\ArchivedReactivationRequiresConfirmation;
use App\Models\Scopes\ApprovedScope;
use App\Models\User;
use App\Notifications\RecordRejected;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Two-step approval workflow (submit -> approve | reject).
 *
 * Invariant: only an APPROVED record is "real". The ApprovedScope global scope
 * keeps pending/rejected records out of every default query; lifecycle effects
 * are applied on approval only. Rejection is a status change, never a delete —
 * this trait does not touch is_deleted / SoftDeletes at all.
 *
 * Models using this trait must expose a member via a `user()` relation and carry
 * `branch_id` / `division_id`. Donation overrides submitterColumn().
 */
trait Approvable
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    /**
     * Boot hook (Eloquent calls boot{TraitName}): install the global scope so
     * unqualified queries see approved records only.
     */
    public static function bootApprovable(): void
    {
        static::addGlobalScope(new ApprovedScope);
    }

    /**
     * Ensure the approval timestamp is a Carbon instance on every model using the
     * trait (none of them declare it in $casts), so views can call ->format().
     */
    public function initializeApprovable(): void
    {
        $this->mergeCasts(['decided_at' => 'datetime']);
    }

    /**
     * Column holding the id of the user who submitted the record.
     * Donation overrides this to 'entered_by_user_id'.
     */
    public function submitterColumn(): string
    {
        return 'submitted_by_user_id';
    }

    /**
     * Whether the record's beneficiary (user_id) is the same person as its
     * submitter ({submitterColumn()}) — e.g. a clerk entering a record in
     * their own name.
     */
    public function getIsSelfDirectedAttribute(): bool
    {
        return $this->user_id !== null
            && $this->user_id === $this->{$this->submitterColumn()};
    }

    /**
     * The reviewer who approved/rejected the record (null while pending).
     */
    public function decidedByUser()
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    /**
     * Short module key used in notifications and UI labels
     * (e.g. 'donation', 'training').
     */
    public function approvalModuleKey(): string
    {
        return $this->approvalModule ?? strtolower(class_basename($this));
    }

    /*
    |--------------------------------------------------------------------------
    | Local scopes (opt out of the approved-only default)
    |--------------------------------------------------------------------------
    */

    /** Only records awaiting a decision. */
    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->withoutGlobalScope(ApprovedScope::class)
            ->where($this->getTable().'.approval_status', self::PENDING);
    }

    /** Only rejected records. */
    public function scopeRejectedOnly(Builder $query): Builder
    {
        return $query->withoutGlobalScope(ApprovedScope::class)
            ->where($this->getTable().'.approval_status', self::REJECTED);
    }

    /** All records regardless of approval status. */
    public function scopeWithAnyApprovalStatus(Builder $query): Builder
    {
        return $query->withoutGlobalScope(ApprovedScope::class);
    }

    /**
     * Records $user is ELIGIBLE to approve: pending, inside the viewer's scope,
     * and NOT submitted by the viewer (four-eyes). National sees all branches.
     */
    public function scopeEligibleForApproval(Builder $query, User $user): Builder
    {
        $table = $this->getTable();
        $submitter = $this->submitterColumn();

        $query->pendingApproval();

        $level = $user->getAccessLevel();
        if ($level === 'branch') {
            $query->where("{$table}.branch_id", $user->getScopedId());
        } elseif ($level === 'division') {
            // Reserved for a future division-level approver role. As of the current
            // permission seeder (PermissionsTableSeeder), no division_* role holds
            // any approve_* permission, so this branch is unreachable in practice —
            // route middleware (can:approve_*) rejects division users before this
            // scope ever runs. Not dead code to remove; kept for when/if a division
            // approver role is introduced.
            $query->where("{$table}.division_id", $user->getScopedId());
        }

        // Exclude the viewer's own submissions (keep orphan/null-submitter rows visible).
        return $query->where(function ($q) use ($table, $submitter, $user) {
            $q->where("{$table}.{$submitter}", '!=', $user->id)
                ->orWhereNull("{$table}.{$submitter}");
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Status helpers
    |--------------------------------------------------------------------------
    */

    public function isPendingApproval(): bool
    {
        return $this->approval_status === self::PENDING;
    }

    public function isApproved(): bool
    {
        return $this->approval_status === self::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->approval_status === self::REJECTED;
    }

    /**
     * Whether $user may withdraw this record (pending AND its submitter).
     * The actual guarded delete is implemented in Phase 2; this is for the UI.
     */
    public function canBeWithdrawnBy(User $user): bool
    {
        $submitterId = $this->{$this->submitterColumn()};

        return $this->isPendingApproval()
            && $submitterId !== null
            && (int) $submitterId === (int) $user->id;
    }

    /*
    |--------------------------------------------------------------------------
    | Decisions
    |--------------------------------------------------------------------------
    */

    /**
     * Approve a pending record. Runs in a transaction.
     *
     * Four-eyes: an approver may not approve their own submission. (Scope and
     * permission checks live in the controller in Phase 2, not here.)
     *
     * Lifecycle: dormant members are reactivated silently. An ARCHIVED member
     * triggers ArchivedReactivationRequiresConfirmation unless
     * $allowArchivedReactivation is true, in which case the member is reactivated
     * and this returns true so the caller can message the approver.
     *
     * @return bool true when an archived member was reactivated, false otherwise.
     */
    public function approve(User $approver, bool $allowArchivedReactivation = false): bool
    {
        return DB::transaction(function () use ($approver, $allowArchivedReactivation) {
            $this->guardPending();
            $this->guardNotSelf($approver, 'approved');

            $member = $this->user;
            $reactivatedFromArchived = false;

            // Archived guard happens BEFORE the approval is committed, so throwing
            // here rolls the whole transaction back.
            if ($member && $member->lifecycle_status === 'archived') {
                if (! $allowArchivedReactivation) {
                    throw new ArchivedReactivationRequiresConfirmation($member, $this);
                }
                $member->update(['lifecycle_status' => 'active']);
                $reactivatedFromArchived = true;
            }

            $this->forceFill([
                'approval_status' => self::APPROVED,
                'decided_by_user_id' => $approver->id,
                'decided_at' => now(),
                'rejection_reason' => null,
            ])->save();

            if ($member) {
                // Silently lift a dormant member back to active before recompute.
                if ($member->lifecycle_status === 'dormant') {
                    $member->update(['lifecycle_status' => 'active']);
                }
                // Authoritative recompute (may legitimately re-demote per policy).
                $member->recalculateLifecycle();
            }

            // Module-specific follow-up after the universal lifecycle work
            // (e.g. Training bumps the denormalised first-aid date). Default no-op.
            $this->afterApproved($member);

            $approver->touchLastAdminActivity();

            return $reactivatedFromArchived;
        });
    }

    /**
     * Hook invoked inside approve()'s transaction, after the universal lifecycle
     * work. Default no-op; modules override to add their own follow-up.
     *
     * @param  User|null  $member  the record's related member (may be null)
     */
    protected function afterApproved(?User $member): void
    {
        // no-op by default
    }

    /**
     * Demote an already-approved record back to pending after a content edit, so
     * it goes through a fresh approval cycle. No-op if the record isn't currently
     * approved (editing a pending or rejected record has nothing to reset).
     *
     * Mirrors approve()'s shape: forceFill + save inside a transaction, an
     * unconditional lifecycle recompute (the fix for the gap where controllers'
     * `if ($model->isApproved()) { recalculateLifecycle(); }` never fires here,
     * since the record is no longer approved once this runs), then a
     * module-specific afterDemoted() hook.
     */
    public function resetApprovalOnEdit(): void
    {
        if (! $this->isApproved()) {
            return;
        }

        DB::transaction(function () {
            $member = $this->user;

            $this->forceFill([
                'approval_status' => self::PENDING,
                'decided_by_user_id' => null,
                'decided_at' => null,
                'rejection_reason' => null,
            ])->save();

            if ($member) {
                $member->recalculateLifecycle();
            }

            // Module-specific follow-up after the universal lifecycle work
            // (e.g. Training recomputes the denormalised first-aid date, since
            // this record no longer counts as approved). Default no-op.
            $this->afterDemoted($member);
        });
    }

    /**
     * Hook invoked inside resetApprovalOnEdit()'s transaction, after the
     * universal lifecycle work. Default no-op; modules override to add their
     * own follow-up. Mirrors afterApproved().
     *
     * @param  User|null  $member  the record's related member (may be null)
     */
    protected function afterDemoted(?User $member): void
    {
        // no-op by default
    }

    /**
     * Reject a pending record. Runs in a transaction.
     *
     * No deletion, no lifecycle change. The submitter is notified via the
     * RecordRejected database notification.
     */
    public function reject(User $approver, string $reason): void
    {
        DB::transaction(function () use ($approver, $reason) {
            $this->guardPending();
            $this->guardNotSelf($approver, 'rejected');

            $this->forceFill([
                'approval_status' => self::REJECTED,
                'decided_by_user_id' => $approver->id,
                'decided_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            $submitterId = $this->{$this->submitterColumn()};
            if ($submitterId && ($submitter = User::find($submitterId))) {
                $submitter->notify(new RecordRejected(
                    $this->approvalModuleKey(),
                    $this->getKey(),
                    $reason
                ));
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Guards
    |--------------------------------------------------------------------------
    */

    private function guardPending(): void
    {
        if (! $this->isPendingApproval()) {
            throw new RuntimeException(sprintf(
                '%s #%s is not pending approval (status: %s).',
                ucfirst($this->approvalModuleKey()),
                $this->getKey(),
                $this->approval_status
            ));
        }
    }

    private function guardNotSelf(User $approver, string $verb): void
    {
        $submitterId = $this->{$this->submitterColumn()};

        if ($submitterId !== null && (int) $submitterId === (int) $approver->id) {
            throw new RuntimeException(
                "A record cannot be {$verb} by the same user who submitted it."
            );
        }
    }
}
