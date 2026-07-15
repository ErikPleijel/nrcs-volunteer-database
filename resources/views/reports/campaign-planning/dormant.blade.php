<x-layouts.admin title="Re-engage Dormant Volunteers">
    <x-slot name="pageHeader">
        <i class="fas fa-user-clock mr-3"></i> Re-engage Dormant Volunteers
    </x-slot>
    <x-slot name="subHeader">
        For re-engaging dormant volunteers
    </x-slot>

    <div class="container mx-auto px-4 py-6">

        {{-- ── WHAT TO DO BUTTON ───────────────────────────────────────── --}}
        <div class="flex justify-center mb-4">
            <x-help-popup trigger-class="help-btn">
                <x-slot:trigger><i class="fas fa-question-circle mr-1"></i> What to do</x-slot:trigger>

                {{-- Header --}}
                <div class="-mt-8 mb-4 text-center">
                    <i class="fas fa-user-clock text-3xl text-sky-500"></i>
                    <h3 class="mt-1 text-base font-semibold text-gray-900">Dormant Persons</h3>
                </div>

                {{-- Slogan --}}
                <div class="mb-4 text-center">
                    <p class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-1">Your mission:</p>
                    <p class="-mt-2 text-2xl font-bold text-sky-600">Bring them back!</p>
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
                                <li>Design a reactivation message — for example, an invitation to an upcoming activity or training.</li>
                                <li><x-wizard-path wizard-name="Re-engage dormant volunteers" /></li>
                                <li>Once a new record (volunteering, training, or payment) is entered and approved, they return to Active automatically.</li>
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
                            <div x-show="open === 'segments'" x-collapse class="px-4 py-3 bg-white">
                                <p class="text-gray-700">
                                    If the total number of pending persons is manageable, one campaign may be enough.
                                    If it is large, split the audience into segments — one campaign per segment.
                                    Use the filters on this page to explore the numbers and develop a strategy.
                                </p>

                                <p class="text-gray-600 font-semibold text-xs uppercase tracking-wide">Each segment becomes one campaign</p>

                                {{-- Strategy A --}}
                                <div class="rounded border border-gray-100 bg-gray-50 p-3">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                                        <i class="fas fa-map-marker-alt mr-1 text-indigo-300"></i> Strategy A — by geography
                                    </p>
                                    <p class="text-gray-600 text-xs mb-2">Roll out division by division. Finish one area before moving to the next. Useful when local coordinators handle follow-up calls.</p>
                                    <div class="space-y-0.5 text-xs font-mono text-gray-700">
                                        <div class="flex gap-2"><span class="text-gray-400">1.</span> Division A — all pending</div>
                                        <div class="flex gap-2"><span class="text-gray-400">2.</span> Division B — all pending</div>
                                        <div class="flex gap-2"><span class="text-gray-400">3.</span> Division C — all pending</div>
                                        <div class="flex gap-2"><span class="text-gray-400">4.</span> Remaining branches — clean-up</div>
                                    </div>
                                </div>

                                {{-- Strategy B --}}
                                <div class="rounded border border-gray-100 bg-gray-50 p-3">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
                                        <i class="fas fa-sliders-h mr-1 text-sky-300"></i> Strategy B — by demographic
                                    </p>
                                    <p class="text-gray-600 text-xs mb-2">Target by gender and age group. Useful when the message or channel differs by audience.</p>
                                    <div class="space-y-0.5 text-xs font-mono text-gray-700">
                                        <div class="flex gap-2"><span class="text-gray-400">3.</span> Division A · Women · Age 15–25</div>
                                        <div class="flex gap-2"><span class="text-gray-400">4.</span> Division A · Women · Age 26+</div>
                                        <div class="flex gap-2"><span class="text-gray-400">1.</span> Division A · Men · Age 15–25</div>
                                        <div class="flex gap-2"><span class="text-gray-400">2.</span> Division A · Men · Age 26+</div>
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
                                    <li>The planning tool shows only statistics. If you want to see exactly the individuals behind the numbers, do this:</li>
                                    <li>Go to <span class="font-semibold">Persons → Show more filters</span> → set <span class="font-semibold">Lifecycle Status: Dormant</span>  →  Member/Volunteer: Volunteers → click <span class="font-semibold">Filter</span></li>
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
                <form action="{{ route('reports.campaign-planning.dormant') }}" method="GET" class="filter-form">

                    @if($isDivisionLevel)
                        <input type="hidden" name="branch_id" value="{{ request('branch_id') }}">
                    @endif

                    <div class="filter-grid-2">

                        {{-- Col 1: Gender --}}
                        <div>
                            <label for="gender" class="filter-label">Gender</label>
                            <select name="gender" id="gender"
                                    class="filter-select {{ request('gender') ? 'filter-active' : '' }}">
                                <option value="">Both genders</option>
                                <option value="male"   @selected(request('gender') === 'male')>Male</option>
                                <option value="female" @selected(request('gender') === 'female')>Female</option>
                            </select>
                        </div>

                        {{-- Col 2: Age group --}}
                        <div>
                            <label for="age_bracket" class="filter-label">Age group</label>
                            <select name="age_bracket" id="age_bracket"
                                    class="filter-select {{ request('age_bracket') ? 'filter-active' : '' }}">
                                <option value="">All ages</option>
                                <optgroup label="Broad groups">
                                    <option value="1|17"  @selected(request('age_bracket') === '1|17')>Under 18</option>
                                    <option value="18|35" @selected(request('age_bracket') === '18|35')>Youth (18–35)</option>
                                    <option value="36|59" @selected(request('age_bracket') === '36|59')>Adults (36–59)</option>
                                    <option value="60|"   @selected(request('age_bracket') === '60|')>Elderly (60+)</option>
                                </optgroup>
                                <optgroup label="Detailed groups">
                                    <option value="1|5"   @selected(request('age_bracket') === '1|5')>Toddlers &amp; pre-school (1–5)</option>
                                    <option value="6|11"  @selected(request('age_bracket') === '6|11')>Primary school (6–11)</option>
                                    <option value="12|14" @selected(request('age_bracket') === '12|14')>Junior secondary (12–14)</option>
                                    <option value="15|17" @selected(request('age_bracket') === '15|17')>Senior secondary (15–17)</option>
                                    <option value="18|25" @selected(request('age_bracket') === '18|25')>Young adults (18–25)</option>
                                    <option value="26|35" @selected(request('age_bracket') === '26|35')>Adults (26–35)</option>
                                    <option value="36|50" @selected(request('age_bracket') === '36|50')>Middle-aged (36–50)</option>
                                    <option value="51|65" @selected(request('age_bracket') === '51|65')>Senior adults (51–65)</option>
                                    <option value="66|"   @selected(request('age_bracket') === '66|')>Elderly (66+)</option>
                                </optgroup>
                            </select>
                        </div>

                    </div>

                    <div class="filter-actions">
                        <div class="filter-button-group">
                            <button type="submit" class="filter-btn-primary">
                                <i class="fas fa-search mr-1"></i>Filter
                            </button>
                            @if($isDivisionLevel)
                                <a href="{{ route('reports.campaign-planning.dormant', ['branch_id' => request('branch_id')]) }}"
                                   class="filter-btn-secondary filter-btn-secondary-active">
                                    <i class="fas fa-times mr-1"></i>Clear
                                </a>
                            @else
                                <a href="{{ route('reports.campaign-planning.dormant') }}"
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
                <a href="{{ route('reports.campaign-planning.dormant', array_merge($filterParams, ['branch_id' => ''])) }}"
                   class="text-indigo-600 hover:text-indigo-800 underline">All Branches</a>
                <span class="text-gray-400 mx-1">/</span>
                <span class="font-medium text-gray-800">{{ $currentBranch->name }}</span>
                @if((int) auth()->user()->branch_id === (int) $currentBranch->id)
                    <span class="ml-1 text-xs text-gray-400">(your branch)</span>
                @endif
            </div>
        @endif

        {{-- ── TABLE ───────────────────────────────────────────────────── --}}
        <div class="bg-white rounded-lg shadow mt-4 overflow-x-auto">
            @if(empty($planningData))
                <div class="py-12 text-center text-gray-400 italic">No dormant persons found for the current filters.</div>
            @else
                @php $areaLabel = $isDivisionLevel ? 'Division' : 'Branch'; @endphp
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="text-left px-4 py-3 font-semibold text-gray-700 min-w-[200px]">{{ $areaLabel }}</th>
                            <th class="text-center px-4 py-3 font-semibold text-gray-600">Total dormant</th>
                            <th class="text-center px-4 py-3 font-semibold text-green-700">
                                Not yet contacted
                                <div class="text-xs font-normal text-gray-500">freshest targets</div>
                            </th>
                            <th class="text-center px-4 py-3 font-semibold text-amber-600">Contacted once</th>
                            <th class="text-center px-4 py-3 font-semibold text-red-600">
                                Contacted 2+
                                <div class="text-xs font-normal text-gray-500">de-prioritise</div>
                            </th>
                            <th class="text-center px-4 py-3 font-semibold text-gray-600">Avg days since last activity</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @php
                            $summaryAvgClass = $summaryAvgDays === null
                                ? 'text-gray-400'
                                : ($summaryAvgDays <= 90 ? 'text-green-600' : ($summaryAvgDays <= 365 ? 'text-amber-500' : 'text-red-600'));
                        @endphp
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
                            <td class="text-center px-4 py-2 {{ $summaryAvgClass }}">
                                {{ $summaryAvgDays !== null ? $summaryAvgDays . ' d' : '—' }}
                            </td>
                        </tr>
                        @foreach($planningData as $row)
                            @php
                                $avgDays  = $row['avg_days'];
                                $avgClass = $avgDays === null
                                    ? 'text-gray-400'
                                    : ($avgDays <= 90 ? 'text-green-600' : ($avgDays <= 365 ? 'text-amber-500' : 'text-red-600'));
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
                                <td class="text-center px-4 py-2 font-semibold {{ $avgClass }}">
                                    {{ $avgDays !== null ? $avgDays . ' d' : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

    </div>
</x-layouts.admin>
