<x-layouts.admin title="Red Cross Units">


    <x-slot name="pageHeader">
        <i class="fas fa-shield-alt mr-3"></i>  Red Cross Unit
    </x-slot>

    <x-slot name="subHeader">
        FIND & FILTER
    </x-slot>

    @can('add_red_cross_unit')
        <x-slot name="button1">
            <a href="{{  route('red-cross-units.create')  }}"
               class="btn-add">
                <i class="fas fa-plus mr-2"></i>
                Add New Unit
            </a>
        </x-slot>
    @endcan


    {{-- ── Guide BUTTON ───────────────────────────────────────────── --}}
    <div class="flex justify-center mb-4">
        <x-help-popup trigger-class="help-btn">
            <x-slot:trigger><i class="fas fa-question-circle text-base mr-1"></i>Guide</x-slot:trigger>

            {{-- Header --}}
            <div class="-mt-8 mb-4 text-center">
                <i class="fas fa-shield-alt text-xl text-indigo-500"></i>
                <h3 class="mt-1 text-base font-semibold text-gray-900">Red Cross Unit Guidelines</h3>
            </div>

            {{-- Accordion --}}
            <div class="max-w-3xl mx-auto">
                <div x-data="{ open: null }" class="space-y-1 text-sm mb-4">

                    {{-- General information --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'general' ? null : 'general'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-circle-info mr-2 text-blue-400"></i>General information</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'general' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'general'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>A <span class="font-semibold">RC Unit</span> is the permanent home for volunteers.</li>
                                <li>When a person is assigned to a RC Unit, they are automatically moved to <span class="font-semibold">Active</span> status.</li>
                                <li>A unit belongs to a Division, which belongs to a Branch — use the filters above to narrow down by either.</li>
                                <li>Each unit can have a Team Leader and an Assistant Team Leader, shown in the table.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Add / edit unit --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'add_edit' ? null : 'add_edit'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-plus mr-2 text-indigo-400"></i>Add / edit a unit</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'add_edit' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'add_edit'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Press <span class="font-semibold">Add New Unit</span> to create one.</li>
                                <li>Press <span class="font-semibold">View → Edit</span> on an existing unit to change its details.</li>
                            </ul>
                        </div>
                    </div>



                    {{-- Add / remove persons --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'members' ? null : 'members'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-people-group mr-2 text-sky-400"></i>Add / remove persons</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'members' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'members'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">

                                <li>Go to <span class="font-semibold">Persons → Edit</span>, then choose the unit from the dropdown.</li>
                                <li>To remove someone from a unit, change their dropdown selection to a different unit, or clear it.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Leadership assignment --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'leadership' ? null : 'leadership'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-user-tie mr-2 text-violet-400"></i>Leadership assignment</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'leadership' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'leadership'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Open the unit's <span class="font-semibold">View → Edit</span> page.</li>
                                <li>Under <span class="font-semibold">Leadership Assignment</span>, set the Team Leader and Assistant Team Leader.</li>
                                <li>Only persons already assigned to the unit can be set as leaders.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Archive / reactivate --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'archive' ? null : 'archive'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-box-archive mr-2 text-red-400"></i>Archive / Reactivate</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'archive' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'archive'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>A unit no longer in use can be archived from its <span class="font-semibold">View → Edit</span> page.</li>
                                <li>An archived unit can be reactivated later from the same page, if needed.</li>
                            </ul>
                        </div>
                    </div>

                </div>{{-- end accordion --}}
            </div>{{-- end max-w-3xl --}}

        </x-help-popup>
    </div>



    <div class="container mx-auto px-4 py-6">


        <!-- Filters -->
        <div class="filter-container">
            <div class="filter-form-content">
                <form method="GET" action="{{ route('red-cross-units.index') }}" id="filterForm" class="filter-form">
                    <div class="filter-grid filter-grid-4">
                        <div>
                            <label for="search" class="filter-label">Search</label>
                            <input type="text" name="search" id="search" value="{{ request('search') }}"
                                   placeholder="Search by unit name..."
                                   class="filter-input {{ request('search') ? 'filter-active' : '' }}">
                        </div>
                        <div>
                            <label for="branch_id" class="filter-label">Branch</label>
                            <select name="branch_id" id="branch_id"
                                    class="filter-select
                                    @if($accessLevel === 'branch' || $accessLevel === 'division') bg-gray-100 cursor-not-allowed @else {{ request('branch_id') ? 'filter-active' : '' }} @endif"
                                    @if($accessLevel === 'branch' || $accessLevel === 'division') disabled @endif>
                                @if($accessLevel === 'national')
                                    <option value="">All Branches</option>
                                @endif
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}"
                                            @if(request('branch_id') == $branch->id) selected
                                            @elseif(($accessLevel === 'branch' || $accessLevel === 'division') && (string)$userBranchId === (string)$branch->id) selected
                                        @endif>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                                @if(($accessLevel === 'branch' || $accessLevel === 'division') && $branches->isEmpty())
                                    <option value="" selected>No Branch Accessible</option>
                                @endif
                            </select>
                        </div>
                        <div>
                            <label for="division_id" class="filter-label">Division</label>
                            <select name="division_id" id="division_id"
                                    class="filter-select
                                    @if($accessLevel === 'division' || ((!request('branch_id') && !$userBranchId) && $accessLevel !== 'national')) bg-gray-100 cursor-not-allowed @else {{ request('division_id') ? 'filter-active' : '' }} @endif"
                                    @if($accessLevel === 'division' || ((!request('branch_id') && !$userBranchId) && $accessLevel !== 'national')) disabled @endif>
                                @if($accessLevel !== 'division')
                                    <option value="">
                                        {{ (request('branch_id') || $userBranchId) ? 'All Divisions' : 'Select a Branch first' }}
                                    </option>
                                @endif
                                @foreach($divisions as $division)
                                    <option value="{{ $division->id }}"
                                            @if(request('division_id') == $division->id) selected
                                            @elseif($accessLevel === 'division' && (string)$userDivisionId === (string)$division->id) selected
                                        @endif>
                                        {{ $division->name }}
                                    </option>
                                @endforeach
                                @if($accessLevel === 'division' && $divisions->isEmpty())
                                    <option value="" selected>No Division Accessible</option>
                                @endif
                            </select>
                        </div>
                        <div>
                            <label for="status" class="filter-label">Status</label>
                            <select name="status" id="status" class="filter-select {{ $status !== 'active' ? 'filter-active' : '' }}">
                                <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="archived" {{ $status === 'archived' ? 'selected' : '' }}>Archived</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-actions">
                        <div class="filter-button-group">
                            <button type="submit" class="filter-btn-primary">
                                <i class="fas fa-search mr-1"></i>Filter
                            </button>
                            <a href="{{ route('red-cross-units.index') }}"
                               class="filter-btn-secondary filter-btn-secondary-active">
                                <i class="fas fa-times mr-1"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Red Cross Units Table -->
        <div class="table-container">
            <div class="table-wrapper">
                <table class="data-table">
                    <thead class="table-header">
                    <tr class="table-header-row">
                        <th class="table-header-cell">Name</th>
                        <th class="table-header-cell">Branch / Division</th>
                        <th class="table-header-cell">Members</th>
                        <th class="table-header-cell">Leadership</th>
                        <th class="table-header-cell">Actions</th>
                    </tr>
                    </thead>
                    <tbody class="table-body">
                    @forelse($redCrossUnits as $unit)
                        <tr class="table-body-row {{ !$unit->is_active ? 'bg-gray-50' : '' }}">

                            <td class="table-body-cell">
                                <div class="table-field-main">
                                    {{ $unit->name }}
                                    @if(! $unit->is_active)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 ml-1">Archived</span>
                                    @endif
                                </div>
                            </td>

                            <td class="table-body-cell">
                                <div class="table-field-main">{{ $unit->division->branch->name ?? 'N/A' }}</div>
                                @if($unit->division)
                                    <div class="table-field-sub">{{ $unit->division->name }}</div>
                                @endif
                            </td>

                            <td class="table-body-cell">
                                <div class="table-field-main">{{ $unit->active_users_count }} {{ $unit->active_users_count != 1 ? 'members' : 'member' }}</div>
                            </td>

                            <td class="table-body-cell">
                                <div class="table-field-main">
                                    {{ $unit->teamLeader->full_name ?? '—' }}
                                    @if($unit->teamLeader && $unit->teamLeader->lifecycle_status === 'archived')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 ml-1">Archived</span>
                                    @endif
                                </div>
                                @if($unit->assistantTeamLeader)
                                    <div class="table-field-sub">
                                        {{ $unit->assistantTeamLeader->full_name }}
                                        @if($unit->assistantTeamLeader->lifecycle_status === 'archived')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 ml-1">Archived</span>
                                        @endif
                                    </div>
                                @endif
                            </td>

                            <td class="table-body-cell">
                                <div class="flex gap-2 items-center">
                                    <a href="{{ route('red-cross-units.show', $unit) }}"
                                       class="btn-primary whitespace-nowrap">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </a>
                                </div>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 text-center text-gray-500 italic">
                                No red cross units found.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="table-pagination">
                {{ $redCrossUnits->withQueryString()->links() }}
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const branchSelect = document.getElementById('branch_id');
                const divisionSelect = document.getElementById('division_id');

                // Get access level and user-specific IDs from PHP
                const accessLevel = "{{ $accessLevel }}";
                const userBranchId = "{{ $userBranchId ?? '' }}";
                const userDivisionId = "{{ $userDivisionId ?? '' }}";

                // Store initially selected values from the request URL, which might also be set by access control in PHP
                const initialSelectedBranch = branchSelect.value; // Read from rendered HTML
                const initialSelectedDivision = divisionSelect.value; // Read from rendered HTML

                // Function to reset and disable a select element with styling
                function resetAndDisableSelect(selectElement, placeholderText) {
                    selectElement.innerHTML = `<option value="">${placeholderText}</option>`;
                    selectElement.disabled = true;
                    selectElement.classList.add('bg-gray-100', 'cursor-not-allowed');
                }

                // Function to populate divisions based on branch
                async function populateDivisions(branchId, selectedDivisionId = '') {
                    // If division select is controlled by access level, don't interfere
                    // For 'division' level, the select is always disabled and pre-selected in PHP.
                    if (accessLevel === 'division') {
                        return;
                    }

                    // Reset and disable if no branch is selected or being processed
                    if (!branchId) {
                        resetAndDisableSelect(divisionSelect, 'Select a Branch first');
                        return;
                    }

                    // Enable and clear previous styling/options
                    divisionSelect.disabled = false;
                    divisionSelect.classList.remove('bg-gray-100', 'cursor-not-allowed');
                    divisionSelect.innerHTML = '<option value="">Loading divisions...</option>';

                    try {
                        const response = await fetch(`/api/divisions/by-branch?branch_id=${branchId}`);
                        const divisions = await response.json();

                        // Clear previous options and add "All Divisions"
                        divisionSelect.innerHTML = '<option value="">All Divisions</option>';

                        divisions.forEach(division => {
                            const option = document.createElement('option');
                            option.value = division.id;
                            option.textContent = division.name;
                            // If the division matches the initial selected division (from URL or PHP pre-selection)
                            if (selectedDivisionId && String(division.id) === String(selectedDivisionId)) {
                                option.selected = true;
                            }
                            divisionSelect.appendChild(option);
                        });
                    } catch (error) {
                        console.error('Error fetching divisions:', error);
                        resetAndDisableSelect(divisionSelect, 'Error loading divisions');
                    }
                }

                // Event listener for branch changes
                // Only attach if the branch select is not disabled by access level (i.e., 'national')
                if (accessLevel === 'national') {
                    branchSelect.addEventListener('change', function () {
                        const branchId = this.value;
                        populateDivisions(branchId);
                    });
                }

                // Initial setup on page load
                // Apply disabled styling initially if elements are disabled by Blade.
                // The disabled attribute and classes are already applied in Blade.
                // We only need to handle dynamic population if not disabled by access level.

                // If a branch is initially selected or provided via user access (and it's not a division-level user, where it's already fully rendered fixed)
                if (initialSelectedBranch && accessLevel !== 'division') {
                    populateDivisions(initialSelectedBranch, initialSelectedDivision);
                } else if (!initialSelectedBranch && accessLevel !== 'division') {
                    // If no branch is pre-selected by request and not a fixed access level, disable divisions.
                    resetAndDisableSelect(divisionSelect, 'Select a Branch first');
                }
                // If accessLevel is 'division', both branch and division dropdowns are disabled and pre-selected by PHP.
                // No dynamic population or explicit disabling for divisions needed here, as PHP handles the initial state.
            });
        </script>
    @endpush
</x-layouts.admin>
