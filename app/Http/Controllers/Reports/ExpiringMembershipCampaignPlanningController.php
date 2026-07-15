<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CampaignPurpose;
use App\Models\Division;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpiringMembershipCampaignPlanningController extends Controller
{
    public function index(Request $request)
    {
        $user        = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId    = $user->getScopedId();

        // ── Scope-aware redirect for branch-level users ───────────────────
        if ($accessLevel === 'branch' && ! $request->has('branch_id')) {
            return redirect()->route('reports.campaign-planning.expiring-membership',
                array_merge($request->query(), ['branch_id' => $scopedId])
            );
        }

        // Default person_type to "all" (no restriction) when no filters set —
        // bakes the default into the URL instead of applying it silently,
        // matching DormantCampaignPlanningController's convention.
        if (! $request->has('person_type')) {
            return redirect()->route('reports.campaign-planning.expiring-membership',
                array_merge($request->query(), ['person_type' => ''])
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

        // Filters (person_type is guaranteed present by the redirect above)
        $personType   = $request->input('person_type');
        $expiryWindow = $request->input('expiry_window', '28');

        // Filter params for link building (gender removed — no longer a UI filter)
        $filterParams = array_filter([
            'person_type'   => $personType,
            'expiry_window' => $expiryWindow,
        ], fn($v) => $v !== null && $v !== '');

        // Area items
        $areaItems = $isDivisionLevel
            ? Division::where('branch_id', $currentBranch->id)->orderBy('name')->get()
            : Branch::orderBy('name')->get();

        // "Contacted" is scoped to pre-expiry reminder messages sent within
        // the last CONTACT_WINDOW_DAYS days — mirrors UserFilterService's
        // campaign_msg=membership_pre_expiry|0|180 logic exactly.
        $purposeId = CampaignPurpose::where('slug', 'membership_pre_expiry')->value('id');
        $contactWindowStart = now()->subDays(CampaignPurpose::CONTACT_WINDOW_DAYS);

        // Build planning rows
        $planningData = [];
        foreach ($areaItems as $item) {
            $baseQuery = User::query()->whereIn('lifecycle_status', ['active', 'dormant']);

            if ($isDivisionLevel) {
                $baseQuery->where('division_id', $item->id);
                $link      = null;
                $highlight = false;
            } else {
                $baseQuery->where('branch_id', $item->id);
                $link      = route('reports.campaign-planning.expiring-membership', array_merge($filterParams, ['branch_id' => $item->id]));
                $highlight = $item->id == $highlightBranchId;
            }

            // Apply filters
            if ($personType === 'member') {
                $baseQuery->hasValidMembership();
            } elseif ($personType === 'volunteer') {
                $baseQuery->whereNotNull('red_cross_unit_id')->where('lifecycle_status', '!=', 'archived');
            }
            if ($expiryWindow === '14') {
                $baseQuery->whereHas('currentMembershipPayment', fn($q) => $q->personal()->whereBetween('expiry_date', [now()->startOfDay(), now()->addDays(14)->endOfDay()]));
            } elseif ($expiryWindow === '28') {
                $baseQuery->whereHas('currentMembershipPayment', fn($q) => $q->personal()->whereBetween('expiry_date', [now()->startOfDay(), now()->addDays(28)->endOfDay()]));
            } elseif ($expiryWindow === 'expired') {
                // Same whereHas()->whereDoesntHave() shape as UserFilterService's
                // expired_members branch (already fixed + verified this session) —
                // ->personal() on both clauses flips both directions, not just
                // narrows: excludes org-contact-only users whose org payment has
                // lapsed, and includes users whose own personal payment is
                // expired but who are separately a contact on a still-valid org
                // payment (previously masked by that org payment).
                $baseQuery->whereHas('membershipPayments', fn ($q) => $q->personal())
                    ->whereDoesntHave('currentMembershipPayment', fn ($q) => $q->personal());
            }

            $baseIds = (clone $baseQuery)->pluck('id');
            $total   = $baseIds->count();

            $contactCounts = $total > 0
                ? DB::table('messaging_recipients')
                    ->join('messaging_campaigns', 'messaging_recipients.messaging_campaign_id', '=', 'messaging_campaigns.id')
                    ->whereIn('messaging_recipients.recipient_id', $baseIds)
                    ->where('messaging_recipients.recipient_type', 'App\\Models\\User')
                    ->where('messaging_recipients.status', 'sent')
                    ->where('messaging_campaigns.purpose_id', $purposeId)
                    ->where('messaging_recipients.sent_at', '>=', $contactWindowStart)
                    ->select('messaging_recipients.recipient_id', DB::raw('COUNT(*) as cnt'))
                    ->groupBy('messaging_recipients.recipient_id')
                    ->pluck('cnt', 'recipient_id')
                : collect();

            $notContacted = $baseIds->filter(fn($id) => ! isset($contactCounts[$id]))->count();
            $once         = $baseIds->filter(fn($id) => ($contactCounts[$id] ?? 0) === 1)->count();
            $twoPlus      = $baseIds->filter(fn($id) => ($contactCounts[$id] ?? 0) >= 2)->count();

            $planningData[] = [
                'label'         => $item->name,
                'link'          => $link,
                'highlight'     => $highlight,
                'total'         => $total,
                'not_contacted' => $notContacted,
                'once'          => $once,
                'two_plus'      => $twoPlus,
            ];
        }

        // ── Summary stats (sum across all rows) ───────────────────────────
        $summaryTotal        = array_sum(array_column($planningData, 'total'));
        $summaryNotContacted = array_sum(array_column($planningData, 'not_contacted'));
        $summaryOnce         = array_sum(array_column($planningData, 'once'));
        $summaryTwoPlus      = array_sum(array_column($planningData, 'two_plus'));

        $user->touchLastAdminActivity();

        return view('reports.campaign-planning.expiring-membership', compact(
            'planningData',
            'isDivisionLevel',
            'currentBranch',
            'filterParams',
            'accessLevel',
            'summaryTotal',
            'summaryNotContacted',
            'summaryOnce',
            'summaryTwoPlus',
        ));
    }
}
