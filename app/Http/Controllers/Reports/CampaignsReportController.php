<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Division;
use App\Models\MessagingCampaign;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CampaignsReportController extends Controller
{
    public function index(Request $request)
    {
        $user        = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId    = $user->getScopedId();

        // Highlight campaigns originated by the viewing user's own branch
        $highlightBranchId = null;
        if ($accessLevel === 'branch') {
            $highlightBranchId = $scopedId;
        } elseif ($accessLevel === 'division') {
            $division = Division::find($scopedId);
            if ($division) {
                $highlightBranchId = $division->branch_id;
            }
        }

        $activeTab = $request->input('tab', 'origin');
        $branchId  = $request->input('branch_id');

        $purposes    = \App\Models\CampaignPurpose::orderBy('name')->get();
        $branches    = Branch::orderBy('name')->get();
        $branchNames = $branches->pluck('name', 'id');

        $campaignQuery = MessagingCampaign::query()
            ->with(['purpose', 'originBranch'])
            ->when($request->filled('date_from'), fn($q) => $q->where('send_started_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn($q) => $q->where('send_started_at', '<=', $request->date_to . ' 23:59:59'))
            ->when($request->filled('purpose_id'), fn($q) => $q->where('purpose_id', $request->purpose_id))
            ->orderBy('send_started_at', 'desc');

        $rows = [];

        if ($activeTab === 'origin') {
            $campaigns = (clone $campaignQuery)
                ->when($branchId, fn($q) => $q->where('origin_branch_id', $branchId))
                ->where('stats_sent', '>', 0)
                ->get();

            foreach ($campaigns as $campaign) {
                $branchLabel = $campaign->origin_level === 'national'
                    ? 'National'
                    : ($campaign->originBranch?->name ?? '—');

                $rows[] = [
                    'campaign_title' => $campaign->title,
                    'purpose'        => $campaign->purpose?->name ?? '—',
                    'origin_code'    => $campaign->code,
                    'branch_label'   => $branchLabel,
                    'date_sent'      => $campaign->send_started_at,
                    'sent_count'     => (int) $campaign->stats_sent,
                    'highlight'      => $highlightBranchId !== null
                        && $campaign->origin_branch_id == $highlightBranchId,
                ];
            }
        } else {
            // Destination tab: one row per (campaign, destination branch) pair.
            $campaigns = $campaignQuery->get()->keyBy('id');

            $sentPairs = DB::table('messaging_recipients')
                ->join('users', 'users.id', '=', 'messaging_recipients.recipient_id')
                ->where('messaging_recipients.recipient_type', User::class)
                ->where('messaging_recipients.status', 'sent')
                ->whereIn('messaging_recipients.messaging_campaign_id', $campaigns->keys())
                ->when($branchId, fn($q) => $q->where('users.branch_id', $branchId))
                ->groupBy('messaging_recipients.messaging_campaign_id', 'users.branch_id')
                ->select(
                    'messaging_recipients.messaging_campaign_id as campaign_id',
                    'users.branch_id as destination_branch_id',
                    DB::raw('count(*) as sent_count')
                )
                ->get()
                ->groupBy('campaign_id');

            // Iterate campaigns in their already date-ordered sequence, so each
            // campaign's per-branch rows stay grouped together in the output;
            // campaigns with zero sent recipients simply produce no rows.
            foreach ($campaigns as $campaign) {
                $pairs = $sentPairs->get($campaign->id, collect())
                    ->sortBy(fn ($pair) => $branchNames[$pair->destination_branch_id] ?? '');

                foreach ($pairs as $pair) {
                    $rows[] = [
                        'campaign_title' => $campaign->title,
                        'purpose'        => $campaign->purpose?->name ?? '—',
                        'origin_code'    => $campaign->code,
                        'branch_label'   => $pair->destination_branch_id
                            ? ($branchNames[$pair->destination_branch_id] ?? '—')
                            : 'No branch',
                        'date_sent'      => $campaign->send_started_at,
                        'sent_count'     => (int) $pair->sent_count,
                        'highlight'      => $highlightBranchId !== null
                            && $campaign->origin_branch_id == $highlightBranchId,
                    ];
                }
            }
        }

        $filterParams = $request->except(['tab']);

        $user->touchLastAdminActivity();

        return view('reports.campaign-planning.campaigns', compact(
            'activeTab',
            'rows',
            'purposes',
            'branches',
            'branchId',
            'filterParams'
        ));
    }
}
