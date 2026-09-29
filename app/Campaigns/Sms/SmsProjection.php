<?php

namespace App\Campaigns\Sms;

use App\Campaigns\Recipients\CampaignRecipientBuilder;
use App\Campaigns\Recipients\SmsNumberPlanner;
use App\Models\MessagingCampaign;
use App\Models\MessagingRecipient;
use App\Support\SmsSegments;
use Illuminate\Support\Facades\Cache;

/**
 * Estimated SMS volume for a campaign: SMS recipients × pages per message = total SMS
 * pages (and cost, when config('sms.price_per_segment') is set). Shown at wizard Step 5
 * and on the admin approval screen.
 *
 * Recipients: once recipient rows exist, the rows that will be texted; before that, the
 * number of distinct numbers SmsNumberPlanner would text (after opt-outs, invalid numbers
 * and shared-number deduplication — the same rules the builder applies). For
 * email_fallback_sms this counts people without usable email; people whose email later
 * fails and falls back to SMS are not included, so it is a lower bound.
 *
 * Pages: the most pages any of the rendered sample messages (body + opt-out footer)
 * needs — placeholders make messages differ in length per person.
 */
final class SmsProjection
{
    private const CACHE_MINUTES = 10;

    public function __construct(private readonly CampaignRecipientBuilder $builder) {}

    /**
     * @param  iterable<string>  $renderedSmsBodies  sample messages, footer included
     * @return array{recipients:int, recipients_source:string, segments:int, encoding:string,
     *               non_gsm:array<int,string>, pages:int, price_per_segment:?float, cost:?float, currency:string}
     */
    public function estimate(MessagingCampaign $campaign, iterable $renderedSmsBodies): array
    {
        $segments = 0;
        $encoding = 'GSM-7';
        $nonGsm = [];

        foreach ($renderedSmsBodies as $body) {
            $analysis = SmsSegments::analyse((string) $body);
            $segments = max($segments, $analysis['segments']);
            if ($analysis['encoding'] === 'UCS-2') {
                $encoding = 'UCS-2';
                $nonGsm = array_values(array_unique([...$nonGsm, ...$analysis['non_gsm']]));
            }
        }

        [$recipients, $source] = $this->recipients($campaign);

        $pages = $recipients * $segments;
        $price = config('sms.price_per_segment');

        return [
            'recipients' => $recipients,
            'recipients_source' => $source,
            'segments' => $segments,
            'encoding' => $encoding,
            'non_gsm' => $nonGsm,
            'pages' => $pages,
            'price_per_segment' => $price,
            'cost' => $price !== null ? round($pages * $price, 2) : null,
            'currency' => (string) config('sms.currency', 'NGN'),
        ];
    }

    /**
     * @return array{0:int, 1:string} count and 'recipients' | 'audience'
     */
    private function recipients(MessagingCampaign $campaign): array
    {
        $rows = MessagingRecipient::query()->where('messaging_campaign_id', $campaign->id);

        if ((clone $rows)->exists()) {
            $texted = (clone $rows)
                ->whereIn('status', ['pending', 'queued', ...MessagingRecipient::SENT_STATUSES])
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->when($campaign->channel === 'email_fallback_sms',
                    fn ($q) => $q->where(fn ($e) => $e->whereNull('email')->orWhere('email', '')));

            return [$campaign->channel === 'email' ? 0 : $texted->count(), 'recipients'];
        }

        if (! in_array($campaign->channel, ['sms', 'both', 'email_fallback_sms'], true)) {
            return [0, 'audience'];
        }

        // Keyed on everything that changes who is in the audience.
        $key = 'sms-projection:'.$campaign->id.':'.md5(json_encode([
            $campaign->channel, $campaign->scope_level, $campaign->scope_id,
            collect($campaign->filter_json ?? [])->except(['_content', '_throttling'])->all(),
        ]));

        $count = Cache::remember($key, now()->addMinutes(self::CACHE_MINUTES), fn () => count(
            SmsNumberPlanner::winners($this->builder->audience($campaign), $campaign->channel)
        ));

        return [(int) $count, 'audience'];
    }
}
