<x-layouts.admin title="Campaign monitor">
    <x-slot name="pageHeader">
        <i class="fas fa-sliders mr-3"></i>  Campaigns Management
    </x-slot>
    <x-slot name="subHeader">
        Monitor
    </x-slot>

    <div>
        <a href="{{ route('campaigns.admin.proposed') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
            <i class="fas fa-arrow-left mr-1"></i>Back to Campaign Management
        </a>
    </div>

    <div class="text-3xl font-bold text-gray-900 mt-3">
        {{ $campaign->code }}
        {{ $campaign->title ?? 'Untitled campaign' }}
        <span class="text-gray-400 font-light mx-2">/</span>
        <span class="text-gray-600">{{ $campaign->creator?->branch?->name ?? '—' }}</span>
    </div>

    <div class="mb-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>

                <div class="mt-1 text-lg text-slate-600">
                    Channel: <span class="font-medium">{{ $campaign->channel }}</span>
                    · Status: <span class="font-medium">{{ $campaign->status }}</span>
                </div>
            </div>


        </div>
    </div>

    {{-- KPI cards --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
            <div class="text-xs text-slate-500">Total</div>
            <div class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format($totalCount) }}</div>
        </div>

        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
            <div class="text-xs text-slate-500">Pending</div>
            <div class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format($pendingCount) }}</div>
        </div>

        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
            <div class="text-xs text-slate-500">Sent</div>
            <div class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format($sentCount) }}</div>
        </div>

        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
            <div class="text-xs text-slate-500">Failed</div>
            <div class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format($failedCount) }}</div>
        </div>
    </div>

    {{-- Status + throttling --}}
    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200 lg:col-span-2">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-sm font-semibold text-slate-900">Send progress</div>
                    <div class="mt-1 text-sm text-slate-600">
                        Started:
                        <span class="font-medium">{{ optional($campaign->send_started_at)->format('Y-m-d H:i') ?? '—' }}</span>
                        · Last run:
                        <span class="font-medium">{{ optional($campaign->last_send_run_at)->format('Y-m-d H:i') ?? '—' }}</span>
                        · Completed:
                        <span class="font-medium">{{ optional($campaign->send_completed_at)->format('Y-m-d H:i') ?? '—' }}</span>
                    </div>
                </div>

                <div class="text-right text-sm text-slate-600">
                    <div>Daily date: <span class="font-medium">{{ $campaign->daily_sent_date ?? '—' }}</span></div>
                    <div>Daily sent: <span class="font-medium">{{ (int)$campaign->daily_sent_count }}</span></div>
                </div>
            </div>

            <div class="mt-4">
                @php
                    $pct = $totalCount > 0 ? round(($sentCount / $totalCount) * 100, 1) : 0;
                @endphp
                <div class="h-3 w-full overflow-hidden rounded-full bg-slate-100 ring-1 ring-slate-200">
                    <div class="h-full rounded-full bg-slate-700" style="width: {{ $pct }}%"></div>
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    {{ $pct }}% sent ({{ $sentCount }} of {{ $totalCount }})
                </div>
            </div>



            <div class="mt-3 text-xs text-slate-500">
                Tip: when you add a scheduler, this page becomes “live”. Until then, use <span class="font-medium">Run once</span>.
            </div>
        </div>

        {{-- Throttling card --}}
        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
            <div class="text-sm font-semibold text-slate-900">Throttling</div>

            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-slate-600">Daily cap</dt>
                    <dd class="font-medium text-slate-900">
                        {{ !empty($throttling['daily_cap']) ? (int)$throttling['daily_cap'] : '—' }}
                    </dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-slate-600">Send window</dt>
                    <dd class="font-medium text-slate-900">
                        @php
                            $ws = $throttling['send_window_start'] ?? null;
                            $we = $throttling['send_window_end'] ?? null;
                        @endphp
                        {{ $ws && $we ? "{$ws}–{$we}" : 'Anytime' }}
                    </dd>
                </div>
            </dl>

            <div class="mt-4 text-xs text-slate-500">
                (Window + daily cap are enforced by the sender engine.)
            </div>
        </div>
    </div>

    {{-- Recipients --}}
    <div class="mt-6 space-y-4">

        {{-- Tabs + Search --}}
        @php
            $recipientTabs = [
                'all'     => 'All',
                'pending' => 'Pending',
                'sent'    => 'Sent',
                'failed'  => 'Failed (Resend)',
            ];
        @endphp

        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex flex-wrap gap-2">
                @foreach($recipientTabs as $key => $label)
                    <a href="{{ route('campaigns.admin.monitor', ['campaign' => $campaign->id, 'tab' => $key, 'q' => $q]) }}"
                       class="inline-flex items-center rounded-md px-3 py-2 text-sm font-medium ring-1 ring-inset transition
                            {{ ($tab ?? 'all') === $key
                                ? 'bg-red-50 text-red-700 ring-red-200'
                                : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('campaigns.admin.monitor', $campaign) }}" class="w-full lg:w-auto">
                <input type="hidden" name="tab" value="{{ $tab ?? 'all' }}">
                <div class="flex gap-2">
                    <input type="text" name="q" value="{{ $q }}"
                           placeholder="Search name, email, phone, or user id…"
                           class="w-full lg:w-80 rounded-md border-gray-300 shadow-sm text-sm">
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium bg-gray-900 text-white hover:bg-black">
                        Search
                    </button>
                    @if(!empty($q))
                        <a href="{{ route('campaigns.admin.monitor', ['campaign' => $campaign->id, 'tab' => ($tab ?? 'all')]) }}"
                           class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium bg-white text-gray-700 ring-1 ring-inset ring-gray-200 hover:bg-gray-50">
                            Clear
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Failed-tab: reset panel --}}
        @if(($tab ?? 'all') === 'failed')
            <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <div class="font-semibold text-yellow-900">Retry failed recipients</div>
                        <div class="text-sm text-yellow-800">
                            Resets failed recipients back to <span class="font-mono">pending</span> so they can be retried.
                            By default resets only <span class="font-mono">failed</span> (not bounced/undeliverable).
                        </div>
                    </div>
                    <form method="POST" action="{{ route('campaigns.admin.recipients.resetFailed', $campaign) }}" class="flex items-center gap-3">
                        @csrf
                        <label class="inline-flex items-center gap-2 text-sm text-yellow-900">
                            <input type="checkbox" name="include_bounced" value="1" class="rounded border-gray-300">
                            Include bounced/undeliverable
                        </label>
                        <button type="submit"
                                onclick="return confirm('Reset recipients to pending?')"
                                class="inline-flex flex-col items-center justify-center px-4 py-2 rounded-md text-sm font-medium bg-yellow-700 text-white hover:bg-yellow-800">
                            <span>Reset to queued</span>
                            <span class="text-sm opacity-90 font-normal">(Needs restart)</span>
                        </button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Table --}}
        <div class="overflow-hidden bg-white shadow rounded-lg">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Recipient</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sent at</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last error</th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($recipients as $r)
                    @php
                        $statusBadge = match ($r->status) {
                            'pending'       => 'bg-gray-100 text-gray-700',
                            'sent'          => 'bg-green-100 text-green-800',
                            'failed'        => 'bg-red-100 text-red-800',
                            'bounced'       => 'bg-orange-100 text-orange-800',
                            'undeliverable' => 'bg-red-100 text-red-800',
                            default         => 'bg-gray-100 text-gray-700',
                        };
                        $payload = is_array($r->payload_json) ? $r->payload_json : [];
                        $name = $payload['full_name'] ?? null;
                    @endphp
                    <tr>
                        <td class="px-4 py-3 text-sm text-gray-900">
                            <div class="font-medium">{{ $name ?: ('User #' . $r->recipient_id) }}</div>
                            <div class="text-xs text-gray-500">{{ class_basename($r->recipient_type) }} · ID {{ $r->recipient_id }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $r->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $r->phone ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $statusBadge }}">
                                {{ ucfirst($r->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700">{{ $r->sent_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700">
                            @if($r->last_error)
                                <span class="text-red-700">{{ \Illuminate\Support\Str::limit($r->last_error, 120) }}</span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">No recipients in this tab.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $recipients->links() }}</div>
    </div>
</x-layouts.admin>
