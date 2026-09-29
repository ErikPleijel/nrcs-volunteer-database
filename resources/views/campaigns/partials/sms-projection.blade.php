{{-- Estimated SMS volume (App\Campaigns\Sms\SmsProjection::estimate()). Expects $smsProjection. --}}
@if (!empty($smsProjection))
    @php($p = $smsProjection)
    <div class="rounded-md border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-950 space-y-2">
        <div class="font-semibold">Estimated SMS volume</div>
        <div class="text-base">
            <strong>{{ number_format($p['recipients']) }}</strong> SMS recipient{{ $p['recipients'] === 1 ? '' : 's' }}
            × <strong>{{ $p['segments'] }}</strong> page{{ $p['segments'] === 1 ? '' : 's' }}
            = <strong>{{ number_format($p['pages']) }}</strong> SMS pages
            @if (!is_null($p['cost']))
                · about <strong>{{ $p['currency'] }} {{ number_format($p['cost'], 2) }}</strong>
                <span class="text-xs text-indigo-800">({{ $p['currency'] }} {{ rtrim(rtrim(number_format($p['price_per_segment'], 4), '0'), '.') }} per page)</span>
            @endif
        </div>
        <div class="text-xs text-indigo-800">
            {{ $p['recipients_source'] === 'recipients'
                ? 'Counted from the built recipient list.'
                : 'Counted from the audience: opted-out people, invalid numbers and duplicate numbers (one SMS per shared number) are excluded.' }}
            @if (($campaign->channel ?? null) === 'email_fallback_sms')
                People whose email fails and fall back to SMS are not included.
            @endif
            Pages use the longest sample message, including the opt-out link.
        </div>
        @if ($p['encoding'] === 'UCS-2')
            <div class="rounded border border-amber-300 bg-amber-50 p-2 text-xs text-amber-900">
                <i class="fas fa-triangle-exclamation mr-1"></i>
                <strong>Unicode SMS.</strong> The message contains characters outside the standard SMS alphabet
                (<span class="font-mono">{{ implode(' ', array_slice($p['non_gsm'], 0, 10)) }}{{ count($p['non_gsm']) > 10 ? ' …' : '' }}</span>),
                so each page holds 70 characters (67 when split) instead of 160 — roughly double the pages and cost.
                Where plain letters are acceptable (for example ƙ → k, ɗ → d, ɓ → b) and without emoji, it would send as standard SMS.
            </div>
        @endif
    </div>
@endif
