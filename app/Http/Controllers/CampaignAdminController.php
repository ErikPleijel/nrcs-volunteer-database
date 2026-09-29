<?php

namespace App\Http\Controllers;

use App\Campaigns\Recipients\CampaignRecipientBuilder;
use App\Campaigns\Sending\SmsFooter;
use App\Campaigns\Sms\SmsProjection;
use Illuminate\Support\Collection;
use App\Models\Log as AuditLog;
use App\Models\MessagingCampaign;
use App\Models\User;
use App\Models\Branch;
use App\Services\UserFilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\MessagingRecipient;
use App\Services\Campaigns\CampaignPipelineStatsService;
use Illuminate\Support\Facades\DB;
use App\Campaigns\Sending\CampaignSendRunner;
use App\Notifications\CampaignDecided;
use App\Services\CampaignAudienceSummaryService;
use App\Services\CampaignContentValidator;
use App\Support\CampaignPlaceholderRenderer;

class CampaignAdminController extends Controller
{
    public function index(Request $request, CampaignPipelineStatsService $pipelineStats)
    {
        $user = Auth::user();
        abort_unless($user->can('campaign_request_approve'), 403);

        $status = $request->get('status', 'all');

        $allowed = ['all', 'proposed', 'approved', 'rejected', 'sending', 'sent', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            $status = 'proposed';
        }

        $q = trim((string)$request->get('q', ''));

        $searchScope = function ($qq) use ($q) {
            $qq->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%");
                if (ctype_digit($q)) {
                    $w->orWhere('id', (int)$q);
                }
            });
        };

        $origin = trim((string) $request->get('origin', ''));

        $query = MessagingCampaign::query()
            ->with(['creator', 'submitter', 'approver', 'rejector', 'purpose', 'originBranch'])
            ->when($q !== '', $searchScope)
            ->when($origin === 'national', fn ($qq) => $qq->where('origin_level', 'national'))
            ->when($origin !== '' && $origin !== 'national', fn ($qq) => $qq->where('origin_level', 'branch')->where('origin_branch_id', (int) $origin))
            ->latest('submitted_at')
            ->latest('id');

        $campaigns = (clone $query)
            ->when($status === 'approved', fn ($qq) => $qq->whereIn('status', ['approved', 'queued']))
            ->when($status !== 'all' && $status !== 'approved', fn ($qq) => $qq->where('status', $status))
            ->paginate(25)
            ->withQueryString();

        // Collect branch IDs from filter_json
        $branchIds = $campaigns->pluck('filter_json')
            ->filter(fn ($f) => is_array($f))
            ->map(fn ($f) => data_get($f, 'branch_id'))
            ->filter()
            ->unique()
            ->values();

        // Load branches in one query
        $branchesById = Branch::whereIn('id', $branchIds)
            ->pluck('name', 'id');

        $branches = Branch::orderBy('name')->get();

        $statusCounts = MessagingCampaign::query()
            ->when($q !== '', $searchScope)
            ->when($origin === 'national', fn ($qq) => $qq->where('origin_level', 'national'))
            ->when($origin !== '' && $origin !== 'national', fn ($qq) => $qq->where('origin_level', 'branch')->where('origin_branch_id', (int) $origin))
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $totalCount = $statusCounts->sum();

        $pipelineSummary = $pipelineStats->summary();

        return view('campaigns.admin.index', compact('campaigns', 'status', 'q', 'origin', 'branches', 'statusCounts', 'totalCount', 'pipelineSummary', 'branchesById'));
    }




    public function show(MessagingCampaign $campaign, UserFilterService $userFilterService, CampaignAudienceSummaryService $audienceSummaryService)
    {
        $user = Auth::user();
        abort_unless($user->can('campaign_request_approve'), 403);


        $filters = is_array($campaign->filter_json) ? $campaign->filter_json : [];
        $filterDescriptionHtml = $campaign->filter_description_html;

        // Build same base query as wizard step2
        $baseQuery = User::query()
            ->with(['branch', 'division', 'redCrossUnit'])
            ->where('is_super_admin', false);

        // Important: apply campaign creator scope, not admin scope
        // so the audience preview matches what the creator actually targeted.
        $filteredQuery = $userFilterService->apply(
            $baseQuery,
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

        // Same columns/relations as the send runner, so the preview renders placeholders identically.
        $sample = (clone $filteredQuery)
            ->select(CampaignPlaceholderRenderer::USER_COLUMNS)
            ->with(CampaignPlaceholderRenderer::USER_RELATIONS)
            ->limit(20)
            ->get();

        $throttling = $filters['_throttling'] ?? [];

        $stuckQueuedCount = MessagingRecipient::query()
            ->where('messaging_campaign_id', $campaign->id)
            ->stuckQueued()
            ->count();

        $smsProjection = $this->smsProjection($campaign, $sample);

        return view('campaigns.admin.show', compact(
            'campaign',
            'stuckQueuedCount',
            'smsProjection',
            'filters',
            'throttling',
            'matchedTotal',
            'emailContactable',
            'smsContactable',
            'sample',
            'filterDescriptionHtml',
            'willEmail',
            'willSms',
            'willReach',
            'mayReceiveTwo',
            'noReach',
            'reachabilityKnown'
        ));

    }

    /**
     * Estimated SMS volume, or null when the campaign sends no SMS. Pages use the SMS body
     * rendered for sample recipients (the campaign's own audience when none are given).
     */
    private function smsProjection(MessagingCampaign $campaign, ?Collection $sample = null): ?array
    {
        $smsText = trim((string) data_get($campaign->filter_json, '_content.sms_body', ''));

        if ($smsText === '' || ! in_array($campaign->channel, ['sms', 'both', 'email_fallback_sms'], true)) {
            return null;
        }

        $sample ??= app(CampaignRecipientBuilder::class)->audience($campaign)
            ->select(CampaignPlaceholderRenderer::USER_COLUMNS)
            ->with(CampaignPlaceholderRenderer::USER_RELATIONS)
            ->limit(20)
            ->get();

        $footer = SmsFooter::preview();
        $bodies = $sample->map(fn (User $u) => trim(CampaignPlaceholderRenderer::render($smsText, $u)).$footer)->all();

        return app(SmsProjection::class)->estimate($campaign, [$smsText.$footer, ...$bodies]);
    }

    public function approve(Request $request, MessagingCampaign $campaign)
    {
        $user = Auth::user();
        abort_unless($user->can('campaign_request_approve'), 403);

        abort_unless($campaign->status === 'proposed', 422);

        abort_if(
            $campaign->submitted_by === $user->id,
            403,
            'You cannot approve or reject a campaign you submitted yourself.'
        );

        $contentErrors = app(CampaignContentValidator::class)->validate($campaign);
        if (! empty($contentErrors)) {
            return back()->with('error', 'Cannot approve: '.implode(' ', $contentErrors));
        }

        $campaign->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),

            // clear any rejection history/note (optional)
            'rejected_by' => null,
            'rejected_at' => null,
            'review_note' => null,
        ]);

        if ($campaign->submitted_by && ($submitter = \App\Models\User::find($campaign->submitted_by))) {
            $submitter->notify(new CampaignDecided(
                'approved',
                $campaign->id,
                $campaign->title ?? "#{$campaign->id}",
            ));
        }

        return back()->with('success', 'Campaign approved.');
    }


    public function reject(Request $request, MessagingCampaign $campaign)
    {
        $user = Auth::user();
        abort_unless($user->can('campaign_request_approve'), 403);

        abort_unless($campaign->status === 'proposed', 422);

        abort_if(
            $campaign->submitted_by === $user->id,
            403,
            'You cannot approve or reject a campaign you submitted yourself.'
        );

        $data = $request->validate([
            'review_note' => ['required','string','min:5','max:2000'],
        ]);

        $campaign->update([
            'status' => 'rejected',
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'review_note' => $data['review_note'],

            // clear approval fields (optional)
            'approved_by' => null,
            'approved_at' => null,
        ]);

        if ($campaign->submitted_by && ($submitter = \App\Models\User::find($campaign->submitted_by))) {
            $submitter->notify(new CampaignDecided(
                'rejected',
                $campaign->id,
                $campaign->title ?? "#{$campaign->id}",
                $data['review_note'],
            ));
        }

        return back()->with('success', 'Campaign rejected.');
    }


    public function queue(Request $request, MessagingCampaign $campaign)
    {
        $user = Auth::user();
        abort_unless($user->can('campaign_request_approve'), 403);

        abort_unless($campaign->status === 'approved', 422);

        $contentErrors = app(CampaignContentValidator::class)->validate($campaign);
        if (! empty($contentErrors)) {
            return back()->with('error', 'Cannot queue: '.implode(' ', $contentErrors));
        }

        $campaign->update([
            'status' => 'queued',
        ]);

        return back()->with('success', 'Campaign queued.');
    }

    public function buildRecipients(Request $request, MessagingCampaign $campaign, CampaignRecipientBuilder $builder)
    {
        $user = Auth::user();
        abort_unless($user->can('campaign_request_approve'), 403);

        abort_unless(in_array($campaign->status, ['queued', 'approved'], true), 422);

        $data = $request->validate([
            'fresh' => ['nullable','boolean'],
            'only_contactable' => ['nullable','boolean'],
        ]);

        $fresh = !empty($data['fresh']);
        $onlyContactable = !empty($data['only_contactable']);

        DB::beginTransaction();

        try {
            $counts = $builder->build($campaign, fresh: $fresh, onlyContactable: $onlyContactable);

            DB::commit();

            return back()->with('success', CampaignRecipientBuilder::summary($counts));

        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', 'Failed to build recipients: ' . $e->getMessage());
        }
    }

    public function resetFailedRecipients(Request $request, MessagingCampaign $campaign)
    {
        $user = Auth::user();
        abort_unless($user->can('campaign_request_approve'), 403);

        $data = $request->validate([
            'include_bounced' => ['nullable', 'boolean'],
            'only' => ['nullable', 'in:failed,failed+bounced'], // optional safety
        ]);

        $includeBounced = !empty($data['include_bounced']) || (($data['only'] ?? '') === 'failed+bounced');

        $statusesToReset = $includeBounced
            ? MessagingRecipient::FAILED_STATUSES
            : ['failed'];

        $affected = MessagingRecipient::query()
            ->where('messaging_campaign_id', $campaign->id)
            ->whereIn('status', $statusesToReset)
            // Stuck rows an admin marked failed may already have been sent — never retry them.
            ->where(fn ($w) => $w->whereNull('last_error')
                ->orWhere('last_error', '!=', MessagingRecipient::STUCK_MARKED_FAILED_ERROR))
            ->update([
                'status' => 'pending',
                'last_error' => null,
                'sent_at' => null,
                // The previous attempt's provider details no longer describe this row.
                'provider' => null,
                'provider_message_id' => null,
                'channel_used' => null,
                'provider_status' => null,
                'delivered_at' => null,
                'updated_at' => now(),
            ]);

        // If we brought recipients back to pending, campaign should no longer be "sent".
        if ($affected > 0 && in_array($campaign->status, ['sent', 'cancelled'], true)) {
            $campaign->status = 'queued';
            $campaign->send_completed_at = null;
        }

        $campaign->refreshRecipientStats(); // also saves the status change above

        $label = $includeBounced ? 'failed/bounced/undeliverable/expired' : 'failed';

        return back()->with('success', "{$affected} {$label} recipients reset to pending.");
    }

    /**
     * Mark recipients stuck in 'queued' (claimed by a runner that never recorded a result)
     * as failed, so the campaign's numbers add up. Deliberately NOT re-queued: the message
     * may already have gone out, and sending is at-most-once (see Decisions.md).
     */
    public function failStuckQueued(Request $request, MessagingCampaign $campaign)
    {
        $user = Auth::user();
        abort_unless($user->can('campaign_request_approve'), 403);

        $affected = MessagingRecipient::query()
            ->where('messaging_campaign_id', $campaign->id)
            ->stuckQueued()
            ->update([
                'status' => 'failed',
                'last_error' => MessagingRecipient::STUCK_MARKED_FAILED_ERROR,
                'updated_at' => now(),
            ]);

        if ($affected > 0) {
            $campaign->refreshRecipientStats();

            AuditLog::write(
                'campaign_stuck_queued_marked_failed',
                $campaign,
                null,
                null,
                [
                    'count' => $affected,
                    'channel' => $campaign->channel,
                    'threshold_minutes' => MessagingRecipient::STUCK_QUEUED_MINUTES,
                ],
                sprintf('%d recipient(s) stuck in queued marked failed on campaign #%d.', $affected, $campaign->id)
            );
        }

        return redirect()
            ->route('campaigns.admin.show', $campaign)
            ->with('success', "{$affected} stuck recipient(s) marked failed. They will not be retried.");
    }

    public function startSending(Request $request, MessagingCampaign $campaign, CampaignSendRunner $runner)
    {
        $user = Auth::user();
        abort_unless($user->can('campaign_request_approve'), 403);

        abort_unless($campaign->status === 'queued', 422);

        $contentErrors = app(CampaignContentValidator::class)->validate($campaign);
        if (! empty($contentErrors)) {
            return back()->with('error', 'Cannot start sending: '.implode(' ', $contentErrors));
        }

        if ((int)$campaign->stats_total <= 0) {
            return back()->with('error', 'No recipients exist yet. Build recipients first.');
        }

        $data = $request->validate([
            'batch' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $dryRun = (bool) $request->boolean('dry_run', false); // optional toggle later
        $batch = (int) ($data['batch'] ?? 50);
        $forceOutsideWindow = $request->boolean('force_outside_window', false);

        $campaign->update([
            'status' => 'sending',
            'send_started_at' => $campaign->send_started_at ?? now(),
            'send_completed_at' => null,
            'last_send_run_at' => null,
            'daily_sent_date' => now()->toDateString(),
            'daily_sent_count' => 0,
        ]);

        // ✅ Kick one run immediately
        $result = $runner->runOneBatch($campaign->fresh(), batch: $batch, dryRun: $dryRun, force: $forceOutsideWindow);

        return back()->with('success', "Campaign started. Processed {$result['processed']} (sent {$result['sent']}, failed {$result['failed']}).");
    }
    public function stopSending(Request $request, MessagingCampaign $campaign)
    {
        $user = Auth::user();
        abort_unless($user->can('campaign_request_approve'), 403);

        abort_unless(in_array($campaign->status, ['sending', 'queued', 'approved'], true), 422);

        $campaign->update([
            'status' => 'cancelled',
        ]);

        if ($campaign->submitted_by && ($submitter = \App\Models\User::find($campaign->submitted_by))) {
            $submitter->notify(new CampaignDecided(
                'cancelled',
                $campaign->id,
                $campaign->title ?? "#{$campaign->id}",
                null,
            ));
        }

        return back()->with('success', 'Campaign cancelled.');
    }

    public function monitor(Request $request, MessagingCampaign $campaign)
    {
        $user = Auth::user();
        abort_unless($user->can('campaign_request_approve'), 403);

        $tabStatuses = [
            'pending' => ['pending'],
            'queued' => ['queued'],
            'sent' => MessagingRecipient::SENT_STATUSES,
            'failed' => MessagingRecipient::FAILED_STATUSES,
            'skipped' => MessagingRecipient::SKIPPED_STATUSES,
        ];

        $tab = $request->get('tab', 'all');
        if ($tab !== 'all' && ! array_key_exists($tab, $tabStatuses)) {
            $tab = 'all';
        }
        $q = trim((string) $request->get('q', ''));

        $base = MessagingRecipient::query()->where('messaging_campaign_id', $campaign->id);

        $statusCounts = (clone $base)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');
        $countFor = fn (string $tab) => (int) $statusCounts->only($tabStatuses[$tab])->sum();

        $totalCount   = (int) $statusCounts->sum();
        $pendingCount = $countFor('pending');
        $queuedCount  = $countFor('queued');
        $sentCount    = $countFor('sent');
        $failedCount  = $countFor('failed');
        $skippedCount = $countFor('skipped');

        $query = (clone $base);
        if ($tab !== 'all') {
            $query->whereIn('status', $tabStatuses[$tab]);
        }

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('email', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.full_name')) LIKE ?", ["%{$q}%"]);
                if (ctype_digit($q)) {
                    $w->orWhere('recipient_id', (int) $q);
                }
                if (str_contains($q, ' ')) {
                    $parts = array_values(array_filter(preg_split('/\s+/', $q)));
                    if (count($parts) >= 2) {
                        foreach ($parts as $p) {
                            $w->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload_json, '$.full_name')) LIKE ?", ["%{$p}%"]);
                        }
                    }
                }
            });
        }

        $recipients = $query->orderByDesc('id')->paginate(50)->withQueryString();

        $filters = is_array($campaign->filter_json) ? $campaign->filter_json : [];
        $throttling = $filters['_throttling'] ?? [];

        $stuckQueuedCount = (clone $base)->stuckQueued()->count();

        return view('campaigns.admin.monitor', compact(
            'campaign',
            'stuckQueuedCount',
            'throttling',
            'totalCount',
            'pendingCount',
            'queuedCount',
            'sentCount',
            'failedCount',
            'skippedCount',
            'recipients',
            'tab',
            'q'
        ));
    }

    public function runOnce(Request $request, MessagingCampaign $campaign, CampaignSendRunner $runner)
    {
        $user = Auth::user();
        abort_unless($user->can('campaign_request_approve'), 403);

        abort_unless(in_array($campaign->status, ['sending'], true), 422);

        $data = $request->validate([
            'batch' => ['nullable', 'integer', 'min:1', 'max:500'],
            'dry_run' => ['nullable', 'boolean'],
            'force' => ['nullable', 'boolean'],
        ]);

        $batch = (int)($data['batch'] ?? 50);
        $dryRun = !empty($data['dry_run']);
        $force = !empty($data['force']);

        $result = $runner->runOneBatch($campaign->fresh(), batch: $batch, dryRun: $dryRun, force: $force);

        return back()->with('success', "Run once: processed {$result['processed']} (sent {$result['sent']}, failed {$result['failed']}).");
    }






}
