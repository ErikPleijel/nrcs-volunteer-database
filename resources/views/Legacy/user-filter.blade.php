<x-layouts.admin title="Filter Users for Messaging">
    <x-slot name="pageHeader">
        <i class="fas fa-paper-plane mr-3"></i> Bulk Email/SMS
    </x-slot>
    <x-slot name="subHeader">
        <i class="fas fa-filter mr-3"></i>  STEP 1: Make filter
    </x-slot>

    <section class="dev-note">
        <span class="dev-note-label">DEV NOTE<br>/Erik</span>
        <p>This function will be developed more...</p>
    </section>

    <div class="container mx-auto px-4 py-6">
        <div class="flex justify-end mb-4">
            <a href="{{ route('messaging.campaigns.index') }}"
               class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center transition-colors duration-200">
                <i class="fas fa-list-alt mr-2"></i> View All Campaigns
            </a>
        </div>

        <!-- Filter Section -->
        <div class="bg-white rounded-lg shadow-sm mb-6 border border-gray-200">
            <div class="p-6">
                <form method="GET" action="{{ route('messaging.filter-users') }}" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                        <!-- Search -->
                        <div class="md:col-span-8">
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-search mr-1 text-gray-400"></i>Search Users
                            </label>
                            <input type="text"
                                   name="search"
                                   id="search"
                                   value="{{ request('search') }}"
                                   placeholder="Search by name, email, phone, or DB-Code..."
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
                        </div>

                        <!-- Sort By -->
                        <div class="md:col-span-4">
                            <label for="sort_by" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-sort mr-1 text-gray-400"></i>Sort By
                            </label>
                            <select name="sort_by"
                                    id="sort_by"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
                                <option value="name_asc" {{ request('sort_by', 'name_asc') == 'name_asc' ? 'selected' : '' }}>
                                    Name (A-Z)
                                </option>
                                <option value="name_desc" {{ request('sort_by') == 'name_desc' ? 'selected' : '' }}>
                                    Name (Z-A)
                                </option>
                                <option value="created_at_desc" {{ request('sort_by') == 'created_at_desc' ? 'selected' : '' }}>
                                    Newest First
                                </option>
                                <option value="created_at_asc" {{ request('sort_by') == 'created_at_asc' ? 'selected' : '' }}>
                                    Oldest First
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Advanced Filters -->
                    <div class="border-t pt-4 mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Branch Filter -->
                        <div>
                            <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Branch
                            </label>
                            @if($accessLevel === 'national')
                                <select name="branch_id"
                                        id="branch_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">All Branches</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ (string)$branch->id === request('branch_id') ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                @php
                                    $selectedBranchName = $branches->firstWhere('id', $userBranchId)->name ?? 'N/A';
                                @endphp
                                <input type="text"
                                       value="{{ $selectedBranchName }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed"
                                       disabled>
                                <input type="hidden" name="branch_id" value="{{ $userBranchId }}">
                            @endif
                        </div>

                        <!-- Division Filter -->
                        <div>
                            <label for="division_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Division
                            </label>
                            @if($accessLevel === 'national' || $accessLevel === 'branch')
                                <select name="division_id"
                                        id="division_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    {{ $accessLevel === 'national' && !request('branch_id') ? 'disabled' : '' }}
                                    {{ $accessLevel === 'branch' && !$userBranchId ? 'disabled' : '' }}>
                                    <option value="">
                                        @if($accessLevel === 'national' && !request('branch_id'))
                                            Select a Branch first
                                        @elseif($accessLevel === 'branch' && !$userBranchId)
                                            Error: Branch ID not set
                                        @else
                                            All Divisions
                                        @endif
                                    </option>
                                    @foreach($divisions as $division)
                                        <option value="{{ $division->id }}" {{ (string)$division->id === request('division_id') ? 'selected' : '' }}>
                                            {{ $division->name }}
                                        </option>
                                    @endforeach
                                </select>
                            @else {{-- $accessLevel === 'division' --}}
                            @php
                                $selectedDivisionName = $divisions->firstWhere('id', $userDivisionId)->name ?? 'N/A';
                            @endphp
                            <input type="text"
                                   value="{{ $selectedDivisionName }}"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-100 cursor-not-allowed"
                                   disabled>
                            <input type="hidden" name="division_id" value="{{ $userDivisionId }}">
                            @endif
                        </div>

                        <!-- Red Cross Unit Filter -->
                        <div>
                            <label for="red_cross_unit_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Red Cross Unit
                            </label>
                            <select name="red_cross_unit_id"
                                    id="red_cross_unit_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                {{ !request('division_id') && ($accessLevel === 'national' || $accessLevel === 'branch') ? 'disabled' : '' }}
                                {{ $accessLevel === 'division' && !$userDivisionId ? 'disabled' : '' }}>
                                <option value="">
                                    @if(($accessLevel === 'national' || $accessLevel === 'branch') && !request('division_id'))
                                        Select a Division first
                                    @elseif($accessLevel === 'division' && !$userDivisionId)
                                        Error: Division ID not set
                                    @else
                                        All Units
                                    @endif
                                </option>
                                @foreach($redCrossUnits as $redCrossUnit)
                                    <option value="{{ $redCrossUnit->id }}" {{ (string)$redCrossUnit->id === request('red_cross_unit_id') ? 'selected' : '' }}>
                                        {{ $redCrossUnit->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Membership Fee Type Filter -->
                        <div>
                            <label for="membership_fee_name" class="block text-sm font-medium text-gray-700 mb-2">Membership Type</label>
                            <select name="membership_fee_name" id="membership_fee_name"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Membership Types</option>
                                @foreach($membershipFees as $fee)
                                    <option value="{{ $fee->name }}" {{ $fee->name === request('membership_fee_name') ? 'selected' : '' }}>
                                        {{ $fee->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Membership Validity Status Filter -->
                        <div>
                            <label for="validity_status" class="block text-sm font-medium text-gray-700 mb-2">Membership Status</label>
                            <select name="validity_status" id="validity_status"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Any Status</option>
                                <option value="valid" {{ 'valid' === request('validity_status') ? 'selected' : '' }}>Valid Memberships</option>
                                <option value="expiring_soon" {{ 'expiring_soon' === request('validity_status') ? 'selected' : '' }}>Expiring Soon (30 days)</option>
                                <option value="expired" {{ 'expired' === request('validity_status') ? 'selected' : '' }}>Expired Memberships</option>
                            </select>
                        </div>

                        <!-- My Records Checkbox -->
                        <div class="flex items-center">
                            <input type="checkbox" name="my_records" id="my_records" value="1"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                {{ request('my_records') === '1' ? 'checked' : '' }}>
                            <label for="my_records" class="ml-2 block text-sm text-gray-900">
                                Show My Records Only
                            </label>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-wrap gap-2 pt-4">
                        <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center transition-colors duration-200">
                            <i class="fas fa-filter mr-2"></i>Apply Filters
                        </button>

                        <a href="{{ route('messaging.filter-users') }}"
                           class="font-medium px-6 py-2 rounded-lg shadow-sm transition-colors duration-200
                                  {{ $hasFilters ? 'bg-gray-500 hover:bg-gray-600 text-white' : 'bg-gray-300 text-gray-500 cursor-not-allowed' }}"
                            {{ $hasFilters ? '' : 'aria-disabled="true" tabindex="-1"' }}>
                            <i class="fas fa-times mr-2"></i>Clear All
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Filter Results Info -->
        @if($hasFilters)
            <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded mb-6">
                <div class="flex items-center justify-between">
                    <div class="text-sm">
                        @if(request('search'))
                            <p>Showing results for: <strong>"{{ request('search') }}"</strong></p>
                        @endif

                        @if(request('branch_id'))
                            @php
                                $selectedBranch = $branches->firstWhere('id', request('branch_id'));
                            @endphp
                            <p class="mt-1">
                                Filtered by branch: <strong>{{ $selectedBranch ? $selectedBranch->name : 'Unknown' }}</strong>
                            </p>
                        @endif

                        @if(request('division_id'))
                            @php
                                $selectedDivision = $divisions->firstWhere('id', request('division_id'));
                            @endphp
                            <p class="mt-1">
                                Filtered by division: <strong>{{ $selectedDivision ? $selectedDivision->name : 'Unknown' }}</strong>
                            </p>
                        @endif

                        @if(request('red_cross_unit_id'))
                            @php
                                $selectedUnit = $redCrossUnits->firstWhere('id', request('red_cross_unit_id'));
                            @endphp
                            <p class="mt-1">
                                Filtered by Red Cross Unit: <strong>{{ $selectedUnit ? $selectedUnit->name : 'Unknown' }}</strong>
                            </p>
                        @endif
                        @if(request('membership_fee_name'))
                            <p class="mt-1">
                                Filtered by Membership Type: <strong>{{ request('membership_fee_name') }}</strong>
                            </p>
                        @endif
                        @if(request('validity_status'))
                            <p class="mt-1">
                                Filtered by Membership Status: <strong>
                                    @if(request('validity_status') === 'valid') Valid Memberships
                                    @elseif(request('validity_status') === 'expiring_soon') Expiring Soon (30 days)
                                    @elseif(request('validity_status') === 'expired') Expired Memberships
                                    @endif
                                </strong>
                            </p>
                        @endif
                        @if(request('my_records'))
                            <p class="mt-1">Showing <strong>My Records Only</strong></p>
                        @endif


                        <p class="mt-1">
                            ({{ $users->total() }} {{ Str::plural('user', $users->total()) }} found)
                        </p>
                    </div>
                </div>
            </div>
        @else
            <div class="bg-gray-50 border border-gray-200 text-gray-700 px-4 py-3 rounded mb-6">
                <div class="text-sm">
                    <p>Showing all user records</p>
                    <p class="mt-1">({{ number_format($totalRecords) }} total {{ Str::plural('record', $totalRecords) }})</p>
                </div>
            </div>
        @endif

        <!-- Users Table -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
            @if($users->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                        </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($users as $user)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <img class="h-10 w-10 rounded-full object-cover" src="{{ $user->profile_photo_url }}" alt="{{ $user->full_name }}">
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ $user->full_name }}</div>
                                            <div class="text-sm text-gray-500">{{ $user->user_id_reference }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $user->email }}</div>
                                    <div class="text-sm text-gray-500">{{ $user->telephone1 }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $user->branch->name ?? 'N/A' }} <br>
                                    {{ $user->division->name ?? '' }} <br>
                                    {{ $user->redCrossUnit->name ?? '' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $user->role_display_name }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-6 py-3 bg-gray-50 border-t border-gray-200">
                    {{ $users->appends(request()->query())->links() }}
                </div>
            @else
                <div class="px-6 py-12 text-center">
                    <i class="fas fa-users-slash text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No users found</h3>
                    <p class="text-gray-500 mb-4">Try adjusting your search or filter criteria.</p>
                </div>
            @endif
        </div>

        <div class="flex justify-end mt-6">
            <form action="{{ route('messaging.compose') }}" method="GET">
                @foreach(request()->except(['page', '_token']) as $key => $value)
                    @if(is_array($value))
                        @foreach($value as $item)
                            <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <input type="hidden" name="source_module" value="user-filter">
                <button type="submit" class="btn-primary" {{ $users->isEmpty() ? 'disabled' : '' }}>
                    <i class="fas fa-arrow-right mr-2"></i> Proceed with {{ $users->total() }} Selected Users
                </button>
            </form>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const branchSelect = document.getElementById('branch_id');
            const divisionSelect = document.getElementById('division_id');
            const redCrossUnitSelect = document.getElementById('red_cross_unit_id');

            const accessLevel = "{{ $accessLevel }}";
            const initialSelectedBranch = "{{ request('branch_id', '') }}";
            const initialSelectedDivision = "{{ request('division_id', '') }}";
            const initialSelectedRedCrossUnit = "{{ request('red_cross_unit_id', '') }}";

            function resetAndDisableSelect(selectElement, placeholderText) {
                selectElement.innerHTML = `<option value="">${placeholderText}</option>`;
                selectElement.disabled = true;
            }

            async function populateDivisions(branchId, selectedDivisionId = '') {
                // Only proceed if the divisionSelect is not disabled by Blade
                if (divisionSelect.disabled && accessLevel !== 'national') {
                    return;
                }

                resetAndDisableSelect(divisionSelect, 'Select a Branch first');
                resetAndDisableSelect(redCrossUnitSelect, 'Select a Division first');

                if (!branchId) {
                    return;
                }

                divisionSelect.disabled = false;
                divisionSelect.innerHTML = '<option value="">All Divisions</option>';

                try {
                    const response = await fetch(`/divisions/by-branch?branch_id=${branchId}`);
                    const divisions = await response.json();

                    divisions.forEach(division => {
                        const option = document.createElement('option');
                        option.value = division.id;
                        option.textContent = division.name;
                        divisionSelect.appendChild(option);
                    });

                    // Set selected division and then populate Red Cross Units
                    if (selectedDivisionId && Array.from(divisionSelect.options).some(option => option.value == selectedDivisionId)) {
                        divisionSelect.value = selectedDivisionId;
                        await populateRedCrossUnits(selectedDivisionId, initialSelectedRedCrossUnit);
                    } else {
                        // If selectedDivisionId is not provided or not found, make sure Red Cross units are reset
                        resetAndDisableSelect(redCrossUnitSelect, 'Select a Division first');
                    }

                } catch (error) {
                    console.error('Error fetching divisions:', error);
                }
            }

            async function populateRedCrossUnits(divisionId, selectedUnitId = '') {
                // Only proceed if the redCrossUnitSelect is not disabled by Blade
                if (redCrossUnitSelect.disabled && accessLevel !== 'national' && accessLevel !== 'branch') {
                    return;
                }

                resetAndDisableSelect(redCrossUnitSelect, 'Select a Division first');

                if (!divisionId) {
                    return;
                }

                redCrossUnitSelect.disabled = false;
                redCrossUnitSelect.innerHTML = '<option value="">All Units</option>';

                try {
                    const response = await fetch(`/red-cross-units/by-division?division_id=${divisionId}`);
                    const units = await response.json();

                    units.forEach(unit => {
                        const option = document.createElement('option');
                        option.value = unit.id;
                        option.textContent = unit.name;
                        redCrossUnitSelect.appendChild(option);
                    });

                    if (selectedUnitId && Array.from(redCrossUnitSelect.options).some(option => option.value == selectedUnitId)) {
                        redCrossUnitSelect.value = selectedUnitId;
                    }
                } catch (error) {
                    console.error('Error fetching Red Cross Units:', error);
                }
            }

            // Add event listeners only if the select element is not disabled by Blade
            if (!branchSelect.disabled) {
                branchSelect.addEventListener('change', function () {
                    populateDivisions(this.value);
                });
            }

            if (!divisionSelect.disabled) {
                divisionSelect.addEventListener('change', function () {
                    populateRedCrossUnits(this.value);
                });
            }

            // Initial population on page load
            // Only populate divisions if the branchSelect is not disabled AND an initial branch is selected
            if (!branchSelect.disabled && initialSelectedBranch) {
                populateDivisions(initialSelectedBranch, initialSelectedDivision);
            } else if (branchSelect.disabled && accessLevel === 'branch') {
                // If branch is disabled for 'branch' level, divisions for that branch are already in $divisions.
                // We still need to potentially populate Red Cross Units based on the initial division.
                if (initialSelectedDivision) {
                    populateRedCrossUnits(initialSelectedDivision, initialSelectedRedCrossUnit);
                }
            } else if (divisionSelect.disabled && accessLevel === 'division') {
                // If division is disabled for 'division' level, red cross units for that division are already in $redCrossUnits.
                // Nothing to do for initial population via JS here for units, as they are already set in Blade.
            }

        });
    </script>
</x-layouts.admin>
