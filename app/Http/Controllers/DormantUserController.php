<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Division;
use App\Models\Log as AuditLog;
use App\Models\MembershipPayment;
use App\Models\RedCrossUnit;
use App\Models\User;
use App\Services\UserFilterService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DormantUserController extends Controller
{
    private const YEAR_OPTIONS = [2, 3, 4, 5, 6, 7];

    private const DEFAULT_YEARS = 4;

    /** The list filters; the archive form posts them back so bulkArchive() checks the same list. */
    private const FILTER_KEYS = ['type', 'years', 'branch_id', 'division_id', 'red_cross_unit_id'];

    public function index(Request $request, UserFilterService $userFilterService)
    {
        $authUser    = Auth::user();
        $accessLevel = $authUser->getAccessLevel();
        $scopedId    = $authUser->getScopedId();

        // Display preference: show profile photos (per-browser cookie,
        // shared site-wide with users/index). Default OFF when absent.
        $showPhotos = $request->cookie('users_show_photos') === '1';

        [$years, $type] = $this->listSettings($request);

        $users = $this->archivableQuery($request, $years, $type, $userFilterService)
            ->with(['branch', 'division', 'redCrossUnit', 'trainings.trainingType',
                'campaignRecipients' => fn ($q) => $q
                    ->with('campaign.purpose')
                    ->whereHas('campaign', fn ($q) => $q
                        ->where('status', 'sent')
                    )
                    ->whereNotNull('sent_at')
                    ->orderByDesc('sent_at')
                    ->limit(5),
            ])
            ->withCount(['donations', 'trainings'])
            ->orderBy($type === 'pending' ? 'created_at' : 'last_activity_at', 'asc')
            ->paginate(20)
            ->withQueryString();

        // Dropdowns for the location cascade, limited to the user's area.
        $branches = collect();
        $divisions = collect();
        $redCrossUnits = collect();
        $divisionId = $request->input('division_id');

        switch ($accessLevel) {
            case 'national':
                $branches = Branch::orderBy('name')->get();
                if ($request->filled('branch_id')) {
                    $divisions = Division::where('branch_id', $request->branch_id)->orderBy('name')->get();
                }
                break;

            case 'branch':
                if ($scopedId) {
                    $branches = Branch::where('id', $scopedId)->get();
                    $divisions = Division::where('branch_id', $scopedId)->orderBy('name')->get();
                }
                break;

            case 'division':
                $userDivision = $scopedId ? Division::find($scopedId) : null;
                if ($userDivision) {
                    $branches = Branch::where('id', $userDivision->branch_id)->get();
                    $divisions = collect([$userDivision]);
                    $divisionId = $userDivision->id;
                }
                break;
        }

        // Units only for a division offered above (never another area's).
        if ($divisionId && $divisions->contains('id', (int) $divisionId)) {
            $redCrossUnits = RedCrossUnit::active()->where('division_id', $divisionId)->orderBy('name')->get();
        }

        $filterParams = $this->filterParams($request, $years, $type);

        return view('dormant-users.index', compact(
            'users', 'years', 'type', 'showPhotos', 'accessLevel', 'scopedId',
            'branches', 'divisions', 'redCrossUnits', 'filterParams',
        ));
    }

    public function bulkArchive(Request $request, UserFilterService $userFilterService)
    {
        $authUser = Auth::user();

        $request->validate([
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $candidateIds = array_values(array_unique(array_map('intval', $request->input('user_ids'))));

        [$years, $type] = $this->listSettings($request);

        // Only users the list itself would show (same query, same filters).
        $validIds = $this->archivableQuery($request, $years, $type, $userFilterService)
            ->whereIn('id', $candidateIds)
            ->pluck('id')
            ->toArray();

        // Role holders are never archived here (same rule as users/edit and
        // self-archive: remove the role first); counted apart so the admin
        // is told why. Only those in the admin's own area are named as such.
        $skippedRoleHolders = $userFilterService
            ->applyScopeAndLocation(User::query(), [], $authUser->getAccessLevel(), $authUser->getScopedId())
            ->whereIn('id', $candidateIds)
            ->whereNotIn('id', $validIds)
            ->whereHas('roles')
            ->count();

        $skippedOther = count($candidateIds) - count($validIds) - $skippedRoleHolders;

        $skippedNote = $this->skipNote($skippedRoleHolders, 'because they hold an administrative role; remove the role first.')
            .$this->skipNote($skippedOther, 'because they do not meet the Archive Tool conditions (for example recent activity or a current membership payment).');

        $redirect = redirect()->route('dormant-users.index', $this->filterParams($request, $years, $type));

        if (empty($validIds)) {
            return $redirect
                ->with('error', 'No users were archived. You may not have permission to archive the selected users.'.$skippedNote);
        }

        // Per user (not one query-builder update) so each gets archived_at /
        // archived_by_id via markArchived() and its own audit entry.
        DB::transaction(function () use ($validIds, $authUser) {
            User::whereIn('id', $validIds)->chunkById(200, function ($users) use ($authUser) {
                foreach ($users as $user) {
                    $lifecycleBefore = $user->lifecycle_status;
                    $user->markArchived($authUser)->save();

                    AuditLog::write(
                        'user_archived',
                        $user,
                        ['branch_id' => $user->branch_id, 'division_id' => $user->division_id],
                        ['lifecycle_status' => $lifecycleBefore],
                        ['lifecycle_status' => 'archived', 'archived_by_id' => $authUser->id],
                        "DB-{$user->id} archived with the archive tool by DB-{$authUser->id}."
                    );
                }
            });
        });
        $count = count($validIds);

        return $redirect
            ->with('success', "{$count} " . ($count === 1 ? 'user has' : 'users have') . " been archived. This can be reversed individually from their profile.".$skippedNote);
    }

    /**
     * Everyone the Archive Tool may list and archive: dormant (or pending
     * engagement) for longer than $years, not a super admin, no admin role,
     * no organisation, no current membership payment (approved or awaiting
     * approval) — within the admin's
     * area and the chosen branch / division / unit. Used by both the list
     * and bulkArchive(), so nothing can be archived that the list would hide.
     */
    private function archivableQuery(Request $request, int $years, string $type, UserFilterService $userFilterService): Builder
    {
        $authUser = Auth::user();
        $cutoff = now()->subYears($years);

        $allAdminRoles = array_merge(
            User::NATIONAL_ROLES,
            User::BRANCH_ROLES,
            User::DIVISION_ROLES,
        );

        $query = User::query()
            ->where('is_super_admin', false)
            ->where(function ($q) use ($type, $cutoff) {
                if ($type === 'pending') {
                    $q->where('lifecycle_status', 'pending_engagement')
                      ->whereNotNull('created_at')
                      ->where('created_at', '<', $cutoff);
                } else {
                    $q->where('lifecycle_status', 'dormant')
                      ->whereNotNull('last_activity_at')
                      ->where('last_activity_at', '<', $cutoff);
                }
            })
            ->whereDoesntHave('organisations')
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', $allAdminRoles))
            // A payment still awaiting approval protects the user too; only
            // a rejected one does not.
            ->whereDoesntHave('membershipPayments', fn ($q) => $q
                ->withAnyApprovalStatus()
                ->whereIn('membership_payments.approval_status', [MembershipPayment::APPROVED, MembershipPayment::PENDING])
                ->where('is_deleted', false)
                ->where('expiry_date', '>=', now()->toDateString())
            );

        return $userFilterService->applyScopeAndLocation(
            $query,
            $request->only(['branch_id', 'division_id', 'red_cross_unit_id']),
            $authUser->getAccessLevel(),
            $authUser->getScopedId(),
        );
    }

    /** @return array{int, string} [years, type], with unknown values back to the defaults. */
    private function listSettings(Request $request): array
    {
        $years = (int) $request->input('years', self::DEFAULT_YEARS);
        if (! in_array($years, self::YEAR_OPTIONS, true)) {
            $years = self::DEFAULT_YEARS;
        }

        $type = $request->input('type', 'dormant');
        if (! in_array($type, ['dormant', 'pending'], true)) {
            $type = 'dormant';
        }

        return [$years, $type];
    }

    /** The list filters that are set, for the archive form and the redirect back to the list. */
    private function filterParams(Request $request, int $years, string $type): array
    {
        $params = array_filter($request->only(self::FILTER_KEYS), fn ($v) => filled($v));

        if (isset($params['years'])) {
            $params['years'] = $years;
        }
        if (isset($params['type'])) {
            $params['type'] = $type;
        }

        return $params;
    }

    private function skipNote(int $count, string $reason): string
    {
        if ($count <= 0) {
            return '';
        }

        return " {$count} ".($count === 1 ? 'user was' : 'users were')." skipped {$reason}";
    }
}
