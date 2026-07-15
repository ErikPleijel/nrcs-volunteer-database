<x-layouts.admin title="Donation Appreciation Campaign Planner">
    <x-slot name="pageHeader">
        <i class="fas fa-hand-holding-heart mr-2 text-sky-500 mr-3 mb-6"></i> Donation Appreciation Planner
    </x-slot>


    <div class="container mx-auto px-4 mb-6">

        <div class="flex justify-center mb-6">
            <div class="max-w-xl text-center">
                <h2 class="text-xl font-semibold text-gray-900 mb-2">
                    Say thank you to your donors
                </h2>
                <p class="text-base text-gray-600 mb-3">
                    Find persons who have made cash or in-kind donations — and send them a personal message of appreciation.
                </p>
                <p class="text-base text-gray-600 mb-3">
                    <strong>Make Campaign:</strong> <br>
                    Persons → Campaign filter wizard → Appreciate donors
                </p>

                <p class="text-base text-gray-600">
                    <i class="fas fa-arrows-rotate text-gray-400"></i>
                    <strong>The cycle:</strong><br>
                    Donors give, and the <strong>bucket fills</strong>.<br>
                    Run a campaign, and the <strong>bucket empties</strong>.
                </p>
                <p class="mt-2 text-sm text-gray-600 italic flex items-center justify-center gap-1.5">
                    <i class="fas fa-triangle-exclamation text-gray-400"></i>
                    Buckets may not empty completely, due to delivery failures.
                </p>
                <p class="mt-3 text-xs text-gray-400">
                    Anonymous donations are excluded from all figures on this page.
                </p>
            </div>
        </div>

        @php
            $tabToggleBase = array_merge(request()->query(), []);
            $donationTabs = [
                'tracker' => ['label' => 'Appreciation Tracker', 'icon' => 'fa-list-check'],
            ];
        @endphp
        @if(count($donationTabs) > 1)
        <div class="flex gap-0 px-6 border-b border-gray-200 mt-2 mb-4">
            @foreach($donationTabs as $tabKey => $tabInfo)
                <a href="{{ route('reports.campaign-planning.donation-appreciation', array_merge($tabToggleBase, ['tab' => $tabKey])) }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium border border-b-0 transition-colors whitespace-nowrap
                       {{ $activeTab === $tabKey
                           ? 'bg-white border-gray-200 text-indigo-700 font-semibold rounded-t-md -mb-px'
                           : 'bg-gray-50 border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-t-md' }}">
                    <i class="fas {{ $tabInfo['icon'] }} text-xs"></i>
                    {{ $tabInfo['label'] }}
                </a>
            @endforeach
        </div>
        @endif

        @if($activeTab === 'tracker')
        {{-- ── BREADCRUMB ───────────────────────────────────────────────── --}}
        @if($isDivisionLevel)
            <div class="mb-4 text-sm text-gray-600 flex items-center gap-1">
                <i class="fas fa-arrow-left text-xs text-gray-400"></i>
                <a href="{{ route('reports.campaign-planning.donation-appreciation', $filterParams) }}"
                   class="text-indigo-600 hover:text-indigo-800 underline">All Branches</a>
                <span class="text-gray-400 mx-1">/</span>
                <span class="font-medium text-gray-800">{{ $currentBranch->name }}</span>
            </div>
        @endif

        {{-- ── TABLE ───────────────────────────────────────────────────── --}}
        <div class="bg-white rounded-lg shadow overflow-x-auto">
            @if(empty($planningData))
                <div class="py-12 text-center text-gray-400 italic">No donor data found.</div>
            @else
                @php
                    $areaLabel = $isDivisionLevel ? 'Division' : 'Branch';
                    $allDonorsSum = collect($planningData)->sum('all_donors');
                    $neverThankedSum = collect($planningData)->sum('never_thanked');
                    $donatedAgainSum = collect($planningData)->sum('donated_again');
                @endphp
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-1"></th>
                            <th class="px-4 py-1"></th>
                            <th class="text-center px-4 py-1 text-amber-500">
                                <i class="fas fa-bucket text-2xl"></i>
                            </th>
                            <th class="text-center px-4 py-1 text-green-600">
                                <i class="fas fa-bucket text-2xl"></i>
                            </th>
                        </tr>
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700 min-w-[200px]">{{ $areaLabel }}</th>
                            <th class="text-center px-4 py-3 font-semibold text-gray-600">
                                All eligible donors
                                <span class="block text-xs font-normal text-gray-600 mt-0.5 normal-case">This includes everyone who has made a donation, whether or not they received a thank-you message.</span>
                            </th>
                            <th class="text-center px-4 py-3 font-semibold text-amber-600">
                                Never thanked
                                <span class="block text-xs font-normal text-gray-600 mt-0.5 normal-case">Have donated but have never received a thank-you message.</span>
                            </th>
                            <th class="text-center px-4 py-3 font-semibold text-green-700">
                                Donated again since being thanked
                                <span class="block text-xs font-normal text-gray-600 mt-0.5 normal-case">Were thanked before &ndash; and have made a new donation since then. Give them another thank-you message. </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr class="bg-gray-100 font-semibold border-b-2 border-gray-300">
                            <td class="px-4 py-2 font-semibold text-gray-900">
                                {{ $isDivisionLevel ? 'All Divisions' : 'All Branches' }}
                            </td>
                            <td class="text-center px-4 py-2">
                                @if($allDonorsSum > 0)
                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                                        {{ $allDonorsSum }}
                                    </span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="text-center px-4 py-2">
                                @if($neverThankedSum > 0)
                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">
                                        {{ $neverThankedSum }}
                                    </span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="text-center px-4 py-2">
                                @if($donatedAgainSum > 0)
                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                        {{ $donatedAgainSum }}
                                    </span>
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
                                    @if($row['all_donors'] > 0)
                                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                                            {{ $row['all_donors'] }}
                                        </span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="text-center px-4 py-2">
                                    @if($row['never_thanked'] > 0)
                                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700">
                                            {{ $row['never_thanked'] }}
                                        </span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="text-center px-4 py-2">
                                    @if($row['donated_again'] > 0)
                                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                            {{ $row['donated_again'] }}
                                        </span>
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
        @endif

    </div>
</x-layouts.admin>
