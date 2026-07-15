<x-layouts.admin title="ID Card Prints Report">
    <x-slot name="pageHeader">
        <i class="fas fa-file-alt mr-3"></i> ID Card Prints Report
    </x-slot>
    <x-slot name="subHeader">
        A list of all recorded ID card print events.
    </x-slot>

    <x-slot name="backLink">
        <a href="{{ route('id-cards.prepare-bulk-print') }}"
           class="btn-backlink">
            ←  Back to CARD Printing
        </a>
    </x-slot>

    <div class="container mx-auto px-4 py-6">
        <!-- Filter Section -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-6 py-4">
                <form method="GET" action="{{ route('id-cards.prints-report') }}" class="space-y-4">
                    <!-- Row 1: search + location filters -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Search by User Name/ID -->
                        <div>
                            <label for="user_id_search" class="block text-sm font-medium text-gray-700 mb-1">User Search</label>
                            <input type="text" id="user_id_search" name="user_id_search" value="{{ request('user_id_search') }}" placeholder="Name or ID" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 {{ request('user_id_search') ? 'filter-active' : '' }}">
                        </div>

                        <!-- Branch Filter -->
                        <div>
                            <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                            <select id="branch_id" name="branch_id" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 {{ $accessLevel === 'national' && request('branch_id') ? 'filter-active' : '' }}"
                                    @if($accessLevel !== 'national') disabled @endif>
                                @if($accessLevel === 'national')
                                    <option value="">All Branches</option>
                                @endif
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" {{ (request('branch_id') == $branch->id || ($accessLevel !== 'national' && $userBranchId == $branch->id)) ? 'selected' : '' }}>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Division Filter -->
                        <div>
                            <label for="division_id" class="block text-sm font-medium text-gray-700 mb-1">Division</label>
                            <select id="division_id" name="division_id" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 {{ $accessLevel !== 'division' && !($accessLevel === 'national' && !request('branch_id')) && request('division_id') ? 'filter-active' : '' }}"
                                    @if($accessLevel === 'division') disabled
                                    @elseif($accessLevel === 'national' && !request('branch_id')) disabled
                                    @endif>
                                @if($accessLevel !== 'division')
                                    <option value="">{{ ($accessLevel === 'national' && !request('branch_id')) ? 'Select Branch First' : 'All Divisions' }}</option>
                                @endif
                                @foreach($divisions as $division)
                                    <option value="{{ $division->id }}" {{ (request('division_id') == $division->id || ($accessLevel === 'division' && $userDivisionId == $division->id)) ? 'selected' : '' }}>
                                        {{ $division->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Red Cross Unit Filter -->
                        <div>
                            <label for="red_cross_unit_id" class="block text-sm font-medium text-gray-700 mb-1">Red Cross Unit</label>
                            <select id="red_cross_unit_id" name="red_cross_unit_id" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 {{ request('red_cross_unit_id') ? 'filter-active' : '' }}" {{ !request('division_id') && !$userDivisionId ? 'disabled' : '' }}>
                                <option value="">{{ (request('division_id') || $userDivisionId) ? 'All Units' : 'Select Division First' }}</option>
                                @foreach($redCrossUnits as $unit)
                                    <option value="{{ $unit->id }}" {{ request('red_cross_unit_id') == $unit->id ? 'selected' : '' }}>
                                        {{ $unit->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Row 2: date range -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Printed From</label>
                            <input type="date" id="start_date" name="start_date" value="{{ request('start_date') }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 {{ request('start_date') ? 'filter-active' : '' }}">
                        </div>
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">Printed To</label>
                            <input type="date" id="end_date" name="end_date" value="{{ request('end_date') }}" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 {{ request('end_date') ? 'filter-active' : '' }}">
                        </div>
                    </div>

                    <div class="flex justify-start items-center mt-4">
                        <div class="flex space-x-2">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                <i class="fas fa-search mr-1"></i>Filter
                            </button>
                            <a href="{{ route('id-cards.prints-report') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                <i class="fas fa-times mr-1"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if($idCardPrints->count() > 0)
            <div class="mb-4 flex justify-between items-center">
                @can('print_idcards')
                <div class="flex items-center space-x-2">
                    <button type="button" id="select-all-prints" class="btn-bulk-select">Select All</button>
                    <button type="button" id="deselect-all-prints" class="btn-bulk-select">Deselect All</button>
                    <span id="selection-counter" class="bulk-selection-counter">0 prints selected</span>
                </div>
                @endcan
                {{-- Bulk Delete Form (soft delete) --}}
                <form id="bulk-delete-form" method="POST" action="{{ route('id-cards.bulk-delete-prints') }}" class="inline">
                    @csrf
                    @method('DELETE') {{-- Use DELETE method for soft deletion --}}
                    <input type="hidden" name="print_ids" id="bulk-delete-print-ids">
                    @can('print_idcards') {{-- Add a permission check --}}
                        <button type="submit" id="bulk-delete-btn" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                            <i class="fas fa-trash-alt mr-1"></i> Delete Selected
                        </button>
                    @endcan
                </form>
            </div>

            <div class="bg-white shadow rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            @can('print_idcards')
                            <input type="checkbox" id="master-checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            @endcan
                        </th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            User
                        </th>
                        {{-- Replaced 'Location' with 'Branch', 'Division', 'Red Cross Unit' --}}
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Branch
                        </th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Division
                        </th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Red Cross Unit
                        </th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Printed By
                        </th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Printed At
                        </th>
                        {{-- REMOVED Status Column --}}
                        {{-- <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Status
                        </th> --}}
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Validity
                        </th>
                        <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Expiry Date
                        </th>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($idCardPrints as $print)
                        <tr>
                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">
                                @can('print_idcards')
                                <input type="checkbox" name="print_ids[]" value="{{ $print->id }}" class="print-checkbox rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                @endcan
                            </td>
                            <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                <a href="{{ route('users.show', $print->user->id) }}" class="text-blue-600 hover:text-blue-900">
                                    {{ $print->user->full_name }}
                                </a>
                                <br>
                                <span class="text-gray-500 text-xs">{{ $print->user->user_id_reference }}</span>
                            </td>
                            {{-- Replaced single 'Location' data with 'Branch', 'Division', 'Red Cross Unit' --}}
                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $print->user->branch->name ?? 'N/A' }}
                            </td>
                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $print->user->division->name ?? 'N/A' }}
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-500 whitespace-normal"> {{-- Added whitespace-normal for wrapping --}}
                                {{ $print->user->redCrossUnit->name ?? 'N/A' }}
                            </td>
                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $print->printedBy->full_name ?? 'SYSTEM' }}
                            </td>
                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $print->printed_at->format('Y-m-d H:i:s') }}
                            </td>
                            {{-- REMOVED Status Data --}}
                            {{-- <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        {{ ucfirst($print->status) }}
                                    </span>
                            </td> --}}
                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">
                                @if($print->validity_months)
                                    {{ $print->validity_months }} Months
                                @else
                                    Membership Expiry
                                @endif
                            </td>
                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">
                                @if($print->expiry_date)
                                    {{ $print->expiry_date->format('Y-m-d') }}
                                    @if($print->expiry_date->isPast())
                                        <i class="fas fa-exclamation-triangle text-red-500 ml-1" title="Expired"></i>
                                    @endif
                                @else
                                    N/A
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $idCardPrints->links() }}
            </div>
        @else
            <div class="text-center py-12 bg-white shadow rounded-lg">
                <i class="fas fa-print text-4xl text-gray-400 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900">No ID Card Prints Found</h3>
                <p class="text-sm text-gray-500">Try adjusting your filter criteria.</p>
            </div>
        @endif
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const branchSelect = document.getElementById('branch_id');
            const divisionSelect = document.getElementById('division_id');
            const redCrossUnitSelect = document.getElementById('red_cross_unit_id');

            const masterCheckbox = document.getElementById('master-checkbox');
            const printCheckboxes = document.querySelectorAll('.print-checkbox');
            const bulkDeleteBtn = document.getElementById('bulk-delete-btn');
            const bulkDeletePrintIdsInput = document.getElementById('bulk-delete-print-ids');
            const selectionCounter = document.getElementById('selection-counter');
            const selectAllBtn = document.getElementById('select-all-prints');
            const deselectAllBtn = document.getElementById('deselect-all-prints');
            const bulkDeleteForm = document.getElementById('bulk-delete-form');


            if (masterCheckbox) {
                function updateBulkDeleteButtonState() {
                    const checkedCheckboxes = document.querySelectorAll('.print-checkbox:checked');
                    const selectedCount = checkedCheckboxes.length;

                    bulkDeleteBtn.disabled = selectedCount === 0;
                    selectionCounter.textContent = `${selectedCount} prints selected`;

                    // Update master checkbox state
                    if (selectedCount === 0) {
                        masterCheckbox.checked = false;
                        masterCheckbox.indeterminate = false;
                    } else if (selectedCount === printCheckboxes.length) {
                        masterCheckbox.checked = true;
                        masterCheckbox.indeterminate = false;
                    } else {
                        masterCheckbox.checked = false;
                        masterCheckbox.indeterminate = true;
                    }
                }

                masterCheckbox.addEventListener('change', function () {
                    printCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                    updateBulkDeleteButtonState();
                });

                printCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', updateBulkDeleteButtonState);
                });

                selectAllBtn.addEventListener('click', function() {
                    printCheckboxes.forEach(checkbox => checkbox.checked = true);
                    updateBulkDeleteButtonState();
                });

                deselectAllBtn.addEventListener('click', function() {
                    printCheckboxes.forEach(checkbox => checkbox.checked = false);
                    updateBulkDeleteButtonState();
                });

                bulkDeleteForm.addEventListener('submit', function (event) {
                    event.preventDefault(); // Prevent default form submission

                    const checkedIds = Array.from(document.querySelectorAll('.print-checkbox:checked')).map(cb => cb.value);

                    if (checkedIds.length === 0) {
                        alert('Please select at least one ID card print to delete.');
                        return;
                    }

                    if (confirm(`Are you sure you want to delete ${checkedIds.length} selected ID card print records? This action is irreversible.`)) {
                        bulkDeletePrintIdsInput.value = JSON.stringify(checkedIds); // Send as JSON string
                        this.submit(); // Submit the form programmatically
                    }
                });

                // Initial state update when page loads
                updateBulkDeleteButtonState();
            }


            // Original dropdown logic (from your provided code)
            const accessLevel = "{{ $accessLevel }}";
            const userBranchId = "{{ $userBranchId ?? '' }}";
            const userDivisionId = "{{ $userDivisionId ?? '' }}";

            // Read initial values from the DOM, which are set by PHP Blade based on request and access level
            const initialSelectedBranch = branchSelect.value;
            const initialSelectedDivision = divisionSelect.value;
            const initialSelectedRedCrossUnit = redCrossUnitSelect.value;


            function resetAndDisableSelect(selectElement, placeholderText) {
                selectElement.innerHTML = `<option value="">${placeholderText}</option>`;
                selectElement.disabled = true;
            }

            async function populateDivisions(branchId, selectedDivisionId = '') {
                // Always reset RCU select when divisions change
                resetAndDisableSelect(redCrossUnitSelect, 'Select Division First');

                // If division select is disabled by access level (set by PHP), do not attempt to enable or populate
                if (divisionSelect.disabled && accessLevel === 'division') {
                    // For division level, the select should remain disabled and pre-selected
                    return;
                }

                if (!branchId) {
                    resetAndDisableSelect(divisionSelect, 'Select Branch First');
                    return;
                }

                divisionSelect.disabled = false;
                divisionSelect.innerHTML = '<option value="">Loading divisions...</option>';

                try {
                    const response = await fetch(`/api/divisions/by-branch?branch_id=${branchId}`);
                    const divisions = await response.json();

                    // If not division-level, add "All Divisions" option
                    if (accessLevel !== 'division') {
                        divisionSelect.innerHTML = '<option value="">All Divisions</option>';
                    } else {
                        divisionSelect.innerHTML = ''; // If division-level, don't show "All Divisions"
                    }

                    divisions.forEach(division => {
                        const option = document.createElement('option');
                        option.value = division.id;
                        option.textContent = division.name;
                        // Select if matches initial selected or is the user's scoped division
                        if (String(division.id) === String(selectedDivisionId) || (accessLevel === 'division' && String(division.id) === userDivisionId)) {
                            option.selected = true;
                        }
                        divisionSelect.appendChild(option);
                    });

                    // After populating divisions, if a division was pre-selected, populate its Red Cross Units
                    // This handles initial load when a branch is selected and a division was also previously selected.
                    if (selectedDivisionId && Array.from(divisionSelect.options).some(option => String(option.value) === String(selectedDivisionId))) {
                        populateRedCrossUnits(selectedDivisionId, initialSelectedRedCrossUnit);
                    } else if (accessLevel === 'division' && userDivisionId) {
                        // If division level, ensure RCUs for their division are populated
                        populateRedCrossUnits(userDivisionId, initialSelectedRedCrossUnit);
                    }
                    else {
                        // If no valid division was pre-selected for this branch, ensure RCU is reset
                        resetAndDisableSelect(redCrossUnitSelect, 'Select Division First');
                    }

                } catch (error) {
                    console.error('Error fetching divisions:', error);
                    resetAndDisableSelect(divisionSelect, 'Error loading divisions');
                }
            }

            async function populateRedCrossUnits(divisionId, selectedUnitId = '') {
                // If red cross unit select is disabled by access level (set by PHP), do not attempt to enable or populate
                if (redCrossUnitSelect.disabled && accessLevel === 'division' && String(divisionId) !== userDivisionId) {
                    // For division level users, if they try to fetch units for a division other than their scoped one,
                    // keep it disabled and do not populate.
                    return;
                }

                if (!divisionId) {
                    resetAndDisableSelect(redCrossUnitSelect, 'Select Division First');
                    return;
                }

                redCrossUnitSelect.disabled = false;
                redCrossUnitSelect.innerHTML = '<option value="">Loading units...</option>';

                try {
                    const response = await fetch(`/red-cross-units/by-division?division_id=${divisionId}`);
                    const units = await response.json();

                    redCrossUnitSelect.innerHTML = '<option value="">All Units</option>';
                    units.forEach(unit => {
                        const option = document.createElement('option');
                        option.value = unit.id;
                        option.textContent = unit.name;
                        if (String(unit.id) === String(selectedUnitId)) {
                            option.selected = true;
                        }
                        redCrossUnitSelect.appendChild(option);
                    });
                } catch (error) {
                    console.error('Error fetching Red Cross Units:', error);
                    resetAndDisableSelect(redCrossUnitSelect, 'Error loading units');
                }
            }

            // Attach event listeners based on access level
            if (accessLevel === 'national') {
                branchSelect.addEventListener('change', function () {
                    populateDivisions(this.value);
                });
                divisionSelect.addEventListener('change', function () {
                    populateRedCrossUnits(this.value);
                });
            } else if (accessLevel === 'branch') {
                // Branch select is disabled for 'branch' level, so no listener needed.
                // Division select is enabled and can be changed.
                divisionSelect.addEventListener('change', function () {
                    populateRedCrossUnits(this.value);
                });
            }
            // For 'division' level, both branch and division selects are disabled, so no listeners needed.

            // Initial population logic for dependent dropdowns when the page loads
            if (accessLevel === 'national' || accessLevel === 'branch') {
                if (initialSelectedBranch) {
                    populateDivisions(initialSelectedBranch, initialSelectedDivision);
                } else {
                    resetAndDisableSelect(divisionSelect, 'Select Branch First');
                    resetAndDisableSelect(redCrossUnitSelect, 'Select Division First');
                }
            } else if (accessLevel === 'division' && userDivisionId) {
                // If division level, and their division is set, populate RCUs for it
                populateRedCrossUnits(userDivisionId, initialSelectedRedCrossUnit);
            }
        });
    </script>
    @endpush
</x-layouts.admin>
