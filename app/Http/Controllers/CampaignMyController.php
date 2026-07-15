<?php

namespace App\Http\Controllers;

use App\Models\MessagingCampaign;
use App\Models\User;
use App\Services\CampaignAudienceSummaryService;
use App\Services\UserFilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CampaignMyController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // National users might want admin inbox; but let them use this too.
        $status = $request->get('status', 'draft');

        $tabScopes = [
            'all' => null,
            'draft' => ['draft'],
            'submitted' => ['proposed', 'queued'],
            'approved' => ['approved', 'sending'],
            'sent' => ['sent'],
            'rejected' => ['rejected'],
            'cancelled' => ['cancelled'],
        ];

        if (!array_key_exists($status, $tabScopes)) {
            $status = 'draft';
        }

        $baseQuery = $user->getAccessLevel() === 'national'
            ? MessagingCampaign::query()->where('origin_level', 'national')
            : MessagingCampaign::query()->where('origin_branch_id', $user->getScopedBranchId());

        $statusCounts = (clone $baseQuery)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $tabCounts = [
            'all' => $statusCounts->sum(),
            'draft' => (int)($statusCounts['draft'] ?? 0),
            'submitted' => (int)($statusCounts['proposed'] ?? 0) + (int)($statusCounts['queued'] ?? 0),
            'approved' => (int)($statusCounts['approved'] ?? 0) + (int)($statusCounts['sending'] ?? 0),
            'sent' => (int)($statusCounts['sent'] ?? 0),
            'rejected' => (int)($statusCounts['rejected'] ?? 0),
            'cancelled' => (int)($statusCounts['cancelled'] ?? 0),
        ];

        $campaigns = $baseQuery
            ->with(['creator', 'submitter', 'approver', 'rejector', 'originBranch'])
            ->when($tabScopes[$status], fn ($q) => $q->whereIn('status', $tabScopes[$status]))
            ->latest('updated_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('campaigns.mine.index', compact('campaigns', 'status', 'tabCounts'));
    }

    public function duplicate(MessagingCampaign $campaign)
    {
        $user = Auth::user();

        // Origin-based: any campaign that originates from the user's own scope can be duplicated.
        $originMatches = $user->getAccessLevel() === 'national'
            ? $campaign->origin_level === 'national'
            : $campaign->origin_branch_id === $user->getScopedBranchId();

        if (! $originMatches) {
            abort(403);
        }

        // You can allow duplicating any status; but most useful for rejected/proposed.
        $new = $campaign->replicate([
            'status',
            'submitted_at',
            'submitted_by',
            'approved_by',
            'approved_at',
            'rejected_by',
            'rejected_at',
            'review_note',
            'stats_total',
            'stats_sent',
            'stats_failed',
            'created_at',
            'updated_at',
        ]);

        $new->status = 'draft';
        $new->submitted_at = null;
        $new->submitted_by = null;
        $new->approved_by = null;
        $new->approved_at = null;
        $new->rejected_by = null;
        $new->rejected_at = null;
        $new->review_note = null;
        $new->stats_total = 0;
        $new->stats_sent = 0;
        $new->stats_failed = 0;

        $new->created_by = $user->id;

        // Small hint to help user distinguish it
        $new->title = $campaign->title ? ($campaign->title . ' (copy)') : 'Untitled campaign (copy)';

        $new->save();

        return redirect()->route('campaigns.wizard.step1', $new)
            ->with('success', 'A new draft copy was created. You can edit and resubmit it.');
    }

    public function show(MessagingCampaign $campaign, UserFilterService $userFilterService, CampaignAudienceSummaryService $audienceSummaryService)
    {
        $user = Auth::user();

        // Origin-based: any campaign that originates from the user's own scope is viewable.
        $originMatches = $user->getAccessLevel() === 'national'
            ? $campaign->origin_level === 'national'
            : $campaign->origin_branch_id === $user->getScopedBranchId();

        if (! $originMatches) {
            abort(403);
        }

        $campaign->load(['creator', 'submitter', 'approver', 'rejector']);

        $recipientCounts = $campaign->recipients()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $deliveryStats = [
            'total' => $recipientCounts->sum(),
            'sent' => (int)($recipientCounts['sent'] ?? 0),
            'failed' => (int)($recipientCounts['failed'] ?? 0) + (int)($recipientCounts['bounced'] ?? 0) + (int)($recipientCounts['undeliverable'] ?? 0),
            'pending' => (int)($recipientCounts['pending'] ?? 0),
            'queued' => (int)($recipientCounts['queued'] ?? 0),
        ];

        // Audience stats (same logic as CampaignAdminController::show)
        $filters = is_array($campaign->filter_json) ? $campaign->filter_json : [];

        $filteredQuery = $userFilterService->apply(
            User::query()->with(['branch', 'division', 'redCrossUnit'])->where('is_super_admin', false),
            $filters,
            $campaign->scope_level,
            $campaign->scope_id
        );

        $channel = $campaign->channel;

        $summary = in_array($campaign->status, ['sending', 'sent'], true)
            ? $audienceSummaryService->summarizeFromRecipients($campaign)
            : $audienceSummaryService->summarize($filteredQuery, $channel);

        $matchedTotal = $summary['matchedTotal'];
        $emailContactable = $summary['emailContactable'];
        $smsContactable = $summary['smsContactable'];
        $willEmail = $summary['willEmail'];
        $willSms = $summary['willSms'];
        $willReach = $summary['willReach'];
        $mayReceiveTwo = $summary['mayReceiveTwo'];
        $noReach = $summary['noReach'];
        $reachabilityKnown = $summary['reachability_known'];

        return view('campaigns.mine.show', compact(
            'campaign',
            'deliveryStats',
            'matchedTotal',
            'emailContactable',
            'smsContactable',
            'willEmail',
            'willSms',
            'willReach',
            'mayReceiveTwo',
            'noReach',
            'reachabilityKnown'
        ));
    }

    public function destroy(MessagingCampaign $campaign)
    {
        $user = Auth::user();

        if ((int)$campaign->created_by !== (int)$user->id && $user->getAccessLevel() !== 'national') {
            abort(403);
        }

        if (!in_array($campaign->status, ['draft', 'rejected'], true)) {
            return redirect()->route('campaigns.mine')
                ->with('error', 'Only draft or rejected campaigns can be deleted.');
        }

        $campaign->delete();

        return redirect()->route('campaigns.mine', ['status' => $campaign->status === 'draft' ? 'draft' : 'rejected'])
            ->with('success', 'Campaign deleted.');
    }
}
