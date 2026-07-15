<x-layouts.admin title="All Campaigns Report">
    <x-slot name="pageHeader">
        <i class="fas fa-list mr-3 mb-6"></i> All Campaigns Report
    </x-slot>


    <div class="container mx-auto px-4 py-6">

        {{-- ── TABS ─────────────────────────────────────────────────────── --}}
        @php
            $tabToggleBase = request()->query();
            $campaignTabs = [
                'origin'      => ['label' => 'Origin', 'icon' => 'fa-tower-broadcast'],
                'destination' => ['label' => 'Destination', 'icon' => 'fa-inbox'],
            ];
        @endphp
        <div class="flex gap-0 px-6 border-b border-gray-200 mt-2 mb-4">
            @foreach($campaignTabs as $tabKey => $tabInfo)
                <a href="{{ route('reports.campaign-planning.campaigns', array_merge($tabToggleBase, ['tab' => $tabKey])) }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium border border-b-0 transition-colors whitespace-nowrap
                       {{ $activeTab === $tabKey
                           ? 'bg-white border-gray-200 text-indigo-700 font-semibold rounded-t-md -mb-px'
                           : 'bg-gray-50 border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-t-md' }}">
                    <i class="fas {{ $tabInfo['icon'] }} text-xs"></i>
                    {{ $tabInfo['label'] }}
                </a>
            @endforeach
        </div>

        {{-- ── FILTER FORM ─────────────────────────────────────────────── --}}
        <div class="filter-container">
            <div class="filter-form-content">
                <form action="{{ route('reports.campaign-planning.campaigns') }}" method="GET" class="filter-form">

                    <input type="hidden" name="tab" value="{{ $activeTab }}">

                    <div class="filter-grid filter-grid-4">

                        {{-- Col 1: Branch (meaning depends on active tab) --}}
                        <div>
                            <label for="branch_id" class="filter-label">Branch</label>
                            <select name="branch_id" id="branch_id"
                                    class="filter-select {{ $branchId ? 'filter-active' : '' }}">
                                <option value="">All Branches</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected($branchId == $branch->id)>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-400">
                                @if($activeTab === 'origin')
                                    Filter to campaigns created by this branch
                                @else
                                    Filter to recipients in this branch
                                @endif
                            </p>
                        </div>

                        {{-- Col 2: Purpose --}}
                        <div>
                            <label for="purpose_id" class="filter-label">Purpose</label>
                            <select name="purpose_id" id="purpose_id"
                                    class="filter-select {{ request('purpose_id') ? 'filter-active' : '' }}">
                                <option value="">All purposes</option>
                                @foreach($purposes as $purpose)
                                    <option value="{{ $purpose->id }}" @selected(request('purpose_id') == $purpose->id)>
                                        {{ $purpose->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Col 3: Date From --}}
                        <div>
                            <label for="date_from" class="filter-label">Date From</label>
                            <input type="date" name="date_from" id="date_from"
                                   value="{{ request('date_from') }}"
                                   class="filter-input {{ request('date_from') ? 'filter-active' : '' }}">
                        </div>

                        {{-- Col 4: Date To --}}
                        <div>
                            <label for="date_to" class="filter-label">Date To</label>
                            <input type="date" name="date_to" id="date_to"
                                   value="{{ request('date_to') }}"
                                   class="filter-input {{ request('date_to') ? 'filter-active' : '' }}">
                        </div>

                    </div>

                    <div class="filter-actions">
                        <div class="filter-button-group">
                            <button type="submit" class="filter-btn-primary">
                                <i class="fas fa-search mr-1"></i>Filter
                            </button>
                            <a href="{{ route('reports.campaign-planning.campaigns', ['tab' => $activeTab]) }}"
                               class="filter-btn-secondary filter-btn-secondary-active">
                                <i class="fas fa-times mr-1"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── REPORT ───────────────────────────────────────────────────── --}}
        @if(empty($rows))
            <div class="mt-6 py-12 text-center text-gray-400 italic bg-white rounded-lg shadow">
                No campaign data found for the current filters.
            </div>
        @else
            <div class="mt-6 bg-white rounded-lg shadow overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="text-left px-4 py-2 font-semibold text-gray-700 min-w-[220px]">Campaign</th>
                            <th class="text-left px-4 py-2 font-semibold text-gray-700">Purpose</th>
                            <th class="text-left px-4 py-2 font-semibold text-gray-700">Origin</th>
                            <th class="text-left px-4 py-2 font-semibold text-gray-700">Branch</th>
                            <th class="text-center px-4 py-2 font-semibold text-gray-700">Date Sent</th>
                            <th class="text-center px-4 py-2 font-semibold text-green-700">
                                <i class="fas fa-paper-plane mr-1"></i>Persons Sent
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($rows as $row)
                            <tr class="{{ $row['highlight'] ? 'border-l-4 border-yellow-400 bg-yellow-50' : 'hover:bg-gray-50' }}">
                                <td class="px-4 py-2 font-medium text-gray-900">{{ $row['campaign_title'] }}</td>
                                <td class="px-4 py-2">
                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
                                        {{ $row['purpose'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-gray-600">{{ $row['origin_code'] }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $row['branch_label'] }}</td>
                                <td class="text-center px-4 py-2 text-gray-600">
                                    {{ $row['date_sent'] ? $row['date_sent']->format('d M Y') : '—' }}
                                </td>
                                <td class="text-center px-4 py-2">
                                    <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">
                                        {{ $row['sent_count'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

    </div>
</x-layouts.admin>
