<x-layouts.admin>
    <x-slot name="title">Edit Red Cross Unit - {{ $redCrossUnit->name }}</x-slot>

    <x-slot name="pageHeader">
        <i class="fas fa-shield-alt mr-3"></i>  Red Cross Unit
    </x-slot>
    <x-slot name="subHeader">
        EDIT
    </x-slot>

    <x-slot name="button1">
        <a href="{{ route('red-cross-units.show', $redCrossUnit) }}" class="btn-primary">
            <i class="fas fa-eye mr-2"></i>Show Red Cross Unit
        </a>
    </x-slot>



    <div class="container mx-auto px-4 py-6">

        @if(session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                {{ session('error') }}
            </div>
        @endif

        <!-- Form -->
        <div class="bg-white shadow rounded-lg">
            @if(!$redCrossUnit->is_active)
                <div class="bg-red-600 text-white text-center py-3 rounded-t-lg font-bold text-lg">
                    <i class="fas fa-ban mr-2"></i>DEACTIVATED
                </div>
            @endif
            <form action="{{ route('red-cross-units.update', $redCrossUnit) }}" method="POST" class="p-6 space-y-6">
                @csrf
                @method('PUT')

                <!-- Unit Basic Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Unit Name -->
                    <div>
                        <label for="name" class="form-label">
                            Unit Name *
                        </label>
                        <input type="text"
                               name="name"
                               id="name"
                               value="{{ old('name', $redCrossUnit->name) }}"
                               class="form-input @error('name') form-input-error @enderror"
                               required>
                        @error('name')
                        <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Division (Display only) -->
                    <div>
                        <label class="form-label">
                            Division
                        </label>
                        <p class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm bg-gray-100 text-gray-700">
                            {{ $redCrossUnit->division->name ?? 'N/A' }}
                            @if($redCrossUnit->division && $redCrossUnit->division->branch)
                                ({{ $redCrossUnit->division->branch->name }})
                            @endif
                        </p>
                    </div>
                </div>

                @if($redCrossUnit->is_active)
                    <div class="important-note" role="alert">
                        <p class="text-xl">Current Unit Members ({{ $unitMembers->count() }})</p>
                        <p><b>How to Add/Remove Members:</b> Persons -> Search & Filter -> Edit -> Select (or deselect) Red Cross Unit</p>
                    </div>
                @endif



                <!-- Leadership Section -->
                @if($redCrossUnit->is_active)
                <div class="border-t pt-6">

                    <h3 class="form-section-header">Leadership Assignment</h3>

                    @if($teamLeaderOptions->count() > 0 || $assistantTeamLeaderOptions->count() > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Team Leader -->
                            <div>
                                <label for="team_leader_user_id" class="form-label">
                                    Team Leader
                                </label>
                                <select name="team_leader_user_id"
                                        id="team_leader_user_id"
                                        class="form-select @error('team_leader_user_id') form-select-error @enderror">
                                    <option value="">Select Team Leader</option>
                                    @foreach($teamLeaderOptions as $member)
                                        <option value="{{ $member->id }}"
                                            {{ old('team_leader_user_id', $redCrossUnit->team_leader_user_id) == $member->id ? 'selected' : '' }}>
                                            {{ $member->full_name }}{{ $member->lifecycle_status === 'archived' ? ' (Archived)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('team_leader_user_id')
                                <p class="form-error">{{ $message }}</p>
                                @enderror
                                @if($redCrossUnit->teamLeader && $redCrossUnit->teamLeader->lifecycle_status === 'archived')
                                    <div class="mt-2 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                                        <i class="fas fa-triangle-exclamation mr-1 text-amber-500"></i>
                                        {{ $redCrossUnit->teamLeader->full_name }} is archived — please select a new Team Leader.
                                    </div>
                                @endif
                            </div>

                            <!-- Assistant Team Leader -->
                            <div>
                                <label for="assistant_team_leader_user_id" class="form-label">
                                    Assistant Team Leader
                                </label>
                                <select name="assistant_team_leader_user_id"
                                        id="assistant_team_leader_user_id"
                                        class="form-select @error('assistant_team_leader_user_id') form-select-error @enderror">
                                    <option value="">Select Assistant Team Leader</option>
                                    @foreach($assistantTeamLeaderOptions as $member)
                                        <option value="{{ $member->id }}"
                                            {{ old('assistant_team_leader_user_id', $redCrossUnit->assistant_team_leader_user_id) == $member->id ? 'selected' : '' }}>
                                            {{ $member->full_name }}{{ $member->lifecycle_status === 'archived' ? ' (Archived)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('assistant_team_leader_user_id')
                                <p class="form-error">{{ $message }}</p>
                                @enderror
                                @if($redCrossUnit->assistantTeamLeader && $redCrossUnit->assistantTeamLeader->lifecycle_status === 'archived')
                                    <div class="mt-2 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                                        <i class="fas fa-triangle-exclamation mr-1 text-amber-500"></i>
                                        {{ $redCrossUnit->assistantTeamLeader->full_name }} is archived — please select a new Assistant Team Leader.
                                    </div>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800">No Unit Members</h3>
                                    <p class="mt-1 text-sm text-yellow-700">
                                        This unit has no members assigned yet. You cannot assign leadership positions until members are added to the unit.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
                @endif

                <div class="border-t pt-6 flex items-center justify-end gap-3">

                    {{-- Cancel / Save --}}
                    <a href="{{ route('red-cross-units.show', $redCrossUnit) }}" class="btn-cancel">
                        Cancel
                    </a>
                    <button type="submit" class="btn-primary">
                        Update Unit
                    </button>

                </div>
            </form>

            {{-- Deactivate / Reactivate — kept as a structurally independent
                 sibling section (not nested inside the "Update Unit" form
                 above) because a <form> nested inside another <form> is
                 invalid HTML: the browser drops the inner form tags, which
                 both misdirects the submit and prematurely closes the outer
                 form. See lines below for the standalone reactivate/deactivate
                 forms. --}}
            <div class="border-t pt-6 px-6 pb-6 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    @php
                        // Unfiltered member count — matches RedCrossUnitController::destroy()'s
                        // FK-integrity guard exactly (archived users still block deactivation there).
                        $allMembersCount = $redCrossUnit->users()->count();
                    @endphp
                    @if(! $redCrossUnit->is_active)
                        <form action="{{ route('red-cross-units.reactivate', $redCrossUnit) }}"
                              method="POST"
                              onsubmit="return confirm('Reactivate this Red Cross Unit?\n\nIt will become selectable again for new members.')">
                            @csrf
                            @method('PUT')
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium bg-yellow-100 text-yellow-800 hover:bg-yellow-200">
                                <i class="fas fa-rotate-left mr-2"></i>Reactivate Unit
                            </button>
                        </form>
                        <span class="text-sm text-gray-500">
                            This unit is currently deactivated.
                        </span>
                    @elseif($allMembersCount === 0)
                        <form action="{{ route('red-cross-units.destroy', $redCrossUnit) }}"
                              method="POST"
                              onsubmit="return confirm('Deactivate this Red Cross Unit?\n\nIt will be hidden from the active list and can no longer be assigned to new members until it is reactivated. Its existing history and records are kept.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-delete">
                                <i class="fas fa-trash-alt mr-2"></i>Deactivate Unit
                            </button>
                        </form>
                        <span class="text-sm text-gray-500">
                            Want to deactivate this Red Cross Unit? Ensure there are no persons assigned to it.
                        </span>
                    @else
                        <button type="button"
                                disabled
                                class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium bg-red-50 text-red-300 border border-red-200 cursor-not-allowed">
                            <i class="fas fa-trash-alt mr-2"></i>Deactivate Unit
                        </button>
                        <span class="text-sm text-red-400">
                            Cannot deactivate — {{ $allMembersCount }} {{ $allMembersCount === 1 ? 'person is' : 'persons are' }} still assigned to this unit.
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript to prevent same person being selected for both positions -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const teamLeaderSelect = document.getElementById('team_leader_user_id');
            const assistantLeaderSelect = document.getElementById('assistant_team_leader_user_id');

            function updateOptions() {
                const teamLeaderValue = teamLeaderSelect.value;
                const assistantLeaderValue = assistantLeaderSelect.value;

                // Reset all options to enabled
                Array.from(teamLeaderSelect.options).forEach(option => {
                    option.disabled = false;
                });
                Array.from(assistantLeaderSelect.options).forEach(option => {
                    option.disabled = false;
                });

                // Disable selected options in the other select
                if (teamLeaderValue) {
                    Array.from(assistantLeaderSelect.options).forEach(option => {
                        if (option.value === teamLeaderValue) {
                            option.disabled = true;
                        }
                    });
                }

                if (assistantLeaderValue) {
                    Array.from(teamLeaderSelect.options).forEach(option => {
                        if (option.value === assistantLeaderValue) {
                            option.disabled = true;
                        }
                    });
                }
            }

            teamLeaderSelect.addEventListener('change', updateOptions);
            assistantLeaderSelect.addEventListener('change', updateOptions);

            // Initial setup
            updateOptions();
        });
    </script>
</x-layouts.admin>
