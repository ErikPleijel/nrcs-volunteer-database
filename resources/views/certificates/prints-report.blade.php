<x-layouts.admin title="Certificate Prints Report">
    <x-slot name="pageHeader">
        <i class="fas fa-file-alt mr-3"></i> Certificate Prints Report
    </x-slot>
    <x-slot name="subHeader">
        A list of all recorded certificate print events.
    </x-slot>

    <x-slot name="backLink">
        <a href="{{ route('certificates.index') }}"
           class="btn-backlink">
            ←  Back to CERTIFICATE Printing
        </a>
    </x-slot>

    <div class="container mx-auto px-4 py-6">
        <!-- Filter Section -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-6 py-4">
                <form method="GET" action="{{ route('certificates.prints-report') }}" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                        <!-- Search by User Name/ID -->
                        <div>
                            <label for="user_id_search" class="block text-sm font-medium text-gray-700 mb-1">User Search</label>
                            <input type="text"
                                   id="user_id_search"
                                   name="user_id_search"
                                   value="{{ request('user_id_search') }}"
                                   placeholder="Name or ID"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 {{ request('user_id_search') ? 'filter-active' : '' }}">
                        </div>

                        <!-- Certificate Type Filter -->
                        <div>
                            <label for="certificate_type" class="block text-sm font-medium text-gray-700 mb-1">Certificate Type</label>
                            <select id="certificate_type"
                                    name="certificate_type"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 {{ request('certificate_type') ? 'filter-active' : '' }}">
                                <option value="">All Types</option>
                                <option value="training_competence" {{ request('certificate_type') == 'training_competence' ? 'selected' : '' }}>Training – Competence</option>
                                <option value="training_attendance" {{ request('certificate_type') == 'training_attendance' ? 'selected' : '' }}>Training – Attendance</option>
                                <option value="membership" {{ request('certificate_type') == 'membership' ? 'selected' : '' }}>Membership</option>
                                <option value="donation" {{ request('certificate_type') == 'donation' ? 'selected' : '' }}>Donation</option>
                                <option value="volunteering" {{ request('certificate_type') == 'volunteering' ? 'selected' : '' }}>Volunteering</option>
                                <option value="organisation_membership" {{ request('certificate_type') == 'organisation_membership' ? 'selected' : '' }}>Organisation – Membership</option>
                                <option value="organisation_donation" {{ request('certificate_type') == 'organisation_donation' ? 'selected' : '' }}>Organisation – Donation</option>
                            </select>
                        </div>

                        <!-- Branch Filter -->
                        <div>
                            <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-1">Branch</label>
                            <select id="branch_id"
                                    name="branch_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 {{ $accessLevel === 'national' && request('branch_id') ? 'filter-active' : '' }}"
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
                            <select id="division_id"
                                    name="division_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 {{ $accessLevel !== 'division' && !($accessLevel === 'national' && !request('branch_id')) && request('division_id') ? 'filter-active' : '' }}"
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
                            <select id="red_cross_unit_id"
                                    name="red_cross_unit_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 {{ request('red_cross_unit_id') ? 'filter-active' : '' }}"
                                {{ !request('division_id') && !$userDivisionId ? 'disabled' : '' }}>
                                <option value="">
                                    {{ (request('division_id') || $userDivisionId) ? 'All Units' : 'Select Division First' }}
                                </option>
                                @foreach($redCrossUnits as $unit)
                                    <option value="{{ $unit->id }}" {{ request('red_cross_unit_id') == $unit->id ? 'selected' : '' }}>
                                        {{ $unit->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Date Range Filter -->
                        <div>
                            <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Printed From</label>
                            <input type="date"
                                   id="start_date"
                                   name="start_date"
                                   value="{{ request('start_date') }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 {{ request('start_date') ? 'filter-active' : '' }}">
                        </div>
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">Printed To</label>
                            <input type="date"
                                   id="end_date"
                                   name="end_date"
                                   value="{{ request('end_date') }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 {{ request('end_date') ? 'filter-active' : '' }}">
                        </div>
                    </div>

                    <div class="flex justify-start items-center mt-4">
                        <div class="flex space-x-2">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                <i class="fas fa-search mr-1"></i>Filter
                            </button>
                            <a href="{{ route('certificates.prints-report') }}"
                               class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                <i class="fas fa-times mr-1"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if($certificatePrints->count() > 0)
            <div class="mb-4 flex justify-between items-center">
                @can('print_certificates')
                <div class="flex items-center space-x-2">
                    <button type="button" id="select-all-prints" class="btn-bulk-select">Select All</button>
                    <button type="button" id="deselect-all-prints" class="btn-bulk-select">Deselect All</button>
                    <span id="selection-counter" class="bulk-selection-counter">0 prints selected</span>
                </div>
                @else
                <div></div>
                @endcan

                {{-- Bulk Delete Form (soft delete) --}}
                <form id="bulk-delete-form"
                      method="POST"
                      action="{{ route('certificates.bulk-delete-prints') }}"
                      class="inline">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="print_ids" id="bulk-delete-print-ids">
                    @can('print_certificates')
                        <button type="submit"
                                id="bulk-delete-btn"
                                class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded disabled:opacity-50 disabled:cursor-not-allowed"
                                disabled>
                            <i class="fas fa-trash-alt mr-1"></i> Delete Selected
                        </button>
                    @endcan
                </form>
            </div>

            <div class="bg-white shadow rounded-lg overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            @can('print_certificates')
                            <input type="checkbox"
                                   id="master-checkbox"
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            @endcan
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            User
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Branch
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Division
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Red Cross Unit
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Certificate Type
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Training (if applicable)
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Printed By
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Printed At
                        </th>
                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Notes
                        </th>
                    </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                    @php
                        $typeLabels = [
                            'training_competence'     => 'Training – Competence',
                            'training_attendance'     => 'Training – Attendance',
                            'membership'              => 'Membership',
                            'donation'                => 'Donation',
                            'volunteering'            => 'Volunteering',
                            'organisation_membership' => 'Organisation – Membership',
                            'organisation_donation'   => 'Organisation – Donation',
                        ];
                    @endphp

                    @foreach($certificatePrints as $print)
                        <tr>
                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-900">
                                @can('print_certificates')
                                <input type="checkbox"
                                       name="print_ids[]"
                                       value="{{ $print->id }}"
                                       class="print-checkbox rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                @endcan
                            </td>

                            <td class="px-3 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                @if($print->user)
                                    <a href="{{ route('users.show', $print->user->id) }}"
                                       class="text-blue-600 hover:text-blue-900">
                                        {{ $print->user->full_name }}
                                    </a>
                                    <br>
                                    <span class="text-gray-500 text-xs">{{ $print->user->user_id_reference }}</span>
                                @elseif($print->organisation)
                                    <span class="text-gray-900">{{ $print->organisation->name }}</span>
                                    <br>
                                    <span class="text-gray-500 text-xs">Organisation</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>

                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $print->user->branch->name ?? ($print->organisation->branch->name ?? 'N/A') }}
                            </td>
                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $print->user->division->name ?? 'N/A' }}
                            </td>
                            <td class="px-3 py-4 text-sm text-gray-500 whitespace-normal">
                                {{ $print->user->redCrossUnit->name ?? 'N/A' }}
                            </td>

                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $typeLabels[$print->certificate_type] ?? ucfirst(str_replace('_', ' ', $print->certificate_type)) }}
                            </td>

                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">
                                @if($print->training)
                                    Training #{{ $print->training->id }}
                                @else
                                    –
                                @endif
                            </td>

                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $print->printedBy->full_name ?? 'SYSTEM' }}
                            </td>

                            <td class="px-3 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $print->printed_at->format('Y-m-d H:i:s') }}
                            </td>

                            <td class="px-3 py-4 text-sm text-gray-500 whitespace-normal">
                                {{ \Illuminate\Support\Str::limit($print->notes, 80) }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $certificatePrints->links() }}
            </div>
        @else
            <div class="text-center py-12 bg-white shadow rounded-lg">
                <i class="fas fa-print text-4xl text-gray-400 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900">No Certificate Prints Found</h3>
                <p class="text-sm text-gray-500">Try adjusting your filter criteria.</p>
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const branchSelect        = document.getElementById('branch_id');
                const divisionSelect      = document.getElementById('division_id');
                const redCrossUnitSelect  = document.getElementById('red_cross_unit_id');

                const masterCheckbox      = document.getElementById('master-checkbox');
                const printCheckboxes     = document.querySelectorAll('.print-checkbox');
                const bulkDeleteBtn       = document.getElementById('bulk-delete-btn');
                const bulkDeletePrintIdsInput = document.getElementById('bulk-delete-print-ids');
                const selectionCounter    = document.getElementById('selection-counter');
                const selectAllBtn        = document.getElementById('select-all-prints');
                const deselectAllBtn      = document.getElementById('deselect-all-prints');
                const bulkDeleteForm      = document.getElementById('bulk-delete-form');

                function updateBulkDeleteButtonState() {
                    const checkedCheckboxes = document.querySelectorAll('.print-checkbox:checked');
                    const selectedCount     = checkedCheckboxes.length;

                    bulkDeleteBtn.disabled  = selectedCount === 0;
                    selectionCounter.textContent = `${selectedCount} prints selected`;

                    if (selectedCount === 0) {
                        masterCheckbox.checked      = false;
                        masterCheckbox.indeterminate = false;
                    } else if (selectedCount === printCheckboxes.length) {
                        masterCheckbox.checked      = true;
                        masterCheckbox.indeterminate = false;
                    } else {
                        masterCheckbox.checked      = false;
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

                selectAllBtn.addEventListener('click', function () {
                    printCheckboxes.forEach(checkbox => checkbox.checked = true);
                    updateBulkDeleteButtonState();
                });

                deselectAllBtn.addEventListener('click', function () {
                    printCheckboxes.forEach(checkbox => checkbox.checked = false);
                    updateBulkDeleteButtonState();
                });

                bulkDeleteForm.addEventListener('submit', function (event) {
                    event.preventDefault();

                    const checkedIds = Array.from(document.querySelectorAll('.print-checkbox:checked'))
                        .map(cb => cb.value);

                    if (checkedIds.length === 0) {
                        alert('Please select at least one certificate print to delete.');
                        return;
                    }

                    if (confirm(`Are you sure you want to delete ${checkedIds.length} selected certificate print records? This action is irreversible.`)) {
                        bulkDeletePrintIdsInput.value = JSON.stringify(checkedIds);
                        this.submit();
                    }
                });

                // Initial selection state
                updateBulkDeleteButtonState();

                // Dropdown logic
                const accessLevel      = "{{ $accessLevel }}";
                const userBranchId     = "{{ $userBranchId ?? '' }}";
                const userDivisionId   = "{{ $userDivisionId ?? '' }}";

                const initialSelectedBranch      = branchSelect.value;
                const initialSelectedDivision    = divisionSelect.value;
                const initialSelectedRedCrossUnit = redCrossUnitSelect.value;

                function resetAndDisableSelect(selectElement, placeholderText) {
                    selectElement.innerHTML = `<option value="">${placeholderText}</option>`;
                    selectElement.disabled  = true;
                }

                async function populateDivisions(branchId, selectedDivisionId = '') {
                    resetAndDisableSelect(redCrossUnitSelect, 'Select Division First');

                    if (divisionSelect.disabled && accessLevel === 'division') {
                        return;
                    }

                    if (!branchId) {
                        resetAndDisableSelect(divisionSelect, 'Select Branch First');
                        return;
                    }

                    divisionSelect.disabled = false;
                    divisionSelect.innerHTML = '<option value="">Loading divisions...</option>';

                    try {
                        const response  = await fetch(`/api/divisions/by-branch?branch_id=${branchId}`);
                        const divisions = await response.json();

                        if (accessLevel !== 'division') {
                            divisionSelect.innerHTML = '<option value="">All Divisions</option>';
                        } else {
                            divisionSelect.innerHTML = '';
                        }

                        divisions.forEach(division => {
                            const option      = document.createElement('option');
                            option.value      = division.id;
                            option.textContent = division.name;

                            if (String(division.id) === String(selectedDivisionId) ||
                                (accessLevel === 'division' && String(division.id) === userDivisionId)) {
                                option.selected = true;
                            }

                            divisionSelect.appendChild(option);
                        });

                        if (selectedDivisionId &&
                            Array.from(divisionSelect.options).some(option => String(option.value) === String(selectedDivisionId))) {
                            populateRedCrossUnits(selectedDivisionId, initialSelectedRedCrossUnit);
                        } else if (accessLevel === 'division' && userDivisionId) {
                            populateRedCrossUnits(userDivisionId, initialSelectedRedCrossUnit);
                        } else {
                            resetAndDisableSelect(redCrossUnitSelect, 'Select Division First');
                        }

                    } catch (error) {
                        console.error('Error fetching divisions:', error);
                        resetAndDisableSelect(divisionSelect, 'Error loading divisions');
                    }
                }

                async function populateRedCrossUnits(divisionId, selectedUnitId = '') {
                    if (redCrossUnitSelect.disabled &&
                        accessLevel === 'division' &&
                        String(divisionId) !== userDivisionId) {
                        return;
                    }

                    if (!divisionId) {
                        resetAndDisableSelect(redCrossUnitSelect, 'Select Division First');
                        return;
                    }

                    redCrossUnitSelect.disabled  = false;
                    redCrossUnitSelect.innerHTML = '<option value="">Loading units...</option>';

                    try {
                        const response = await fetch(`/red-cross-units/by-division?division_id=${divisionId}`);
                        const units    = await response.json();

                        redCrossUnitSelect.innerHTML = '<option value="">All Units</option>';
                        units.forEach(unit => {
                            const option      = document.createElement('option');
                            option.value      = unit.id;
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

                if (accessLevel === 'national') {
                    branchSelect.addEventListener('change', function () {
                        populateDivisions(this.value);
                    });

                    divisionSelect.addEventListener('change', function () {
                        populateRedCrossUnits(this.value);
                    });
                } else if (accessLevel === 'branch') {
                    divisionSelect.addEventListener('change', function () {
                        populateRedCrossUnits(this.value);
                    });
                }

                if (accessLevel === 'national' || accessLevel === 'branch') {
                    if (initialSelectedBranch) {
                        populateDivisions(initialSelectedBranch, initialSelectedDivision);
                    } else {
                        resetAndDisableSelect(divisionSelect, 'Select Branch First');
                        resetAndDisableSelect(redCrossUnitSelect, 'Select Division First');
                    }
                } else if (accessLevel === 'division' && userDivisionId) {
                    populateRedCrossUnits(userDivisionId, initialSelectedRedCrossUnit);
                }
            });
        </script>
    @endpush
</x-layouts.admin>
