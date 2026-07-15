<?php

namespace App\Services\Campaigns;

use App\Models\MessagingRecipient;
use Illuminate\Support\Carbon;

class CampaignPipelineStatsService
{
    /**
     * Provide a compact view of current pipeline volume.
     */
    public function summary(): array
    {
        $recipientTable = (new MessagingRecipient())->getTable();
        $todayStart = Carbon::now()->startOfDay();
        $failedSince = Carbon::now()->subDays(7);

        $stats = MessagingRecipient::query()
            ->from("{$recipientTable} as mr")
            ->join('messaging_campaigns as mc', 'mr.messaging_campaign_id', '=', 'mc.id')
            ->selectRaw(
                "SUM(CASE WHEN mr.status = 'pending' AND mc.status = 'queued' THEN 1 ELSE 0 END) as queued_total,
                 SUM(CASE WHEN mr.status = 'pending' AND mc.status = 'sending' THEN 1 ELSE 0 END) as sending_total,
                 SUM(CASE WHEN mr.status in ('failed','bounced','undeliverable') AND mr.updated_at >= ? THEN 1 ELSE 0 END) as failed_total,
                 SUM(CASE WHEN mr.status = 'sent' AND mr.sent_at >= ? THEN 1 ELSE 0 END) as sent_today_total",
                [$failedSince, $todayStart]
            )
            ->first();

        return [
            'queued_total' => (int) ($stats?->queued_total ?? 0),
            'sending_total' => (int) ($stats?->sending_total ?? 0),
            'failed_total' => (int) ($stats?->failed_total ?? 0),
            'sent_today_total' => (int) ($stats?->sent_today_total ?? 0),
        ];
    }
}
