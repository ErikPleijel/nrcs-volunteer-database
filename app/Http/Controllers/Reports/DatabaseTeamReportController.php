<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Division;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DatabaseTeamReportController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();
        $defaultTab = in_array($accessLevel, ['branch', 'division']) ? 'branch' : 'national';
        $activeTab = $request->input('tab', $defaultTab);
        $showPhotos = $request->boolean('show_photos', false);

        $defaultBranchId = null;
        if ($accessLevel === 'branch') {
            $defaultBranchId = $scopedId;
        } elseif ($accessLevel === 'division') {
            $defaultBranchId = $user->getScopedBranchId();
        }

        $branches = Branch::orderBy('name')->get();

        $statsNational = null;
        $statsBranches = null;

        // Tab 1 — National
        $nationalData = [];
        if ($activeTab === 'national') {
            $nationalRoles = ['national_db_administrator', 'national_db_assistant', 'observer_national_level'];
            foreach ($nationalRoles as $roleSlug) {
                $nationalData[$roleSlug] = User::role($roleSlug)->orderBy('first_name')->get();
            }
        }

        // Tab 2 — Branch
        $branchData = [];
        $selectedBranch = null;
        if ($activeTab === 'branch') {
            $selectedBranchId = $request->input('branch_id', $defaultBranchId);
            $selectedBranch = $selectedBranchId ? Branch::find($selectedBranchId) : null;

            if ($selectedBranch) {
                $branchRoles = ['branch_secretary', 'branch_db_administrator', 'branch_db_assistant'];
                foreach ($branchRoles as $roleSlug) {
                    $branchData['branch'][$roleSlug] = User::role($roleSlug)
                        ->where('branch_id', $selectedBranch->id)
                        ->orderBy('first_name')
                        ->get();
                }

                $divisions = Division::where('branch_id', $selectedBranch->id)->orderBy('name')->get();
                foreach ($divisions as $division) {
                    $divRoles = ['division_db_assistant_finance', 'division_db_assistant_operations'];
                    $branchExclude = ['branch_secretary', 'branch_db_administrator', 'branch_db_assistant'];
                    $divPersons = User::whereHas('roles', fn ($q) => $q->whereIn('name', $divRoles))
                        ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', $branchExclude))
                        ->where('division_id', $division->id)
                        ->orderBy('first_name')
                        ->get();
                    $divPersons->each(function ($p) {
                        $roleName = $p->getRoleNames()->first();
                        $p->team_role_label = match ($roleName) {
                            'division_db_assistant_finance' => 'Division DB Assistant — Finance',
                            'division_db_assistant_operations' => 'Division DB Assistant — Operations',
                            default => 'Division DB Assistant',
                        };
                    });
                    $branchData['divisions'][] = [
                        'division' => $division,
                        'persons' => $divPersons,
                    ];
                }
            }
        }

        // Tab 4 — Statistics
        if ($activeTab === 'statistics') {
            $statsNational = [
                'national_db_administrator' => User::role('national_db_administrator')->count(),
                'national_db_assistant'     => User::role('national_db_assistant')->count(),
                'observer_national_level'   => User::role('observer_national_level')->count(),
                'direct_permissions'        => DB::table('model_has_permissions')
                                                  ->join('permissions', 'permissions.id',
                                                         '=', 'model_has_permissions.permission_id')
                                                  ->where('model_has_permissions.model_type',
                                                          'App\\Models\\User')
                                                  ->select('permissions.name',
                                                           DB::raw('COUNT(DISTINCT model_has_permissions.model_id) as user_count'))
                                                  ->groupBy('permissions.name')
                                                  ->orderBy('permissions.name')
                                                  ->get(),
            ];

            $roleCountsByBranch = DB::table('users')
                ->join('model_has_roles', function ($j) {
                    $j->on('model_has_roles.model_id', '=', 'users.id')
                      ->where('model_has_roles.model_type', 'App\\Models\\User');
                })
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->whereIn('roles.name', [
                    'branch_secretary',
                    'branch_db_administrator',
                    'branch_db_assistant',
                    'division_db_assistant_finance',
                    'division_db_assistant_operations',
                ])
                ->select('users.branch_id', 'roles.name as role_name', DB::raw('COUNT(*) as cnt'))
                ->groupBy('users.branch_id', 'roles.name')
                ->get()
                ->groupBy('branch_id');

            $statsBranches = $branches->map(function ($branch) use ($roleCountsByBranch) {
                $counts = $roleCountsByBranch->get($branch->id, collect());
                $get = fn ($role) => $counts->firstWhere('role_name', $role)?->cnt ?? 0;
                return [
                    'branch'        => $branch,
                    'secretary'     => $get('branch_secretary'),
                    'db_admin'      => $get('branch_db_administrator'),
                    'db_assistant'  => $get('branch_db_assistant'),
                    'fin'           => $get('division_db_assistant_finance'),
                    'ops'           => $get('division_db_assistant_operations'),
                    'total'         => $get('branch_secretary')
                                     + $get('branch_db_administrator')
                                     + $get('branch_db_assistant')
                                     + $get('division_db_assistant_finance')
                                     + $get('division_db_assistant_operations'),
                ];
            });
        }

        // Tab 3 — Activity
        $activityData = [];
        $activityBranch = null;
        $activityLevel = null;
        $activitySelection = null;
        if ($activeTab === 'activity') {
            $activitySelection = $request->input('activity_scope', $defaultBranchId ? 'branch_'.$defaultBranchId : 'national');

            if ($activitySelection === 'national') {
                $activityLevel = 'national';
                $staffRoles = ['national_db_administrator', 'national_db_assistant', 'observer_national_level'];
                $staffPersons = User::role($staffRoles)->orderBy('first_name')->get();
                $activityData = [['group' => 'National Staff', 'persons' => $staffPersons]];
            } elseif (str_starts_with($activitySelection, 'branch_')) {
                $activityLevel = 'branch';
                $actBranchId = (int) str_replace('branch_', '', $activitySelection);
                $activityBranch = Branch::find($actBranchId);

                if ($activityBranch) {
                    $branchStaffRoles = ['branch_secretary', 'branch_db_administrator', 'branch_db_assistant'];
                    $branchStaffPersons = User::role($branchStaffRoles)
                        ->where('branch_id', $actBranchId)
                        ->orderBy('first_name')
                        ->get();
                    $activityData[] = ['group' => 'Branch Admin Staff', 'persons' => $branchStaffPersons];

                    $divisions = Division::where('branch_id', $actBranchId)->orderBy('name')->get();
                    foreach ($divisions as $division) {
                        $divRoles = ['division_db_assistant_finance', 'division_db_assistant_operations'];
                        $branchExclude = ['branch_secretary', 'branch_db_administrator', 'branch_db_assistant'];
                        $divPersons = User::whereHas('roles', fn ($q) => $q->whereIn('name', $divRoles))
                            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', $branchExclude))
                            ->where('division_id', $division->id)
                            ->orderBy('first_name')
                            ->get();
                        if ($divPersons->isNotEmpty()) {
                            $activityData[] = ['group' => $division->name, 'persons' => $divPersons];
                        }
                    }
                }
            }

            foreach ($activityData as &$group) {
                foreach ($group['persons'] as $person) {
                    $pid = $person->id;
                    // §8.2 definition: "entered" credits the staffer for work they submitted —
                    // counts PENDING + APPROVED, excludes REJECTED.
                    $person->cnt_membership_entered = DB::table('membership_payments')->where('submitted_by_user_id', $pid)->where('approval_status', '!=', 'rejected')->count();
                    $person->cnt_volunteering_entered = DB::table('activities')->where('submitted_by_user_id', $pid)->where('approval_status', '!=', 'rejected')->count();
                    $person->cnt_trainings_entered = DB::table('trainings')->where('submitted_by_user_id', $pid)->where('approval_status', '!=', 'rejected')->count();
                    $person->cnt_donations_entered = DB::table('donations')->where('entered_by_user_id', $pid)->where('approval_status', '!=', 'rejected')->count();
                }
            }
            unset($group);
        }

        return view('reports.database-team.index', compact(
            'activeTab',
            'showPhotos',
            'branches',
            'defaultBranchId',
            'nationalData',
            'branchData',
            'selectedBranch',
            'activityData',
            'activitySelection',
            'statsNational',
            'statsBranches',
        ));
    }
}
