@props(['campaign'])

@php
    $hasRecipients = (int)($campaign->stats_total ?? 0) > 0;
    $status = $campaign->status;
@endphp

<div class="flex flex-wrap items-center gap-2 text-xs">
    <a href="{{ route('campaigns.admin.show', $campaign) }}"
       class="btn-light {{ $status === 'proposed' ? 'ring-2 ring-offset-1 ring-yellow-400 border-yellow-400 font-semibold' : '' }}">
        Review
    </a>

    <a href="{{ route('campaigns.admin.monitor', $campaign) }}"
       class="btn-light">
        Monitor
    </a>

    @if ($status === 'approved')
        <form method="POST" action="{{ route('campaigns.admin.queue', $campaign) }}">
            @csrf
            <button type="submit"
                    class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium bg-blue-600 text-white hover:bg-blue-700">
                Queue
            </button>
        </form>
    @endif

    @if ($status === 'queued')
        @php
            $startDisabled = !$hasRecipients;
            $buildDisabled = !$startDisabled;
        @endphp

        <form method="POST" action="{{ route('campaigns.admin.buildRecipients', $campaign) }}">
            @csrf
            <input type="hidden" name="only_contactable" value="1">
            <button type="submit" {{ $buildDisabled ? 'disabled' : '' }}
            class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium {{ $buildDisabled ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-gray-900 text-white hover:bg-black' }}">
                Build
            </button>
        </form>

        @php
            $th = $campaign->filter_json['_throttling'] ?? [];
            $hasWindow = !empty($th['send_window_start']) && !empty($th['send_window_end']);
        @endphp

        @if ($hasWindow)
            <p class="text-xs text-gray-600 mb-2">
                Call window: <strong>{{ $th['send_window_start'] }}–{{ $th['send_window_end'] }}</strong>.
                Outside this window, sending will pause automatically until the window reopens.
            </p>
        @else
            <p class="text-xs text-gray-600 mb-2">
                Default sending hours: <strong>08:00–20:00</strong>.
                Outside these hours, sending will pause automatically until they reopen.
            </p>
        @endif

        <form method="POST" action="{{ route('campaigns.admin.startSending', $campaign) }}">
            @csrf
            @php
                $startButtonClasses = $startDisabled
                    ? 'inline-flex items-center px-3 py-1.5 rounded-md text-sm font-semibold bg-gray-200 text-gray-500 cursor-not-allowed'
                    : 'inline-flex items-center px-3 py-1.5 rounded-md text-sm font-semibold bg-indigo-600 text-white hover:bg-indigo-700';
            @endphp

            <label class="flex items-center gap-2 text-xs text-amber-800 mb-2">
                <input type="checkbox" name="force_outside_window" value="1" class="rounded border-amber-300">
                Send this batch now even if outside the call window (e.g. for an urgent message)
            </label>

            <button type="submit" {{ $startDisabled ? 'disabled' : '' }}
            class="{{ $startButtonClasses }}">
                Start
            </button>
        </form>
    @endif

    @if (in_array($status, ['approved', 'queued', 'sending'], true))
        @php
            $cancelLabel = $status === 'sending' ? 'Stop' : 'Cancel';
            $cancelConfirmText = $status === 'sending'
                ? 'Stop this campaign? It will be marked cancelled. Already-sent messages will not be undone, and no further messages will be sent.'
                : 'Cancel this campaign? It will be marked cancelled. Already-built recipients are not deleted, but nothing will be sent.';
        @endphp
        <form method="POST" action="{{ route('campaigns.admin.stopSending', $campaign) }}"
              onsubmit="return confirm('{{ $cancelConfirmText }}')">
            @csrf
            <button type="submit"
                    class="text-sm text-gray-500 hover:text-red-600 underline">
                {{ $cancelLabel }}
            </button>
        </form>
    @endif
</div>
