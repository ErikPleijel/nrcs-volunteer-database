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
                                @click="open = open === 'wizard' ? null : 'wizard'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-magic mr-2 text-indigo-400"></i>Start a new campaign</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'wizard' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'wizard'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Campaigns aren't created here — build one using the <span class="font-semibold">Campaign Wizard</span>, started from the <span class="font-semibold">Users</span> or <span class="font-semibold">Organisations</span> index page.</li>
                                <li>This page is where you come back afterward to <span class="font-semibold">monitor progress, finish drafts, and manage</span> what you've already started or sent.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Understand the tabs --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'tabs' ? null : 'tabs'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-bullhorn mr-2 text-violet-400"></i>Understand the tabs</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'tabs' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'tabs'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li><span class="font-semibold">Drafts</span> — started in the wizard but not yet submitted for approval.</li>
                                <li><span class="font-semibold">Submitted</span> — sent for approval and awaiting a decision.</li>
                                <li><span class="font-semibold">Rejected</span> — turned down, with feedback available on the campaign.</li>
                                <li><span class="font-semibold">Approved/Sending</span> — approved and currently being queued, built, or sent.</li>
                                <li><span class="font-semibold">Sent</span> — fully delivered, with sent/failed counts shown per campaign.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Finish a draft or fix a rejected campaign --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'edit' ? null : 'edit'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-pencil mr-2 text-amber-400"></i>Finish a draft or fix a rejected campaign</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'edit' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'edit'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>On a <span class="font-semibold">Draft</span> campaign, click <span class="font-semibold">Continue editing</span> to pick up where you left off in the wizard.</li>
                                <li>On a <span class="font-semibold">Rejected</span> campaign, click <span class="font-semibold">Edit campaign</span> to make changes and resubmit.</li>
                                <li>Click <span class="font-semibold">View</span> on any campaign to see its full details.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Delete a campaign --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'delete' ? null : 'delete'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-trash mr-2 text-red-400"></i>Delete a campaign</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'delete' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'delete'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>The <span class="font-semibold">Delete</span> button only appears on <span class="font-semibold">Draft</span> and <span class="font-semibold">Rejected</span> campaigns.</li>
                                <li>Once a campaign is submitted, approved, sending, or sent, it can no longer be deleted from here.</li>
                                <li>Deleting is permanent and cannot be undone.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Track delivery progress --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'monitor' ? null : 'monitor'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-chart-line mr-2 text-sky-400"></i>Track delivery progress</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'monitor' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'monitor'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Each card shows a live delivery line, e.g. <span class="font-semibold">Queued</span>, <span class="font-semibold">Sending…</span>, or <span class="font-semibold">X / Y sent</span>.</li>
                                <li>If any messages failed, the failed count shows in red next to the sent count.</li>
                                <li>Click <span class="font-semibold">View</span> for full details on a specific campaign's delivery.</li>
                            </ul>
                        </div>
                    </div>

                </div>{{-- end accordion --}}
            </div>{{-- end max-w-3xl --}}

        </x-help-popup>
    </div>

    <div class="space-y-1">

        @php
            $currentStatus = $status ?? request('status', 'submitted');
            $tabs = [
                'all'       => 'All',
                'draft'     => 'Drafts',
                'submitted' => 'Submitted',
                'rejected'  => 'Rejected',
                'approved'  => 'Approved/Sending',
                'sent'      => 'Sent',
                'cancelled' => 'Cancelled',
            ];
        @endphp

        <div>
            <div class="flex flex-wrap gap-0 mt-4">
                @foreach ($tabs as $key => $label)
                    @php $isActive = $currentStatus === $key; @endphp
                    <a href="{{ route('campaigns.mine', ['status' => $key]) }}"
                       class="relative inline-flex items-center gap-2 px-5 py-2.5 text-sm font-medium border-t-2 border-l-2 border-r-2 transition-colors whitespace-nowrap rounded-t-lg
                           {{ $isActive
                               ? 'bg-white border-gray-400 text-indigo-700 font-bold z-10 shadow-sm'
                               : 'bg-gray-100 border-gray-300 text-gray-500 hover:text-gray-800 hover:bg-gray-50' }}">
                        {{ $label }}
                        <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                            {{ $isActive
                                ? 'bg-indigo-100 text-indigo-700'
                                : 'bg-gray-200 text-gray-600' }}">
                            {{ $tabCounts[$key] ?? 0 }}
                        </span>
                        @if($isActive)
                            {{-- Cover the bottom border of the active tab --}}
                            <span class="absolute bottom-0 left-0 right-0 h-px bg-white"></span>
                        @endif
                    </a>
                @endforeach
            </div>
            <div class="border-t border-gray-300 -mt-px"></div>
        </div>

        @if (session('success'))
            <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        @if($campaigns->isEmpty())
            <div class="bg-white rounded-lg p-4">
                <div class="py-12 text-center rounded-lg border border-gray-100">
                    <i class="fas fa-bullhorn text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500 text-sm">No campaigns in this tab.</p>
                </div>
            </div>
        @else

            <div class="bg-white rounded-lg p-4">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                @foreach($campaigns as $campaign)
                    @php
                        $badge = match ($campaign->status) {
                            'draft'    => 'bg-gray-100 text-gray-700',
                            'proposed' => 'bg-yellow-100 text-yellow-800',
                            'approved' => 'bg-green-100 text-green-800',
                            'queued'   => 'bg-blue-100 text-blue-800',
                            'sending'  => 'bg-indigo-100 text-indigo-800',
                            'sent'     => 'bg-emerald-100 text-emerald-800',
                            'rejected' => 'bg-red-100 text-red-800',
                            default    => 'bg-gray-100 text-gray-700',
                        };

                        $statusLabel = match ($campaign->status) {
                            'proposed' => 'Submitted',
                            default    => ucfirst($campaign->status),
                        };

                        $total  = (int)($campaign->stats_total ?? 0);
                        $sent   = (int)($campaign->stats_sent ?? 0);
                        $failed = (int)($campaign->stats_failed ?? 0);

                        if ($total > 0) {
                            $delivery = $sent . ' / ' . $total . ' sent' . ($failed ? " · {$failed} failed" : '');
                        } else {
                            $delivery = match ($campaign->status) {
                                'draft'              => null,
                                'proposed','approved','queued' => 'Queued',
                                'sending'            => 'Sending…',
                                'sent'               => 'Sent',
                                'rejected'           => 'Rejected',
                                default              => null,
                            };
                        }

                        $channelIcon = match($campaign->channel ?? '') {
                            'email'              => 'fa-envelope',
                            'sms'                => 'fa-comment-sms',
                            'both'               => 'fa-comments',
                            'email_fallback_sms' => 'fa-envelope-open-text',
                            default              => 'fa-bullhorn',
                        };
                    @endphp

                    <div class="bg-blue-50 rounded-lg shadow border border-gray-200 flex flex-col">
                        {{-- Card header --}}
                        <div class="px-5 pt-5 pb-3 flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-gray-900 text-base leading-snug truncate">
                                    {{ $campaign->title ?? 'Untitled campaign' }}
                                </div>
                                <div class="text-xs text-gray-400 mt-0.5">{{ $campaign->code }}</div>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium flex-shrink-0 {{ $badge }}">
                                {{ $statusLabel }}
                            </span>
                        </div>

                        {{-- Card body --}}
                        <div class="px-5 pb-4 flex-1 space-y-2 text-sm text-gray-600">
                            <div class="flex items-center gap-2">
                                <i class="fas {{ $channelIcon }} text-gray-400 w-4 text-center"></i>
                                <span class="uppercase text-xs font-medium text-gray-500">{{ str_replace('_', ' ', $campaign->channel ?? '—') }}</span>
                            </div>

                            @if($delivery)
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-paper-plane text-gray-400 w-4 text-center"></i>
                                    <span>{{ $delivery }}</span>
                                </div>
                            @endif

                            @if($campaign->submitter)
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-user text-gray-400 w-4 text-center"></i>
                                    <span>{{ $campaign->submitter->full_name }}</span>
                                </div>
                            @endif

                            <div class="flex items-center gap-2">
                                <i class="fas fa-clock text-gray-400 w-4 text-center"></i>
                                <span>{{ $campaign->updated_at?->format('M d, Y H:i') ?? '—' }}</span>
                            </div>
                        </div>

                        {{-- Card footer --}}
                        <div class="px-5 py-3 border-t border-blue-100 flex justify-between items-center">
                            {{-- Delete button (draft and rejected only) --}}
                            @if(in_array($campaign->status, ['draft', 'rejected']))
                                <form method="POST"
                                      action="{{ route('campaigns.mine.destroy', $campaign) }}"
                                      onsubmit="return confirm('Delete this campaign? This cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium text-red-600 hover:bg-red-50 border border-red-200 transition">
                                        <i class="fas fa-trash mr-1.5 text-xs"></i>Delete
                                    </button>
                                </form>
                            @else
                                <div></div>{{-- spacer to keep justify-between working --}}
                            @endif

                            <div class="flex items-center gap-2">
                                @if($campaign->status === 'draft')
                                    <a href="{{ route('campaigns.wizard.step1', $campaign) }}"
                                       class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium bg-blue-600 text-white hover:bg-blue-700">
                                        <i class="fas fa-pencil mr-1.5 text-xs"></i>Continue editing
                                    </a>
                                @elseif($campaign->status === 'rejected')
                                    <a href="{{ route('campaigns.wizard.step1', $campaign) }}"
                                       class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium bg-blue-600 text-white hover:bg-blue-700">
                                        <i class="fas fa-pencil mr-1.5 text-xs"></i>Edit campaign
                                    </a>
                                @endif
                                <a href="{{ route('campaigns.mine.show', $campaign) }}"
                                   class="inline-flex items-center px-3 py-1.5 rounded-md text-sm font-medium bg-gray-700 text-white hover:bg-gray-800">
                                    <i class="fas fa-eye mr-1.5 text-xs"></i>View
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            </div>
        @endif

        <div>
            {{ $campaigns->links() }}
        </div>

    </div>
</x-layouts.admin>
