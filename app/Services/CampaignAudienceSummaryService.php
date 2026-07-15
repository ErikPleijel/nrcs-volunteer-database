<?php

namespace App\Services;

use App\Models\MessagingCampaign;
use App\Models\MessagingRecipient;
use Illuminate\Database\Eloquent\Builder;

class CampaignAudienceSummaryService
{
    /**
     * Compute the matched/contactable/reach figures for a campaign's filtered
     * audience query. Single source of truth for the numbers shown across the
     * wizard (step 2 & 5), admin review, and "my campaigns" pages.
     */
    public function summarize(Builder $filteredQuery, string $channel): array
    {
        $matchedTotal = (clone $filteredQuery)->count();

        $emailContactable = (clone $filteredQuery)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->count();

        $smsContactable = (clone $filteredQuery)
            ->where(function ($q) {
                $q->whereNotNull('telephone1')->where('telephone1', '!=', '')
                    ->orWhereNotNull('telephone2')->where('telephone2', '!=', '');
            })
            ->count();

        // Exact overlap (has BOTH email and phone) — useful for "both" and "fallback"
        $bothContactable = (clone $filteredQuery)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where(function ($q) {
                $q->whereNotNull('telephone1')->where('telephone1', '!=', '')
                    ->orWhereNotNull('telephone2')->where('telephone2', '!=', '');
            })
            ->count();

        // Exact fallback SMS: has phone but NO email
        $fallbackSmsOnly = (clone $filteredQuery)
            ->where(function ($q) {
                $q->whereNotNull('telephone1')->where('telephone1', '!=', '')
                    ->orWhereNotNull('telephone2')->where('telephone2', '!=', '');
            })
            ->where(function ($q) {
                $q->whereNull('email')->orWhere('email', '=', '');
            })
            ->count();

        $willEmail = 0;
        $willSms = 0;
        $willReach = 0;     // recipients who receive at least one message
        $mayReceiveTwo = 0; // recipients likely to receive two messages

        switch ($channel) {
            case 'sms':
                $willSms = $smsContactable;
                $willReach = $willSms;
                break;

            case 'email_fallback_sms':
                $willEmail = $emailContactable;
                $willSms = $fallbackSmsOnly;             // exact: phone + no email
                $willReach = min($matchedTotal, $willEmail + $willSms);
                break;

            case 'both':
                $willEmail = $emailContactable;
                $willSms = $smsContactable;
                $willReach = max($willEmail, $willSms);  // at least one channel
                $mayReceiveTwo = $bothContactable;       // exact overlap
                break;

            case 'email': // legacy
            default:
                $willEmail = $emailContactable;
                $willReach = $willEmail;
                break;
        }

        $noReach = max(0, $matchedTotal - $willReach);

        return [
            'matchedTotal' => $matchedTotal,
            'emailContactable' => $emailContactable,
            'smsContactable' => $smsContactable,
            'bothContactable' => $bothContactable,
            'fallbackSmsOnly' => $fallbackSmsOnly,
            'willEmail' => $willEmail,
            'willSms' => $willSms,
            'willReach' => $willReach,
            'mayReceiveTwo' => $mayReceiveTwo,
            'noReach' => $noReach,
            'reachability_known' => true,
        ];
    }

    /**
     * Post-send equivalent of summarize(): sourced from the frozen
     * messaging_recipients rows for this campaign instead of re-applying the
     * live filter, which drifts once recipients have actually been messaged
     * (e.g. a "not contacted in N days" campaign_msg filter excludes people
     * this campaign itself just contacted).
     *
     * "Not reachable" cannot be honestly reconstructed from this table:
     * recipients are normally built with only_contactable=1, so people with
     * neither email nor phone never get a row in the first place, and the
     * original matched total isn't persisted anywhere else. So matchedTotal
     * and noReach are intentionally omitted rather than faked.
     */
    public function summarizeFromRecipients(MessagingCampaign $campaign): array
    {
        $base = MessagingRecipient::query()
            ->where('messaging_campaign_id', $campaign->id)
            ->where('status', 'sent');

        $willEmail = (clone $base)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->count();

        $willSms = (clone $base)
            ->where(function ($q) {
                $q->whereNull('email')->orWhere('email', '=', '');
            })
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->count();

        return [
            'matchedTotal' => null,
            'emailContactable' => null,
            'smsContactable' => null,
            'bothContactable' => null,
            'fallbackSmsOnly' => null,
            'willEmail' => $willEmail,
            'willSms' => $willSms,
            'willReach' => $willEmail + $willSms,
            'mayReceiveTwo' => null,
            'noReach' => null,
            'reachability_known' => false,
        ];
    }
}
