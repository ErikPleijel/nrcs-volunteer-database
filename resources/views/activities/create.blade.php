<x-layouts.admin title="Volunteering Log Management">

    <x-slot name="pageHeader">
        <i class="fas fa-hands-helping mr-3"></i> Volunteer Activity Log
    </x-slot>
    <x-slot name="subHeader">
        Log new volunteering
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
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Search for volunteer
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
                            <p class="text-gray-500 text-sm">No volunteers found matching your search.</p>
                            <p class="mt-1 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2">
                                <i class="fas fa-triangle-exclamation mr-1"></i>
                                Only persons assigned to a Red Cross Unit can have volunteering recorded.
                                <strong>If the person you are looking for is not appearing</strong>, they may not yet be
                                placed in a unit — contact your branch administrator.
                            </p>
                        </div>
                    </div>

                    <!-- Activity Form Section (Initially Hidden) -->
                    <div id="activity-form-section" class="hidden">
                        <div class="border-t pt-6 mt-6">
                            <div class="max-w-2xl mx-auto">
                                <div class="flex justify-between items-center mb-6">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">Person Details</h3>
                                        <p class="text-sm text-gray-600">Selected User: <span id="selected-user-name" class="font-medium"></span></p>
                                        <p class="text-sm text-gray-600">DB Reference: <span id="selected-user-reference" class="font-medium"></span></p>
                                        <p class="text-sm text-gray-600">Branch: <span id="selected-user-branch" class="font-medium"></span></p>
                                        <p class="text-sm text-gray-600">Division: <span id="selected-user-division" class="font-medium"></span></p>
                                        <p class="text-sm text-gray-600" id="selected-rcu-line">RC Unit: <span id="selected-user-rcu" class="font-medium"></span></p>
                                    </div>
                                    <button type="button"
                                            id="change-user-btn"
                                            class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        Change User
                                    </button>
                                </div>

                                <form method="POST" action="{{ route('activities.store') }}">
                                    @csrf

                                    <!-- Hidden User ID -->
                                    <input type="hidden" name="user_id" id="selected-user-id">
                                    <!-- Hidden Branch ID -->
                                    <input type="hidden" name="branch_id" id="selected-branch-id">
                                    <!-- Hidden Division ID -->
                                    <input type="hidden" name="division_id" id="selected-division-id">

                                    <div class="entry-card">
                                        <h4 class="entry-card-title">Enter activity details</h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <!-- Activity Type -->
                                            <div>
                                                <label for="activity_type_id" class="block text-sm font-medium text-gray-700 mb-2">
                                                    Activity Type <span class="text-red-500">*</span>
                                                </label>
                                                <select name="activity_type_id" id="activity_type_id" required
                                                        class="entry-field @error('activity_type_id') border-red-500 @enderror">
                                                    <option value="">Select Activity Type</option>
                                                    @foreach($activityTypes->sortBy('name') as $type) {{-- Activity type sorted alphabetically --}}
                                                    <option value="{{ $type->id }}" {{ old('activity_type_id') == $type->id ? 'selected' : '' }}>
                                                        {{ $type->name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                                @error('activity_type_id')
                                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <!-- Date -->
                                            <div>
                                                <label for="date" class="block text-sm font-medium text-gray-700 mb-2">
                                                    Date <span class="text-red-500">*</span>
                                                </label>
                                                <input type="date" name="date" id="date" value="{{ old('date', date('Y-m-d')) }}" required
                                                       class="entry-field @error('date') border-red-500 @enderror">
                                                @error('date')
                                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <!-- Hours -->
                                            <div>
                                                <label for="hours" class="block text-sm font-medium text-gray-700 mb-2">
                                                    Hours <span class="text-red-500">*</span>
                                                </label>
                                                <input type="number" name="hours" id="hours" value="{{ old('hours') }}" required
                                                       min="1" max="24"
                                                       class="entry-field @error('hours') border-red-500 @enderror">
                                                @error('hours')
                                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <!-- Reference -->
                                            <div>
                                                <label for="reference" class="block text-sm font-medium text-gray-700 mb-2">
                                                    Reference
                                                </label>
                                                <input type="text" name="reference" id="reference"
                                                       class="entry-field"
                                                       value="{{ old('reference') }}" placeholder="Activity reference">
                                                @error('reference')
                                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Red Cross Unit & Task Force Selection -->
                                    <div id="assignment-block" class="mt-6 border-t pt-6">
                                        <div id="assignment-options-container" class="space-y-4">
                                            {{-- Option 1: Assign to Red Cross Unit --}}
                                            <div id="user-red-cross-unit-section" class="hidden">
                                                <div class="flex items-center">
                                                    <input type="checkbox" name="use_user_red_cross_unit" id="use_user_red_cross_unit" value="1"
                                                           class="mr-2 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                    <label for="use_user_red_cross_unit" class="text-sm font-medium text-gray-700">
                                                        Assign this to Red Cross Unit: <span id="user-red-cross-unit-name" class="font-bold"></span>
                                                    </label>
                                                    <input type="hidden" name="user_red_cross_unit_id" id="user-red-cross-unit-id">
                                                </div>
                                                @error('user_red_cross_unit_id')
                                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            {{-- Option 2: Assign to Task Force --}}
                                            <div id="user-task-force-section" class="hidden">
                                                <div class="flex items-center mb-2">
                                                    <input type="checkbox" name="use_user_task_force" id="use_user_task_force" value="1"
                                                           class="mr-2 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                                    <label for="use_user_task_force" class="text-sm font-medium text-gray-700">
                                                        Assign to Task Force
                                                    </label>
                                                </div>
                                                <div id="task-force-select-container" class="hidden">
                                                    <label for="selected_task_force_id" class="block text-sm font-medium text-gray-700 mb-2">
                                                        Select Task Force
                                                    </label>
                                                    <select name="selected_task_force_id" id="selected_task_force_id"
                                                            class="w-full md:w-1/2 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('selected_task_force_id') border-red-500 @enderror">
                                                        <option value="">Select a Task Force</option>
                                                        {{-- Options will be populated by JavaScript --}}
                                                    </select>
                                                    @error('selected_task_force_id')
                                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            </div>

                                            {{-- Option 3: Not Assigned --}}
                                            <div>
                                                <div class="flex items-center">
                                                    <input type="checkbox" name="not_assigned" id="not_assigned" value="1"
                                                           class="mr-2 h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"> {{-- Removed 'checked' attribute here --}}
                                                    <label for="not_assigned" class="text-sm font-medium text-gray-700">
                                                        Do not assign this to a RC unit or task force
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Form Actions -->
                                    <div class="flex items-center justify-end space-x-4 mt-8">
                                        <a href="{{ route('activities.index') }}"
                                           class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                            Cancel
                                        </a>
                                        <button type="submit"
                                                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                            Create Activity Log
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
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

        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-8">
                <div class="p-6 text-gray-900">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Recent Activities Registered by {{ auth()->user()->full_name }}</h3>
                        <span class="text-sm text-gray-500">Latest first</span>
                    </div>

                        @php
                            // MODIFIED: Changed eager loading from 'redCrossUnit' to 'assignable'
                            // Show ALL approval statuses so the submitter can withdraw pending
                            // entries and see rejection reasons (default scope is approved-only).
                            $myRecentActivities = \App\Models\Activity::withAnyApprovalStatus()
                                ->with(['user', 'activityType', 'assignable'])
                                ->where('submitted_by_user_id', auth()->id())
                                ->where('is_deleted', false) // Assuming activities can be soft deleted
                                ->whereHas('user')
                                ->whereHas('activityType')
                                ->orderBy('created_at', 'desc')
                                ->orderBy('date', 'desc')
                                ->paginate(10, ['*'], 'my_activities');
                        @endphp

                        @if($myRecentActivities->count() > 0)
                            <!-- Mobile Card List -->
                            <div class="md:hidden space-y-3">
                                @foreach($myRecentActivities as $activity)
                                    <div class="border border-gray-200 rounded-lg bg-white p-4">
                                        <div class="flex justify-between items-start gap-2">
                                            <div class="min-w-0">
                                                <div class="font-medium text-gray-900 truncate">{{ $activity->user->full_name ?? 'No Name' }}</div>
                                                <div class="text-xs text-gray-500">{!! $activity->user->getUserIdReferenceLinkAttribute() !!}</div>
                                            </div>
                                        </div>
                                        <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
                                            <div>
                                                <dt class="text-xs uppercase text-gray-400">Activity Type</dt>
                                                <dd class="text-gray-900">{{ $activity->activityType->name ?? 'N/A' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs uppercase text-gray-400">Date</dt>
                                                <dd class="text-gray-900">{{ \Carbon\Carbon::parse($activity->date)->format('M d, Y') }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs uppercase text-gray-400">Hours</dt>
                                                <dd class="text-gray-900">{{ $activity->hours }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs uppercase text-gray-400">RC Unit / TF</dt>
                                                <dd class="text-gray-900">
                                                    @if($activity->assignable)
                                                        @if($activity->assignable_type === \App\Models\RedCrossUnit::class)
                                                            RCU: {{ $activity->assignable->name }}
                                                        @elseif($activity->assignable_type === \App\Models\TaskForce::class)
                                                            TF: {{ $activity->assignable->name }}
                                                        @else
                                                            N/A
                                                        @endif
                                                    @else
                                                        N/A
                                                    @endif
                                                </dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs uppercase text-gray-400">Reference</dt>
                                                <dd class="text-gray-900">
                                                    <div>{{ $activity->activity_reference }}</div>
                                                    @if($activity->reference)
                                                        <div class="text-xs text-gray-500"><i class="fas fa-hashtag mr-1"></i>{{ $activity->reference }}</div>
                                                    @endif
                                                </dd>
                                            </div>
                                        </dl>
                                        <div class="mt-3">
                                            <x-recent-log-actions
                                                :status="$activity->approval_status"
                                                :rejection-reason="$activity->rejection_reason"
                                                :review-url="route('activities.review', $activity->id)"
                                                :withdraw-url="route('activities.withdraw', $activity->id)" />
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
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activity Type</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Hours</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RC Unit / TF</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($myRecentActivities as $activity)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                {{ $activity->user->full_name ?? 'No Name' }}
                                            </td>
                                            <td class="px-3 py-2 text-sm">
                                                {!! $activity->user->getUserIdReferenceLinkAttribute() !!}
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                {{ $activity->activityType->name ?? 'N/A' }}
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                {{ \Carbon\Carbon::parse($activity->date)->format('M d, Y') }}
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                {{ $activity->hours }}
                                            </td>
                                            <td class="px-3 py-2 text-sm text-center">
                                                @if($activity->assignable)
                                                    @if($activity->assignable_type === \App\Models\RedCrossUnit::class)
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                                RCU: {{ $activity->assignable->name }}
                                                            </span>
                                                    @elseif($activity->assignable_type === \App\Models\TaskForce::class)
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                                TF: {{ $activity->assignable->name }}
                                                            </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                                N/A
                                                            </span>
                                                    @endif
                                                @else
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                            N/A
                                                        </span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                <div>{{ $activity->activity_reference }}</div>
                                                @if($activity->reference)
                                                    <div class="text-xs text-gray-500"><i class="fas fa-hashtag mr-1"></i>{{ $activity->reference }}</div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-sm">
                                                <x-approval-status-badge :status="$activity->approval_status" />
                                                @if($activity->approval_status === 'rejected' && $activity->rejection_reason)
                                                    <div class="text-xs text-red-600 mt-1"><i class="fas fa-comment-dots mr-1"></i>{{ $activity->rejection_reason }}</div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                <div class="flex items-center gap-3">
                                                    <a href="{{ route('activities.review', $activity->id) }}"
                                                       class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs font-medium transition-colors">
                                                        View
                                                    </a>
                                                    @if($activity->approval_status === 'pending')
                                                        <x-withdraw-button :url="route('activities.withdraw', $activity->id)" />
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            @if($myRecentActivities->hasPages())
                                <div class="mt-4">
                                    {{ $myRecentActivities->links() }}
                                </div>
                            @endif
                        @else
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center">
                                <div class="text-gray-400 mb-2">
                                    <svg class="mx-auto h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <p class="text-gray-600 text-sm">No activities registered by you yet.</p>
                            </div>
                        @endif
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const userSearch = document.getElementById('user-search');
            const searchBtn = document.getElementById('search-btn');
            const searchResults = document.getElementById('search-results');
            const resultsList = document.getElementById('results-list');
            const noResults = document.getElementById('no-results');
            const userSearchSection = document.getElementById('user-search-section');
            const activityFormSection = document.getElementById('activity-form-section');
            const selectedUserName = document.getElementById('selected-user-name');
            const selectedUserId = document.getElementById('selected-user-id');
            const selectedUserReference = document.getElementById('selected-user-reference');
            const selectedUserBranch = document.getElementById('selected-user-branch');
            const selectedUserDivision = document.getElementById('selected-user-division');
            const selectedBranchId = document.getElementById('selected-branch-id');
            const selectedDivisionId = document.getElementById('selected-division-id');
            const changeUserBtn = document.getElementById('change-user-btn');
            const preselectedUser = @json($user ?? null);


            // Red Cross Unit related elements
            const userRedCrossUnitSection = document.getElementById('user-red-cross-unit-section');
            const userRedCrossUnitNameSpan = document.getElementById('user-red-cross-unit-name');
            const userRedCrossUnitIdInput = document.getElementById('user-red-cross-unit-id');
            const useUserRedCrossUnitCheckbox = document.getElementById('use_user_red_cross_unit');

            // Task Force related elements
            const userTaskForceSection = document.getElementById('user-task-force-section');
            const useUserTaskForceCheckbox = document.getElementById('use_user_task_force');
            const taskForceSelectContainer = document.getElementById('task-force-select-container');
            const selectedTaskForceIdSelect = document.getElementById('selected_task_force_id');

            // Not Assigned checkbox
            const notAssignedCheckbox = document.getElementById('not_assigned');

            // Mutual exclusion logic for assignment options
            function handleAssignmentCheckboxChange(changedCheckbox) {
                if (changedCheckbox.checked) {
                    if (changedCheckbox === useUserRedCrossUnitCheckbox) {
                        useUserTaskForceCheckbox.checked = false;
                        taskForceSelectContainer.classList.add('hidden');
                        selectedTaskForceIdSelect.value = '';
                        notAssignedCheckbox.checked = false;
                    } else if (changedCheckbox === useUserTaskForceCheckbox) {
                        useUserRedCrossUnitCheckbox.checked = false;
                        notAssignedCheckbox.checked = false;
                        taskForceSelectContainer.classList.remove('hidden');
                    } else if (changedCheckbox === notAssignedCheckbox) {
                        useUserRedCrossUnitCheckbox.checked = false;
                        useUserTaskForceCheckbox.checked = false;
                        taskForceSelectContainer.classList.add('hidden');
                        selectedTaskForceIdSelect.value = '';
                    }
                } else {
                    // If a checkbox is unchecked, and it's not the "Not assigned" one,
                    // and no other assignment is active, then tick "Not assigned".
                    if (changedCheckbox !== notAssignedCheckbox &&
                        !useUserRedCrossUnitCheckbox.checked &&
                        !useUserTaskForceCheckbox.checked) {
                        notAssignedCheckbox.checked = true;
                    }
                    // If task force checkbox is unchecked, hide its dropdown
                    if (changedCheckbox === useUserTaskForceCheckbox) {
                        taskForceSelectContainer.classList.add('hidden');
                        selectedTaskForceIdSelect.value = '';
                    }
                }
            }

            // Search function
            function searchUsers() {
                const query = userSearch.value.trim();
                if (query.length < 2) {
                    searchResults.classList.add('hidden');
                    noResults.classList.add('hidden');
                    return;
                }

                fetch(`{{ route('activities.search-users') }}?query=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(users => {
                        resultsList.innerHTML = '';

                        if (users.length === 0) {
                            searchResults.classList.add('hidden');
                            noResults.classList.remove('hidden');
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
                        noResults.classList.remove('hidden');
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
                document.getElementById('selected-user-rcu').textContent = user.red_cross_unit ? user.red_cross_unit.name : '—';

                selectedBranchId.value = user.branch_id || '';
                selectedDivisionId.value = user.division_id || '';

                // Reset all assignment related elements
                useUserRedCrossUnitCheckbox.checked = false;
                userRedCrossUnitSection.classList.add('hidden'); // Ensure RCU section is hidden by default
                userRedCrossUnitNameSpan.textContent = '';
                userRedCrossUnitIdInput.value = '';

                useUserTaskForceCheckbox.checked = false;
                userTaskForceSection.classList.add('hidden'); // Ensure Task Force section is hidden by default
                taskForceSelectContainer.classList.add('hidden');
                selectedTaskForceIdSelect.innerHTML = '<option value="">Select a Task Force</option>'; // Clear dropdown
                selectedTaskForceIdSelect.value = '';

                let hasRCU = false;
                let hasTaskForces = false;

                // Handle Red Cross Unit for the selected user
                if (user.red_cross_unit) {
                    userRedCrossUnitNameSpan.textContent = user.red_cross_unit.name;
                    userRedCrossUnitIdInput.value = user.red_cross_unit.id;
                    userRedCrossUnitSection.classList.remove('hidden'); // Show RCU section if data exists
                    hasRCU = true;
                }

                // Handle Task Forces for the selected user
                if (user.task_forces && user.task_forces.length > 0) {
                    user.task_forces.forEach(tf => {
                        const option = document.createElement('option');
                        option.value = tf.id;
                        option.textContent = tf.name;
                        selectedTaskForceIdSelect.appendChild(option);
                    });
                    userTaskForceSection.classList.remove('hidden'); // Show TF section if data exists
                    hasTaskForces = true;
                }

                // Show/hide the entire assignment block based on whether the user has an RCU or TF
                const assignmentBlock = document.getElementById('assignment-block');
                const noAssignment = !hasRCU && !hasTaskForces;
                assignmentBlock.classList.toggle('hidden', noAssignment);
                if (noAssignment) {
                    notAssignedCheckbox.checked = true;
                }

                // Show activity form, hide search
                userSearchSection.classList.add('hidden');
                activityFormSection.classList.remove('hidden'); // Form becomes visible here

                // Default assignment: RCU if user has one, otherwise "Not assigned".
                if (hasRCU) {
                    useUserRedCrossUnitCheckbox.checked = true;
                    handleAssignmentCheckboxChange(useUserRedCrossUnitCheckbox);
                } else {
                    notAssignedCheckbox.checked = true;
                    handleAssignmentCheckboxChange(notAssignedCheckbox);
                }

                // If old input exists for RCU or Task Force, re-check those boxes
                // (This part would typically be handled by Blade's old() helper, but can be done here too for JS controlled elements)
                // For this scenario, we assume "Not assigned" is the strict default unless the user interacts.
            }

            // Change user function
            function changeUser() {
                document.getElementById('assignment-block').classList.remove('hidden');
                userSearchSection.classList.remove('hidden');
                activityFormSection.classList.add('hidden');
                userSearch.value = '';
                searchResults.classList.add('hidden');
                noResults.classList.add('hidden');

                // Reset all assignment options and set 'Not assigned' as default
                useUserRedCrossUnitCheckbox.checked = false;
                userRedCrossUnitSection.classList.add('hidden');
                userRedCrossUnitNameSpan.textContent = '';
                userRedCrossUnitIdInput.value = '';

                useUserTaskForceCheckbox.checked = false;
                userTaskForceSection.classList.add('hidden');
                taskForceSelectContainer.classList.add('hidden');
                selectedTaskForceIdSelect.innerHTML = '<option value="">Select a Task Force</option>';
                selectedTaskForceIdSelect.value = '';

                notAssignedCheckbox.checked = true; // Default to not assigned when changing user
                handleAssignmentCheckboxChange(notAssignedCheckbox); // Apply mutual exclusion
            }

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

            userSearch.addEventListener('input', function() {
                if (this.value.length >= 2) {
                    searchUsers();
                }
            });

            changeUserBtn.addEventListener('click', changeUser);

            useUserRedCrossUnitCheckbox.addEventListener('change', function() {
                handleAssignmentCheckboxChange(this);
            });

            useUserTaskForceCheckbox.addEventListener('change', function() {
                handleAssignmentCheckboxChange(this);
            });

            notAssignedCheckbox.addEventListener('change', function() {
                handleAssignmentCheckboxChange(this);
            });

            // If a task force is explicitly selected, make sure its checkbox is ticked
            selectedTaskForceIdSelect.addEventListener('change', function() {
                if (this.value) { // If a task force is selected
                    useUserTaskForceCheckbox.checked = true;
                    useUserRedCrossUnitCheckbox.checked = false; // Uncheck RCU
                    notAssignedCheckbox.checked = false; // Uncheck Not assigned
                } else { // If "Select a Task Force" is chosen (empty value)
                    useUserTaskForceCheckbox.checked = false;
                    // If no RCU is checked either, then tick Not assigned
                    if (!useUserRedCrossUnitCheckbox.checked) {
                        notAssignedCheckbox.checked = true;
                    }
                }
                handleAssignmentCheckboxChange(useUserTaskForceCheckbox); // Re-evaluate state
            });

            // Initial state check for task force dropdown visibility if checkbox is already checked (e.g., old input)
            if (useUserTaskForceCheckbox.checked) {
                taskForceSelectContainer.classList.remove('hidden');
            } else {
                taskForceSelectContainer.classList.add('hidden');
            }

            if (preselectedUser) {
                const fullName = [preselectedUser.first_name, preselectedUser.middle_name, preselectedUser.last_name]
                    .filter(Boolean).join(' ');
                selectUser(preselectedUser, fullName);
            }

        });
    </script>
</x-layouts.admin>
