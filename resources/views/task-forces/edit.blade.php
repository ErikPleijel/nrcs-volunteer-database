<x-layouts.admin>
    <x-slot name="pageHeader">
        <i class="fas fa-users-gear mr-3"></i> Task Forces
    </x-slot>

    <x-slot name="subHeader">
        EDIT TASKFORCE
    </x-slot>

    <x-slot name="button1">
        <a href="{{ route('task-forces.show', $taskForce) }}" class="btn-primary">
            <i class="fas fa-eye mr-2"></i>Show Task Force
        </a>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form action="{{ route('task-forces.update', $taskForce) }}" method="POST" id="task-force-edit-form">
                        @csrf
                        @method('PUT')

                        {{-- Task Force Details Section --}}
                        <h3 class="text-lg font-semibold mb-4">Task Force Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Task Force Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       name="name"
                                       id="name"
                                       value="{{ old('name', $taskForce->name) }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('name') border-red-500 @enderror"
                                       required>
                                @error('name')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="task_force_type_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Task Force Type <span class="text-red-500">*</span>
                                </label>
                                <select name="task_force_type_id"
                                        id="task_force_type_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('task_force_type_id') border-red-500 @enderror"
                                        required>
                                    <option value="">Select Type</option>
                                    @foreach($taskForceTypes as $type)
                                        <option value="{{ $type->id }}"
                                            {{ old('task_force_type_id', $taskForce->task_force_type_id) == $type->id ? 'selected' : '' }}>
                                            {{ $type->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('task_force_type_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Branch
                                </label>
                                <p class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100 text-gray-700">
                                    {{ $taskForce->branch->name ?? 'N/A' }}
                                </p>
                            </div>
                        </div>

                        {{-- Team Leaders Section --}}
                        <h3 class="text-lg font-semibold mb-4">Team Leaders</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="team_leader_user_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Team Leader
                                </label>
                                <select name="team_leader_user_id"
                                        id="team_leader_user_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">No Team Leader</option> {{-- Added "No Team Leader" option --}}
                                    @foreach($teamLeaderOptions as $user)
                                        <option value="{{ $user->id }}" {{ old('team_leader_user_id', $currentTeamLeaderId) == $user->id ? 'selected' : '' }}>
                                            {{ $user->full_name }} ({{ $user->getUserIdReferenceShortAttribute() }}){{ $user->lifecycle_status === 'archived' ? ' (Archived)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('team_leader_user_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                                @if($taskForce->teamLeader && $taskForce->teamLeader->lifecycle_status === 'archived')
                                    <div class="mt-2 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                                        <i class="fas fa-triangle-exclamation mr-1 text-amber-500"></i>
                                        {{ $taskForce->teamLeader->full_name }} is archived — please select a new Team Leader.
                                    </div>
                                @endif
                            </div>
                            <div>
                                <label for="assist_team_leader_user_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Assistant Team Leader
                                </label>
                                <select name="assist_team_leader_user_id"
                                        id="assist_team_leader_user_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">No Assistant Team Leader</option> {{-- Added "No Assistant Team Leader" option --}}
                                    @foreach($assistantTeamLeaderOptions as $user)
                                        <option value="{{ $user->id }}" {{ old('assist_team_leader_user_id', $currentAssistantTeamLeaderId) == $user->id ? 'selected' : '' }}>
                                            {{ $user->full_name }} ({{ $user->getUserIdReferenceShortAttribute() }}){{ $user->lifecycle_status === 'archived' ? ' (Archived)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('assist_team_leader_user_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                                @if($taskForce->assistantTeamLeader && $taskForce->assistantTeamLeader->lifecycle_status === 'archived')
                                    <div class="mt-2 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                                        <i class="fas fa-triangle-exclamation mr-1 text-amber-500"></i>
                                        {{ $taskForce->assistantTeamLeader->full_name }} is archived — please select a new Assistant Team Leader.
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Error message for team leader selection --}}
                        <div id="leader-status-message" class="mb-4 p-3 rounded text-sm hidden"></div>

                        <div class="flex justify-end space-x-4 mb-6">
                            <a href="{{ route('task-forces.index') }}"
                               class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                                Cancel
                            </a>
                            <button type="submit"
                                    class="btn-primary">
                                Save Changes
                            </button>
                        </div>


                        {{-- Task Force Members Section --}}
                        <h3 class="text-lg font-semibold mb-4">Task Force Members</h3>

                        {{-- Message Display Area for members --}}
                        <div id="status-message" class="mb-4 p-3 rounded text-sm hidden"></div>

                        <div class="mb-6">
                            <label for="member-search" class="block text-sm font-medium text-gray-700 mb-2">
                                Add Persons
                                <span class="font-normal text-xs">(Field only picks volunteers, i.e. members of a RC Unit)</span>
                            </label>
                            <input type="text"
                                   id="member-search"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                   placeholder="Search by Name, ID, Email...">
                            <div id="search-results" class="mt-2 border border-gray-200 rounded-md max-h-60 overflow-y-auto bg-white hidden">
                                {{-- Search results will be loaded here via JavaScript --}}
                            </div>
                        </div>

                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Current Members
                            </label>
                            <ul id="current-members-list" class="border border-gray-200 rounded-md divide-y divide-gray-200">
                                @forelse($taskForce->users->sortBy('first_name') as $user)
                                    <li class="p-3 flex items-center justify-between" data-user-id="{{ $user->id }}" data-archived="{{ $user->lifecycle_status === 'archived' ? 'true' : 'false' }}">
                                        <div class="flex items-center gap-2">
                                            <span>{{ $user->full_name }} ({{ $user->getUserIdReferenceShortAttribute() }})</span>
                                            @if($user->lifecycle_status === 'archived')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Archived</span>
                                            @endif
                                        </div>
                                        <button type="button"
                                                class="remove-member-btn text-red-600 hover:text-red-900 text-sm font-medium"
                                                data-user-id="{{ $user->id }}">
                                            Remove
                                        </button>
                                    </li>
                                @empty
                                    <li id="no-members-message" class="p-3 text-gray-500">No members assigned.</li>
                                @endforelse
                            </ul>
                            {{-- Container for dynamically generated hidden inputs --}}
                            <div id="members-hidden-inputs-container">
                                {{-- Initial members will be added here on page load --}}
                                @foreach($taskForce->users as $user)
                                    <input type="hidden" name="members[]" value="{{ $user->id }}">
                                @endforeach
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('task-force-edit-form');
                const memberSearchInput = document.getElementById('member-search');
                const searchResultsDiv = document.getElementById('search-results');
                const currentMembersList = document.getElementById('current-members-list');
                const noMembersMessageElement = document.getElementById('no-members-message');
                const statusMessageDiv = document.getElementById('status-message'); // For member actions
                const leaderStatusMessageDiv = document.getElementById('leader-status-message'); // For leader selection warnings
                const teamLeaderSelect = document.getElementById('team_leader_user_id');
                const assistantTeamLeaderSelect = document.getElementById('assist_team_leader_user_id');
                const taskForceId = {{ $taskForce->id }};
                const hiddenMembersContainer = document.getElementById('members-hidden-inputs-container');

                let searchTimeout;

                function displayMessage(message, type = 'success', targetDiv = statusMessageDiv) {
                    targetDiv.textContent = message;
                    targetDiv.classList.remove('hidden', 'bg-green-100', 'text-green-800', 'bg-red-100', 'text-red-800', 'bg-yellow-100', 'text-yellow-800');
                    if (type === 'success') {
                        targetDiv.classList.add('bg-green-100', 'text-green-800');
                    } else if (type === 'error') {
                        targetDiv.classList.add('bg-red-100', 'text-red-800');
                    } else { // Default to info/warning
                        targetDiv.classList.add('bg-yellow-100', 'text-yellow-800');
                    }
                    targetDiv.classList.remove('hidden');
                    setTimeout(() => {
                        targetDiv.classList.add('hidden');
                    }, 5000); // Hide message after 5 seconds
                }

                function updateHiddenMembersInput() {
                    hiddenMembersContainer.innerHTML = ''; // Clear previous inputs

                    // Add hidden inputs for all current members for the main form submission
                    Array.from(currentMembersList.children).forEach(li => {
                        if (li.dataset.userId && li.id !== 'no-members-message') { // Ensure it's a member LI and not the placeholder
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'members[]';
                            input.value = li.dataset.userId;
                            hiddenMembersContainer.appendChild(input);
                        }
                    });
                    updateTeamLeaderDropdowns(); // Also update team leader dropdowns whenever members change
                }

                function updateTeamLeaderDropdowns() {
                    // Store current selections before clearing
                    const currentSelectedLeader = teamLeaderSelect.value;
                    const currentSelectedAssistant = assistantTeamLeaderSelect.value;

                    teamLeaderSelect.innerHTML = '<option value="">No Team Leader</option>'; // Keep "No Team Leader" option
                    assistantTeamLeaderSelect.innerHTML = '<option value="">No Assistant Team Leader</option>'; // Keep "No Assistant Team Leader" option

                    // Collect members, sort alphabetically, then repopulate
                    const memberOptions = Array.from(currentMembersList.children)
                        .filter(li => li.dataset.userId && li.id !== 'no-members-message')
                        .map(li => ({ userId: li.dataset.userId, userName: li.querySelector('span').textContent, archived: li.dataset.archived === 'true' }))
                        .sort((a, b) => a.userName.localeCompare(b.userName));

                    // Archived members are excluded as leader/assistant candidates, unless
                    // they're the one currently selected in that specific dropdown — that
                    // exception keeps an already-archived leader from being silently
                    // unassigned by a rebuild triggered by an unrelated member change.
                    memberOptions
                        .filter(({ userId, archived }) => !archived || userId === currentSelectedLeader)
                        .forEach(({ userId, userName, archived }) => {
                            const optionLeader = document.createElement('option');
                            optionLeader.value = userId;
                            optionLeader.textContent = userName + (archived ? ' (Archived)' : '');
                            teamLeaderSelect.appendChild(optionLeader);
                        });

                    memberOptions
                        .filter(({ userId, archived }) => !archived || userId === currentSelectedAssistant)
                        .forEach(({ userId, userName, archived }) => {
                            const optionAssistant = document.createElement('option');
                            optionAssistant.value = userId;
                            optionAssistant.textContent = userName + (archived ? ' (Archived)' : '');
                            assistantTeamLeaderSelect.appendChild(optionAssistant);
                    });

                    // Restore selections if the user is still in the list or if "No Team Leader" was selected
                    if (currentSelectedLeader === '' || teamLeaderSelect.querySelector(`option[value="${currentSelectedLeader}"]`)) {
                        teamLeaderSelect.value = currentSelectedLeader;
                    } else {
                        teamLeaderSelect.value = ''; // Reset if selected leader is no longer a member
                    }

                    if (currentSelectedAssistant === '' || assistantTeamLeaderSelect.querySelector(`option[value="${currentSelectedAssistant}"]`)) {
                        assistantTeamLeaderSelect.value = currentSelectedAssistant;
                    } else {
                        assistantTeamLeaderSelect.value = ''; // Reset if selected assistant is no longer a member
                    }
                }


                memberSearchInput.addEventListener('keyup', function () {
                    clearTimeout(searchTimeout);
                    const query = this.value;

                    if (query.length < 2) {
                        searchResultsDiv.innerHTML = '';
                        searchResultsDiv.classList.add('hidden');
                        return;
                    }

                    searchTimeout = setTimeout(() => {
                        fetch(`/task-forces/${taskForceId}/members/search?query=${query}`)
                            .then(response => response.json())
                            .then(users => {
                                searchResultsDiv.innerHTML = '';
                                if (users.length > 0) {
                                    users.forEach(user => {
                                        const userElement = document.createElement('div');
                                        userElement.classList.add('p-3', 'hover:bg-gray-100', 'cursor-pointer', 'flex', 'justify-between', 'items-center');
                                        userElement.innerHTML = `
                                            <span>${user.full_name} (${user.user_id_reference})</span>
                                            <button type="button" class="add-member-btn bg-green-500 hover:bg-green-600 text-white text-xs font-bold py-1 px-2 rounded" data-user-id="${user.id}" data-user-full-name="${user.full_name}" data-user-ref="${user.user_id_reference}">
                                                Add
                                            </button>
                                        `;
                                        searchResultsDiv.appendChild(userElement);
                                    });
                                    searchResultsDiv.classList.remove('hidden');
                                } else {
                                    searchResultsDiv.innerHTML = '<div class="p-3 text-gray-500">No users found.</div>';
                                    searchResultsDiv.classList.remove('hidden');
                                }
                            })
                            .catch(error => {
                                console.error('Error searching users:', error);
                                displayMessage('Error searching users.', 'error');
                                searchResultsDiv.innerHTML = '<div class="p-3 text-red-500">Error searching users.</div>';
                                searchResultsDiv.classList.remove('hidden');
                            });
                    }, 300); // Debounce search input
                });

                searchResultsDiv.addEventListener('click', function (event) {
                    if (event.target.classList.contains('add-member-btn')) {
                        const userId = event.target.dataset.userId;
                        const userFullName = event.target.dataset.userFullName;
                        const userIdReference = event.target.dataset.userRef;
                        addMember(userId, userFullName, userIdReference);
                        memberSearchInput.value = ''; // Clear search input
                        searchResultsDiv.classList.add('hidden'); // Hide search results
                    }
                });

                currentMembersList.addEventListener('click', function (event) {
                    if (event.target.classList.contains('remove-member-btn')) {
                        const userId = event.target.dataset.userId;
                        removeMember(userId);
                    }
                });

                function addMember(userId, userFullName, userIdReference) {
                    fetch(`/task-forces/${taskForceId}/add-member`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ user_id: userId })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                if (noMembersMessageElement && noMembersMessageElement.parentNode) {
                                    noMembersMessageElement.parentNode.removeChild(noMembersMessageElement);
                                }
                                const newMemberElement = document.createElement('li');
                                newMemberElement.classList.add('p-3', 'flex', 'items-center', 'justify-between');
                                newMemberElement.dataset.userId = userId;
                                newMemberElement.dataset.archived = 'false'; // Search results are always active (scopeVolunteers()), never archived
                                newMemberElement.innerHTML = `
                                <span>${userFullName} (${userIdReference})</span>
                                <button type="button"
                                        class="remove-member-btn text-red-600 hover:text-red-900 text-sm font-medium"
                                        data-user-id="${userId}">
                                    Remove
                                </button>
                            `;
                                currentMembersList.appendChild(newMemberElement);
                                const addedUserBtn = document.querySelector(`#search-results button[data-user-id="${userId}"]`);
                                if (addedUserBtn) {
                                    addedUserBtn.closest('div').remove();
                                }
                                updateHiddenMembersInput(); // Update hidden input and dropdowns after adding
                                displayMessage(`${userFullName} added successfully.`, 'success');
                            } else {
                                displayMessage(data.message || 'Failed to add member.', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error adding member:', error);
                            displayMessage('Error adding member.', 'error');
                        });
                }

                function removeMember(userId) {
                    if (!confirm('Are you sure you want to remove this member?')) {
                        return;
                    }

                    fetch(`/task-forces/${taskForceId}/remove-member`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ user_id: userId })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const removedElement = document.querySelector(`#current-members-list li[data-user-id="${userId}"]`);
                                if (removedElement) {
                                    removedElement.remove();
                                }
                                if (currentMembersList.children.length === 0) {
                                    const noMembersMsg = document.createElement('li');
                                    noMembersMsg.id = 'no-members-message';
                                    noMembersMsg.classList.add('p-3', 'text-gray-500');
                                    noMembersMsg.textContent = 'No members assigned.';
                                    currentMembersList.appendChild(noMembersMsg);
                                }
                                updateHiddenMembersInput(); // Update hidden input and dropdowns after removing
                                displayMessage(data.message || 'Member removed successfully.', 'success');
                            } else {
                                displayMessage(data.message || 'Failed to remove member.', 'error');
                            }
                        })
                        .catch(error => {
                            console.error('Error removing member:', error);
                            displayMessage('Error removing member.', 'error');
                        });
                }

                // Initial calls on page load
                updateHiddenMembersInput(); // This will also call updateTeamLeaderDropdowns

                // Add form submission listener for validation
                form.addEventListener('submit', function(event) {
                    const teamLeaderId = teamLeaderSelect.value;
                    const assistantTeamLeaderId = assistantTeamLeaderSelect.value;

                    // Clear previous leader warning
                    leaderStatusMessageDiv.classList.add('hidden');

                    if (teamLeaderId && assistantTeamLeaderId && teamLeaderId === assistantTeamLeaderId) {
                        event.preventDefault(); // Stop form submission
                        displayMessage('Team Leader and Assistant Team Leader cannot be the same person.', 'error', leaderStatusMessageDiv);
                        // Scroll to the error message or relevant section if needed
                        leaderStatusMessageDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
            });
        </script>
    @endpush
</x-layouts.admin>
