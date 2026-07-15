<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Division;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DonationAppreciationCampaignPlanningController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();

        $activeTab = $request->input('tab', 'tracker');

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
        $branchId = $request->input('branch_id');
        $currentBranch = $branchId ? Branch::find($branchId) : null;
        $isDivisionLevel = $currentBranch !== null;

        // Filter params for breadcrumb and branch links (no branch_id)
        $filterParams = $request->except(['branch_id', 'tab']);

        // Purpose ID for donation_appreciation campaigns
        $purposeId = \App\Models\CampaignPurpose::where('slug', 'donation_appreciation')->value('id');

        // Area items
        $areaItems = $isDivisionLevel
            ? Division::where('branch_id', $currentBranch->id)->orderBy('name')->get()
            : Branch::orderBy('name')->get();

        $areaField = $isDivisionLevel ? 'division_id' : 'branch_id';
        $areaIds = $areaItems->pluck('id');

        $donationExists = 'EXISTS (
            SELECT 1 FROM donations d
            WHERE d.user_id = users.id
              AND d.is_deleted = 0
              AND d.anonymous = 0
              AND d.approval_status = \'approved\'
              AND d.removed_date IS NULL
        )';

        // Lifecycle restriction matching the campaign wizard's 'operational' default
        // (archived_filter default in UserFilterService::apply()).
        $operationalStatuses = User::OPERATIONAL_STATUSES;

        // All Donors — anyone with a qualifying donation, same lifecycle population
        // as the campaign wizard's default (matches donation_filter=has exactly).
        $allDonorsMap = User::query()
            ->select($areaField, DB::raw('COUNT(*) as cnt'))
            ->whereIn($areaField, $areaIds)
            ->whereIn('lifecycle_status', $operationalStatuses)
            ->whereRaw($donationExists)
            ->groupBy($areaField)
            ->pluck('cnt', $areaField);

        // Never Thanked — same EXISTS/NOT EXISTS logic as
        // UserFilterService::apply()'s donation_since_contact 'never' branch.
        $neverThankedMap = User::query()
            ->select($areaField, DB::raw('COUNT(*) as cnt'))
            ->whereIn($areaField, $areaIds)
            ->whereIn('lifecycle_status', $operationalStatuses)
            ->whereRaw(
                "{$donationExists}
                AND NOT EXISTS (
                    SELECT 1 FROM messaging_recipients mr
                    INNER JOIN messaging_campaigns mc
                            ON mr.messaging_campaign_id = mc.id
                    WHERE mc.purpose_id = ?
                      AND mr.recipient_type = ?
                      AND mr.recipient_id = users.id
                      AND mr.status = 'sent'
                )",
                [$purposeId, 'App\\Models\\User']
            )
            ->groupBy($areaField)
            ->pluck('cnt', $areaField);

        // Donated Again — same MAX(date_donation) > MAX(sent_at) comparison as
        // UserFilterService::apply()'s donation_since_contact 'since_last' branch.
        $donatedAgainMap = User::query()
            ->select($areaField, DB::raw('COUNT(*) as cnt'))
            ->whereIn($areaField, $areaIds)
            ->whereIn('lifecycle_status', $operationalStatuses)
            ->whereRaw(
                '(SELECT MAX(d.date_donation) FROM donations d
                    WHERE d.user_id = users.id
                      AND d.is_deleted = 0
                      AND d.anonymous = 0
                      AND d.approval_status = \'approved\'
                      AND d.removed_date IS NULL)
                 >
                 (SELECT MAX(mr.sent_at) FROM messaging_recipients mr
                    INNER JOIN messaging_campaigns mc
                            ON mr.messaging_campaign_id = mc.id
                    WHERE mc.purpose_id = ?
                      AND mr.recipient_type = ?
                      AND mr.recipient_id = users.id
                      AND mr.status = \'sent\')',
                [$purposeId, 'App\\Models\\User']
            )
            ->groupBy($areaField)
            ->pluck('cnt', $areaField);

        // Build planning rows
        $planningData = [];
        foreach ($areaItems as $item) {
            if ($isDivisionLevel) {
                $link = null;
                $highlight = false;
            } else {
                $link = route('reports.campaign-planning.donation-appreciation', array_merge($filterParams, ['branch_id' => $item->id]));
                $highlight = $item->id == $highlightBranchId;
            }

            $planningData[] = [
                'label' => $item->name,
                'link' => $link,
                'highlight' => $highlight,
                'all_donors' => (int) ($allDonorsMap[$item->id] ?? 0),
                'never_thanked' => (int) ($neverThankedMap[$item->id] ?? 0),
                'donated_again' => (int) ($donatedAgainMap[$item->id] ?? 0),
            ];
        }

        $user->touchLastAdminActivity();

        return view('reports.campaign-planning.donation-appreciation', compact(
            'planningData',
            'isDivisionLevel',
            'currentBranch',
            'filterParams',
            'activeTab'
        ));
    }
}
