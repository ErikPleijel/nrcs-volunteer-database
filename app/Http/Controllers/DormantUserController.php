<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Log as AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DormantUserController extends Controller
{
    public function index(Request $request)
    {
        $authUser    = Auth::user();
        $accessLevel = $authUser->getAccessLevel();
        $scopedId    = $authUser->getScopedId();

        // Display preference: show profile photos (per-browser cookie,
        // shared site-wide with users/index). Default OFF when absent.
        $showPhotos = $request->cookie('users_show_photos') === '1';

        $years = (int) $request->input('years', 4);



        if (!in_array($years, [2, 3, 4, 5, 6, 7])) {
            $years = 2;
        }

        $cutoff = now()->subYears($years);

        $type = $request->input('type', 'dormant');
        if (!in_array($type, ['dormant', 'pending'])) {
            $type = 'dormant';
        }

        $allAdminRoles = array_merge(
            User::NATIONAL_ROLES,
            User::BRANCH_ROLES,
            User::DIVISION_ROLES,
        );

        $query = User::with(['branch', 'division', 'redCrossUnit', 'trainings.trainingType',
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
            ->whereDoesntHave('membershipPayments', fn ($q) => $q
                ->where('is_deleted', false)
                ->where('expiry_date', '>=', now()->toDateString())
            )
            ->orderBy($type === 'pending' ? 'created_at' : 'last_activity_at', 'asc');

        if ($accessLevel === 'branch') {
            $query->where('branch_id', $scopedId);
        } elseif ($accessLevel === 'division') {
            $query->where('division_id', $scopedId);
        } elseif ($accessLevel === 'national' && $request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        $users = $query->paginate(20)->withQueryString();

        $branches = collect();
        if ($accessLevel === 'national') {
            $branches = Branch::orderBy('name')->get();
        } elseif ($accessLevel === 'branch' && $scopedId) {
            $branches = Branch::where('id', $scopedId)->get();
        }

        return view('dormant-users.index', compact('users', 'years', 'type', 'showPhotos', 'branches', 'accessLevel', 'scopedId'));
    }

    public function bulkArchive(Request $request)
    {
        $authUser    = Auth::user();
        $accessLevel = $authUser->getAccessLevel();
        $scopedId    = $authUser->getScopedId();

        $request->validate([
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $candidateIds = $request->input('user_ids');

        $validQuery = User::whereIn('id', $candidateIds)
            ->where('is_super_admin', false)
            ->where('lifecycle_status', '!=', 'archived')
            ->whereDoesntHave('organisations');

        if ($accessLevel === 'branch') {
            $validQuery->where('branch_id', $scopedId);
        } elseif ($accessLevel === 'division') {
            $validQuery->where('division_id', $scopedId);
        }

        // Role holders are never archived here (same rule as users/edit and
        // self-archive: remove the role first); counted so the admin is told.
        $skippedRoleHolders = (clone $validQuery)->whereHas('roles')->count();
        $validIds = $validQuery->whereDoesntHave('roles')->pluck('id')->toArray();

        $skippedNote = $skippedRoleHolders === 0 ? '' : " {$skippedRoleHolders} "
            .($skippedRoleHolders === 1 ? 'user was' : 'users were')
            .' skipped because they hold an administrative role; remove the role first.';

        if (empty($validIds)) {
            return redirect()->route('dormant-users.index')
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

        return redirect()->route('dormant-users.index')
            ->with('success', "{$count} " . ($count === 1 ? 'user has' : 'users have') . " been archived. This can be reversed individually from their profile.".$skippedNote);
    }
}
