<?php

namespace App\Services\Campaigns;

use App\Models\MessagingRecipient;
use App\Models\Setting;
use Illuminate\Support\Carbon;

class CampaignDailyRateStatsService
{
    /**
     * Summarize per-channel sends for the current day using MessagingRecipient records.
     */
    public function summary(): array
    {
        $start = Carbon::today();
        $end = Carbon::tomorrow();

        $stats = MessagingRecipient::query()
            ->from('messaging_recipients as mr')
            ->join('messaging_campaigns as mc', 'mr.messaging_campaign_id', '=', 'mc.id')
            ->where('mr.status', 'sent')
            ->whereBetween('mr.sent_at', [$start, $end])
            ->selectRaw(
                "SUM(CASE\n"
                . "        WHEN (mc.channel IN ('email','both') AND mr.email IS NOT NULL AND mr.email <> '')\n"
                . "          OR (mc.channel = 'email_fallback_sms' AND mr.email IS NOT NULL AND mr.email <> '')\n"
                . "        THEN 1 ELSE 0 END) as email_sent_today,\n"
                . "SUM(CASE\n"
                . "        WHEN (mc.channel IN ('sms','both') AND mr.phone IS NOT NULL AND mr.phone <> '')\n"
                . "          OR (mc.channel = 'email_fallback_sms' AND (mr.email IS NULL OR mr.email = '') AND mr.phone IS NOT NULL AND mr.phone <> '')\n"
                . "        THEN 1 ELSE 0 END) as sms_sent_today"
            )
            ->first();

        return [
            'email_sent_today' => (int) ($stats?->email_sent_today ?? 0),
            'sms_sent_today' => (int) ($stats?->sms_sent_today ?? 0),
            'email_cap' => Setting::getInt('campaigns_daily_email_cap', 0),
            'sms_cap' => Setting::getInt('campaigns_daily_sms_cap', 0),
        ];
    }
}
