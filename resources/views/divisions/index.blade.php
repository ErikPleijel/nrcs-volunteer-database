<x-layouts.admin title="Divisions">
    <x-slot name="pageHeader">
        <i class="fas fa-layer-group mr-3"></i> Divisions
    </x-slot>

    <x-slot name="subHeader">
        Find & Filter
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

                    {{-- Search & filter --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'filter' ? null : 'filter'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-filter mr-2 text-sky-400"></i>Search &amp; filter divisions</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'filter' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'filter'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Use <span class="font-semibold">Search</span> to find a division by name — matching text is highlighted in the results.</li>
                                <li>Use <span class="font-semibold">Branch</span> to narrow the list down to one branch's divisions.</li>
                                <li>Click <span class="font-semibold">Clear</span> to reset both filters.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Read the divisions table --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'table' ? null : 'table'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-table mr-2 text-indigo-400"></i>Read the divisions table</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'table' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'table'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li><span class="font-semibold">RC Units</span>, <span class="font-semibold">Volunteers</span>, and <span class="font-semibold">Members</span> show live counts for each division.</li>
                                <li>Click <span class="font-semibold">View</span> to see the division's full details, or <span class="font-semibold">Edit</span> to update its information.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Edit division information --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'edit' ? null : 'edit'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-pencil mr-2 text-amber-400"></i>Edit division information</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'edit' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'edit'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>You can update a division's <span class="font-semibold">Physical Address</span>, <span class="font-semibold">Postal Address</span>, <span class="font-semibold">Telephone</span>, and <span class="font-semibold">Email</span>.</li>

                            </ul>
                        </div>
                    </div>

                </div>{{-- end accordion --}}
            </div>{{-- end max-w-3xl --}}

        </x-help-popup>
    </div>

    <div class="container mx-auto px-4 py-6">
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        <div class="filter-container">
            <div class="filter-form-content">
                <form method="GET" action="{{ route('divisions.index') }}" class="filter-form">
                    <div class="filter-grid filter-grid-4">
                        <div>
                            <label for="search" class="filter-label">Search</label>
                            <input type="text"
                                   name="search"
                                   id="search"
                                   value="{{ $search }}"
                                   placeholder="Search divisions..."
                                   class="filter-input {{ $search ? 'filter-active' : '' }}">
                        </div>

                        <div>
                            <label for="branch_id" class="filter-label">Branch</label>
                            <select name="branch_id" id="branch_id"
                                    class="filter-select {{ $branchId ? 'filter-active' : '' }}">
                                <option value="">All Branches</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ (string) $branchId === (string) $branch->id ? 'selected' : '' }}>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions">
                        <div class="filter-button-group">
                            <button type="submit" class="filter-btn-primary">
                                <i class="fas fa-search mr-1"></i>Filter
                            </button>
                            <a href="{{ route('divisions.index') }}"
                               class="filter-btn-secondary {{ ($search || $branchId) ? 'filter-btn-secondary-active' : 'filter-btn-disabled' }}">
                                <i class="fas fa-times mr-1"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if($search)
            <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded mb-4 text-sm">
                Showing results for "<strong>{{ $search }}</strong>" — {{ $divisions->total() }} result(s) found
            </div>
        @endif

        <div class="table-container">
            <div class="table-wrapper">
                <table class="data-table">
                    <thead class="table-header">
                        <tr class="table-header-row">
                            <th class="table-header-cell">Name</th>
                            <th class="table-header-cell">Branch</th>
                            <th class="table-header-cell">RC Units</th>
                            <th class="table-header-cell">Volunteers</th>
                            <th class="table-header-cell">Members</th>
                            <th class="table-header-cell">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="table-body">
                        @forelse($divisions as $division)
                            <tr class="table-body-row">

                                <td class="table-body-cell">
                                    <div class="table-field-main">
                                        @if($search)
                                            {!! str_ireplace($search, '<mark class="bg-yellow-200 px-1 rounded">' . $search . '</mark>', e($division->name)) !!}
                                        @else
                                            {{ $division->name }}
                                        @endif
                                    </div>
                                </td>

                                <td class="table-body-cell">
                                    <div class="table-field-main">{{ $division->branch->name ?? 'N/A' }}</div>
                                </td>

                                <td class="table-body-cell">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $division->redCrossUnits()->count() }} units
                                    </span>
                                </td>

                                <td class="table-body-cell">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        {{ $volunteerCountsByDivision[$division->id] ?? 0 }} volunteers
                                    </span>
                                </td>

                                <td class="table-body-cell">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                        {{ $memberCountsByDivision[$division->id] ?? 0 }} members
                                    </span>
                                </td>

                                <td class="table-body-cell-no-wrap">
                                    <div class="flex gap-2 items-center">
                                        <a href="{{ route('divisions.show', $division) }}"
                                           class="btn-primary inline-flex items-center gap-1">
                                            <i class="fas fa-eye mr-1"></i>View
                                        </a>
                                    </div>
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-4 text-center text-gray-500 italic">
                                    @if($search)
                                        No divisions found matching "{{ $search }}".
                                    @else
                                        No divisions found.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="table-pagination">
                {{ $divisions->appends(request()->query())->links() }}
            </div>

        </div>


    </div>
</x-layouts.admin>
