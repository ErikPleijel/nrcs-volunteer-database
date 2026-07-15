{{--
    Shared "contacted" summary block for campaigns.admin.show and campaigns.mine.show.
    Expects: $campaign, $matchedTotal, $willEmail, $willSms, $noReach, $mayReceiveTwo.
    $hasEmail / $hasSms may be passed in; otherwise derived from $campaign->channel.
    $reachabilityKnown (default true): false once a campaign has sent, since
    "Not reachable" can no longer be honestly derived from messaging_recipients
    (see CampaignAudienceSummaryService::summarizeFromRecipients).
--}}
@php
    $channel = $campaign->channel ?? 'email';
    $hasEmail = $hasEmail ?? in_array($channel, ['email', 'both', 'email_fallback_sms'], true);
    $hasSms   = $hasSms ?? in_array($channel, ['sms', 'both', 'email_fallback_sms'], true);
    $reachabilityKnown = $reachabilityKnown ?? true;

    // "both" is the only channel where email/SMS reach can overlap (same person
    // may get both messages), so Total contacted must use willReach there rather
    // than a naive sum that would double-count the overlap.
    $overlapping = $channel === 'both';
    $totalContacted = (! $reachabilityKnown || $overlapping)
        ? ($willReach ?? 0)
        : (($willEmail ?? 0) + ($willSms ?? 0));
    $overlapNote = $overlapping ? ' (may overlap)' : '';
@endphp

<div class="text-base text-gray-800 leading-relaxed">
    <span class="font-semibold">{{ $reachabilityKnown ? 'Would contact' : 'Total contacted' }}: {{ number_format($totalContacted) }}</span>
    @if(! $reachabilityKnown || ($hasEmail && $hasSms))
        <span class="text-gray-700">(Email: {{ number_format($willEmail ?? 0) }}, SMS: {{ number_format($willSms ?? 0) }}{{ $overlapNote }})</span>
    @endif
    @if($reachabilityKnown)
        <span class="text-gray-500">&middot; Not reachable: {{ number_format($noReach ?? 0) }}</span>
    @endif
</div>

{{--
@if($reachabilityKnown && (($overlapping && ($mayReceiveTwo ?? 0) > 0) || ($noReach ?? 0) > 0))
    <div class="mt-3 rounded-md bg-amber-50 border border-amber-200 p-3 text-base text-amber-900 space-y-1">
        @if($overlapping && ($mayReceiveTwo ?? 0) > 0)
            <div><span class="font-semibold">Up to {{ number_format($mayReceiveTwo) }} person(s)</span> may receive both an email and an SMS.</div>
        @endif
        @if(($noReach ?? 0) > 0)
            <div class="text-amber-800">
                <span class="font-semibold">{{ number_format($noReach) }}</span>
                @if($channel === 'sms')
                    person(s) have no phone number and will not receive the message.
                @elseif($channel === 'email')
                    person(s) have no email address and will not receive the message.
                @else
                    person(s) have neither email nor phone number and will not receive any message.
                @endif
            </div>
        @endif
    </div>
@endif
--}}
