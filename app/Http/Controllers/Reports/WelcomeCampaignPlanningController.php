<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Division;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WelcomeCampaignPlanningController extends Controller
{
    public function index(Request $request)
    {
        $user        = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId    = $user->getScopedId();
        $currentYear = now()->year;

        // Branch-level admins land on their own branch by default.
        // Only redirect when branch_id is absent from the URL entirely — an explicit
        // branch_id= (even empty) means the user navigated intentionally, so leave them alone.
        if ($accessLevel === 'branch' && $scopedId && ! $request->has('branch_id')) {
            return redirect()->route(
                'reports.campaign-planning.welcome',
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
        $branchId        = $request->input('branch_id');
        $currentBranch   = $branchId ? Branch::find($branchId) : null;
        $isDivisionLevel = $currentBranch !== null;

        // Filters — contribution defaults to 'volunteering'
        $contribution     = $request->input('contribution', 'volunteering');
        $registeredWithin = $request->input('registered_within');
        $gender           = $request->input('gender');
        $ageBracket       = $request->input('age_bracket');

        // Filter params for link building (no branch_id); contribution always included
        $filterParams = ['contribution' => $contribution];
        if ($registeredWithin) $filterParams['registered_within'] = $registeredWithin;
        if ($gender)           $filterParams['gender']            = $gender;
        if ($ageBracket)       $filterParams['age_bracket']       = $ageBracket;

        // Area items
        $areaItems = $isDivisionLevel
            ? Division::where('branch_id', $currentBranch->id)->orderBy('name')->get()
            : Branch::orderBy('name')->get();

        // Build planning rows
        $planningData   = [];
        $summarySumDays = 0;
        $summaryCntDays = 0;
        foreach ($areaItems as $item) {
            $baseQuery = User::query()->awaitingEngagement();

            if ($isDivisionLevel) {
                $baseQuery->where('division_id', $item->id);
                $link      = null;
                $highlight = false;
            } else {
                $baseQuery->where('branch_id', $item->id);
                $link      = route('reports.campaign-planning.welcome', array_merge($filterParams, ['branch_id' => $item->id]));
                $highlight = $item->id == $highlightBranchId;
            }

            // Apply filters
            if ($contribution === 'volunteering') {
                $baseQuery->where('can_contribute_volunteering', true);
            } elseif ($contribution === 'member') {
                $baseQuery->where('can_contribute_member', true);
            }
            if ($registeredWithin) {
                $baseQuery->where('created_at', '>=', now()->subDays((int) $registeredWithin));
            }
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
                ? (clone $baseQuery)->selectRaw('SUM(DATEDIFF(NOW(), created_at)) as sum_days, COUNT(created_at) as cnt_days')->first()
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

        // Global lifecycle counts (unfiltered) for the popup
        $awaitingEngagement = User::query()->awaitingEngagement()->count();
        $pendingVolunteers  = User::query()->awaitingEngagement()->where('can_contribute_volunteering', true)->count();
        $pendingMembers     = User::query()->awaitingEngagement()->where('can_contribute_member', true)->count();
        $archivedCount      = User::query()->archived()->count();

        // Aggregate filtered totals from planningData for the stat cards
        $summaryTotal        = array_sum(array_column($planningData, 'total'));
        $summaryNotContacted = array_sum(array_column($planningData, 'not_contacted'));
        $summaryOnce         = array_sum(array_column($planningData, 'once'));
        $summaryTwoPlus      = array_sum(array_column($planningData, 'two_plus'));
        $summaryAvgDays      = $summaryCntDays > 0 ? round($summarySumDays / $summaryCntDays) : null;

        $user->touchLastAdminActivity();

        return view('reports.campaign-planning.welcome', compact(
            'planningData',
            'isDivisionLevel',
            'currentBranch',
            'filterParams',
            'accessLevel',
            'currentYear',
            'awaitingEngagement',
            'pendingVolunteers',
            'pendingMembers',
            'archivedCount',
            'summaryTotal',
            'summaryNotContacted',
            'summaryOnce',
            'summaryTwoPlus',
            'summaryAvgDays'
        ));
    }
}
