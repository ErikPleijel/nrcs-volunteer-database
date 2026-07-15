<x-layouts.admin title="Training Statistics Report">
    <x-slot name="pageHeader">
        <i class="fas fa-graduation-cap mr-3 mb-6"></i> Training Statistics
    </x-slot>


    <div class="container mx-auto px-4 py-6">

        <div class="flex justify-center mb-4">
            <x-help-popup trigger-class="help-btn">
                <x-slot:trigger><i class="fas fa-question-circle mr-1"></i> What to do</x-slot:trigger>

                {{-- Header --}}
                <div class="-mt-8 mb-4 text-center">
                    <i class="fas fa-chart-line text-3xl text-sky-500"></i>
                    <h3 class="mt-1 text-base font-semibold text-gray-900">Training Statistics</h3>
                </div>

                {{-- Slogan --}}
                <div class="mb-4 text-center">
                    <p class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-1">Your mission:</p>
                    <p class="-mt-2 text-2xl font-bold text-sky-600">Keep everyone trained and&nbsp;certified!</p>
                </div>

                {{-- Accordion --}}
                <div class="max-w-3xl mx-auto">
                    <div x-data="{ open: null }" class="space-y-1 text-sm mb-4">

                        {{-- Training Coverage --}}
                        <div class="rounded-md border border-gray-200 overflow-hidden">
                            <button type="button"
                                    @click="open = open === 'coverage' ? null : 'coverage'"
                                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                <span><i class="fas fa-users mr-2 text-indigo-400"></i>Training Coverage</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                   :class="open === 'coverage' ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open === 'coverage'" x-collapse class="px-4 py-3 bg-white">
                                <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                    <li>See how many volunteers (or, with the "All" toggle, volunteers and members) have completed each training type — and how many haven't.</li>
                                    <li>Click a training type to drill down by branch → division → Red Cross Unit, and find exactly where the gaps are.</li>
                                    <li>Use this to plan targeted training sessions in the areas with  low coverage.</li>
                                    <li>First Aid trainings are grouped separately from other trainings, since they're the most safety-critical.</li>
                                    <li>
                                        <x-wizard-path wizard-name="Invite to upcoming training" />
                                    </li>
                                </ul>
                            </div>
                        </div>

                        {{-- Expiry Timeline --}}
                        <div class="rounded-md border border-gray-200 overflow-hidden">
                            <button type="button"
                                    @click="open = open === 'expiry' ? null : 'expiry'"
                                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                <span><i class="fas fa-clock mr-2 text-orange-400"></i>Expiry Timeline</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                   :class="open === 'expiry' ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open === 'expiry'" x-collapse class="px-4 py-3 bg-white">
                                <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                    <li>See how many certifications are expiring soon (or have already expired) for each training type, month by month.</li>
                                    <li>Trainings expiring in the next 1–2 months need urgent attention — plan refresher sessions before the deadline.</li>
                                    <li>Click a training type to drill down by branch → division → Red Cross Unit and target the refresher campaign geographically.</li>
                                    <li>Trainings that already expired months ago are shown in grey — a backlog worth clearing.</li>
                                    <li>
                                        <x-wizard-path wizard-name="Remind about expiring training certification" />
                                    </li>
                                    <li>
                                        <span class="inline-flex items-center gap-1 font-semibold text-amber-700">
                                            &#x1F4A1;
                                            Tip:
                                        </span>
                                        For a more detailed picture, go to <strong>Donation Records</strong>, filter by training type and expiry date.
                                    </li>
                                </ul>
                            </div>
                        </div>

                        {{-- First Aid Staleness --}}
                        <div class="rounded-md border border-gray-200 overflow-hidden">
                            <button type="button"
                                    @click="open = open === 'staleness' ? null : 'staleness'"
                                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                <span><i class="fas fa-kit-medical mr-2 text-red-400"></i>First Aid Staleness</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                   :class="open === 'staleness' ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open === 'staleness'" x-collapse class="px-4 py-3 bg-white">
                                <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                    <li>This tab calculates the time since each person's most recent First Aid training of any kind — not a specific course, but whichever First Aid training they last completed.</li>
                                    <li>See how long it's been since each person's last First Aid training, grouped into time bands (12–23 months, 24–35 months, and so on).</li>
                                    <li>The further right the count, the staler the skill — someone at "≥ 60m" hasn't had a refresher in five years or more.</li>
                                    <li>Use the Volunteers/All toggle to decide whether you're tracking active volunteers only, or the whole membership base.</li>
                                    <li>Drill down by branch → division → Red Cross Unit to find where refresher training is most overdue.</li>
                                    <li>
                                        <x-wizard-path wizard-name="Refresh first-aid training" />
                                    </li>
                                </ul>
                            </div>
                        </div>

                        {{-- Certificates --}}
                        <div class="rounded-md border border-gray-200 overflow-hidden">
                            <button type="button"
                                    @click="open = open === 'certificates' ? null : 'certificates'"
                                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                <span><i class="fas fa-certificate mr-2 text-purple-400"></i>Certificates</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                   :class="open === 'certificates' ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open === 'certificates'" x-collapse class="px-4 py-3 bg-white">
                                <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                    <li>See how many attendance and competence certificates have been printed for each training type — and how many trainings still have none.</li>
                                    <li>Use the Date From / Date To fields (top right of this tab) to narrow certificate counts to a specific period, such as a reporting quarter.</li>
                                    <li>Click a training type to drill down by branch → division → Red Cross Unit and follow up where certificates are still missing.</li>
                                    <li>A high "No Certificate" count usually means the training happened but the paperwork hasn't caught up yet — a good administrative follow-up task.</li>

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
                <form id="training-stats-filter-form" action="{{ route('reports.trainings.stats') }}" method="GET" class="filter-form">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">

                    <div class="filter-grid filter-grid-3">

                        <div>
                            <label for="branch_id" class="filter-label-small">Branch</label>
                            <select name="branch_id" id="branch_id"
                                    class="filter-select-small disabled:bg-gray-200 disabled:opacity-75 {{ $accessLevel === 'national' && request('branch_id') ? 'filter-active' : '' }}"
                                    @if($accessLevel !== 'national') disabled @endif>
                                @if($accessLevel === 'national')
                                    <option value="">All Branches</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                @else
                                    @php $userBranch = $branches->firstWhere('id', $userBranchId); @endphp
                                    @if($userBranch)
                                        <option value="{{ $userBranch->id }}" selected>{{ $userBranch->name }}</option>
                                    @endif
                                @endif
                            </select>
                            @if($accessLevel !== 'national')
                                <input type="hidden" name="branch_id" value="{{ $userBranchId }}">
                            @endif
                        </div>

                        <div>
                            <label for="division_id" class="filter-label-small">Division</label>
                            <select name="division_id" id="division_id"
                                    class="filter-select-small disabled:bg-gray-200 disabled:opacity-75 {{ in_array($accessLevel, ['national', 'branch']) && request('division_id') ? 'filter-active' : '' }}"
                                    @if(!in_array($accessLevel, ['national', 'branch'])) disabled
                                    @elseif($accessLevel === 'national' && !request('branch_id')) disabled @endif>
                                @if(in_array($accessLevel, ['national', 'branch']))
                                    <option value="">All Divisions</option>
                                    @foreach($divisions as $division)
                                        <option value="{{ $division->id }}" @selected(request('division_id') == $division->id)>{{ $division->name }}</option>
                                    @endforeach
                                @else
                                    @php $userDiv = $divisions->firstWhere('id', $userDivisionId); @endphp
                                    @if($userDiv)
                                        <option value="{{ $userDiv->id }}" selected>{{ $userDiv->name }}</option>
                                    @endif
                                @endif
                            </select>
                            @if(!in_array($accessLevel, ['national', 'branch']))
                                <input type="hidden" name="division_id" value="{{ $userDivisionId }}">
                            @endif
                        </div>

                        <div>
                            <label for="red_cross_unit_id" class="filter-label-small">Red Cross Unit</label>
                            <select name="red_cross_unit_id" id="red_cross_unit_id"
                                    class="filter-select-small {{ request('red_cross_unit_id') ? 'filter-active' : '' }}"
                                    @if(!request('division_id') && !$userDivisionId) disabled @endif>
                                <option value="">All Units</option>
                                @foreach($redCrossUnits as $unit)
                                    <option value="{{ $unit->id }}" @selected(request('red_cross_unit_id') == $unit->id)>{{ $unit->name }}</option>
                                @endforeach
                            </select>
                        </div>

                    </div>

                    <div class="filter-actions">
                        <div class="filter-button-group">
                            <button type="submit" class="filter-btn-primary">
                                <i class="fas fa-search mr-1"></i>Filter
                            </button>
                            <a href="{{ route('reports.trainings.stats', ['tab' => $activeTab]) }}"
                               class="filter-btn-secondary filter-btn-secondary-active">
                                <i class="fas fa-times mr-1"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── TABS ─────────────────────────────────────────────────────── --}}
        @php
            $tabParams = request()->except('tab');
        @endphp
        <div class="flex gap-2 mt-6 border-b border-gray-200">
            @foreach([
                'coverage'     => ['label' => 'Training Coverage',  'icon' => 'fa-users'],
                'expiry'       => ['label' => 'Expiry Timeline',     'icon' => 'fa-clock'],
                'staleness'    => ['label' => 'First Aid Staleness', 'icon' => 'fa-kit-medical'],
                'certificates' => ['label' => 'Certificates',        'icon' => 'fa-certificate'],
            ] as $tabKey => $tabDef)
                <a href="{{ route('reports.trainings.stats', array_merge($tabParams, ['tab' => $tabKey])) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-t-md border border-b-0 transition-colors
                       {{ $activeTab === $tabKey
                           ? 'bg-white border-gray-200 text-indigo-700 font-semibold'
                           : 'bg-gray-50 border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-100' }}">
                    <i class="fas {{ $tabDef['icon'] }} text-xs"></i>
                    {{ $tabDef['label'] }}
                </a>
            @endforeach
        </div>

        {{-- ── POPULATION FILTER (Coverage / Staleness / Expiry only) ─────── --}}
        @if(in_array($activeTab, ['coverage', 'staleness', 'expiry']))
            <div class="flex justify-end mt-3" x-data="{ population: '{{ request('population', 'volunteers') }}' }">
                <div class="text-right">
                    <input type="hidden" name="population" form="training-stats-filter-form" :value="population">
                    <div class="flex rounded-md overflow-hidden border border-gray-200 divide-x divide-gray-200">
                        <button type="button"
                                @click="population = 'volunteers'; $nextTick(() => document.getElementById('training-stats-filter-form').submit())"
                                :class="population === 'volunteers' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                                class="px-3 py-1.5 text-sm font-medium transition-colors">
                            Volunteers
                        </button>
                        <button type="button"
                                @click="population = 'all'; $nextTick(() => document.getElementById('training-stats-filter-form').submit())"
                                :class="population === 'all' ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                                class="px-3 py-1.5 text-sm font-medium transition-colors">
                            All
                        </button>
                    </div>
                    @if(request('population', 'volunteers') === 'all')
                        <p class="text-xs text-gray-500 mt-1">Includes both volunteers and members, active and dormant.</p>
                    @endif
                </div>
            </div>
        @endif

        {{-- ── CERTIFICATE DATE RANGE (Certificates only) ──────────────────── --}}
        @if($activeTab === 'certificates')
            <div class="flex justify-end mt-3">
                <div class="flex items-end gap-2">
                    <div>
                        <label for="cert_date_from" class="block text-xs font-medium text-gray-700 mb-1">Date From</label>
                        <input type="date" name="cert_date_from" id="cert_date_from" form="training-stats-filter-form"
                               value="{{ $certDateFrom }}"
                               class="text-xs px-2 py-1 w-32 border border-gray-300 rounded shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 {{ $certDateFrom ? 'filter-active' : '' }}">
                    </div>
                    <div>
                        <label for="cert_date_to" class="block text-xs font-medium text-gray-700 mb-1">Date To</label>
                        <input type="date" name="cert_date_to" id="cert_date_to" form="training-stats-filter-form"
                               value="{{ $certDateTo }}"
                               class="text-xs px-2 py-1 w-32 border border-gray-300 rounded shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 {{ $certDateTo ? 'filter-active' : '' }}">
                    </div>
                    <button type="submit" form="training-stats-filter-form"
                            class="text-xs px-3 py-1.5 rounded-md bg-indigo-600 text-white hover:bg-indigo-700 transition-colors">
                        Apply
                    </button>
                </div>
            </div>
        @endif

        {{-- Area mode banner --}}
        @if($areaMode && $activeTab !== 'staleness')
            <div class="px-4 py-2 bg-indigo-50 border border-indigo-200 border-t-0 text-sm text-indigo-700 flex items-center gap-2">
                <i class="fas fa-filter text-indigo-400"></i>
                Showing results for training type: <strong>{{ $selectedType->name }}</strong> —
                rows show {{ $accessLevel === 'national' ? 'branches' : ($accessLevel === 'branch' ? 'divisions' : 'RC units') }}.
                <a href="{{ route('reports.trainings.stats', array_merge(request()->except('training_type_id'), ['tab' => $activeTab])) }}"
                   class="ml-2 underline text-indigo-600 hover:text-indigo-800">Clear type filter</a>
            </div>
        @endif

        {{-- ── VIEW 1: EXPIRY TIMELINE ──────────────────────────────────── --}}
        @if($activeTab === 'expiry')
            @if($areaMode)
                @php
                    $expiryDrillCrumbs = [];

                    if ($accessLevel === 'national' && $drillLevel === 'branch') {
                        $expiryDrillCrumbs[] = [
                            'label' => 'National',
                            'href' => null,
                            'badge' => null,
                        ];
                    }

                    $expiryDrillCrumbs[] = [
                        'label' => 'Training Types',
                        'href' => route('reports.trainings.stats', array_merge(request()->except('training_type_id'), ['tab' => 'expiry'])),
                        'badge' => null,
                    ];

                    if ($currentBranch && in_array($drillLevel, ['division', 'unit'])) {
                        $expiryBranchHref = $accessLevel === 'national'
                            ? route('reports.trainings.stats', array_merge(request()->except(['division_id', 'red_cross_unit_id']), ['tab' => 'expiry']))
                            : null;
                        $expiryDrillCrumbs[] = [
                            'label' => $currentBranch->name,
                            'href' => $expiryBranchHref,
                            'badge' => $accessLevel === 'branch' && (int) auth()->user()->branch_id === (int) $currentBranch->id ? 'your branch' : null,
                        ];
                    }

                    if ($currentDivision && $drillLevel === 'unit') {
                        $expiryDivisionHref = in_array($accessLevel, ['national', 'branch'])
                            ? route('reports.trainings.stats', array_merge(request()->except('red_cross_unit_id'), ['tab' => 'expiry']))
                            : null;
                        $expiryDrillCrumbs[] = [
                            'label' => $currentDivision->name,
                            'href' => $expiryDivisionHref,
                            'badge' => $accessLevel === 'division' && (int) auth()->user()->division_id === (int) $currentDivision->id ? 'your division' : null,
                        ];
                    }
                @endphp
                <x-reports.drill-breadcrumb :crumbs="$expiryDrillCrumbs" />
            @endif
            <div class="bg-white rounded-b-lg rounded-tr-lg shadow {{ $areaMode ? 'mt-4' : 'mt-0' }} overflow-x-auto">
                @if(empty($expiryData) && $areaMode && $areaRows->isEmpty())
                    @php
                        $expiryParentNoun = $expiryAreaHeader === 'RC Unit' ? 'division' : ($expiryAreaHeader === 'Division' ? 'branch' : 'area');
                        $expiryChildNounPlural = match ($expiryAreaHeader) {
                            'Branch' => 'branches',
                            'Division' => 'divisions',
                            default => 'RC units',
                        };
                    @endphp
                    <div class="py-12 text-center text-gray-400 italic">This {{ $expiryParentNoun }} has no {{ $expiryChildNounPlural }} to show.</div>
                @elseif(empty($expiryData))
                    <div class="py-12 text-center text-gray-400 italic">No expiry data found for the current filters.</div>
                @else
                    @php
                        $bucketKeys = ['past_3','past_2','past_1','next_1','next_2','next_3','next_4','next_5','next_6','future'];
                        $bucketLabels = [
                            'past_3' => '> 3mo ago',
                            'past_2' => '2 mo ago',
                            'past_1' => '1 mo ago',
                            'next_1' => 'In 1 mo',
                            'next_2' => 'In 2 mo',
                            'next_3' => 'In 3 mo',
                            'next_4' => 'In 4 mo',
                            'next_5' => 'In 5 mo',
                            'next_6' => 'In 6 mo',
                            'future' => '> 6 mo',
                        ];
                        $isPast = fn($k) => in_array($k, ['past_3','past_2','past_1']);
                    @endphp
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="text-left px-4 py-3 font-semibold text-gray-700 sticky left-0 bg-gray-50 z-10 min-w-[180px]">
                            {{ $expiryAreaHeader }}
                        </th>
                                @foreach($bucketKeys as $key)
                                    <th class="text-center px-2 py-3 font-semibold whitespace-nowrap
                                        {{ $isPast($key) ? 'text-gray-400' : ($key === 'next_1' || $key === 'next_2' ? 'text-red-600' : ($key === 'next_3' || $key === 'next_4' ? 'text-orange-500' : 'text-blue-600')) }}">
                                        {{ $bucketLabels[$key] }}
                                    </th>
                                @endforeach
                                <th class="text-center px-3 py-3 font-semibold text-gray-700">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($expiryData as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 font-medium text-gray-900 sticky left-0 bg-white">
                                        @if($areaMode)
                                            @if($drillLevel !== 'unit')
                                                @php $expiryRowField = $drillLevel === 'branch' ? 'branch_id' : 'division_id'; @endphp
                                                <a href="{{ route('reports.trainings.stats', array_merge(request()->query(), [$expiryRowField => $row['id'], 'tab' => 'expiry'])) }}"
                                                   class="text-indigo-600 hover:text-indigo-800 hover:underline">{{ $row['label'] }}</a>
                                            @else
                                                {{ $row['label'] }}
                                            @endif
                                        @else
                                            <a href="{{ route('reports.trainings.stats', array_merge(request()->query(), ['training_type_id' => $row['id'], 'tab' => 'expiry'])) }}"
                                               class="text-indigo-600 hover:text-indigo-800 hover:underline">{{ $row['label'] }}</a>
                                        @endif
                                    </td>
                                    @foreach($bucketKeys as $key)
                                        @php $count = $row['buckets'][$key]; @endphp
                                        <td class="text-center px-2 py-2">
                                            @if($count > 0)
                                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold
                                                    {{ $isPast($key)
                                                        ? 'bg-gray-100 text-gray-500'
                                                        : ($key === 'next_1' || $key === 'next_2'
                                                            ? 'bg-red-100 text-red-700'
                                                            : ($key === 'next_3' || $key === 'next_4'
                                                                ? 'bg-orange-100 text-orange-700'
                                                                : 'bg-blue-50 text-blue-700')) }}">
                                                    {{ $count }}
                                                </span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="text-center px-3 py-2 font-bold text-gray-700">{{ $row['total'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif

        {{-- ── VIEW 2: COVERAGE ─────────────────────────────────────────── --}}
        @if($activeTab === 'coverage')
            @if($areaMode)
                {{-- Area mode: single table, rows = branches/divisions/units, drill-clickable --}}
                @php
                    $coverageDrillCrumbs = [];

                    if ($accessLevel === 'national' && $drillLevel === 'branch') {
                        $coverageDrillCrumbs[] = [
                            'label' => 'National',
                            'href' => null,
                            'badge' => null,
                        ];
                    }

                    $coverageDrillCrumbs[] = [
                        'label' => 'Training Types',
                        'href' => route('reports.trainings.stats', array_merge(request()->except('training_type_id'), ['tab' => 'coverage'])),
                        'badge' => null,
                    ];

                    if ($currentBranch && in_array($drillLevel, ['division', 'unit'])) {
                        $branchHref = $accessLevel === 'national'
                            ? route('reports.trainings.stats', array_merge(request()->except(['division_id', 'red_cross_unit_id']), ['tab' => 'coverage']))
                            : null;
                        $coverageDrillCrumbs[] = [
                            'label' => $currentBranch->name,
                            'href' => $branchHref,
                            'badge' => $accessLevel === 'branch' && (int) auth()->user()->branch_id === (int) $currentBranch->id ? 'your branch' : null,
                        ];
                    }

                    if ($currentDivision && $drillLevel === 'unit') {
                        $divisionHref = in_array($accessLevel, ['national', 'branch'])
                            ? route('reports.trainings.stats', array_merge(request()->except('red_cross_unit_id'), ['tab' => 'coverage']))
                            : null;
                        $coverageDrillCrumbs[] = [
                            'label' => $currentDivision->name,
                            'href' => $divisionHref,
                            'badge' => $accessLevel === 'division' && (int) auth()->user()->division_id === (int) $currentDivision->id ? 'your division' : null,
                        ];
                    }

                    $coverageAreaHeader = $drillLevel === 'branch' ? 'Branch' : ($drillLevel === 'division' ? 'Division' : 'RC Unit');
                @endphp
                <x-reports.drill-breadcrumb :crumbs="$coverageDrillCrumbs" />

                <div class="bg-white rounded-b-lg rounded-tr-lg shadow mt-4 overflow-x-auto">

                    @if(empty($coverageData) && $areaMode && $areaRows->isEmpty())
                        @php
                            $coverageParentNoun = $coverageAreaHeader === 'RC Unit' ? 'division' : ($coverageAreaHeader === 'Division' ? 'branch' : 'area');
                            $coverageChildNounPlural = match ($coverageAreaHeader) {
                                'Branch' => 'branches',
                                'Division' => 'divisions',
                                default => 'RC units',
                            };
                        @endphp
                        <div class="py-12 text-center text-gray-400 italic">This {{ $coverageParentNoun }} has no {{ $coverageChildNounPlural }} to show.</div>
                    @elseif(empty($coverageData))
                        <div class="py-12 text-center text-gray-400 italic">No coverage data found for the current filters.</div>
                    @else
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="text-left px-4 py-3 font-semibold text-gray-700 min-w-[180px]">
                                        {{ $coverageAreaHeader }}
                                    </th>
                                    <th class="text-center px-4 py-3 font-semibold text-green-700">
                                        <i class="fas fa-circle-check mr-1"></i>Trained<br>
                                    </th>
                                    <th class="text-center px-4 py-3 font-semibold text-red-600">
                                        <i class="fas fa-circle-xmark mr-1"></i>Not Trained<br>
                                    </th>
                                    <th class="text-center px-4 py-3 font-semibold text-gray-500">Coverage %</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($coverageData as $i => $row)
                                    @php
                                        $pct = isset($row['total']) && $row['total'] > 0 ? round(($row['trained'] / $row['total']) * 100) : 0;
                                        $pctClass = $pct >= 70 ? 'text-green-600' : ($pct >= 40 ? 'text-orange-500' : 'text-red-600');
                                        $area = $areaRows[$i] ?? null;
                                    @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2 font-medium text-gray-900">
                                            @if($area && ($area['scope'] ?? null) !== 'unit')
                                                <a href="{{ route('reports.trainings.stats', array_merge(request()->query(), [$area['field'] => $area['id'], 'tab' => 'coverage'])) }}"
                                                   class="text-indigo-600 hover:text-indigo-800 hover:underline">{{ $row['label'] }}</a>
                                            @else
                                                {{ $row['label'] }}
                                            @endif
                                        </td>
                                        <td class="text-center px-4 py-2">
                                            @if($row['trained'] > 0)
                                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                                    {{ $row['trained'] }}
                                                </span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center px-4 py-2">
                                            @if($row['not_trained'] > 0)
                                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                                                    {{ $row['not_trained'] }}
                                                </span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center px-4 py-2 font-bold {{ $pctClass }}">{{ $pct }}%</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @else
                {{-- Default view: split into First Aid and Other Trainings sections --}}
                @php
                    $coverageScopeLabel = null;
                    if ($accessLevel === 'national') {
                        $coverageScopeLabel = 'National';
                    } elseif ($accessLevel === 'branch' && $currentBranch) {
                        $coverageScopeLabel = $currentBranch->name;
                    } elseif ($accessLevel === 'division' && $currentBranch && $currentDivision) {
                        $coverageScopeLabel = $currentBranch->name.' / '.$currentDivision->name;
                    }
                @endphp
                @if($coverageScopeLabel)
                    <x-reports.drill-breadcrumb :crumbs="[['label' => $coverageScopeLabel, 'href' => null, 'badge' => null]]" />
                @endif
                @foreach([
                    ['title' => 'First Aid Trainings', 'data' => $coverageDataFirstAid, 'first' => true],
                    ['title' => 'Other Trainings',     'data' => $coverageDataOther,   'first' => false],
                ] as $section)
                    <div class="bg-white shadow overflow-x-auto {{ $section['first'] ? 'rounded-b-lg rounded-tr-lg mt-4' : 'rounded-lg mt-6' }}">
                        <h3 class="px-4 pt-4 pb-2 text-base font-semibold text-gray-800">{{ $section['title'] }}</h3>
                        @if(empty($section['data']))
                            <div class="py-8 text-center text-gray-400 italic">No coverage data found for the current filters.</div>
                        @else
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 border-b">
                                    <tr>
                                        <th class="text-left px-4 py-3 font-semibold text-gray-700 min-w-[180px]">Training Type</th>
                                        <th class="text-center px-4 py-3 font-semibold text-green-700">
                                            <i class="fas fa-circle-check mr-1"></i>Trained<br>
                                        </th>
                                        <th class="text-center px-4 py-3 font-semibold text-red-600">
                                            <i class="fas fa-circle-xmark mr-1"></i>Not Trained<br>
                                        </th>
                                        <th class="text-center px-4 py-3 font-semibold text-gray-500">Coverage %</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($section['data'] as $row)
                                        @php
                                            $pct = isset($row['total']) && $row['total'] > 0 ? round(($row['trained'] / $row['total']) * 100) : 0;
                                            $pctClass = $pct >= 70 ? 'text-green-600' : ($pct >= 40 ? 'text-orange-500' : 'text-red-600');
                                            $isSummary = $row['is_summary'] ?? false;
                                            $rowType = $isSummary ? null : $trainingTypes->firstWhere('name', $row['label']);
                                        @endphp
                                        <tr class="{{ $isSummary ? 'bg-blue-50 font-semibold' : 'hover:bg-gray-50' }}">
                                            <td class="px-4 py-2 {{ $isSummary ? 'font-semibold text-blue-900' : 'font-medium text-gray-900' }}">
                                                @if($isSummary)<i class="fas fa-kit-medical text-blue-500 mr-1"></i>@endif
                                                @if($rowType)
                                                    <a href="{{ route('reports.trainings.stats', array_merge(request()->query(), ['training_type_id' => $rowType->id, 'tab' => 'coverage'])) }}"
                                                       class="text-indigo-600 hover:text-indigo-800 hover:underline">{{ $row['label'] }}</a>
                                                @else
                                                    {{ $row['label'] }}
                                                @endif
                                            </td>
                                            <td class="text-center px-4 py-2">
                                                @if($row['trained'] > 0)
                                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                                        {{ $row['trained'] }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-300">—</span>
                                                @endif
                                            </td>
                                            <td class="text-center px-4 py-2">
                                                @if($row['not_trained'] > 0)
                                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-700">
                                                        {{ $row['not_trained'] }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-300">—</span>
                                                @endif
                                            </td>
                                            <td class="text-center px-4 py-2 font-bold {{ $pctClass }}">{{ $pct }}%</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                @endforeach
            @endif
        @endif

        {{-- ── VIEW 3: CERTIFICATES ─────────────────────────────────────── --}}
        @if($activeTab === 'certificates')
            @if($areaMode)
                @php
                    $certDrillCrumbs = [];

                    if ($accessLevel === 'national' && $drillLevel === 'branch') {
                        $certDrillCrumbs[] = [
                            'label' => 'National',
                            'href' => null,
                            'badge' => null,
                        ];
                    }

                    $certDrillCrumbs[] = [
                        'label' => 'Training Types',
                        'href' => route('reports.trainings.stats', array_merge(request()->except('training_type_id'), ['tab' => 'certificates'])),
                        'badge' => null,
                    ];

                    if ($currentBranch && in_array($drillLevel, ['division', 'unit'])) {
                        $certBranchHref = $accessLevel === 'national'
                            ? route('reports.trainings.stats', array_merge(request()->except(['division_id', 'red_cross_unit_id']), ['tab' => 'certificates']))
                            : null;
                        $certDrillCrumbs[] = [
                            'label' => $currentBranch->name,
                            'href' => $certBranchHref,
                            'badge' => $accessLevel === 'branch' && (int) auth()->user()->branch_id === (int) $currentBranch->id ? 'your branch' : null,
                        ];
                    }

                    if ($currentDivision && $drillLevel === 'unit') {
                        $certDivisionHref = in_array($accessLevel, ['national', 'branch'])
                            ? route('reports.trainings.stats', array_merge(request()->except('red_cross_unit_id'), ['tab' => 'certificates']))
                            : null;
                        $certDrillCrumbs[] = [
                            'label' => $currentDivision->name,
                            'href' => $certDivisionHref,
                            'badge' => $accessLevel === 'division' && (int) auth()->user()->division_id === (int) $currentDivision->id ? 'your division' : null,
                        ];
                    }
                @endphp
                <x-reports.drill-breadcrumb :crumbs="$certDrillCrumbs" />

                <div class="bg-white rounded-b-lg rounded-tr-lg shadow mt-4 overflow-x-auto">
                    @if(empty($certificateData) && $areaRows->isEmpty())
                        @php
                            $certParentNoun = $certAreaHeader === 'RC Unit' ? 'division' : ($certAreaHeader === 'Division' ? 'branch' : 'area');
                            $certChildNounPlural = match ($certAreaHeader) {
                                'Branch' => 'branches',
                                'Division' => 'divisions',
                                default => 'RC units',
                            };
                        @endphp
                        <div class="py-12 text-center text-gray-400 italic">This {{ $certParentNoun }} has no {{ $certChildNounPlural }} to show.</div>
                    @elseif(empty($certificateData))
                        <div class="py-12 text-center text-gray-400 italic">No certificate data found for the current filters.</div>
                    @else
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="text-left px-4 py-3 font-semibold text-gray-700 min-w-[180px]">
                                        {{ $certAreaHeader }}
                                    </th>
                                    <th class="text-center px-4 py-3 font-semibold text-blue-700">
                                        <i class="fas fa-certificate mr-1"></i>Attendance Cert<br>
                                        <span class="text-xs font-normal text-gray-500">printed</span>
                                    </th>
                                    <th class="text-center px-4 py-3 font-semibold text-purple-700">
                                        <i class="fas fa-certificate mr-1"></i>Competence Cert<br>
                                        <span class="text-xs font-normal text-gray-500">printed</span>
                                    </th>
                                    <th class="text-center px-4 py-3 font-semibold text-gray-500">
                                        <i class="fas fa-circle-minus mr-1"></i>No Certificate<br>
                                        <span class="text-xs font-normal text-gray-500">printed yet</span>
                                    </th>
                                    <th class="text-center px-4 py-3 font-semibold text-gray-700">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach($certificateData as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2 font-medium text-gray-900">
                                            @if($drillLevel !== 'unit')
                                                @php $certRowField = $drillLevel === 'branch' ? 'branch_id' : 'division_id'; @endphp
                                                <a href="{{ route('reports.trainings.stats', array_merge(request()->query(), [$certRowField => $row['id'], 'tab' => 'certificates'])) }}"
                                                   class="text-indigo-600 hover:text-indigo-800 hover:underline">{{ $row['label'] }}</a>
                                            @else
                                                {{ $row['label'] }}
                                            @endif
                                        </td>
                                        <td class="text-center px-4 py-2">
                                            @if($row['attendance_printed'] > 0)
                                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">{{ $row['attendance_printed'] }}</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center px-4 py-2">
                                            @if($row['competence_printed'] > 0)
                                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">{{ $row['competence_printed'] }}</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center px-4 py-2">
                                            @if($row['no_certificate'] > 0)
                                                <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">{{ $row['no_certificate'] }}</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="text-center px-4 py-2 font-bold text-gray-700">{{ $row['total'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @else
                {{-- Default view: split into First Aid and Other Trainings sections --}}
                @php
                    $certScopeLabel = null;
                    if ($accessLevel === 'national') {
                        $certScopeLabel = 'National';
                    } elseif ($accessLevel === 'branch' && $currentBranch) {
                        $certScopeLabel = $currentBranch->name;
                    } elseif ($accessLevel === 'division' && $currentBranch && $currentDivision) {
                        $certScopeLabel = $currentBranch->name.' / '.$currentDivision->name;
                    }
                @endphp
                @if($certScopeLabel)
                    <x-reports.drill-breadcrumb :crumbs="[['label' => $certScopeLabel, 'href' => null, 'badge' => null]]" />
                @endif
                @foreach([
                    ['title' => 'First Aid Trainings', 'data' => $certificateDataFirstAid, 'first' => true],
                    ['title' => 'Other Trainings',     'data' => $certificateDataOther,   'first' => false],
                ] as $section)
                    <div class="bg-white shadow overflow-x-auto {{ $section['first'] ? 'rounded-b-lg rounded-tr-lg mt-4' : 'rounded-lg mt-6' }}">
                        <h3 class="px-4 pt-4 pb-2 text-base font-semibold text-gray-800">{{ $section['title'] }}</h3>
                        @if(empty($section['data']))
                            <div class="py-8 text-center text-gray-400 italic">No certificate data found for the current filters.</div>
                        @else
                            <table class="min-w-full text-sm">
                                <thead class="bg-gray-50 border-b">
                                    <tr>
                                        <th class="text-left px-4 py-3 font-semibold text-gray-700 min-w-[180px]">Training Type</th>
                                        <th class="text-center px-4 py-3 font-semibold text-blue-700">
                                            <i class="fas fa-certificate mr-1"></i>Attendance Cert<br>
                                            <span class="text-xs font-normal text-gray-500">printed</span>
                                        </th>
                                        <th class="text-center px-4 py-3 font-semibold text-purple-700">
                                            <i class="fas fa-certificate mr-1"></i>Competence Cert<br>
                                            <span class="text-xs font-normal text-gray-500">printed</span>
                                        </th>
                                        <th class="text-center px-4 py-3 font-semibold text-gray-500">
                                            <i class="fas fa-circle-minus mr-1"></i>No Certificate<br>
                                            <span class="text-xs font-normal text-gray-500">printed yet</span>
                                        </th>
                                        <th class="text-center px-4 py-3 font-semibold text-gray-700">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($section['data'] as $row)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 font-medium text-gray-900">
                                                <a href="{{ route('reports.trainings.stats', array_merge(request()->query(), ['training_type_id' => $row['id'], 'tab' => 'certificates'])) }}"
                                                   class="text-indigo-600 hover:text-indigo-800 hover:underline">{{ $row['label'] }}</a>
                                            </td>
                                            <td class="text-center px-4 py-2">
                                                @if($row['attendance_printed'] > 0)
                                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">{{ $row['attendance_printed'] }}</span>
                                                @else
                                                    <span class="text-gray-300">—</span>
                                                @endif
                                            </td>
                                            <td class="text-center px-4 py-2">
                                                @if($row['competence_printed'] > 0)
                                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">{{ $row['competence_printed'] }}</span>
                                                @else
                                                    <span class="text-gray-300">—</span>
                                                @endif
                                            </td>
                                            <td class="text-center px-4 py-2">
                                                @if($row['no_certificate'] > 0)
                                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">{{ $row['no_certificate'] }}</span>
                                                @else
                                                    <span class="text-gray-300">—</span>
                                                @endif
                                            </td>
                                            <td class="text-center px-4 py-2 font-bold text-gray-700">{{ $row['total'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                @endforeach
            @endif
        @endif

        {{-- ── VIEW 5: FIRST AID STALENESS ───────────────────────────────── --}}
        @if($activeTab === 'staleness')
            @php
                $stalenessDrillCrumbs = [];

                if ($accessLevel === 'national') {
                    $stalenessDrillCrumbs[] = [
                        'label' => 'National',
                        'href' => route('reports.trainings.stats', array_merge(request()->except(['branch_id', 'division_id', 'red_cross_unit_id']), ['tab' => 'staleness'])),
                        'badge' => null,
                    ];
                }

                if ($currentBranch && (in_array($stalenessAreaHeader, ['Division', 'RC Unit']) || in_array($accessLevel, ['branch', 'division']))) {
                    $stalenessBranchHref = $accessLevel === 'national'
                        ? route('reports.trainings.stats', array_merge(request()->except(['division_id', 'red_cross_unit_id']), ['tab' => 'staleness']))
                        : null;
                    $stalenessDrillCrumbs[] = [
                        'label' => $currentBranch->name,
                        'href' => $stalenessBranchHref,
                        'badge' => $accessLevel === 'branch' && (int) auth()->user()->branch_id === (int) $currentBranch->id ? 'your branch' : null,
                    ];
                }

                if ($currentDivision && $stalenessAreaHeader === 'RC Unit') {
                    $stalenessDivisionHref = in_array($accessLevel, ['national', 'branch'])
                        ? route('reports.trainings.stats', array_merge(request()->except('red_cross_unit_id'), ['tab' => 'staleness']))
                        : null;
                    $stalenessDrillCrumbs[] = [
                        'label' => $currentDivision->name,
                        'href' => $stalenessDivisionHref,
                        'badge' => $accessLevel === 'division' && (int) auth()->user()->division_id === (int) $currentDivision->id ? 'your division' : null,
                    ];
                }
            @endphp
            <x-reports.drill-breadcrumb :crumbs="$stalenessDrillCrumbs" />

            <div class="bg-white rounded-b-lg rounded-tr-lg shadow mt-4 overflow-x-auto">

                @if(empty($stalenessData))
                    <div class="py-12 text-center text-gray-400 italic">No data found for the current filters.</div>
                @else
                    @php
                        $stKeys   = ['b12','b24','b36','b48','ge60'];
                        $stLabels = ['b12'=>'12–23m','b24'=>'24–35m','b36'=>'36–47m','b48'=>'48–59m','ge60'=>'≥ 60m'];
                        $stClass  = [
                            'b12'=>'bg-green-50 text-green-700',
                            'b24'=>'bg-blue-50 text-blue-700','b36'=>'bg-orange-100 text-orange-700',
                            'b48'=>'bg-orange-100 text-orange-700','ge60'=>'bg-red-100 text-red-700',
                        ];
                    @endphp
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="text-left px-4 py-2 font-semibold text-gray-700 sticky left-0 bg-gray-50 z-10 min-w-[160px] align-bottom">{{ $stalenessAreaHeader }}</th>
                                @foreach($stKeys as $k)
                                    <th class="text-center px-2 py-2 font-semibold whitespace-nowrap border-l border-gray-200 text-gray-700">{{ $stLabels[$k] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr class="bg-gray-100 font-semibold text-gray-900 border-y-2 border-gray-300">
                                <td class="px-4 py-2 font-semibold text-gray-900 sticky left-0 bg-gray-100">All Areas</td>
                                @foreach($stKeys as $k)
                                    @php $c = $totalsRow[$k]; @endphp
                                    <td class="text-center px-1 py-2 border-l border-gray-200">
                                        @if($c > 0)
                                            <span class="inline-block px-1.5 py-0.5 rounded-full text-xs font-semibold {{ $stClass[$k] }}">{{ $c }}</span>
                                        @else <span class="text-gray-300">—</span> @endif
                                    </td>
                                @endforeach
                            </tr>
                            @foreach($stalenessData as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 font-medium text-gray-900 sticky left-0 bg-white">
                                        @if($stalenessAreaHeader !== 'RC Unit')
                                            <a href="{{ route('reports.trainings.stats', array_merge(request()->query(), [$areaField => $row['id'], 'tab' => 'staleness'])) }}"
                                               class="text-indigo-600 hover:text-indigo-800 hover:underline">{{ $row['label'] }}</a>
                                        @else
                                            {{ $row['label'] }}
                                        @endif
                                    </td>
                                    @foreach($stKeys as $k)
                                        @php $c = $row['counts'][$k]; @endphp
                                        <td class="text-center px-1 py-2 border-l border-gray-100">
                                            @if($c > 0)
                                                <span class="inline-block px-1.5 py-0.5 rounded-full text-xs font-semibold {{ $stClass[$k] }}">{{ $c }}</span>
                                            @else <span class="text-gray-300">—</span> @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif

    </div>

    <script>
        // Cascade branch → division → unit
        const branchSelect   = document.getElementById('branch_id');
        const divisionSelect = document.getElementById('division_id');
        const unitSelect     = document.getElementById('red_cross_unit_id');

        if (branchSelect) {
            branchSelect.addEventListener('change', function () {
                divisionSelect.innerHTML = '<option value="">All Divisions</option>';
                divisionSelect.disabled  = true;
                if (unitSelect) { unitSelect.innerHTML = '<option value="">All Units</option>'; unitSelect.disabled = true; }

                if (this.value) {
                    fetch(`/api/divisions/by-branch?branch_id=${this.value}`)
                        .then(r => r.json())
                        .then(data => {
                            divisionSelect.disabled = false;
                            data.forEach(d => divisionSelect.add(new Option(d.name, d.id)));
                        });
                }
            });
        }

        if (divisionSelect) {
            divisionSelect.addEventListener('change', function () {
                if (unitSelect) {
                    unitSelect.innerHTML = '<option value="">All Units</option>';
                    unitSelect.disabled  = true;
                    if (this.value) {
                        fetch(`/red-cross-units/by-division?division_id=${this.value}`)
                            .then(r => r.json())
                            .then(data => {
                                unitSelect.disabled = false;
                                data.forEach(u => unitSelect.add(new Option(u.name, u.id)));
                            });
                    }
                }
            });
        }
    </script>
</x-layouts.admin>
