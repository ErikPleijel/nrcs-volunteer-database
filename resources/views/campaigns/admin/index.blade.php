<x-layouts.admin>
    <x-slot name="title">Campaigns</x-slot>

    <x-slot name="pageHeader">
        <i class="fas fa-sliders mr-3"></i>  Campaigns Management
    </x-slot>
    <x-slot name="subHeader">
        Overview
    </x-slot>

    <div class="flex justify-center mb-4">
        <x-help-popup trigger-class="help-btn">
            <x-slot:trigger><i class="fas fa-question-circle text-base mr-1"></i>Guide</x-slot:trigger>

            {{-- Header --}}
            <div class="-mt-8 mb-4 text-center">
                <i class="fas fa-question-circle text-xl text-sky-500"></i>
                <h3 class="mt-1 text-base font-semibold text-gray-900">How do I...</h3>
            </div>

            {{-- Accordion --}}
            <div class="max-w-3xl mx-auto">
                <div x-data="{ open: null }" class="space-y-1 text-sm mb-4">


                    {{-- Where campaigns come from --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'create' ? null : 'create'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-plus mr-2 text-amber-400"></i>Where campaigns come from</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'create' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'create'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Campaigns aren't created here — they're built using the <span class="font-semibold">Campaign Wizard</span>, starting from the Users index page.</li>
                                <li>This page is for <span class="font-semibold">approving and sending</span> campaigns that have already been proposed.</li>
                                <li>New campaigns arrive here in the <span class="font-semibold">Proposed</span> tab, waiting for review.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Understand campaign status tabs --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'tabs' ? null : 'tabs'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-bullhorn mr-2 text-indigo-400"></i>Understand campaign status tabs</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'tabs' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'tabs'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li><span class="font-semibold">Proposed</span> — newly created campaigns awaiting approval.</li>
                                <li><span class="font-semibold">Approved/Queued</span> — approved and waiting to be sent.</li>
                                <li><span class="font-semibold">Sending</span> — currently going out to recipients.</li>
                                <li><span class="font-semibold">Sent</span> — fully delivered, with sent/failed counts shown per campaign.</li>
                                <li><span class="font-semibold">Cancelled</span> / <span class="font-semibold">Rejected</span> — stopped before completion, or turned down at proposal stage.</li>
                                <li>The count badge on each tab updates live as campaigns move through the pipeline.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Approve, reject, queue & send a campaign --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'actions' ? null : 'actions'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-check-circle mr-2 text-green-400"></i>Approve, reject, queue &amp; send a campaign</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'actions' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'actions'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>In the <span class="font-semibold">Proposed</span> tab, click <span class="font-semibold">Review</span> on a campaign.</li>
                                <li>Read the review page CAREFULLY — check the channel, audience, and recipient count before deciding.</li>
                                <li>Click <span class="font-semibold">Approve</span> or <span class="font-semibold">Reject</span>. If rejecting, provide feedback to the sender.</li>
                                <li>Once approved, the campaign moves to the <span class="font-semibold">Approved/Queued</span> tab.</li>
                                <li>From there, the sequence is: click <span class="font-semibold">Queue</span> → <span class="font-semibold">Build</span> → <span class="font-semibold">Send</span>.</li>
                                <li>Once sent, click <span class="font-semibold">Monitor</span> to track delivery progress.</li>
                            </ul>
                        </div>
                    </div>



                    {{-- Read the Messages Pipeline summary --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'pipeline' ? null : 'pipeline'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-gauge-high mr-2 text-violet-400"></i>Read the Messages Pipeline summary</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'pipeline' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'pipeline'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>This bar at the top shows real-time counts across <span class="font-semibold">all</span> campaigns, not just the active tab.</li>
                                <li><span class="font-semibold">Queued</span> — messages waiting to be sent.</li>
                                <li><span class="font-semibold">Sending</span> — messages actively being delivered right now.</li>
                                <li><span class="font-semibold">Failed</span> — messages that didn't go through.</li>
                                <li><span class="font-semibold">Sent today</span> — total successfully delivered since midnight.</li>
                                <li>Use this to spot a stuck queue or a spike in failures before checking individual campaigns.</li>
                            </ul>
                        </div>
                    </div>



                    {{-- Search & find campaigns --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'search' ? null : 'search'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-magnifying-glass mr-2 text-sky-400"></i>Search &amp; find campaigns</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'search' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'search'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Use the search box to find a campaign by <span class="font-semibold">title</span> or <span class="font-semibold">campaign ID</span>.</li>
                                <li>Search stays scoped to whichever status tab you're currently on.</li>
                                <li>Click <span class="font-semibold">Clear</span> to reset the search and see all campaigns in that tab again.</li>
                            </ul>
                        </div>
                    </div>

                </div>{{-- end accordion --}}
            </div>{{-- end max-w-3xl --}}

        </x-help-popup>
    </div>



    <div class="space-y-6">

        {{-- Header --}}


        {{-- Tabs --}}
        @php
            $currentStatus = $status ?? request('status', 'all');
            $tabs = [
                'all'       => 'All',
                'proposed'  => 'Proposed',
                'approved'  => 'Approved/Queued',
                'sending'   => 'Sending',
                'sent'      => 'Sent',
                'cancelled' => 'Cancelled',
                'rejected'  => 'Rejected',
            ];
        @endphp

        {{-- Search --}}
        <form method="GET" action="{{ route('campaigns.admin.proposed') }}" class="flex gap-2">
            <input type="hidden" name="status" value="{{ $currentStatus }}">
            <input type="text"
                   name="q"
                   value="{{ $q ?? '' }}"
                   placeholder="Search title or campaign id…"
                   class="w-full sm:w-80 rounded-md border-gray-300 shadow-sm text-sm">
            <select name="origin"
                    onchange="this.form.submit()"
                    class="rounded-md border-gray-300 shadow-sm text-sm">
                <option value="" {{ ($origin ?? '') === '' ? 'selected' : '' }}>All origins</option>
                <option value="national" {{ ($origin ?? '') === 'national' ? 'selected' : '' }}>National</option>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" {{ ($origin ?? '') === (string) $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                @endforeach
            </select>
            <button type="submit"
                    class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium bg-gray-900 text-white hover:bg-black">
                Search
            </button>
            @if(!empty($q) || !empty($origin))
                <a href="{{ route('campaigns.admin.proposed', ['status' => $currentStatus]) }}"
                   class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium bg-white text-gray-700 ring-1 ring-inset ring-gray-200 hover:bg-gray-50">
                    Clear
                </a>
            @endif
        </form>

        {{-- Flash messages --}}
        @if (session('success'))
            <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        <div>
            <div class="flex flex-wrap gap-0 mt-4">
                @foreach ($tabs as $key => $label)
                    @php $isActive = $currentStatus === $key; @endphp
                    <a href="{{ route('campaigns.admin.proposed', ['status' => $key, 'q' => $q, 'origin' => $origin]) }}"
                       class="relative inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium border-t-2 border-l-2 border-r-2 transition-colors whitespace-nowrap rounded-t-lg
                           {{ $isActive
                               ? 'bg-white border-gray-400 text-indigo-700 font-bold z-10 shadow-sm'
                               : 'bg-gray-100 border-gray-300 text-gray-500 hover:text-gray-800 hover:bg-gray-50' }}">
                        {{ $label }}
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                            {{ $isActive ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-200 text-gray-600' }}">
                            @php
                                $countValue = $key === 'all'
                                    ? ($totalCount ?? $campaigns->total())
                                    : ($key === 'approved'
                                        ? ($statusCounts['approved'] ?? 0) + ($statusCounts['queued'] ?? 0)
                                        : ($statusCounts[$key] ?? 0));
                            @endphp
                            {{ number_format($countValue) }}
                        </span>
                        @if($isActive)
                            <span class="absolute bottom-0 left-0 right-0 h-px bg-white"></span>
                        @endif
                    </a>
                @endforeach
            </div>
            <div class="border-t border-gray-300 -mt-px"></div>

            <div class="bg-white rounded-lg p-4">
                @if($campaigns->isEmpty())
                    <div class="py-12 text-center rounded-lg border border-gray-100">
                        <i class="fas fa-bullhorn text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500 text-sm">No campaigns found.</p>
                    </div>
                @else
                    <div class="table-wrapper">
                        <table class="data-table">
                            <thead class="table-header">
                                <tr class="table-header-row">
                                    <th class="table-header-cell">Origin</th>
                                    <th class="table-header-cell">Status</th>
                                    <th class="table-header-cell">Purpose / Title</th>
                                    <th class="table-header-cell">Channel</th>
                                    <th class="table-header-cell">Submitted</th>
                                    <th class="table-header-cell">Recipients</th>
                                    <th class="table-header-cell">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="table-body">
                                @foreach($campaigns as $campaign)
                                    @php
                                        $badge = match ($campaign->status) {
                                            'proposed'  => 'bg-yellow-100 text-yellow-800',
                                            'approved'  => 'bg-green-100 text-green-800',
                                            'rejected'  => 'bg-red-100 text-red-800',
                                            'queued'    => 'bg-blue-100 text-blue-800',
                                            'sending'   => 'bg-indigo-100 text-indigo-800',
                                            'sent'      => 'bg-emerald-100 text-emerald-800',
                                            'cancelled' => 'bg-gray-100 text-gray-700',
                                            default     => 'bg-gray-100 text-gray-700',
                                        };

                                        $channelIcon = match($campaign->channel ?? '') {
                                            'email'              => 'fa-envelope',
                                            'sms'                => 'fa-comment-sms',
                                            'both'               => 'fa-comments',
                                            'email_fallback_sms' => 'fa-envelope-open-text',
                                            default              => 'fa-bullhorn',
                                        };

                                        $total          = (int)($campaign->stats_total ?? 0);
                                        $sent           = (int)($campaign->stats_sent ?? 0);
                                        $failed         = (int)($campaign->stats_failed ?? 0);
                                        $estimatedTotal = (int) data_get($campaign->filter_json, '_audience_total', 0);

                                    @endphp

                                    <tr class="table-body-row">
                                        <td class="table-body-cell">
                                            <div class="table-field-main">{{ $campaign->code }}</div>
                                        </td>

                                        <td class="table-body-cell">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge }}">
                                                {{ ucfirst($campaign->status) }}
                                            </span>
                                        </td>

                                        <td class="table-body-cell">
                                            <div class="table-field-main">{{ $campaign->purpose?->name ?? ucfirst($campaign->audience_type) }}</div>
                                            <div class="table-field-sub max-w-[220px] truncate" title="{{ $campaign->title ?? 'Untitled campaign' }}">{{ $campaign->title ?? 'Untitled campaign' }}</div>
                                        </td>

                                        <td class="table-body-cell">
                                            <div class="flex items-center gap-2">
                                                <i class="fas {{ $channelIcon }} text-gray-400 w-4 text-center flex-shrink-0"></i>
                                                <span class="uppercase text-xs font-medium text-gray-500">{{ str_replace('_', ' ', $campaign->channel ?? '—') }}</span>
                                            </div>
                                        </td>

                                        <td class="table-body-cell">
                                            @if($campaign->submitted_at)
                                                <div class="table-field-main">{{ $campaign->submitted_at->format('M d, Y H:i') }}</div>
                                            @else
                                                <div class="table-field-main text-gray-400">—</div>
                                            @endif
                                            @if($campaign->submitter)
                                                <div class="table-field-sub">{{ $campaign->submitter->full_name }}</div>
                                            @endif
                                        </td>

                                        <td class="table-body-cell">
                                            @if($estimatedTotal > 0)
                                                <div class="table-field-main">~{{ number_format($estimatedTotal) }}</div>
                                            @elseif($total > 0)
                                                <div class="table-field-main">{{ number_format($total) }}</div>
                                            @else
                                                <div class="table-field-main text-gray-400">—</div>
                                            @endif

                                            @if(in_array($campaign->status, ['sent', 'sending', 'cancelled']) && ($sent > 0 || $failed > 0))
                                                <div class="table-field-sub">
                                                    {{ number_format($sent) }} sent
                                                    @if($failed > 0)
                                                        · <span class="text-red-600 font-medium">{{ number_format($failed) }} failed</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>

                                        <td class="table-body-cell-nowrap">
                                            @include('campaigns.admin.partials.action-buttons', ['campaign' => $campaign])
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Pagination --}}
        <div>
            {{ $campaigns->links() }}
        </div>

    </div>
</x-layouts.admin>
