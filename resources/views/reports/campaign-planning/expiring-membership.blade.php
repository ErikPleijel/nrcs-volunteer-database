<x-layouts.admin title="Expiring Membership Campaign Planner">
    <x-slot name="pageHeader">
        <i class="fas fa-id-card mr-3"></i> Expiring Membership Campaign Planner
    </x-slot>
    <x-slot name="subHeader">
        Active &amp; dormant persons with expiring or expired memberships — contact priority view
    </x-slot>

    <div class="container mx-auto px-4 py-6">

        {{-- ── WHAT TO DO BUTTON ───────────────────────────────────────── --}}
        <div class="flex justify-center mb-4">
            <x-help-popup trigger-class="help-btn">
                <x-slot:trigger><i class="fas fa-question-circle mr-1"></i> What to do</x-slot:trigger>

                {{-- Header --}}
                <div class="-mt-8 mb-4 text-center">
                    <i class="fas fa-id-card text-3xl text-sky-500"></i>
                    <h3 class="mt-1 text-base font-semibold text-gray-900">Expiring Memberships</h3>
                </div>

                {{-- Slogan --}}
                <div class="mb-4 text-center">
                    <p class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-1">Your mission:</p>
                    <p class="-mt-2 text-2xl font-bold text-sky-600">Get them to renew!</p>
                </div>

                {{-- Accordion --}}
                <div class="max-w-3xl mx-auto">
                    <div x-data="{ open: null }" class="space-y-1 text-sm mb-4">

                        {{-- Plan Campaign --}}
                        <div class="rounded-md border border-gray-200 overflow-hidden">
                            <button type="button"
                                    @click="open = open === 'campaigns' ? null : 'campaigns'"
                                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                <span><i class="fas fa-envelope-open-text mr-2 text-indigo-400"></i>Plan Campaign</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                   :class="open === 'campaigns' ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open === 'campaigns'" x-collapse class="px-4 py-3 bg-white">
                                <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                    <li>Send a renewal reminder — one clear message with a simple next step.</li>
                                    <li>Prioritise persons who have <span class="font-semibold">not yet been contacted</span> (green column). They are the freshest targets.</li>
                                    <li>Design your message:
                                        <ul class="list-circle pl-4 mt-1 space-y-0.5">
                                            <li>For <span class="font-semibold">members</span>: remind them of the renewal deadline and instruct how to pay.</li>
                                            <li>For <span class="font-semibold">volunteers</span>: remind them to contact their branch to renew their volunteer fee.</li>
                                        </ul>
                                    </li>
                                    <li>
                                        <x-wizard-path wizard-name="Remind about expiring membership" />
                                    </li>
                                </ul>
                            </div>
                        </div>

                        {{-- Segment your audience --}}
                        <div class="rounded-md border border-gray-200 overflow-hidden">
                            <button type="button"
                                    @click="open = open === 'segments' ? null : 'segments'"
                                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                <span><i class="fas fa-layer-group mr-2 text-violet-400"></i>Segment your audience</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                   :class="open === 'segments' ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open === 'segments'" x-collapse class="px-4 py-3 bg-white space-y-3">
                                <p class="text-gray-700">
                                    If the total number of expiring memberships is manageable, one campaign may be enough.
                                    If it is large, split the audience into segments — one campaign per segment.
                                    Use the filters on this page to explore the numbers and develop a strategy.
                                </p>
                                <p class="text-gray-600 font-semibold text-xs uppercase tracking-wide">Each segment becomes one campaign</p>

                                {{-- Strategy A --}}
                                <div class="rounded border border-gray-100 bg-gray-50 p-3">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                                        <i class="fas fa-map-marker-alt mr-1 text-indigo-300"></i> Strategy A — by geography
                                    </p>
                                    <p class="text-gray-600 text-xs mb-2">Roll out division by division. Useful when local coordinators handle follow-up.</p>
                                    <div class="space-y-0.5 text-xs font-mono text-gray-700">
                                        <div class="flex gap-2"><span class="text-gray-400">1.</span> Division A — all expiring</div>
                                        <div class="flex gap-2"><span class="text-gray-400">2.</span> Division B — all expiring</div>
                                        <div class="flex gap-2"><span class="text-gray-400">3.</span> Remaining branches — clean-up</div>
                                    </div>
                                </div>

                                {{-- Strategy B --}}
                                <div class="rounded border border-gray-100 bg-gray-50 p-3">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                                        <i class="fas fa-clock mr-1 text-amber-400"></i> Strategy B — by urgency
                                    </p>
                                    <p class="text-gray-600 text-xs mb-2">Contact those closest to expiry first, then follow up with the rest.</p>
                                    <div class="space-y-0.5 text-xs font-mono text-gray-700">
                                        <div class="flex gap-2"><span class="text-gray-400">1.</span> Expiring within 14 days</div>
                                        <div class="flex gap-2"><span class="text-gray-400">2.</span> Expiring within 28 days</div>
                                        <div class="flex gap-2"><span class="text-gray-400">3.</span> Already expired — last chance</div>
                                    </div>
                                </div>

                                <p class="text-xs text-gray-500 italic">
                                    There is no single right strategy — experiment with the filters, look at the numbers, and decide what fits your situation.
                                </p>
                            </div>
                        </div>

                        {{-- Who is behind the numbers --}}
                        <div class="rounded-md border border-gray-200 overflow-hidden">
                            <button type="button"
                                    @click="open = open === 'onebyone' ? null : 'onebyone'"
                                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                <span><i class="fas fa-user mr-2 text-sky-400"></i>Who is behind the numbers?</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                   :class="open === 'onebyone' ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open === 'onebyone'" x-collapse class="px-4 py-3 bg-white">
                                <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                    <li>This planning tool shows only statistics. To see the individuals behind the numbers:</li>
                                    <li>Go to <span class="font-semibold">Persons → Show more filters</span> → set <span class="font-semibold">Payments</span> to the relevant window → click <span class="font-semibold">Filter</span></li>
                                </ul>
                            </div>
                        </div>

                    </div>{{-- end accordion --}}
                </div>{{-- end max-w-3xl --}}

            </x-help-popup>
        </div>

        {{-- ── FILTER FORM ─────────────────────────────────────────────── --}}
        <div class="filter-container">
            <div class="filter-form-content">
                <form action="{{ route('reports.campaign-planning.expiring-membership') }}" method="GET" class="filter-form">

                    @if($isDivisionLevel)
                        <input type="hidden" name="branch_id" value="{{ request('branch_id') }}">
                    @endif

                    <div class="filter-grid filter-grid-3">

                        {{-- Col 1: Person type --}}
                        <div>
                            <label for="person_type" class="filter-label">Members/Volunteers</label>
                            <select name="person_type" id="person_type"
                                    class="filter-select {{ request('person_type', '') !== '' ? 'filter-active' : '' }}">
                                <option value="" @selected(request('person_type', '') === '')>All</option>
                                <option value="member"    @selected(request('person_type') === 'member')>Members</option>
                                <option value="volunteer" @selected(request('person_type') === 'volunteer')>Volunteers</option>
                            </select>
                        </div>

                        {{-- Col 2: Time to expiry — 28 days first --}}
                        <div>
                            <label for="expiry_window" class="filter-label">Time to expiry</label>
                            <select name="expiry_window" id="expiry_window"
                                    class="filter-select {{ request('expiry_window', '28') !== '28' ? 'filter-active' : '' }}">
                                <option value="28"      @selected(request('expiry_window', '28') === '28')>Expiring within 28 days</option>
                                <option value="14"      @selected(request('expiry_window', '28') === '14')>Expiring within 14 days</option>
                                <option value="expired" @selected(request('expiry_window') === 'expired')>Expired</option>
                            </select>
                        </div>

                    </div>

                    <div class="filter-actions">
                        <div class="filter-button-group">
                            <button type="submit" class="filter-btn-primary">
                                <i class="fas fa-search mr-1"></i>Filter
                            </button>
                            @if($isDivisionLevel)
                                <a href="{{ route('reports.campaign-planning.expiring-membership', ['branch_id' => request('branch_id')]) }}"
                                   class="filter-btn-secondary filter-btn-secondary-active">
                                    <i class="fas fa-times mr-1"></i>Clear
                                </a>
                            @else
                                <a href="{{ route('reports.campaign-planning.expiring-membership') }}"
                                   class="filter-btn-secondary filter-btn-secondary-active">
                                    <i class="fas fa-times mr-1"></i>Clear
                                </a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── BREADCRUMB ───────────────────────────────────────────────── --}}
        @if($isDivisionLevel)
            <div class="mt-4 text-sm text-gray-600 flex items-center gap-1">
                <i class="fas fa-arrow-left text-xs text-gray-400"></i>
                <a href="{{ route('reports.campaign-planning.expiring-membership', $filterParams) }}"
                   class="text-indigo-600 hover:text-indigo-800 underline">All Branches</a>
                <span class="text-gray-400 mx-1">/</span>
                <span class="font-medium text-gray-800">{{ $currentBranch->name }}</span>
            </div>
        @endif

        {{-- ── TABLE ───────────────────────────────────────────────────── --}}
        <div class="bg-white rounded-lg shadow mt-4 overflow-x-auto">
            @if(empty($planningData))
                <div class="py-12 text-center text-gray-400 italic">No persons with expiring or expired memberships found for the current filters.</div>
            @else
                @php $areaLabel = $isDivisionLevel ? 'Division' : 'Branch'; @endphp
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-gray-700 min-w-[200px]">{{ $areaLabel }}</th>
                        <th class="text-center px-4 py-3 font-semibold text-gray-600">Total</th>
                        <th class="text-center px-4 py-3 font-semibold text-green-700">
                            Not yet contacted
                            <div class="text-xs font-normal text-gray-500">freshest targets</div>
                        </th>
                        <th class="text-center px-4 py-3 font-semibold text-amber-600">Contacted once</th>
                        <th class="text-center px-4 py-3 font-semibold text-red-600">
                            Contacted 2+
                            <div class="text-xs font-normal text-gray-500">de-prioritise</div>
                        </th>
                    </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    <tr class="bg-gray-50 font-semibold border-b-2 border-gray-200">
                        <td class="px-4 py-2 text-gray-900">
                            {{ $isDivisionLevel ? $currentBranch->name.' Total' : 'All Branches' }}
                        </td>
                        <td class="text-center px-4 py-2">
                            @if($summaryTotal > 0)
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                                        {{ number_format($summaryTotal) }}
                                    </span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="text-center px-4 py-2">
                            @if($summaryNotContacted > 0)
                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                        {{ number_format($summaryNotContacted) }}
                                    </span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="text-center px-4 py-2">
                            @if($summaryOnce > 0)
                                <span class="contacted-box">{{ number_format($summaryOnce) }}</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="text-center px-4 py-2">
                            @if($summaryTwoPlus > 0)
                                <span class="contacted-box contacted-box--red">{{ number_format($summaryTwoPlus) }}</span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                    </tr>
                    @foreach($planningData as $row)
                        @php
                            $rowClass = $row['highlight']
                                ? 'bg-yellow-50 border-l-4 border-yellow-400'
                                : 'hover:bg-gray-50';
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td class="px-4 py-2 font-medium text-gray-900">
                                @if($row['link'])
                                    <a href="{{ $row['link'] }}" class="text-indigo-600 hover:text-indigo-800 underline">
                                        {{ $row['label'] }}
                                    </a>
                                @else
                                    {{ $row['label'] }}
                                @endif
                            </td>
                            <td class="text-center px-4 py-2">
                                @if($row['total'] > 0)
                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                                            {{ $row['total'] }}
                                        </span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="text-center px-4 py-2">
                                @if($row['not_contacted'] > 0)
                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                            {{ $row['not_contacted'] }}
                                        </span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="text-center px-4 py-2">
                                @if($row['once'] > 0)
                                    <span class="contacted-box">{{ $row['once'] }}</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="text-center px-4 py-2">
                                @if($row['two_plus'] > 0)
                                    <span class="contacted-box contacted-box--red">{{ $row['two_plus'] }}</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>

    </div>
</x-layouts.admin>
