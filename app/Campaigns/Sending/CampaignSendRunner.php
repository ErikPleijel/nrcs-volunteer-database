<?php

namespace App\Campaigns\Sending;

use App\Campaigns\Delivery\CampaignDeliveryService;
use App\Campaigns\Delivery\DeliveryMessage;
use App\Models\MessagingCampaign;
use App\Models\MessagingRecipient;
use App\Models\User;
use App\Support\CampaignPlaceholderRenderer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CampaignSendRunner
{
    public function __construct(
        private readonly CampaignDeliveryService $delivery,
    ) {}

    /**
     * Run one "send batch" for a single campaign.
     * Returns: ['sent' => int, 'failed' => int, 'processed' => int]
     */
    public function runOneBatch(MessagingCampaign $campaign, int $batch = 50, bool $dryRun = false, bool $force = false): array
    {
        if ($campaign->status !== 'sending') {
            $this->logStop($campaign, 'not_sending', [
                'dry_run' => $dryRun,
                'force' => $force,
                'batch' => $batch,
            ]);

            return ['sent' => 0, 'failed' => 0, 'processed' => 0];
        }

        $throttling = is_array($campaign->filter_json) ? ($campaign->filter_json['_throttling'] ?? []) : [];

        // Reset daily counter if date changed
        $today = now()->toDateString();
        if ($campaign->daily_sent_date !== $today) {
            $campaign->daily_sent_date = $today;
            $campaign->daily_sent_count = 0;
            $campaign->save();
        }

        // Respect send window
        if (! $force && ! $this->isWithinSendWindow($throttling)) {
            $this->logStop($campaign, 'outside_send_window', [
                'dry_run' => $dryRun,
                'force' => $force,
                'batch' => $batch,
                'send_window_start' => $throttling['send_window_start'] ?? null,
                'send_window_end' => $throttling['send_window_end'] ?? null,
            ]);

            return ['sent' => 0, 'failed' => 0, 'processed' => 0];
        }

        // Respect daily cap
        $remainingToday = $this->remainingToday($campaign, $throttling);
        if ($remainingToday <= 0) {
            $this->logStop($campaign, 'daily_cap_reached', [
                'dry_run' => $dryRun,
                'force' => $force,
                'batch' => $batch,
                'daily_cap' => $throttling['daily_cap'] ?? null,
                'daily_sent_date' => $campaign->daily_sent_date,
                'daily_sent_count' => (int) $campaign->daily_sent_count,
                'remaining_today' => $remainingToday,
            ]);

            return ['sent' => 0, 'failed' => 0, 'processed' => 0];
        }

        $take = min($batch, $remainingToday);

        $recipients = MessagingRecipient::query()
            ->where('messaging_campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($take)
            ->get();

        // Extract canonical email/SMS bodies stored by the wizard (filter_json._content).
        // Falls back to campaign.body so pre-wizard or migrated campaigns still work.
        $filterContent = is_array($campaign->filter_json) ? ($campaign->filter_json['_content'] ?? []) : [];
        $baseEmailBody = (string) ($filterContent['email_body'] ?? $campaign->body ?? '');
        $baseSmsBody = (string) ($filterContent['sms_body'] ?? $campaign->body ?? '');

        // One bounded fetch per batch: token + opt-out flags AND the columns/relations the
        // placeholder tokens read, so per-recipient rendering issues zero extra queries.
        $userRecipientIds = $recipients->where('recipient_type', User::class)->pluck('recipient_id');
        $userRows = User::whereIn('id', $userRecipientIds)
            ->select([
                'id', 'id_check_token', 'email_opt_out', 'sms_opt_out',
                // columns read by column-backed placeholder tokens
                'first_name', 'middle_name', 'last_name', 'email',
                'telephone1', 'telephone2', 'lifecycle_status', 'last_first_aid_at',
                // foreign keys for relation-backed placeholder tokens
                'branch_id', 'division_id', 'red_cross_unit_id',
            ])
            ->with([
                'branch:id,name,code',
                'division:id,name',
                'redCrossUnit:id,name',
                'currentMembershipPayment.membershipFee',
            ])
            ->get()
            ->keyBy('id');

        if ($recipients->isEmpty()) {
            // complete if no pending left
            $pendingLeft = MessagingRecipient::query()
                ->where('messaging_campaign_id', $campaign->id)
                ->where('status', 'pending')
                ->count();

            $campaign->last_send_run_at = now();
            if ($pendingLeft === 0) {
                $campaign->status = 'sent';
                $campaign->send_completed_at = now();
            }
            $campaign->save();

            $this->refreshCampaignStats($campaign);

            $this->logStop($campaign, 'no_pending_recipients', [
                'dry_run' => $dryRun,
                'force' => $force,
                'batch' => $batch,
                'pending_left' => $pendingLeft,
                'final_status' => $campaign->status,
            ]);

            return ['sent' => 0, 'failed' => 0, 'processed' => 0];
        }

        $sentThisRun = 0;
        $failedThisRun = 0;

        DB::beginTransaction();
        try {
            foreach ($recipients as $r) {
                $email = $r->email ? trim((string) $r->email) : null;
                $phone = $r->phone ? trim((string) $r->phone) : null;

                if ($campaign->channel === 'email' && ! $email) {
                    $r->update(['status' => 'undeliverable', 'last_error' => 'Missing email']);
                    $failedThisRun++;

                    continue;
                }

                if ($campaign->channel === 'sms' && ! $phone) {
                    $r->update(['status' => 'undeliverable', 'last_error' => 'Missing phone']);
                    $failedThisRun++;

                    continue;
                }

                if (in_array($campaign->channel, ['both', 'email_fallback_sms'], true) && ! $email && ! $phone) {
                    $r->update(['status' => 'undeliverable', 'last_error' => 'Missing email and phone']);
                    $failedThisRun++;

                    continue;
                }

                $subject = $campaign->subject ?: ($campaign->title ?: 'Message from Red Cross');
                $body = (string) ($campaign->body ?? '');

                $wantsEmail = in_array($campaign->channel, ['email', 'both', 'email_fallback_sms'], true);
                $wantsSms = in_array($campaign->channel, ['sms', 'both', 'email_fallback_sms'], true);

                // Safety net: re-check current opt-out status for User recipients.
                // Guards against users who opted out after recipient rows were built.
                if ($r->recipient_type === User::class) {
                    $userRow = $userRows->get($r->recipient_id);
                    $emailOptOut = (bool) ($userRow?->email_opt_out ?? false);
                    $smsOptOut = (bool) ($userRow?->sms_opt_out ?? false);

                    $skipForOptOut =
                        ($campaign->channel === 'email' && $emailOptOut) ||
                        ($campaign->channel === 'sms' && $smsOptOut) ||
                        (in_array($campaign->channel, ['both', 'email_fallback_sms'], true) && $emailOptOut && $smsOptOut);

                    if ($skipForOptOut) {
                        $errorMsg = match (true) {
                            $emailOptOut && $smsOptOut => 'Opted out of all channels',
                            $emailOptOut => 'Opted out of email',
                            default => 'Opted out of SMS',
                        };
                        $r->update(['status' => 'undeliverable', 'last_error' => $errorMsg]);
                        $failedThisRun++;

                        continue;
                    }
                }

                // Personalise placeholders per recipient. Render into LOCAL copies only — the
                // shared $campaign model is never mutated ($subject/$body are re-initialised from
                // $campaign at the top of each iteration). Only User recipients have a model to
                // resolve against; non-User recipients keep raw bodies (unchanged behaviour).
                $user = $r->recipient_type === User::class ? $userRows->get($r->recipient_id) : null;

                if ($user) {
                    $subject = CampaignPlaceholderRenderer::render($subject, $user);
                    $body = CampaignPlaceholderRenderer::render($body, $user);
                }

                // Build per-recipient finalised bodies with unsubscribe content.
                $emailBodyFinal = $user ? CampaignPlaceholderRenderer::render($baseEmailBody, $user) : $baseEmailBody;
                $smsBodyFinal = $user ? CampaignPlaceholderRenderer::render($baseSmsBody, $user) : $baseSmsBody;

                if ($r->recipient_type === User::class) {
                    $token = $user?->id_check_token;
                    if ($token) {
                        $profileUrl = route('welcome');
                        if ($wantsEmail) {
                            $emailUrl = route('unsubscribe.email.show', $token);
                            $emailBodyFinal .=
                                '<hr style="margin:30px 0;border:none;border-top:1px solid #e0e0e0;">'
                                .'<p style="font-size:12px;color:#888888;text-align:center;margin:0 0 8px 0;line-height:1.6;">'
                                .'Stay up to date with the Nigerian Red Cross — '
                                .'<a href="'.e($profileUrl).'" style="color:#888888;">visit our website</a>'
                                .' and log in to your account to see what\'s on file: your membership status, training history, volunteering record, and any donations you\'ve made.'
                                .'</p>'
                                .'<p style="font-size:12px;color:#888888;text-align:center;margin:0;line-height:1.6;">'
                                .'You are receiving this message because you are a registered member or volunteer of the Nigerian Red Cross Society.'
                                .'<br><a href="'.e($emailUrl).'" style="color:#888888;">Unsubscribe</a>.'
                                .'</p>';
                        }
                        if ($wantsSms) {
                            $smsBodyFinal .= "\nTo stop: ".url('/u/'.$token.'/sms');
                        }
                    }
                }

                $message = new DeliveryMessage(
                    subject: $wantsEmail ? $subject : null,
                    body: $body,
                    emailBody: $wantsEmail ? $emailBodyFinal : null,
                    smsBody: $wantsSms ? $smsBodyFinal : null,
                    meta: [
                        'dry_run' => $dryRun,
                        'campaign_id' => $campaign->id,
                        'campaign_title' => $campaign->title,
                        'campaign_channel' => $campaign->channel,
                        'from_email' => $campaign->from_email ?? null,
                        'from_name' => $campaign->from_name ?? null,
                    ],
                );

                try {
                    $outcome = $this->delivery->deliver($campaign->channel, $r, $message);

                    if ($dryRun) {
                        $r->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
                        $sentThisRun++;

                        continue;
                    }

                    $ok = in_array($campaign->channel, ['both', 'email_fallback_sms'], true)
                        ? $outcome->okAtLeastOne()
                        : $outcome->okAll();

                    if ($ok) {
                        $r->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
                        $sentThisRun++;
                    } else {
                        $fail = $outcome->firstFailure();
                        $r->update(['status' => 'failed', 'last_error' => $fail?->errorMessage ?? 'Delivery failed']);
                        $failedThisRun++;
                    }
                } catch (\Throwable $e) {
                    $r->update(['status' => 'failed', 'last_error' => $e->getMessage()]);
                    $failedThisRun++;

                    // Keep errors in the same campaign_deliveries log for ops debugging
                    Log::channel('campaign_deliveries')->error('Recipient send failed', [
                        'campaign_id' => $campaign->id,
                        'recipient_id' => $r->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $campaign->daily_sent_count = (int) $campaign->daily_sent_count + $sentThisRun;
            $campaign->last_send_run_at = now();
            $campaign->save();

            $this->refreshCampaignStats($campaign);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            Log::channel('campaign_deliveries')->error('CampaignSendRunner failed', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        // If no pending left after this run, complete campaign
        $pendingLeft = MessagingRecipient::query()
            ->where('messaging_campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->count();

        if ($pendingLeft === 0) {
            $campaign->update([
                'status' => 'sent',
                'send_completed_at' => now(),
            ]);
        }

        return [
            'sent' => $sentThisRun,
            'failed' => $failedThisRun,
            'processed' => $sentThisRun + $failedThisRun,
        ];
    }

    private function logStop(MessagingCampaign $campaign, string $reason, array $context = []): void
    {
        Log::channel('campaign_deliveries')->info('campaigns:send stop', array_merge([
            'campaign_id' => $campaign->id,
            'campaign_channel' => $campaign->channel,
            'status' => $campaign->status,
            'reason' => $reason,
        ], $context));
    }

    private function refreshCampaignStats(MessagingCampaign $campaign): void
    {
        $total = MessagingRecipient::query()->where('messaging_campaign_id', $campaign->id)->count();
        $sent = MessagingRecipient::query()->where('messaging_campaign_id', $campaign->id)->where('status', 'sent')->count();
        $failed = MessagingRecipient::query()
            ->where('messaging_campaign_id', $campaign->id)
            ->whereIn('status', ['failed', 'bounced', 'undeliverable'])
            ->count();

        $campaign->update([
            'stats_total' => $total,
            'stats_sent' => $sent,
            'stats_failed' => $failed,
        ]);
    }

    private function remainingToday(MessagingCampaign $campaign, array $throttling): int
    {
        $cap = isset($throttling['daily_cap']) && $throttling['daily_cap']
            ? (int) $throttling['daily_cap']
            : null;

        if (! $cap || $cap <= 0) {
            return PHP_INT_MAX;
        }

        $already = (int) $campaign->daily_sent_count;

        return max(0, $cap - $already);
    }

    private function isWithinSendWindow(array $throttling): bool
    {
        $start = $throttling['send_window_start'] ?? null;
        $end = $throttling['send_window_end'] ?? null;

        if (! $start || ! $end) {
            return true;
        }

        $now = now();
        $startTime = $now->copy()->setTimeFromTimeString($start);
        $endTime = $now->copy()->setTimeFromTimeString($end);

        if ($startTime->equalTo($endTime)) {
            return true;
        }

        if ($startTime->lessThan($endTime)) {
            return $now->between($startTime, $endTime);
        }

        return $now->greaterThanOrEqualTo($startTime) || $now->lessThanOrEqualTo($endTime);
    }
}
