<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\ArchivedReactivationRequiresConfirmation;
use App\Models\Branch;
use App\Models\Division;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 2 controller wiring for the two-step approval workflow.
 *
 * Shared approve / reject / withdraw actions for all four module controllers.
 * The lifecycle/audit/notification behaviour lives in the Approvable model trait;
 * scope + permission + four-eyes authorization live HERE (per Phase 1 design).
 *
 * IMPORTANT (audit §8.1): these actions never type-hint the model — implicit
 * route-model binding applies the ApprovedScope global scope and would 404 the
 * pending/rejected records we need to act on. We resolve explicitly with a
 * scope-lifting query instead.
 */
trait HandlesRecordApproval
{
    /** Fully-qualified model class for this module (e.g. Donation::class). */
    abstract protected function approvalModelClass(): string;

    /** Human label for flash messages (e.g. 'Donation'). */
    abstract protected function approvalLabel(): string;

    /** Route-name prefix for this module (e.g. 'donations', 'membership-payments'). */
    abstract protected function approvalRouteName(): string;

    /** Permission required to approve/reject this module (e.g. 'approve_donations'). */
    abstract protected function approvalPermission(): string;

    /**
     * Approvals tab: the PENDING records the current user is eligible to approve
     * (in scope, not their own submissions). National users may narrow by
     * branch/division. Route is gated by can:approve_<module>.
     */
    public function approvals(Request $request): View
    {
        $modelClass = $this->approvalModelClass();
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();

        $query = $modelClass::eligibleForApproval($user)
            ->with(['user.redCrossUnit', 'branch', 'division', 'submittedByUser']);

        // National reviewers may optionally narrow by branch/division.
        $branches = collect();
        $divisions = collect();
        if ($accessLevel === 'national') {
            $branches = Branch::select('id', 'name')->orderBy('name')->get();
            if ($request->filled('branch_id')) {
                $query->where((new $modelClass)->getTable().'.branch_id', $request->branch_id);
                $divisions = Division::where('branch_id', $request->branch_id)
                    ->select('id', 'name')->orderBy('name')->get();
            }
            if ($request->filled('division_id')) {
                $query->where((new $modelClass)->getTable().'.division_id', $request->division_id);
            }
        }

        $records = $query->orderBy('created_at', 'asc')->paginate(20)->appends($request->query());

        return view('approvals.index', [
            'records' => $records,
            'moduleLabel' => $this->approvalLabel(),
            'routeName' => $this->approvalRouteName(),
            'permission' => $this->approvalPermission(),
            'pendingCount' => $records->total(),
            'accessLevel' => $accessLevel,
            'branches' => $branches,
            'divisions' => $divisions,
        ]);
    }

    /**
     * Bulk-approve a selected subset from the approvals tab (clean-subset,
     * skip-and-report). Each record is processed independently:
     *  - re-verify eligibility (scope, not own, still pending) — defence in depth;
     *  - approve WITHOUT the archived-reactivation flag (never reactivate an
     *    archived member inside a bulk action) — such records are skipped;
     *  - any already-decided / out-of-scope record is skipped with a reason.
     */
    public function bulkApprove(Request $request): RedirectResponse
    {
        $ids = array_filter((array) $request->input('ids', []));
        $modelClass = $this->approvalModelClass();
        $approver = Auth::user();

        $approved = 0;
        $skipped = [];

        foreach ($ids as $id) {
            $record = $modelClass::pendingApproval()->find($id);

            if (! $record) {
                $skipped[] = ['id' => $id, 'reason' => 'No longer pending (already decided or withdrawn)'];

                continue;
            }

            // Re-verify scope + four-eyes even though the tab pre-filters.
            if (! $this->viewerMayApprove($approver, $record)) {
                $skipped[] = ['id' => $id, 'reason' => 'Outside your scope, or your own submission'];

                continue;
            }

            try {
                // false => never silently reactivate an archived member.
                $record->approve($approver, false);
                $approved++;
            } catch (ArchivedReactivationRequiresConfirmation $e) {
                $skipped[] = ['id' => $id, 'reason' => 'Member is archived — approve individually to confirm reactivation'];
            } catch (\Throwable $e) {
                $skipped[] = ['id' => $id, 'reason' => 'Could not be approved (status changed)'];
            }
        }

        return back()->with('bulk_result', [
            'approved' => $approved,
            'skipped' => $skipped,
            'routeName' => $this->approvalRouteName(),
            'label' => $this->approvalLabel(),
        ]);
    }

    /**
     * Pending review page. Binding LIFTS the global scope so pending/rejected
     * records resolve. Viewable by an eligible approver OR the record's submitter.
     */
    public function review($id): View
    {
        $modelClass = $this->approvalModelClass();
        $record = $modelClass::withAnyApprovalStatus()
            ->with(['user.redCrossUnit', 'user.branch', 'branch', 'division', 'submittedByUser', 'decidedByUser'])
            ->findOrFail($id);

        $user = Auth::user();
        $submitterId = $record->{$record->submitterColumn()};
        $isSubmitter = $submitterId !== null && (int) $submitterId === (int) $user->id;

        $canApprove = $this->viewerMayApprove($user, $record);

        // The reviewer who decided it may still view it afterwards (so approving/
        // rejecting from this page doesn't bounce them to a 403 on reload).
        $isDecider = $record->decided_by_user_id !== null
            && (int) $record->decided_by_user_id === (int) $user->id;

        abort_unless($isSubmitter || $canApprove || $isDecider, 403, 'You are not authorized to view this record.');

        return view('approvals.show', [
            'record' => $record,
            'moduleLabel' => $this->approvalLabel(),
            'routeName' => $this->approvalRouteName(),
            'canApprove' => $canApprove,
            'isSubmitter' => $isSubmitter,
        ]);
    }

    /**
     * Whether $user is an eligible approver for $record right now:
     * has the permission, record is pending + in scope, and not their own submission.
     */
    private function viewerMayApprove(User $user, Model $record): bool
    {
        if (! $user->can($this->approvalPermission()) || ! $record->isPendingApproval()) {
            return false;
        }

        $submitterId = $record->{$record->submitterColumn()};
        if ($submitterId !== null && (int) $submitterId === (int) $user->id) {
            return false;
        }

        return match ($user->getAccessLevel()) {
            'national' => true,
            'branch' => (int) $record->branch_id === (int) $user->getScopedId(),
            'division' => (int) $record->division_id === (int) $user->getScopedId(),
            default => false,
        };
    }

    /**
     * Approve a pending record.
     * Route is gated by can:approve_<module>; here we add scope + four-eyes.
     */
    public function approve(Request $request, $id): RedirectResponse
    {
        $modelClass = $this->approvalModelClass();

        // §8.1: explicit scope-lifting resolution (no implicit binding).
        $record = $modelClass::pendingApproval()->findOrFail($id);

        $approver = Auth::user();
        $this->authorizeApprovalScope($approver, $record);
        $this->guardFourEyes($approver, $record);

        try {
            $reactivated = $record->approve(
                $approver,
                $request->boolean('confirm_archived_reactivation')
            );
        } catch (ArchivedReactivationRequiresConfirmation $e) {
            return back()->with('warning',
                'This record belongs to an archived member. Re-submit the approval with '
                .'confirmation to reactivate the member and approve the record.');
        }

        if ($reactivated) {
            return back()->with('success',
                $this->approvalLabel().' approved. The member was archived and has been reactivated.');
        }

        return back()->with('success', $this->approvalLabel().' approved successfully.');
    }

    /**
     * Reject a pending record with a required reason.
     * Route is gated by can:approve_<module>; here we add scope + four-eyes.
     */
    public function reject(Request $request, $id): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $modelClass = $this->approvalModelClass();

        $record = $modelClass::pendingApproval()->findOrFail($id);

        $approver = Auth::user();
        $this->authorizeApprovalScope($approver, $record);
        $this->guardFourEyes($approver, $record);

        $record->reject($approver, $validated['reason']);

        return back()->with('success', $this->approvalLabel().' rejected.');
    }

    /**
     * Withdraw an own pending record (submitter-only; no approve permission needed).
     *
     * Atomic guarded hard-delete: the DELETE itself is the race-safe guard.
     */
    public function withdraw(Request $request, $id): RedirectResponse
    {
        $modelClass = $this->approvalModelClass();
        $user = Auth::user();
        $submitterColumn = (new $modelClass)->submitterColumn();

        // Atomic, race-safe delete: only removes the row if still own + pending.
        $deleted = $modelClass::pendingApproval()
            ->where('id', $id)
            ->where($submitterColumn, $user->id)
            ->delete();

        if ($deleted > 0) {
            return back()->with('success', $this->approvalLabel().' withdrawn.');
        }

        // 0 rows deleted — reload (scope-lifted) to explain why.
        $current = $modelClass::withAnyApprovalStatus()->find($id);

        if (! $current) {
            abort(404);
        }

        if ($current->approval_status === $modelClass::APPROVED) {
            return back()->with('warning',
                'This record was approved by a reviewer a moment ago and can no longer be withdrawn.');
        }

        return back()->with('error', 'This record can no longer be withdrawn.');
    }

    /**
     * Record must fall within the approver's geographic scope.
     * National: any. Branch: same branch_id. Division: same division_id.
     */
    private function authorizeApprovalScope(User $approver, Model $record): void
    {
        $level = $approver->getAccessLevel();
        $scopedId = $approver->getScopedId();

        switch ($level) {
            case 'national':
                return; // national reviewers cover every record (this is the escalation path)
            case 'branch':
                if ((int) $record->branch_id !== (int) $scopedId) {
                    abort(403, 'This record is outside your branch.');
                }

                return;
            case 'division':
                if ((int) $record->division_id !== (int) $scopedId) {
                    abort(403, 'This record is outside your division.');
                }

                return;
            default:
                abort(403, 'You are not authorized to review records.');
        }
    }

    /**
     * Four-eyes: nobody (national included) may decide on their own submission.
     */
    private function guardFourEyes(User $approver, Model $record): void
    {
        $submitterId = $record->{$record->submitterColumn()};

        if ($submitterId !== null && (int) $submitterId === (int) $approver->id) {
            abort(403, 'You cannot approve or reject a record you submitted yourself.');
        }
    }
}
