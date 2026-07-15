@php
    $campaignsLabel = auth()->user()->getAccessLevel() === 'national'
        ? 'NAT Campaigns'
        : (\App\Models\Branch::find(auth()->user()->getScopedBranchId())?->code ?? 'Branch') . ' Campaigns';
@endphp
<x-layouts.admin>
    <x-slot name="title">{{ $campaignsLabel }}</x-slot>

    <x-slot name="pageHeader">
        <i class="fas fa-bullhorn mr-3 mb-6"></i>{{ $campaignsLabel }}
    </x-slot>



    @php
        $badge = match ($campaign->status) {
            'draft' => 'bg-gray-100 text-gray-700',
            'proposed' => 'bg-yellow-100 text-yellow-800',
            'approved' => 'bg-green-100 text-green-800',
            'queued' => 'bg-blue-100 text-blue-800',
            'sending' => 'bg-indigo-100 text-indigo-800',
            'sent' => 'bg-emerald-100 text-emerald-800',
            'rejected' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-700',
        };

        $statusLabel = match ($campaign->status) {
            'proposed' => 'Submitted',
            default => ucfirst($campaign->status),
        };

        $statsTotal = (int)($campaign->stats_total ?? 0);
        $statsSent = (int)($campaign->stats_sent ?? 0);
        $statsFailed = (int)($campaign->stats_failed ?? 0);

        $deliveryTotal = $deliveryStats['total'] ?: $statsTotal;
        $deliverySent = max($deliveryStats['sent'], $statsSent);
        $deliveryFailed = max($deliveryStats['failed'], $statsFailed);
        $deliveryPending = $deliveryStats['pending'];
        $deliveryQueued = $deliveryStats['queued'];

        $progressPct = $deliveryTotal > 0 ? round(($deliverySent / $deliveryTotal) * 100) : 0;

        $showSms = in_array($campaign->channel, ['sms', 'both', 'email_fallback_sms'], true);

        $hasEmail = in_array($campaign->channel, ['email', 'both', 'email_fallback_sms'], true);
        $hasSms   = in_array($campaign->channel, ['sms', 'both', 'email_fallback_sms'], true);
    @endphp

    <div class="space-y-8">

        <div>
            <a href="{{ route('campaigns.mine') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
                <i class="fas fa-arrow-left mr-1"></i>Back to {{ $campaignsLabel }}
            </a>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <div class="space-y-6 lg:col-span-7">
                <div class="wizard-card">
                    <div class="w-full text-center mb-4">
                        <div class="text-2xl font-bold text-gray-900">{{ $campaign->title ?? '(no title)' }}</div>
                        <div class="mt-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-2xl font-medium {{ $badge }}">
                                {{ $statusLabel }}
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">

                        <div class="rounded-md bg-gray-50 p-3">
                            <div class="text-sm font-semibold uppercase tracking-wide text-gray-500">Created by</div>
                            <div class="mt-1 text-base text-gray-900">{{ $campaign->creator?->full_name ?? '—' }}</div>
                        </div>
                        <div class="rounded-md bg-gray-50 p-3">
                            <div class="text-sm font-semibold uppercase tracking-wide text-gray-500">Approved by</div>
                            <div class="mt-1 text-base text-gray-900">{{ $campaign->approver?->full_name ?? '—' }}</div>
                        </div>
                        <div class="rounded-md bg-gray-50 p-3">
                            <div class="text-sm font-semibold uppercase tracking-wide text-gray-500">Origin</div>
                            <div class="mt-1 text-base font-bold text-gray-900">
                                @if($campaign->origin_level === 'national')
                                    National
                                @elseif($campaign->origin_level === 'branch')
                                    {{ $campaign->originBranch?->name ?? '—' }}
                                @else
                                    —
                                @endif
                            </div>
                        </div>
                    </div>

                    @php
                        $filterRaw = $campaign->filter_json ?? [];
                        $filter = is_array($filterRaw) ? $filterRaw : (json_decode((string) $filterRaw, true) ?: []);
                        $filterHtml = $campaign->filter_description_html ?? null;
                        $content = data_get($filter, '_content', []);
                    @endphp
                    @if($filterHtml)
                    <div class="mt-4">
                        <div class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-1">Filter</div>
                        <div class="w-full rounded-md border border-gray-200 bg-white p-4 text-sm text-gray-700 shadow-sm">
                            {!! $filterHtml !!}
                        </div>
                    </div>
                    @endif
                </div>

                <div class="wizard-card">

                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-2xl font-semibold text-gray-900">Delivery</h2>
                        </div>
                    </div>

                    <div class="mt-1 text-base text-gray-900">CHANNEL: {{ $campaign->channel_label }}</div>

                    <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                        <div class="rounded-md bg-gray-50 p-3">
                            <div class="text-sm font-semibold uppercase tracking-wide text-gray-500">Total</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($deliveryTotal) }}</div>
                        </div>
                        <div class="rounded-md bg-gray-50 p-3">
                            <div class="text-sm font-semibold uppercase tracking-wide text-gray-500">Sent</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($deliverySent) }}</div>
                            @if(! ($reachabilityKnown ?? true) && (($willEmail ?? 0) > 0 || ($willSms ?? 0) > 0))
                                <div class="mt-0.5 text-base text-gray-600">Email: {{ number_format($willEmail ?? 0) }} &middot; SMS: {{ number_format($willSms ?? 0) }}</div>
                            @endif
                        </div>
                        <div class="rounded-md bg-gray-50 p-3">
                            <div class="text-sm font-semibold uppercase tracking-wide text-gray-500">Failed</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($deliveryFailed) }}</div>
                        </div>
                        <div class="rounded-md bg-gray-50 p-3">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Queued / pending</div>
                            <div class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($deliveryQueued + $deliveryPending) }}</div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="h-3 w-full overflow-hidden rounded-full bg-gray-100 ring-1 ring-gray-200">
                            <div class="h-full rounded-full bg-gray-700" style="width: {{ $progressPct }}%"></div>
                        </div>
                        <div class="mt-1 text-sm text-gray-500">{{ $progressPct }}% sent ({{ number_format($deliverySent) }} of {{ number_format($deliveryTotal) }})</div>
                    </div>

                    @if($reachabilityKnown ?? true)
                        {{-- Not yet sent: no "Sent" breakdown exists yet, so show the live
                             audience match (Matched/Email/SMS/Not-reachable) as its own section. --}}
                        <div class="mt-4 pt-4 border-t border-gray-200">
                            @include('campaigns.partials.audience-summary')
                        </div>
                    @endif
                </div>

                <div class="wizard-card space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-semibold text-gray-900">Message preview</h2>
                        @if($campaign->subject || $campaign->body)
                        <div class="flex rounded-md border border-gray-300 overflow-hidden text-xs font-medium">
                            <button type="button" id="show-tab-code"
                                    class="px-3 py-1 bg-white text-gray-600 hover:bg-gray-50 transition-colors"
                                    onclick="setShowTab('code')">
                                <i class="fas fa-code mr-1"></i>Code
                            </button>
                            <button type="button" id="show-tab-preview"
                                    class="px-3 py-1 bg-indigo-600 text-white transition-colors"
                                    onclick="setShowTab('preview')">
                                <i class="fas fa-eye mr-1"></i>Preview
                            </button>
                        </div>
                        @endif
                    </div>

                    @if($campaign->subject || $campaign->body)
                        {{-- From / Reply-to --}}
                        @if($campaign->from_name || $campaign->reply_to_email)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pb-4 border-b border-gray-100">
                            <div>
                                <div class="text-sm font-semibold uppercase tracking-wide text-gray-500">From name</div>
                                <div class="mt-1 text-base text-gray-900">{{ $campaign->from_name ?: '—' }}</div>
                            </div>
                            <div>
                                <div class="text-sm font-semibold uppercase tracking-wide text-gray-500">Reply-to</div>
                                <div class="mt-1 text-base text-gray-900">{{ $campaign->reply_to_email ?: '—' }}</div>
                            </div>
                        </div>
                        @endif

                        {{-- Subject --}}
                        <div>
                            <div class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-1">Email subject</div>
                            <div class="text-base font-medium text-gray-900">{{ $campaign->subject ?: '—' }}</div>
                        </div>

                        {{-- Code view --}}
                        <div id="show-code-view" class="hidden">
                            <div class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-1">Email body</div>
                            <div class="rounded-md border border-gray-200 bg-gray-50 p-4 text-xs text-gray-800 whitespace-pre-wrap min-h-[200px] font-mono overflow-auto">
                                {{ trim((string)$campaign->body) ?: '—' }}
                            </div>
                        </div>

                        {{-- Preview view --}}
                        <div id="show-preview-view">
                            <div class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-1">Email body</div>
                            <iframe id="show-preview-frame"
                                    class="w-full rounded-md border border-gray-200 bg-white"
                                    style="height: 400px;"
                                    sandbox="allow-same-origin">
                            </iframe>
                            <p class="text-sm text-gray-400 mt-1">
                                <i class="fas fa-info-circle mr-1"></i>
                                Placeholders shown as-is — substituted with real recipient data at send time.
                            </p>
                        </div>
                    @else
                        <div class="text-base text-gray-500 italic">No message content recorded.</div>
                    @endif

                    @if($showSms)
                        <div class="mt-4 rounded-md border border-gray-200 bg-gray-50 p-4 space-y-2">
                            <div class="text-sm font-semibold uppercase tracking-wide text-gray-500">SMS text</div>
                            <div class="whitespace-pre-wrap text-base text-gray-800">{{ trim((string)($content['sms_body'] ?? '')) ?: '—' }}</div>
                        </div>
                    @endif

                    <script>
                        (function () {
                            const emailBody = @json(trim((string)($campaign->body ?? '')));

                            function setShowTab(tab) {
                                const codeView    = document.getElementById('show-code-view');
                                const previewView = document.getElementById('show-preview-view');
                                const tabCode     = document.getElementById('show-tab-code');
                                const tabPreview  = document.getElementById('show-tab-preview');
                                const frame       = document.getElementById('show-preview-frame');

                                [tabCode, tabPreview].forEach(btn => {
                                    if (btn) {
                                        btn.classList.remove('bg-indigo-600', 'text-white');
                                        btn.classList.add('bg-white', 'text-gray-600');
                                    }
                                });

                                if (tab === 'preview') {
                                    codeView.classList.add('hidden');
                                    previewView.classList.remove('hidden');
                                    tabPreview.classList.add('bg-indigo-600', 'text-white');
                                    tabPreview.classList.remove('bg-white', 'text-gray-600');
                                    if (frame) {
                                        const doc = frame.contentDocument || frame.contentWindow.document;
                                        doc.open();
                                        doc.write(emailBody || '<p style="color:#999;font-family:sans-serif;padding:1rem;">No email body.</p>');
                                        doc.close();
                                    }
                                } else {
                                    codeView.classList.remove('hidden');
                                    previewView.classList.add('hidden');
                                    tabCode.classList.add('bg-indigo-600', 'text-white');
                                    tabCode.classList.remove('bg-white', 'text-gray-600');
                                }
                            }

                            window.setShowTab = setShowTab;

                            document.addEventListener('DOMContentLoaded', function () {
                                setShowTab('preview');
                            });
                        })();
                    </script>
                </div>
            </div>

            <div class="space-y-6 lg:col-span-5">
                <div class="wizard-card">
                    <h2 class="text-2xl font-semibold text-gray-900">Review notes</h2>
                    <p class="mt-1 text-base text-gray-600">Any feedback from reviewers will appear here.</p>
                    <div class="mt-3 rounded-md p-4 text-base {{ $campaign->review_note ? 'bg-red-100 text-gray-800' : 'bg-gray-50 text-gray-400 italic' }}">
                        {{ $campaign->review_note ?: 'No review notes recorded.' }}
                    </div>
                </div>

                <div class="wizard-card">
                    <h2 class="text-2xl font-semibold text-gray-900">Timeline</h2>
                    <p class="mt-1 text-base text-gray-600">Key events for this campaign.</p>
                    <dl class="mt-4 space-y-3 text-base text-gray-800">
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-gray-600">Created</dt>
                            <dd class="font-medium text-gray-900">{{ $campaign->created_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-gray-600">Submitted</dt>
                            <dd class="font-medium text-gray-900">
                                {{ $campaign->submitted_at?->format('Y-m-d H:i') ?? '—' }}

                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-gray-600">Approved</dt>
                            <dd class="font-medium text-gray-900">
                                {{ $campaign->approved_at?->format('Y-m-d H:i') ?? '—' }}

                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-gray-600">Last send attempt</dt>
                            <dd class="font-medium text-gray-900">{{ $campaign->last_send_run_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="text-gray-600">Send completed</dt>
                            <dd class="font-medium text-gray-900">{{ $campaign->send_completed_at?->format('Y-m-d H:i') ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
