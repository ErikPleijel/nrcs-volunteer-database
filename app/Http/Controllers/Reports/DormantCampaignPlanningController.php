<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Division;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DormantCampaignPlanningController extends Controller
{
    public function index(Request $request)
    {
        $user        = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId    = $user->getScopedId();
        $currentYear = now()->year;

        // Scope-aware redirect for branch-level users with no branch_id
        if ($accessLevel === 'branch' && ! $request->has('branch_id')) {
            return redirect()->route('reports.campaign-planning.dormant',
                array_merge($request->query(), ['branch_id' => $scopedId])
            );
        }

        // Highlight the row belonging to the current user's branch
        $highlightBranchId = null;
        if ($accessLevel === 'branch') {
            $highlightBranchId = $scopedId;
        } elseif ($accessLevel === 'division') {
            $division = Division::find($scopedId);
            if ($division) {
                $highlightBranchId = $division->branch_id;
            }
        }

        // Drill-down level
        $branchId        = $request->has('branch_id') ? $request->input('branch_id') : null;
        $currentBranch   = $branchId ? Branch::find($branchId) : null;
        $isDivisionLevel = $currentBranch !== null;

        // Filters
        $gender           = $request->input('gender');
        $ageBracket       = $request->input('age_bracket');

        // Filter params for link building (no branch_id)
        $filterParams = array_filter([
            'gender'       => $gender,
            'age_bracket'  => $ageBracket,
        ], fn($v) => $v !== null && $v !== '');

        // Area items
        $areaItems = $isDivisionLevel
            ? Division::where('branch_id', $currentBranch->id)->orderBy('name')->get()
            : Branch::orderBy('name')->get();

        // Build planning rows
        $planningData   = [];
        $summarySumDays = 0;
        $summaryCntDays = 0;
        foreach ($areaItems as $item) {
            $baseQuery = User::query()->dormant()
                ->where(function ($query) {
                    $query->whereNotNull('red_cross_unit_id')
                        ->orWhere(function ($q) {
                            $q->unassignedGhost();
                        });
                })
                ->where('lifecycle_status', '!=', 'archived');

            if ($isDivisionLevel) {
                $baseQuery->where('division_id', $item->id);
                $link      = null;
                $highlight = false;
            } else {
                $baseQuery->where('branch_id', $item->id);
                $link      = route('reports.campaign-planning.dormant', array_merge($filterParams, ['branch_id' => $item->id]));
                $highlight = $item->id == $highlightBranchId;
            }

            // Apply filters
            if ($gender) {
                $baseQuery->where('gender', $gender);
            }
            if ($ageBracket) {
                [$ageMin, $ageMax] = explode('|', $ageBracket) + [1 => ''];
                if ($ageMin !== '') $baseQuery->where('birth_year', '<=', $currentYear - (int) $ageMin);
                if ($ageMax !== '') $baseQuery->where('birth_year', '>=', $currentYear - (int) $ageMax);
            }
            $baseIds = (clone $baseQuery)->pluck('id');
            $total   = $baseIds->count();

            $contactCounts = $total > 0
                ? DB::table('messaging_recipients')
                    ->whereIn('recipient_id', $baseIds)
                    ->where('recipient_type', 'App\\Models\\User')
                    ->where('status', 'sent')
                    ->select('recipient_id', DB::raw('COUNT(*) as cnt'))
                    ->groupBy('recipient_id')
                    ->pluck('cnt', 'recipient_id')
                : collect();

            $notContacted = $baseIds->filter(fn($id) => ! isset($contactCounts[$id]))->count();
            $once         = $baseIds->filter(fn($id) => ($contactCounts[$id] ?? 0) === 1)->count();
            $twoPlus      = $baseIds->filter(fn($id) => ($contactCounts[$id] ?? 0) >= 2)->count();
            // Accumulate raw sum/count (not per-row averages) so the summary
            // row below can compute a true population-wide average.
            $avgRow  = $total > 0
                ? (clone $baseQuery)->selectRaw('SUM(DATEDIFF(NOW(), last_activity_at)) as sum_days, COUNT(last_activity_at) as cnt_days')->first()
                : null;
            $avgDays = ($avgRow && $avgRow->cnt_days > 0) ? round($avgRow->sum_days / $avgRow->cnt_days) : null;

            $summarySumDays += $avgRow->sum_days ?? 0;
            $summaryCntDays += $avgRow->cnt_days ?? 0;

            $planningData[] = [
                'label'         => $item->name,
                'link'          => $link,
                'highlight'     => $highlight,
                'total'         => $total,
                'not_contacted' => $notContacted,
                'once'          => $once,
                'two_plus'      => $twoPlus,
                'avg_days'      => $avgDays,
            ];
        }

        $summaryTotal        = array_sum(array_column($planningData, 'total'));
        $summaryNotContacted = array_sum(array_column($planningData, 'not_contacted'));
        $summaryOnce         = array_sum(array_column($planningData, 'once'));
        $summaryTwoPlus      = array_sum(array_column($planningData, 'two_plus'));
        $summaryAvgDays      = $summaryCntDays > 0 ? round($summarySumDays / $summaryCntDays) : null;

        $user->touchLastAdminActivity();

        return view('reports.campaign-planning.dormant', compact(
            'planningData',
            'isDivisionLevel',
            'currentBranch',
            'filterParams',
            'accessLevel',
            'currentYear',
            'summaryTotal',
            'summaryNotContacted',
            'summaryOnce',
            'summaryTwoPlus',
            'summaryAvgDays',
        ));
    }
}
