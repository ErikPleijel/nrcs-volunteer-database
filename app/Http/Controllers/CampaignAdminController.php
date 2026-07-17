<?php

namespace App\Http\Controllers;

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

        $sample = (clone $filteredQuery)
            ->select(['id','first_name','last_name','email','telephone1','telephone2','lifecycle_status','branch_id','division_id','red_cross_unit_id'])
            ->limit(20)
            ->get();

        $throttling = $filters['_throttling'] ?? [];

        return view('campaigns.admin.show', compact(
            'campaign',
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

    public function buildRecipients(Request $request, MessagingCampaign $campaign, UserFilterService $userFilterService)
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

        $filters = is_array($campaign->filter_json) ? $campaign->filter_json : [];

        $baseQuery = User::query()
            ->where('is_super_admin', false);

        $filteredQuery = $userFilterService->apply(
            $baseQuery,
            $filters,
            $campaign->scope_level,
            $campaign->scope_id
        );

        DB::beginTransaction();

        try {
            if ($fresh) {
                MessagingRecipient::query()
                    ->where('messaging_campaign_id', $campaign->id)
                    ->delete();
            }

            $created = 0;
            $updated = 0;
            $skipped = 0;
            $optOutSkipped = 0;

            (clone $filteredQuery)
                ->select(['id', 'email', 'telephone1', 'telephone2', 'first_name', 'last_name', 'email_opt_out', 'sms_opt_out'])
                ->orderBy('id')
                ->chunkById(500, function ($users) use ($campaign, $onlyContactable, &$created, &$updated, &$skipped, &$optOutSkipped) {

                    foreach ($users as $u) {
                        $email = trim((string)($u->email ?? '')) ?: null;
                        $phone = $this->pickPhone($u->telephone1 ?? null, $u->telephone2 ?? null);

                        $channel          = $campaign->channel;
                        $channelUsesEmail = in_array($channel, ['email', 'both', 'email_fallback_sms'], true);
                        $channelUsesSms   = in_array($channel, ['sms', 'both', 'email_fallback_sms'], true);

                        $emailOptOut = (bool) ($u->email_opt_out ?? false);
                        $smsOptOut   = (bool) ($u->sms_opt_out ?? false);

                        // Mask contact info for opted-out channels so downstream logic is consistent.
                        $effectiveEmail = ($channelUsesEmail && $emailOptOut) ? null : $email;
                        $effectivePhone = ($channelUsesSms   && $smsOptOut)   ? null : $phone;

                        // Skip entirely when every applicable channel is opted out.
                        $skipForOptOut =
                            ($channel === 'email' && $emailOptOut) ||
                            ($channel === 'sms'   && $smsOptOut)   ||
                            (in_array($channel, ['both', 'email_fallback_sms'], true) && $emailOptOut && $smsOptOut);

                        if ($skipForOptOut) {
                            $optOutSkipped++;
                            continue;
                        }

                        // Contactability check (uses effective values so opt-out masking feeds in).
                        if ($onlyContactable) {
                            if ($channel === 'email' && !$effectiveEmail) { $skipped++; continue; }
                            if ($channel === 'sms'   && !$effectivePhone) { $skipped++; continue; }

                            if (in_array($channel, ['both', 'email_fallback_sms'], true) && !$effectiveEmail && !$effectivePhone) {
                                $skipped++;
                                continue;
                            }
                        }

                        $payload = [
                            'first_name' => $u->first_name,
                            'last_name' => $u->last_name,
                            'full_name' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')),
                        ];

                        // Look for existing row first, so we don't reset status/sent_at on rebuild.
                        $existing = MessagingRecipient::query()
                            ->where('messaging_campaign_id', $campaign->id)
                            ->where('recipient_type', User::class)
                            ->where('recipient_id', $u->id)
                            ->first();

                        if ($existing) {
                            // Update contact info + payload only (opt-out masking applied).
                            $existing->update([
                                'email' => $effectiveEmail,
                                'phone' => $effectivePhone,
                                'payload_json' => $payload,
                            ]);
                            $updated++;
                            continue;
                        }

                        MessagingRecipient::create([
                            'messaging_campaign_id' => $campaign->id,
                            'recipient_type' => User::class,
                            'recipient_id' => $u->id,
                            'email' => $effectiveEmail,
                            'phone' => $effectivePhone,
                            'payload_json' => $payload,
                            'status' => 'pending',
                            'last_error' => null,
                            'sent_at' => null,
                        ]);

                        $created++;
                    }
                });

            // Org representative emails (only when filter flag is set)
            $orgEmailsAdded = 0;

            if (data_get($filters, 'org_representatives')) {
                $orgQuery = \App\Models\Organisation::query()
                    ->whereNotNull('email')
                    ->where('email', '!=', '')
                    ->whereHas('users');

                if ($campaign->scope_level === 'branch' && $campaign->scope_id) {
                    $orgQuery->where('branch_id', $campaign->scope_id);
                }

                $orgQuery->select(['id', 'name', 'email'])
                    ->orderBy('id')
                    ->each(function ($org) use ($campaign, &$orgEmailsAdded) {
                        $exists = MessagingRecipient::query()
                            ->where('messaging_campaign_id', $campaign->id)
                            ->where('recipient_type', \App\Models\Organisation::class)
                            ->where('recipient_id', $org->id)
                            ->exists();

                        if ($exists) {
                            return;
                        }

                        MessagingRecipient::create([
                            'messaging_campaign_id' => $campaign->id,
                            'recipient_type'        => \App\Models\Organisation::class,
                            'recipient_id'          => $org->id,
                            'email'                 => trim((string) $org->email),
                            'phone'                 => null,
                            'payload_json'          => [
                                'full_name'  => $org->name,
                                'first_name' => $org->name,
                                'last_name'  => '',
                            ],
                            'status'                => 'pending',
                            'last_error'            => null,
                            'sent_at'               => null,
                        ]);

                        $orgEmailsAdded++;
                    });
            }

            // Update stats
            $statsTotal = MessagingRecipient::query()
                ->where('messaging_campaign_id', $campaign->id)
                ->count();

            $statsSent = MessagingRecipient::query()
                ->where('messaging_campaign_id', $campaign->id)
                ->where('status', 'sent')
                ->count();

            $statsFailed = MessagingRecipient::query()
                ->where('messaging_campaign_id', $campaign->id)
                ->whereIn('status', ['failed', 'bounced', 'undeliverable'])
                ->count();

            $campaign->update([
                'stats_total' => $statsTotal,
                'stats_sent' => $statsSent,
                'stats_failed' => $statsFailed,
            ]);

            DB::commit();

            return back()->with(
                'success',
                "Recipients built. Total: {$statsTotal}. Created: {$created}. Updated: {$updated}. Skipped: {$skipped}. Skipped (opted out): {$optOutSkipped}. Org emails added: {$orgEmailsAdded}."
            );

        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return back()->with('error', 'Failed to build recipients: ' . $e->getMessage());
        }
    }

    private function pickPhone(?string $t1, ?string $t2): ?string
    {
        $t1 = trim((string)$t1);
        $t2 = trim((string)$t2);
        if ($t1 !== '') return $t1;
        if ($t2 !== '') return $t2;
        return null;
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
            ? ['failed', 'bounced', 'undeliverable']
            : ['failed'];

        $affected = MessagingRecipient::query()
            ->where('messaging_campaign_id', $campaign->id)
            ->whereIn('status', $statusesToReset)
            ->update([
                'status' => 'pending',
                'last_error' => null,
                'sent_at' => null,
                'updated_at' => now(),
            ]);

        // If we brought recipients back to pending, campaign should no longer be "sent".
        if ($affected > 0 && in_array($campaign->status, ['sent', 'cancelled'], true)) {
            $campaign->status = 'queued';
            $campaign->send_completed_at = null; // only if you have this column
        }

        // Refresh campaign stats
        $statsTotal = MessagingRecipient::query()
            ->where('messaging_campaign_id', $campaign->id)
            ->count();

        $statsSent = MessagingRecipient::query()
            ->where('messaging_campaign_id', $campaign->id)
            ->where('status', 'sent')
            ->count();

        $statsFailed = MessagingRecipient::query()
            ->where('messaging_campaign_id', $campaign->id)
            ->whereIn('status', ['failed', 'bounced', 'undeliverable'])
            ->count();

        $update = [
            'stats_total'  => $statsTotal,
            'stats_sent'   => $statsSent,
            'stats_failed' => $statsFailed,
        ];

        if ($affected > 0 && in_array($campaign->status, ['sent', 'cancelled'], true)) {
            $update['status'] = 'queued';
            // If you track completion timestamps:
            // $update['send_completed_at'] = null;
        }

        $campaign->update($update);

        $label = $includeBounced ? 'failed/bounced/undeliverable' : 'failed';

        return back()->with('success', "{$affected} {$label} recipients reset to pending.");
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

        $tab = $request->get('tab', 'all');
        if (!in_array($tab, ['all', 'pending', 'sent', 'failed'], true)) {
            $tab = 'all';
        }
        $q = trim((string) $request->get('q', ''));

        $base = MessagingRecipient::query()->where('messaging_campaign_id', $campaign->id);

        $totalCount   = (clone $base)->count();
        $pendingCount = (clone $base)->where('status', 'pending')->count();
        $sentCount    = (clone $base)->where('status', 'sent')->count();
        $failedCount  = (clone $base)->whereIn('status', ['failed', 'bounced', 'undeliverable'])->count();

        $query = (clone $base);
        if ($tab === 'pending') {
            $query->where('status', 'pending');
        } elseif ($tab === 'sent') {
            $query->where('status', 'sent');
        } elseif ($tab === 'failed') {
            $query->whereIn('status', ['failed', 'bounced', 'undeliverable']);
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

        return view('campaigns.admin.monitor', compact(
            'campaign',
            'throttling',
            'totalCount',
            'pendingCount',
            'sentCount',
            'failedCount',
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
