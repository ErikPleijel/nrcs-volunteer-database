<x-layouts.admin title="Training Log Management">
    <x-slot name="pageHeader">
        <i class="fas fa-graduation-cap mr-3"></i>Trainings
    </x-slot>
    <x-slot name="subHeader">
        LOG NEW TRAINING
    </x-slot>




    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    <!-- Success Message -->
                    @if (session('success'))
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                            {{ session('success') }}
                        </div>
                    @endif

                    <!-- Error Messages -->
                    @if ($errors->any())
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                            <div class="font-medium">Please fix the following errors:</div>
                            <ul class="list-disc list-inside mt-2">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- User Search Section -->
                    <div id="user-search-section" @if($user) class="hidden" @endif>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Search for person
                            @if(auth()->user()->search_scope_description)
                                <span class="text-base font-normal text-gray-600">in {{ auth()->user()->search_scope_description }}</span>
                            @endif
                        </h3>


                        <div class="mb-4">

                            <div class="flex max-w-md">
                                <input type="text"
                                       id="user-search"
                                       class="flex-1 border-gray-300 rounded-l-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 pl-3"
                                       placeholder="Enter DB-code or name..."
                                       autocomplete="off">
                                <button type="button"
                                        id="search-btn"
                                        class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-r-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    Search
                                </button>
                            </div>
                        </div>

                        <!-- Search Results -->
                        <div id="search-results" class="hidden">
                            <h4 class="text-md font-medium text-gray-900 mb-3">Search Results</h4>
                            <div id="results-list" class="space-y-1 max-h-96 overflow-y-auto">
                                <!-- Results will be populated here -->
                            </div>
                        </div>

                        <!-- No Results Message -->
                        <div id="no-results" class="hidden">
                            <p class="text-gray-500 text-sm">No users found. Please refine your search.</p>
                        </div>
                    </div>

                    <!-- Training Form Section (Initially Hidden) -->
                    <div id="training-form-section" class="hidden">
                        <div class="border-t pt-6 mt-6">
                            <div class="max-w-2xl mx-auto">
                                <div class="flex justify-between items-center mb-6">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">Person Details</h3>
                                        <p class="text-sm text-gray-600">Selected Person: <span id="selected-user-name" class="font-medium"></span></p>
                                        <p class="text-sm text-gray-600">DB Reference: <span id="selected-user-reference" class="font-medium"></span></p>
                                        <p class="text-sm text-gray-600">Branch: <span id="selected-user-branch" class="font-medium"></span></p>
                                        <p class="text-sm text-gray-600">Division: <span id="selected-user-division" class="font-medium"></span></p>
                                        <p class="text-sm text-gray-600">RC Unit: <span id="selected-user-rcu" class="font-medium"></span></p>
                                    </div>
                                    <button type="button"
                                            id="change-user-btn"
                                            class="text-blue-600 hover:bg-blue-800 text-sm font-medium">
                                        Change Person
                                    </button>
                                </div>

                                <form method="POST" action="{{ route('trainings.store') }}">
                                    @csrf

                                    <!-- Hidden User ID -->
                                    <input type="hidden" name="user_id" id="selected-user-id">
                                    <!-- Hidden Branch ID -->
                                    <input type="hidden" name="branch_id" id="selected-branch-id">
                                    <!-- Hidden Division ID -->
                                    <input type="hidden" name="division_id" id="selected-division-id">

                                    <div class="entry-card">
                                        <h4 class="entry-card-title">Enter training details</h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <!-- Training Type -->
                                            <div>
                                                <label for="training_type_id" class="block text-sm font-medium text-gray-700 mb-2">
                                                    Training Type <span class="text-red-500">*</span>
                                                </label>
                                                <select name="training_type_id" id="training_type_id" required
                                                        class="entry-field @error('training_type_id') border-red-500 @enderror">
                                                    <option value="">Select Training Type</option>
                                                    @php $currentGroup = null; @endphp
                                                    @foreach($trainingTypes as $type)
                                                        @if($type->group && $currentGroup !== $type->group->group_name)
                                                            @if($currentGroup)
                                                                </optgroup>
                                                    @endif
                                                    <optgroup label="{{ $type->group->group_name }}">
                                                        @php $currentGroup = $type->group->group_name; @endphp
                                                        @endif
                                                        <option value="{{ $type->id }}" {{ old('training_type_id') == $type->id ? 'selected' : '' }}>
                                                            {{ $type->name }}
                                                        </option>
                                                        @endforeach
                                                        @if($currentGroup)
                                                    </optgroup>
                                                    @endif
                                                </select>
                                                @error('training_type_id')
                                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                                @enderror
                                                <p id="validity-reminder" class="hidden mt-2 text-xs text-amber-700">
                                                    <i class="fas fa-circle-info mr-1"></i><span id="validity-reminder-text"></span>
                                                </p>
                                            </div>

                                            <!-- Training Date -->
                                            <div>
                                                <label for="training_date" class="block text-sm font-medium text-gray-700 mb-2">
                                                    Training Date <span class="text-red-500">*</span>
                                                </label>
                                                <input type="date" name="training_date" id="training_date" value="{{ old('training_date', date('Y-m-d')) }}" required
                                                       class="entry-field @error('training_date') border-red-500 @enderror">
                                                @error('training_date')
                                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <!-- Duration -->
                                            <div>
                                                <label for="duration" class="block text-sm font-medium text-gray-700 mb-2">
                                                    Duration (days)
                                                </label>
                                                <input type="number" name="duration" id="duration" value="{{ old('duration', 1) }}"
                                                       min="1"
                                                       class="entry-field @error('duration') border-red-500 @enderror">
                                                @error('duration')
                                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <!-- Reference -->
                                            <div>
                                                <label for="reference" class="block text-sm font-medium text-gray-700 mb-2">
                                                    Reference
                                                </label>
                                                <input type="text" name="reference" id="reference"
                                                       class="entry-field @error('reference') border-red-500 @enderror"
                                                       value="{{ old('reference') }}" maxlength="255">
                                                @error('reference')
                                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Form Actions -->
                                    <div class="flex items-center justify-end space-x-4 mt-8">
                                        <a href="{{ route('trainings.index') }}"
                                           class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                            Cancel
                                        </a>
                                        <button type="submit"
                                                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                            Create Training
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if (session('warning'))
                <div class="mt-8 flex items-center gap-3 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                    <i class="fas fa-triangle-exclamation text-yellow-500"></i>{{ session('warning') }}
                </div>
            @endif
            @if (session('error'))
                <div class="mt-8 flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <i class="fas fa-circle-exclamation text-red-500"></i>{{ session('error') }}
                </div>
            @endif
        </div>
        <div class="w-full sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-8">
                <div class="p-6 text-gray-900">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Recent Trainings Registered by {{ auth()->user()->full_name }}</h3>
                        <span class="text-sm text-gray-500">Latest first</span>
                    </div>

                        @if($myRecentTrainings->count() > 0)
                            <!-- Mobile Card List -->
                            <div class="md:hidden space-y-3">
                                @foreach($myRecentTrainings as $training)
                                    <div class="border border-gray-200 rounded-lg bg-white p-4">
                                        <div class="flex justify-between items-start gap-2">
                                            <div class="min-w-0">
                                                <div class="font-medium text-gray-900 truncate">{{ $training->user->full_name ?? 'No Name' }}</div>
                                                <div class="text-xs text-gray-500">{!! $training->user->getUserIdReferenceLinkAttribute() !!}</div>
                                            </div>
                                        </div>
                                        <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
                                            <div>
                                                <dt class="text-xs uppercase text-gray-400">Training Type</dt>
                                                <dd class="text-gray-900">{{ $training->trainingType->name ?? 'N/A' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs uppercase text-gray-400">Date</dt>
                                                <dd class="text-gray-900">{{ \Carbon\Carbon::parse($training->training_date)->format('M d, Y') }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs uppercase text-gray-400">Duration</dt>
                                                <dd class="text-gray-900">{{ $training->formatted_duration }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs uppercase text-gray-400">Expiry Date</dt>
                                                <dd class="text-gray-900">{{ $training->expiry_date?->format('M d, Y') ?? 'No expiry' }}</dd>
                                            </div>
                                            @if($training->reference)
                                                <div>
                                                    <dt class="text-xs uppercase text-gray-400">Reference</dt>
                                                    <dd class="text-gray-900">{{ $training->reference }}</dd>
                                                </div>
                                            @endif
                                        </dl>
                                        <div class="mt-3">
                                            <x-recent-log-actions
                                                :status="$training->approval_status"
                                                :rejection-reason="$training->rejection_reason"
                                                :review-url="route('trainings.review', $training->id)"
                                                :withdraw-url="route('trainings.withdraw', $training->id)" />
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Desktop Table -->
                            <div class="hidden md:block bg-white border border-gray-200 rounded-lg shadow-sm overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Volunteer</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">DB-Number</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Training Type</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expiry Date</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($myRecentTrainings as $training)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                {{ $training->user->full_name ?? 'No Name' }}
                                            </td>
                                            <td class="px-3 py-2 text-sm">
                                                {!! $training->user->getUserIdReferenceLinkAttribute() !!}
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                {{ $training->trainingType->name ?? 'N/A' }}
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                {{ \Carbon\Carbon::parse($training->training_date)->format('M d, Y') }}
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                {{ $training->formatted_duration }}
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                {{ $training->expiry_date?->format('M d, Y') ?? 'No expiry' }}
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                <div>{{ $training->training_reference }}</div>
                                                @if($training->reference)
                                                    <div class="text-xs text-gray-500"><i class="fas fa-hashtag mr-1"></i>{{ $training->reference }}</div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-sm">
                                                <x-approval-status-badge :status="$training->approval_status" />
                                                @if($training->approval_status === 'rejected' && $training->rejection_reason)
                                                    <div class="text-xs text-red-600 mt-1"><i class="fas fa-comment-dots mr-1"></i>{{ $training->rejection_reason }}</div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                <div class="flex items-center gap-3">
                                                    <a href="{{ route('trainings.review', $training->id) }}"
                                                       class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs font-medium transition-colors">
                                                        View
                                                    </a>
                                                    @if($training->approval_status === 'pending')
                                                        <x-withdraw-button :url="route('trainings.withdraw', $training->id)" />
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            @if($myRecentTrainings->hasPages())
                                <div class="mt-4">
                                    {{ $myRecentTrainings->links('pagination::tailwind', ['my_trainings']) }}
                                </div>
                            @endif
                        @else
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center">
                                <div class="text-gray-400 mb-2">
                                    <svg class="mx-auto h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <p class="text-gray-600 text-sm">No trainings registered by you yet.</p>
                            </div>
                        @endif
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Map of training type ID -> validity_years_limit (null if not set)
            const trainingTypeValidityMap = @json(
                $trainingTypes->mapWithKeys(fn($type) => [$type->id => $type->validity_years_limit])
            );

            const trainingTypeSelect = document.getElementById('training_type_id');
            const validityReminder = document.getElementById('validity-reminder');
            const validityReminderText = document.getElementById('validity-reminder-text');

            if (trainingTypeSelect && validityReminder && validityReminderText) {
                function updateValidityReminder() {
                    const selectedId = trainingTypeSelect.value;
                    const limit = trainingTypeValidityMap[selectedId];
                    if (!selectedId) { validityReminder.classList.add('hidden'); return; }
                    if (limit !== null && limit !== undefined) {
                        const years = Number(limit);
                        validityReminderText.textContent =
                            `Heads up: this training type expires ${years} year${years === 1 ? '' : 's'} after the training date.`;
                    } else {
                        validityReminderText.textContent = 'This training type has no expiry.';
                    }
                    validityReminder.classList.remove('hidden');
                }
                trainingTypeSelect.addEventListener('change', updateValidityReminder);
                updateValidityReminder(); // reflect any pre-selected / old() value on load
            }

            const userSearch = document.getElementById('user-search');
            const searchBtn = document.getElementById('search-btn');
            const searchResults = document.getElementById('search-results');
            const resultsList = document.getElementById('results-list');
            const noResults = document.getElementById('no-results');
            const userSearchSection = document.getElementById('user-search-section');
            const activityFormSection = document.getElementById('activity-form-section'); // This will be null on the training page
            const trainingFormSection = document.getElementById('training-form-section'); // This should be present on the training page
            const selectedUserName = document.getElementById('selected-user-name');
            const selectedUserId = document.getElementById('selected-user-id');
            const selectedUserReference = document.getElementById('selected-user-reference');
            const selectedUserBranch = document.getElementById('selected-user-branch');
            const selectedUserDivision = document.getElementById('selected-user-division');
            const selectedBranchId = document.getElementById('selected-branch-id');
            const selectedDivisionId = document.getElementById('selected-division-id');
            const changeUserBtn = document.getElementById('change-user-btn');
            const preselectedUser = @json($user ?? null);


            // Added for loading and error messages
            const searchStatusMessage = document.createElement('p');
            searchStatusMessage.className = 'text-center text-gray-500 text-sm mt-3 hidden';
            userSearchSection.querySelector('div.mb-4').after(searchStatusMessage); // Insert after the search input div

            // Red Cross Unit related elements (Activities specific) - these will be null on training page
            const userRedCrossUnitSection = document.getElementById('user-red-cross-unit-section');
            const userRedCrossUnitNameSpan = document.getElementById('user-red-cross-unit-name');
            const userRedCrossUnitIdInput = document.getElementById('user-red-cross-unit-id');
            const useUserRedCrossUnitCheckbox = document.getElementById('use_user_red_cross_unit');

            // Task Force related elements (Activities specific) - these will be null on training page
            const userTaskForceSection = document.getElementById('user-task-force-section');
            const useUserTaskForceCheckbox = document.getElementById('use_user_task_force');
            const taskForceSelectContainer = document.getElementById('task-force-select-container');
            const selectedTaskForceIdSelect = document.getElementById('selected_task_force_id');

            // Not Assigned checkbox (Activities specific) - this will be null on training page
            const notAssignedCheckbox = document.getElementById('not_assigned');

            // Mutual exclusion logic for assignment options (Activities specific)
            // This function is only relevant for activities, so we'll guard its execution.
            function handleAssignmentCheckboxChange(changedCheckbox) {
                if (!activityFormSection) return; // Only run if activityFormSection exists (i.e., on activities page)

                if (changedCheckbox.checked) {
                    if (changedCheckbox === useUserRedCrossUnitCheckbox) {
                        useUserTaskForceCheckbox.checked = false;
                        if (taskForceSelectContainer) taskForceSelectContainer.classList.add('hidden');
                        if (selectedTaskForceIdSelect) selectedTaskForceIdSelect.value = '';
                        if (notAssignedCheckbox) notAssignedCheckbox.checked = false;
                    } else if (changedCheckbox === useUserTaskForceCheckbox) {
                        if (useUserRedCrossUnitCheckbox) useUserRedCrossUnitCheckbox.checked = false;
                        if (notAssignedCheckbox) notAssignedCheckbox.checked = false;
                        if (taskForceSelectContainer) taskForceSelectContainer.classList.remove('hidden');
                    } else if (changedCheckbox === notAssignedCheckbox) {
                        if (useUserRedCrossUnitCheckbox) useUserRedCrossUnitCheckbox.checked = false;
                        if (useUserTaskForceCheckbox) useUserTaskForceCheckbox.checked = false;
                        if (taskForceSelectContainer) taskForceSelectContainer.classList.add('hidden');
                        if (selectedTaskForceIdSelect) selectedTaskForceIdSelect.value = '';
                    }
                } else {
                    // If a checkbox is unchecked, and it's not the "Not assigned" one,
                    // and no other assignment is active, then tick "Not assigned".
                    if (changedCheckbox !== notAssignedCheckbox &&
                        (!useUserRedCrossUnitCheckbox || !useUserRedCrossUnitCheckbox.checked) &&
                        (!useUserTaskForceCheckbox || !useUserTaskForceCheckbox.checked)) {
                        if (notAssignedCheckbox) notAssignedCheckbox.checked = true;
                    }
                    // If task force checkbox is unchecked, hide its dropdown
                    if (changedCheckbox === useUserTaskForceCheckbox) {
                        if (taskForceSelectContainer) taskForceSelectContainer.classList.add('hidden');
                        if (selectedTaskForceIdSelect) selectedTaskForceIdSelect.value = '';
                    }
                }
            }

            // Search function
            function searchUsers() {
                const query = userSearch.value.trim();
                resultsList.innerHTML = ''; // Clear previous results immediately
                searchResults.classList.add('hidden');
                noResults.classList.add('hidden');
                searchStatusMessage.classList.add('hidden'); // Hide any previous status

                if (query.length < 2) {
                    searchStatusMessage.textContent = 'Please enter at least 2 characters.';
                    searchStatusMessage.classList.remove('hidden');
                    return;
                }

                searchStatusMessage.textContent = 'Searching...';
                searchStatusMessage.classList.remove('hidden');

                // Determine the correct route based on the current page context
                const currentPath = window.location.pathname;
                let searchRoute;

                if (currentPath.includes('/activities/create')) {
                    searchRoute = '{{ route('activities.search-users') }}';
                } else if (currentPath.includes('/trainings/create')) {
                    searchRoute = '{{ route('trainings.search-users') }}';
                } else {
                    console.error('Unknown page context for user search.');
                    searchStatusMessage.textContent = 'Error: Search functionality not configured for this page.';
                    searchStatusMessage.classList.remove('hidden');
                    return;
                }


                fetch(`${searchRoute}?query=${encodeURIComponent(query)}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(users => {
                        searchStatusMessage.classList.add('hidden'); // Hide searching message

                        if (users.length === 0) {
                            noResults.classList.remove('hidden');
                            searchResults.classList.add('hidden');
                            return;
                        }

                        noResults.classList.add('hidden');
                        users.forEach(user => {
                            const fullName = [user.first_name, user.middle_name, user.last_name]
                                .filter(Boolean).join(' ');

                            // Generate DB-code for display
                            const branchCode = user.branch && user.branch.code ? user.branch.code.toUpperCase() :
                                (user.branch && user.branch.name ? user.branch.name.substring(0, 3).toUpperCase() : 'UNK');
                            const dbCode = `DB-${user.id}/${branchCode}`;

                            const actionHtml = user.lifecycle_status === 'archived'
                                ? `<span class="text-red-600 font-semibold text-sm px-4 py-2">Archived</span>`
                                : `<button type="button" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium select-user-btn" data-user='${JSON.stringify(user)}' data-fullname='${fullName}'>Select</button>`;

                            const userItem = document.createElement('div');
                            userItem.className = 'flex items-center justify-between p-3 border border-gray-200 rounded-md hover:bg-gray-50 cursor-pointer' + (user.lifecycle_status === 'archived' ? ' opacity-70' : '');
                            userItem.innerHTML = `
                            <div class="flex-1">
                                <div class="font-medium text-gray-900">${fullName}</div>
                                <div class="text-sm text-gray-600">
                                    DB-code: ${dbCode} • ${user.email || 'No email'}
                                    ${user.telephone1 ? '• ' + user.telephone1 : ''}
                                </div>
                                <div class="text-xs text-gray-500">
                                    ${user.branch ? user.branch.name : 'No branch'}
                                    ${user.division ? ' - ' + user.division.name : ''}
                                    ${user.red_cross_unit ? ' - RCU: ' + user.red_cross_unit.name : ''}
                                    ${user.task_forces && user.task_forces.length > 0 ? ' - TFs: ' + user.task_forces.map(tf => tf.name).join(', ') : ''}
                                </div>
                            </div>
                            ${actionHtml}
                        `;

                            resultsList.appendChild(userItem);
                        });

                        searchResults.classList.remove('hidden');
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        searchStatusMessage.textContent = 'An error occurred during search. Please try again.';
                        searchStatusMessage.classList.remove('hidden');
                        noResults.classList.add('hidden');
                        searchResults.classList.add('hidden');
                    });
            }

            // Select user function
            function selectUser(user, fullName) {
                selectedUserId.value = user.id;
                selectedUserName.textContent = fullName;

                const branchCode = user.branch && user.branch.code ? user.branch.code.toUpperCase() :
                    (user.branch && user.branch.name ? user.branch.name.substring(0, 3).toUpperCase() : 'UNK');
                selectedUserReference.textContent = `DB-${user.id}/${branchCode}`;

                selectedUserBranch.textContent = user.branch ? user.branch.name : 'No branch assigned';
                selectedUserDivision.textContent = user.division ? user.division.name : 'No division assigned';

                selectedBranchId.value = user.branch_id || '';
                selectedDivisionId.value = user.division_id || '';

                // Handle Red Cross Unit display in the form header for both activity and training forms
                const selectedUserRcuSpan = document.getElementById('selected-user-rcu');
                if (selectedUserRcuSpan) {
                    selectedUserRcuSpan.textContent = user.red_cross_unit ? user.red_cross_unit.name : 'N/A';
                }

                // Activities specific assignment options reset and display logic
                // These elements and logic only exist in the activity form.
                if (activityFormSection) { // This block will be skipped on training page as activityFormSection is null
                    if (useUserRedCrossUnitCheckbox) useUserRedCrossUnitCheckbox.checked = false;
                    if (userRedCrossUnitSection) userRedCrossUnitSection.classList.add('hidden'); // Ensure RCU section is hidden by default
                    if (userRedCrossUnitNameSpan) userRedCrossUnitNameSpan.textContent = '';
                    if (userRedCrossUnitIdInput) userRedCrossUnitIdInput.value = '';

                    if (useUserTaskForceCheckbox) useUserTaskForceCheckbox.checked = false;
                    if (userTaskForceSection) userTaskForceSection.classList.add('hidden'); // Ensure Task Force section is hidden by default
                    if (taskForceSelectContainer) taskForceSelectContainer.classList.add('hidden');
                    if (selectedTaskForceIdSelect) selectedTaskForceIdSelect.innerHTML = '<option value="">Select a Task Force</option>'; // Clear dropdown
                    if (selectedTaskForceIdSelect) selectedTaskForceIdSelect.value = '';

                    // Handle Red Cross Unit for the selected user (Activities specific)
                    if (userRedCrossUnitNameSpan && userRedCrossUnitIdInput && userRedCrossUnitSection && user.red_cross_unit) {
                        userRedCrossUnitNameSpan.textContent = user.red_cross_unit.name;
                        userRedCrossUnitIdInput.value = user.red_cross_unit.id;
                        userRedCrossUnitSection.classList.remove('hidden'); // Show RCU section if data exists
                    }

                    // Handle Task Forces for the selected user (Activities specific)
                    if (selectedTaskForceIdSelect && userTaskForceSection && user.task_forces && user.task_forces.length > 0) {
                        selectedTaskForceIdSelect.innerHTML = '<option value="">Select a Task Force</option>'; // Ensure clear before populating
                        user.task_forces.forEach(tf => {
                            const option = document.createElement('option');
                            option.value = tf.id;
                            option.textContent = tf.name;
                            selectedTaskForceIdSelect.appendChild(option);
                        });
                        userTaskForceSection.classList.remove('hidden'); // Show TF section if data exists
                    }

                    // After the form is visible, set the default assignment state for activities.
                    if (notAssignedCheckbox) { // Activities specific
                        notAssignedCheckbox.checked = true;
                        handleAssignmentCheckboxChange(notAssignedCheckbox); // Enforce mutual exclusion
                    }
                } // End if (activityFormSection)

                // Show activity/training form, hide search
                userSearchSection.classList.add('hidden');
                if (window.location.pathname.includes('/activities/create') && activityFormSection) {
                    activityFormSection.classList.remove('hidden');
                } else if (window.location.pathname.includes('/trainings/create') && trainingFormSection) {
                    trainingFormSection.classList.remove('hidden');
                }
            } // End selectUser function

            // Change user function
            function changeUser() {
                userSearchSection.classList.remove('hidden');
                if (activityFormSection) activityFormSection.classList.add('hidden'); // Hide activity form
                if (trainingFormSection) trainingFormSection.classList.add('hidden'); // Hide training form too
                userSearch.value = '';
                resultsList.innerHTML = ''; // Clear results when changing user
                searchResults.classList.add('hidden');
                noResults.classList.add('hidden');
                searchStatusMessage.classList.add('hidden'); // Hide status message

                // Reset all assignment options and set 'Not assigned' as default (Activities specific)
                // This block is only relevant for activities, so guard its execution.
                if (activityFormSection) { // This block will be skipped on training page as activityFormSection is null
                    if (useUserRedCrossUnitCheckbox) useUserRedCrossUnitCheckbox.checked = false;
                    if (userRedCrossUnitSection) userRedCrossUnitSection.classList.add('hidden');
                    if (userRedCrossUnitNameSpan) userRedCrossUnitNameSpan.textContent = '';
                    if (userRedCrossUnitIdInput) userRedCrossUnitIdInput.value = '';

                    if (useUserTaskForceCheckbox) useUserTaskForceCheckbox.checked = false;
                    if (userTaskForceSection) userTaskForceSection.classList.add('hidden');
                    if (taskForceSelectContainer) taskForceSelectContainer.classList.add('hidden');
                    if (selectedTaskForceIdSelect) selectedTaskForceIdSelect.innerHTML = '<option value="">Select a Task Force</option>';
                    if (selectedTaskForceIdSelect) selectedTaskForceIdSelect.value = '';

                    if (notAssignedCheckbox) { // Activities specific
                        notAssignedCheckbox.checked = true; // Default to not assigned when changing user
                        handleAssignmentCheckboxChange(notAssignedCheckbox); // Apply mutual exclusion
                    }
                } // End if (activityFormSection)
            } // End changeUser function

            // Event delegation for dynamically created select buttons
            resultsList.addEventListener('click', function(e) {
                if (e.target.classList.contains('select-user-btn')) {
                    const user = JSON.parse(e.target.getAttribute('data-user'));
                    const fullName = e.target.getAttribute('data-fullname');
                    selectUser(user, fullName);
                }
            });

            // Event listeners for search
            searchBtn.addEventListener('click', searchUsers);
            userSearch.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchUsers();
                }
            });

            // Auto-search on input change after a delay
            let searchTimeout;
            userSearch.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    searchUsers();
                }, 300); // 300ms debounce
            });

            changeUserBtn.addEventListener('click', changeUser);

            // Activities specific event listeners for assignment options
            // Only attach if elements exist (i.e., on the activities create page)
            if (useUserRedCrossUnitCheckbox) { // This will be skipped on training page as useUserRedCrossUnitCheckbox is null
                useUserRedCrossUnitCheckbox.addEventListener('change', function() {
                    handleAssignmentCheckboxChange(this);
                });
            }
            if (useUserTaskForceCheckbox) { // This will be skipped on training page as useUserTaskForceCheckbox is null
                useUserTaskForceCheckbox.addEventListener('change', function() {
                    handleAssignmentCheckboxChange(this);
                });
            }
            if (notAssignedCheckbox) { // This will be skipped on training page as notAssignedCheckbox is null
                notAssignedCheckbox.addEventListener('change', function() {
                    handleAssignmentCheckboxChange(this);
                });
            }

            // If a task force is explicitly selected, make sure its checkbox is ticked (Activities specific)
            // Only attach if elements exist (i.e., on the activities create page)
            if (selectedTaskForceIdSelect) { // This will be skipped on training page as selectedTaskForceIdSelect is null
                selectedTaskForceIdSelect.addEventListener('change', function() {
                    if (this.value) { // If a task force is selected
                        if (useUserTaskForceCheckbox) useUserTaskForceCheckbox.checked = true;
                        if (useUserRedCrossUnitCheckbox) useUserRedCrossUnitCheckbox.checked = false; // Uncheck RCU
                        if (notAssignedCheckbox) notAssignedCheckbox.checked = false; // Uncheck Not assigned
                    } else { // If "Select a Task Force" is chosen (empty value)
                        if (useUserTaskForceCheckbox) useUserTaskForceCheckbox.checked = false;
                        // If no RCU is checked either, then tick Not assigned
                        if (useUserRedCrossUnitCheckbox && !useUserRedCrossUnitCheckbox.checked && notAssignedCheckbox) {
                            notAssignedCheckbox.checked = true;
                        }
                    }
                    if (useUserTaskForceCheckbox) handleAssignmentCheckboxChange(useUserTaskForceCheckbox); // Re-evaluate state
                });

                // Initial state check for task force dropdown visibility if checkbox is already checked (e.g., old input)
                if (useUserTaskForceCheckbox && useUserTaskForceCheckbox.checked) {
                    if (taskForceSelectContainer) taskForceSelectContainer.classList.remove('hidden');
                } else {
                    if (taskForceSelectContainer) taskForceSelectContainer.classList.add('hidden');
                }
            }
            if (preselectedUser) {
                const fullName = [preselectedUser.first_name, preselectedUser.middle_name, preselectedUser.last_name]
                    .filter(Boolean).join(' ');
                selectUser(preselectedUser, fullName);
            }

        }); // End DOMContentLoaded
    </script>
</x-layouts.admin>
