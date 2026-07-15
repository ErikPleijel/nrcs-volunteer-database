<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Division;
use App\Models\RedCrossUnit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RedCrossUnitsReportController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();
        $activeTab = $request->input('tab', 'demographics');

        // Determine highlight branch
        $highlightBranchId = null;
        if ($accessLevel === 'branch') {
            $highlightBranchId = $scopedId;
        } elseif ($accessLevel === 'division') {
            $highlightBranchId = Division::find($scopedId)?->branch_id;
        }

        // Drill-down level
        $branchId = $request->input('branch_id');
        $divisionId = $request->input('division_id');

        if (! $branchId) {
            $level = 'branch';
            $rows = Branch::orderBy('name')->get();
            $currentBranch = null;
            $currentDivision = null;
        } elseif (! $divisionId) {
            $level = 'division';
            $rows = Division::where('branch_id', $branchId)->orderBy('name')->get();
            $currentBranch = Branch::find($branchId);
            $currentDivision = null;
        } else {
            $level = 'unit';
            $rows = RedCrossUnit::where('division_id', $divisionId)
                ->where('is_active', true)->orderBy('name')->get();
            $currentBranch = Branch::find($branchId);
            $currentDivision = Division::find($divisionId);
        }

        $baseUsers = function ($rowItem) use ($level) {
            $q = User::query()->whereIn('lifecycle_status', ['active', 'dormant']);
            if ($level === 'unit') {
                $q->where('red_cross_unit_id', $rowItem->id);
            } elseif ($level === 'division') {
                $unitIds = RedCrossUnit::where('division_id', $rowItem->id)
                    ->where('is_active', true)->pluck('id');
                $q->whereIn('red_cross_unit_id', $unitIds);
            } elseif ($level === 'branch') {
                $unitIds = RedCrossUnit::whereHas('division', fn ($d) => $d->where('branch_id', $rowItem->id)
                )->where('is_active', true)->pluck('id');
                $q->whereIn('red_cross_unit_id', $unitIds);
            }

            return $q;
        };

        $rowLink = function ($rowItem) use ($level, $branchId, $activeTab) {
            if ($level === 'branch') {
                return route('reports.red-cross-units.index', ['branch_id' => $rowItem->id, 'tab' => $activeTab]);
            } elseif ($level === 'division') {
                return route('reports.red-cross-units.index', ['branch_id' => $branchId, 'division_id' => $rowItem->id, 'tab' => $activeTab]);
            }

            return null;
        };

        $countUnits = function ($rowItem) use ($level) {
            if ($level === 'branch') {
                return RedCrossUnit::whereHas('division', fn ($d) => $d->where('branch_id', $rowItem->id)
                )->where('is_active', true)->count();
            } elseif ($level === 'division') {
                return RedCrossUnit::where('division_id', $rowItem->id)
                    ->where('is_active', true)->count();
            }

            return null;
        };

        // Tab 1 — Demographics
        $demographicsData = [];
        if ($activeTab === 'demographics') {
            $currentYear = now()->year;
            $ageGroups = [
                'under_15' => ['label' => '< 15',  'min' => null, 'max' => 14],
                'age_15_24' => ['label' => '15–24', 'min' => 15,   'max' => 24],
                'age_25_34' => ['label' => '25–34', 'min' => 25,   'max' => 34],
                'age_35_44' => ['label' => '35–44', 'min' => 35,   'max' => 44],
                'age_45_54' => ['label' => '45–54', 'min' => 45,   'max' => 54],
                'age_55_64' => ['label' => '55–64', 'min' => 55,   'max' => 64],
                'age_65plus' => ['label' => '65+',   'min' => 65,   'max' => null],
            ];

            foreach ($rows as $row) {
                $q = $baseUsers($row);
                $total = (clone $q)->count();
                $men = (clone $q)->where('gender', 'male')->count();
                $women = (clone $q)->where('gender', 'female')->count();
                $avgAge = (clone $q)->whereNotNull('birth_year')
                    ->selectRaw('AVG(? - birth_year) as avg_age', [$currentYear])
                    ->value('avg_age');

                $groups = [];
                foreach ($ageGroups as $key => $group) {
                    $gq = (clone $q)->whereNotNull('birth_year');
                    if ($group['min']) {
                        $gq->where('birth_year', '<=', $currentYear - $group['min']);
                    }
                    if ($group['max']) {
                        $gq->where('birth_year', '>=', $currentYear - $group['max']);
                    }
                    $groups[$key] = $gq->count();
                }

                $demographicsData[] = [
                    'id' => $row->id,
                    'label' => $row->name,
                    'link' => $rowLink($row),
                    'total_volunteers' => $total,
                    'total_units' => $countUnits($row),
                    'men' => $men,
                    'women' => $women,
                    'women_display' => $women.($total > 0 ? ' ('.round(($women / $total) * 100).'%)' : ''),
                    'avg_age' => $avgAge ? round($avgAge, 1) : null,
                    'groups' => $groups,
                ];
            }
        }

        // Tab 2 — Training
        $trainingData = [];
        if ($activeTab === 'training') {
            foreach ($rows as $row) {
                $q = $baseUsers($row);
                $total = (clone $q)->count();

                $anyTraining = (clone $q)->whereHas('trainings', fn ($t) => $t->where('is_deleted', false)
                )->count();

                $firstAidTraining = (clone $q)->whereHas('trainings', fn ($t) => $t->where('is_deleted', false)
                    ->whereHas('trainingType', fn ($tt) => $tt->where('is_first_aid', true))
                )->count();

                $trained12m = (clone $q)->whereHas('trainings', fn ($t) => $t->where('is_deleted', false)
                    ->where('training_date', '>=', now()->subMonths(12))
                )->count();

                $trained3m = (clone $q)->whereHas('trainings', fn ($t) => $t->where('is_deleted', false)
                    ->where('training_date', '>=', now()->subMonths(3))
                )->count();

                $trained1m = (clone $q)->whereHas('trainings', fn ($t) => $t->where('is_deleted', false)
                    ->where('training_date', '>=', now()->subMonths(1))
                )->count();

                $trainingData[] = [
                    'id' => $row->id,
                    'label' => $row->name,
                    'link' => $rowLink($row),
                    'total_volunteers' => $total,
                    'total_units' => $countUnits($row),
                    'pct_any' => $total > 0 ? round(($anyTraining / $total) * 100) : null,
                    'pct_first_aid' => $total > 0 ? round(($firstAidTraining / $total) * 100) : null,
                    'trained_12m' => $trained12m,
                    'trained_3m' => $trained3m,
                    'trained_1m' => $trained1m,
                ];
            }
        }

        // Tab 3 — Activity
        $activityData = [];
        if ($activeTab === 'activity') {
            foreach ($rows as $row) {
                $q = $baseUsers($row);
                $total = (clone $q)->count();
                $userIds = (clone $q)->pluck('id');

                $allTimeHours = DB::table('activities')
                    ->whereIn('user_id', $userIds)
                    ->where('is_deleted', false)
                    ->where('approval_status', 'approved') // Phase 2: only approved records are real
                    ->sum('hours');

                $hours12m = DB::table('activities')
                    ->whereIn('user_id', $userIds)
                    ->where('is_deleted', false)
                    ->where('approval_status', 'approved') // Phase 2: only approved records are real
                    ->where('date', '>=', now()->subMonths(12))
                    ->sum('hours');

                $hours3m = DB::table('activities')
                    ->whereIn('user_id', $userIds)
                    ->where('is_deleted', false)
                    ->where('approval_status', 'approved') // Phase 2: only approved records are real
                    ->where('date', '>=', now()->subMonths(3))
                    ->sum('hours');

                $hours1m = DB::table('activities')
                    ->whereIn('user_id', $userIds)
                    ->where('is_deleted', false)
                    ->where('approval_status', 'approved') // Phase 2: only approved records are real
                    ->where('date', '>=', now()->subMonths(1))
                    ->sum('hours');

                $activityData[] = [
                    'id' => $row->id,
                    'label' => $row->name,
                    'link' => $rowLink($row),
                    'total_volunteers' => $total,
                    'total_units' => $countUnits($row),
                    'total_hours' => $allTimeHours,
                    'hours_12m' => $hours12m,
                    'hours_3m' => $hours3m,
                    'hours_1m' => $hours1m,
                    'hours_per_volunteer' => ($total >= 5 && $total > 0)
                                               ? round($allTimeHours / $total, 1)
                                               : null,
                ];
            }
        }

        // Tab 4 — Account Status
        $accountData = [];
        if ($activeTab === 'account') {
            foreach ($rows as $row) {
                $q = $baseUsers($row);
                $total = (clone $q)->count();

                $neverLoggedIn = (clone $q)->whereNull('last_login_at')->count();
                $pctNeverLoggedIn = $total > 0 ? round(($neverLoggedIn / $total) * 100) : null;

                $avgDaysSinceLogin = (clone $q)->whereNotNull('last_login_at')
                    ->selectRaw('AVG(DATEDIFF(NOW(), last_login_at)) as avg_days')
                    ->value('avg_days');

                $hasEmail = (clone $q)->whereNotNull('email')->count();
                $pctHasEmail = $total > 0 ? round(($hasEmail / $total) * 100) : null;

                $hasPicture = (clone $q)->whereNotNull('picture')->count();
                $pctPicture = $total > 0 ? round(($hasPicture / $total) * 100) : null;

                $avgDaysSinceActivity = (clone $q)->whereNotNull('last_activity_at')
                    ->selectRaw('AVG(DATEDIFF(NOW(), last_activity_at)) as avg_days')
                    ->value('avg_days');

                $dormantCount = (clone $q)->where('lifecycle_status', 'dormant')->count();
                $pctDormant = $total > 0 ? round(($dormantCount / $total) * 100) : null;

                $accountData[] = [
                    'id' => $row->id,
                    'label' => $row->name,
                    'link' => $rowLink($row),
                    'total_volunteers' => $total,
                    'total_units' => $countUnits($row),
                    'pct_never_logged_in' => $pctNeverLoggedIn,
                    'avg_days_since_login' => $avgDaysSinceLogin ? round($avgDaysSinceLogin) : null,
                    'pct_has_email' => $pctHasEmail,
                    'pct_picture' => $pctPicture,
                    'avg_days_since_activity' => $avgDaysSinceActivity ? round($avgDaysSinceActivity) : null,
                    'pct_dormant' => $pctDormant,
                ];
            }
        }

        return view('reports.red-cross-units.index', compact(
            'activeTab',
            'level',
            'rows',
            'currentBranch',
            'currentDivision',
            'highlightBranchId',
            'demographicsData',
            'trainingData',
            'activityData',
            'accountData',
        ));
    }
}
