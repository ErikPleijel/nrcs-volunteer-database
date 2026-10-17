<x-layouts.admin title="Authorizations">
    <x-slot name="pageHeader">
        <i class="fas fa-key mr-3 mb-6"></i>
        {{ __('Manage User Roles and Permissions') }}
    </x-slot>

    <x-audit-notice />

    {{-- Authorization Governance Note --}}
    <div class="mb-4 flex justify-center">
        <div class="w-full max-w-2xl rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
            <p class="font-semibold">
                System governance
            </p>
            <p class="mt-1">
                The Nigerian Red Cross President and Secretary General hold overall super-admin authority in this system,
                using their official NRCS email accounts. Day-to-day user authorization is normally handled by the appointed
                National Database Administrator(s) and Branch Database Administrator(s).
            </p>
        </div>
    </div>

    {{-- Data Protection Officer (dpo.* settings — not a role) --}}
    <div class="mb-4 flex justify-center">
        <x-dpo-contact class="w-full max-w-2xl" />
    </div>


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

                    {{-- System governance --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'governance' ? null : 'governance'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-crown mr-2 text-purple-400"></i>Understand system governance</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'governance' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'governance'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>The <span class="font-semibold">NRCS President and Secretary General</span> hold overall super-admin authority in this system, using their official NRCS email accounts.</li>
                                <li>Day-to-day user authorization is normally handled by the appointed <span class="font-semibold">National DB Administrator(s)</span> and <span class="font-semibold">Branch DB Administrator(s)</span>.</li>
                                <li>This is an important responsibility — handle it with care.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Authorize at branch level --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'branch' ? null : 'branch'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-building mr-2 text-green-400"></i>Authorize a branch-level role</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'branch' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'branch'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li><span class="font-semibold">Branch Secretary</span> — branch administration.</li>
                                <li><span class="font-semibold">Branch DB Administrator</span> — in the database, holds the same authority as Branch Secretary.</li>
                                <li><span class="font-semibold">Branch DB Assistant</span> — branch data entry.</li>
                                <li><span class="font-semibold">Division Assistant — Finance</span> — handles Payments, Donations, Trainings &amp; Volunteering.</li>
                                <li><span class="font-semibold">Division Assistant — Operations</span> — handles Trainings &amp; Volunteering.</li>
                                <li>A <span class="font-semibold">Division Assistant</span> sees only their own division. Tick boxes can add <span class="font-semibold">Can upload photos and signatures</span> and <span class="font-semibold">Can register new users</span> (both off by default, own division only).</li>
                                <li>A <span class="font-semibold">Branch DB Administrator</span> can approve donations and fees unless HQ unticks <span class="font-semibold">Can approve financial records</span>. Only HQ can change it.</li>
                                <li>Search for the person, then use the <span class="font-semibold">Assign Role</span> dropdown to give — or remove — a role.</li>
                                <li>Select <span class="font-semibold">"-- No Role --"</span> to remove authorization entirely.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Authorize at national level --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'national' ? null : 'national'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-globe-americas mr-2 text-blue-400"></i>Authorize a national-level role</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'national' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'national'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li><span class="font-semibold">National DB Administrator</span> — authorizes &amp; oversees the whole system.</li>
                                <li><span class="font-semibold">National DB Assistant</span> — national data entry. Can be given any combination of extra <span class="font-semibold">Special Permissions</span>: Approve Campaign Requests, Print Certificates, Print ID Cards.</li>
                                <li>The Special Permissions tick boxes shown are the ones for the role selected in <span class="font-semibold">Assign Role</span>.</li>
                                <li><span class="font-semibold">Observer</span> — read-only reports; full access to statistics, but cannot change anything in the database.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Search & assign a role --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'assign' ? null : 'assign'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-magnifying-glass mr-2 text-amber-400"></i>Search for a person &amp; assign a role</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'assign' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'assign'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Use <span class="font-semibold">Search for a User</span> to find the person by name, email, or ID.</li>
                                <li>Their <span class="font-semibold">current role</span> is shown at the top of the form once selected.</li>
                                <li>Choose a new role from <span class="font-semibold">Assign Role</span> and click <span class="font-semibold">Update Roles &amp; Permissions</span> to save.</li>
                                <li>Click <span class="font-semibold">Clear / Search for another user</span> to start over with someone else.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Review Users by Role --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'review' ? null : 'review'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-table mr-2 text-violet-400"></i>Review Users by Role</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'review' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'review'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>The table below shows everyone currently authorized, grouped by role.</li>
                                <li>Any extra <span class="font-semibold">Special Permissions</span> a person holds are shown as red tags next to their name.</li>
                                <li>Click <span class="font-semibold">Edit</span> on any row to jump straight to that person's role form.</li>
                                <li>National DB Administrators can only be edited by a super-admin.</li>
                            </ul>
                        </div>
                    </div>

                </div>{{-- end accordion --}}
            </div>{{-- end max-w-3xl --}}

        </x-help-popup>
    </div>




    <div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg" id="edit-user-form">
            <div class="p-6 sm:px-20 bg-white border-b border-gray-200">

                <!-- Admin Level Header -->
                <div class="mb-6 p-4 rounded-lg
                    @if($isSuperAdmin) bg-purple-100 text-purple-800 border border-purple-200
                    @elseif($accessLevel === 'national') bg-blue-100 text-blue-800 border border-blue-200
                    @elseif($accessLevel === 'branch') bg-green-100 text-green-800 border border-green-200
                    @else bg-gray-100 text-gray-800 @endif">
                    <p class="font-semibold">
                        @if($isSuperAdmin)
                            <i class="fas fa-crown mr-2"></i> Super Admin View
                        @elseif($accessLevel === 'national')
                            <i class="fas fa-globe-americas mr-2"></i> National Level Administrator
                        @elseif($accessLevel === 'branch')
                            <i class="fas fa-building mr-2"></i> Branch Level Administrator
                        @endif
                    </p>
                    <p class="text-sm">
                        @if($isSuperAdmin)
                            You have full control to manage all roles and permissions across the system.
                        @elseif($accessLevel === 'national')
                            You can assign national and branch-level roles.
                        @elseif($accessLevel === 'branch')
                            You can assign roles for users within your branch.
                        @endif
                    </p>
                </div>

                <!-- User Search -->
                <div class="mb-6">
                    <h2 class="text-xl font-semibold mb-2">Search for a User
                        @if(auth()->user()->search_scope_description)
                            <span class="text-base font-normal text-gray-600">in {{ auth()->user()->search_scope_description }}</span>
                        @endif</h2>

                    <div class="relative">
                        <input type="text" id="user-search" class="w-full form-input" placeholder="Start typing to search by name, email, or ID...">
                        <div id="search-results" class="absolute z-10 w-full bg-white border border-gray-300 rounded-md mt-1 shadow-lg hidden"></div>
                    </div>
                    @error('user_id')
                        <p class="mt-2 rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">
                            <i class="fas fa-circle-exclamation mr-1"></i>{{ $message }}
                        </p>
                    @enderror
                </div>

                @if ($selectedUser)
                    <!-- Role and Permission Form -->
                    <hr class="my-6">
                    <div class="mt-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h2 class="text-xl font-semibold">Editing: {{ $selectedUser->fullName }}</h2>
                                <p class="mt-1 text-sm text-gray-600">{{ $selectedUser->email }}</p>
                                <p class="text-sm text-gray-600">{{ $selectedUser->userIdReference }}</p>
                            </div>
                            <a href="{{ route('users.roles.edit') }}" class="text-sm text-blue-500 hover:underline ml-4 flex-shrink-0">Clear / Search for another user</a>
                        </div>

                        @if($selectedUser->lifecycle_status === 'pending_engagement')
                            <div class="mb-4 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">
                                <i class="fas fa-triangle-exclamation mr-1 text-amber-500"></i>
                                This person is still in <span class="font-semibold">Pending Engagement</span> — not yet an
                                active volunteer or member. They won't appear in the default Persons filter until they're
                                assigned to a Red Cross Unit or make a membership payment (set Lifecycle Status to "All" or
                                "Pending" to find them again later). <strong>Consider assigning them to a unit first.</strong>
                            </div>
                        @endif

                        <form id="roles-form" action="{{ route('users.roles.update') }}" method="POST"
                              onsubmit="{{ $selectedUser->lifecycle_status === 'pending_engagement' ? 'return confirm(\'This person is still Pending Engagement. Assigning an admin role now may make them hard to find later. Continue anyway?\');' : '' }}">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ $selectedUser->id }}">

                            {{-- Current role + deauthorize guidance --}}
                            <div class="mb-4 rounded-md border border-gray-200 bg-gray-50 p-4 text-sm">
                                <p class="text-gray-700">
                                    <span class="font-semibold">Current role:</span>
                                    @if($userRole)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-800">
                                            {{ Str::title(str_replace('_', ' ', $userRole)) }}
                                        </span>
                                    @else
                                        <span class="text-gray-500 italic">No role assigned</span>
                                    @endif
                                </p>
                                @if($userRole && $canRemoveCurrentRole)
                                    <p class="mt-2 text-gray-600">
                                        To remove (deauthorize) this person's role, select
                                        <span class="font-medium">“-- No Role --”</span> in the dropdown below and save.
                                        They will keep their account but lose this role's access.
                                    </p>
                                @endif
                                @if($userRole && $roleOptions->isEmpty() && ! $canRemoveCurrentRole)
                                    <p class="mt-2 text-gray-600">
                                        You are not authorized to change or remove this person's role.
                                    </p>
                                @endif
                            </div>

                            <!-- Role Assignment -->
                            <div class="mb-6">
                                <label for="role" class="block font-medium text-sm text-gray-700 mb-2">Assign Role</label>
                                {{-- A current role the actor cannot assign is shown as a disabled, selected
                                     "keep current" first option with an empty value: with `required`, the
                                     browser will not submit the form until another option is chosen. That is
                                     extra protection only: a missing or empty role is "no change" on the
                                     server, and only "-- No Role --" (REMOVE_ROLE) removes a role. --}}
                                {{-- When the actor may change the current role's special permissions, the
                                     keep-current option carries the role itself, so the form can be saved
                                     with the role unchanged. --}}
                                <select name="role" id="role" @required(! $currentRoleAssignable && ! $canKeepCurrentRole) class="form-select w-full rounded-md shadow-sm border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    @if (! $currentRoleAssignable)
                                        @if ($canKeepCurrentRole)
                                            <option value="{{ $userRole }}" selected>
                                                {{ Str::title(str_replace('_', ' ', $userRole)) }} (current — keep)
                                            </option>
                                        @else
                                            <option value="" disabled selected>
                                                {{ Str::title(str_replace('_', ' ', $userRole)) }} (current — keep)
                                            </option>
                                        @endif
                                    @endif
                                    @if (! $userRole || $canRemoveCurrentRole)
                                        <option value="{{ \App\Http\Controllers\UserController::REMOVE_ROLE }}">-- No Role --</option>
                                    @endif
                                    @foreach ($roleOptions as $role)
                                        <option value="{{ $role->name }}" @selected($userRole == $role->name)>
                                            {{ Str::title(str_replace('_', ' ', $role->name)) }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('role')
                                    <p class="mt-2 text-sm text-red-600"><i class="fas fa-circle-exclamation mr-1"></i>{{ $message }}</p>
                                @enderror
                                @error('permissions')
                                    <p class="mt-2 text-sm text-red-600"><i class="fas fa-circle-exclamation mr-1"></i>{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Special Permissions: one section per role that offers tick boxes;
                                 only the section of the role selected above is shown and submitted. -->
                            @if ($specialSections !== [])
                                <input type="hidden" name="special_permissions_shown" value="1">
                                <div class="mb-6" id="special-permissions">
                                    <h3 class="font-medium text-sm text-gray-700 mb-1">Special Permissions</h3>
                                    <p class="text-xs text-gray-500 mb-2 italic">
                                        Extra permissions for this person only, shown for the role selected above.
                                        They are removed when the role is removed or changed.
                                    </p>
                                    @foreach ($specialSections as $sectionRole => $boxes)
                                        @php $sectionShown = $sectionRole === $userRole; @endphp
                                        <div data-special-role="{{ $sectionRole }}" @class(['p-4 border rounded-md bg-gray-50 space-y-2', 'hidden' => ! $sectionShown])>
                                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                                {{ Str::title(str_replace('_', ' ', $sectionRole)) }}
                                            </p>
                                            @foreach ($boxes as $key => $box)
                                                <label @class(['flex items-start gap-3 p-2 rounded-md',
                                                               'hover:bg-gray-100 cursor-pointer' => $box['editable'],
                                                               'opacity-60 cursor-not-allowed' => ! $box['editable']])>
                                                    <input type="checkbox" name="permissions[]" value="{{ $key }}"
                                                           data-special-key="{{ $key }}"
                                                           data-editable="{{ $box['editable'] ? '1' : '0' }}"
                                                           class="form-checkbox h-5 w-5 mt-0.5 text-blue-600 rounded"
                                                           @checked($box['checked'])
                                                           @disabled(! $sectionShown || ! $box['editable'])>
                                                    <span>
                                                        <span class="text-gray-700">{{ $box['label'] }}</span>
                                                        @if ($box['help'])
                                                            <span class="block text-xs text-gray-500">{{ $box['help'] }}</span>
                                                        @endif
                                                        @unless ($box['editable'])
                                                            <span class="block text-xs text-gray-500 italic">Only HQ can change this. Contact a National DB Administrator.</span>
                                                        @endunless
                                                    </span>
                                                </label>
                                            @endforeach

                                            @if ($sectionRole === 'branch_db_administrator' && ($boxes[\App\Support\SpecialPermissions::FINANCIAL_APPROVAL]['editable'] ?? false))
                                                <div id="financial-approval-warning" class="hidden rounded-md bg-amber-50 border border-amber-300 p-3 text-sm text-amber-900">
                                                    <p>
                                                        <i class="fas fa-triangle-exclamation mr-1 text-amber-500"></i>
                                                        <span class="font-semibold">Heads up:</span> Without this permission, this person cannot approve
                                                        donations or fees. Records entered by the branch secretary will then need approval from HQ,
                                                        which may increase HQ's workload. Consider asking the branch secretary to let others enter
                                                        records, so the branch can still approve its own records.
                                                    </p>
                                                    @if ($otherFinancialApprovers === 0)
                                                        <p class="mt-2 font-semibold text-red-700">
                                                            No one in this branch will be able to approve financial records. All approvals will go to HQ.
                                                        </p>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="flex items-center justify-end mt-6">
                                <button type="submit" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 active:bg-red-900 focus:outline-none focus:border-red-900 focus:ring focus:ring-red-300 disabled:opacity-25 transition">
                                    Update Roles & Permissions
                                </button>
                            </div>
                        </form>
                    </div>
                @else
                    <div class="text-center py-12 text-gray-500">
                        <i class="fas fa-search fa-3x mb-4"></i>
                        <p>Search for a user to begin editing their roles and permissions.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- User and Role Table -->
        <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mt-8">
            <div class="p-6 sm:px-20 bg-white border-b border-gray-200">
                <h3 class="text-xl font-semibold mb-4">Users by Role</h3>
                <div class="border rounded-lg overflow-x-auto">
                    <table class="min-w-full" style="border-collapse: collapse;">
                        <thead class="bg-gray-100 sticky top-0">
                        <tr>
                            <th class="border text-left px-3 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider">Name & Permissions</th>
                            <th class="border text-left px-3 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider">{{ $accessLevel === 'national' ? 'Branch' : 'Division' }}</th>
                            <th class="border text-left px-3 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider whitespace-nowrap">DB-Code</th>
                            <th class="border text-left px-3 py-2 text-xs font-medium text-gray-600 uppercase tracking-wider">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        {{-- Users by Role --}}
                        @php $currentRole = null; @endphp
                        @forelse ($usersForTable as $user)
                            @if ($user->role_name !== $currentRole)
                                @php $currentRole = $user->role_name; @endphp
                                <tr class="bg-gray-50">
                                    <td colspan="4" class="border px-3 py-2 text-sm font-semibold text-gray-800">
                                        {{ Str::title(str_replace('_', ' ', $currentRole)) }}
                                        @if($user->role_description)
                                            <div class="text-xs font-normal text-gray-600 mt-1">{{ $user->role_description }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @endif
                            <tr class="bg-white">
                                <td class="border px-3 py-1 text-sm text-gray-900 align-top">
                                <div>{{ $user->fullName }}</div>
                                @php
                                    $directPerms = $user->getDirectPermissions();
                                    // For branch DB administrators financial approval is the normal case:
                                    // tag only the exception (turned off), not the two permissions.
                                    $financialOff = false;
                                    if ($user->role_name === 'branch_db_administrator') {
                                        $financialPerms = \App\Support\SpecialPermissions::FINANCIAL_APPROVAL_PERMISSIONS;
                                        $financialOff = $directPerms->pluck('name')->intersect($financialPerms)->count() < count($financialPerms);
                                        $directPerms = $directPerms->reject(fn ($perm) => in_array($perm->name, $financialPerms, true));
                                    }
                                @endphp
                                @if($financialOff)
                                    <div class="mt-1">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold
                                                     bg-amber-100 text-amber-800 border border-amber-300">
                                            <i class="fas fa-ban text-[10px]"></i>
                                            cannot approve financial records
                                        </span>
                                    </div>
                                @endif
                                @if($directPerms->isNotEmpty())
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        @foreach($directPerms as $perm)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5
                                                         rounded text-xs font-semibold
                                                         bg-red-100 text-red-700 border border-red-300">
                                                <i class="fas fa-key text-[10px]"></i>
                                                {{ str_replace('_', ' ', $perm->name) }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                                @if($accessLevel === 'national')
                                    @if(in_array($user->role_name, ['national_db_administrator', 'national_db_assistant', 'observer_national_level']))
                                        <td class="border px-3 py-1 text-sm text-gray-500">National</td>
                                    @else
                                        <td class="border px-3 py-1 text-sm text-gray-500">{{ $user->branch->name ?? '—' }}</td>
                                    @endif
                                @else
                                    <td class="border px-3 py-1 text-sm text-gray-500">{{ $user->division->name ?? '—' }}</td>
                                @endif
                                <td class="border px-3 py-2 text-sm text-gray-500 font-mono align-top whitespace-nowrap">{{ $user->getUserIdReferenceAttribute() }}</td>
                                <td><x-user-admin-status-badge :user="$user" /></td>
                                <td><x-user-digital-status-badge :user="$user" /></td>

                                @if($user->role_name === 'national_db_administrator' && !$isSuperAdmin)
                                    <td class="border px-3 py-1 text-sm text-gray-400 italic text-center">
                                        Edit by super-admin
                                    </td>
                                @else
                                    <td class="border px-3 py-1 text-sm text-center">
                                        <a href="{{ route('users.roles.edit', ['user_id' => $user->id]) }}" class="inline-flex items-center px-3 py-1 bg-blue-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-600 focus:outline-none focus:ring focus:ring-blue-300 transition">
                                            Edit
                                        </a>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="border text-center py-10 text-gray-500">
                                    No users with roles found within your scope.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" class="fixed top-20 right-10 mt-4 p-4 bg-green-100 text-green-800 rounded-md shadow-lg">
                {{ session('success') }}
            </div>
        @endif
        @if (session('info'))
            <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4000)" x-show="show" class="fixed top-20 right-10 mt-4 p-4 bg-gray-100 text-gray-800 rounded-md shadow-lg">
                {{ session('info') }}
            </div>
        @endif
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const searchInput = document.getElementById('user-search');
                const searchResults = document.getElementById('search-results');
                let debounceTimeout;

                searchInput.addEventListener('input', function () {
                    clearTimeout(debounceTimeout);
                    debounceTimeout = setTimeout(() => {
                        const query = searchInput.value;

                        if (query.length < 2) {
                            searchResults.innerHTML = '';
                            searchResults.classList.add('hidden');
                            return;
                        }

                        fetch(`{{ route('users.search-for-roles') }}?search=${query}`)
                            .then(response => response.json())
                            .then(users => {
                                searchResults.innerHTML = '';
                                if (users.length > 0) {
                                    users.forEach(user => {
                                        const userElement = document.createElement('a');
                                        userElement.href = `{{ route('users.roles.edit') }}?user_id=${user.id}`;
                                        userElement.classList.add('block', 'p-3', 'hover:bg-gray-100');
                                        userElement.innerHTML = `
                                        <div class="font-bold">${user.first_name} ${user.last_name}</div>
                                        <div class="text-sm text-gray-600">${user.user_id_reference}</div>
                                    `;
                                        searchResults.appendChild(userElement);
                                    });
                                    searchResults.classList.remove('hidden');
                                } else {
                                    searchResults.innerHTML = '<div class="p-3 text-gray-500">No users found.</div>';
                                    searchResults.classList.remove('hidden');
                                }
                            });
                    }, 300);
                });

                // Hide results when clicking outside
                document.addEventListener('click', function(event) {
                    if (!searchInput.contains(event.target)) {
                        searchResults.classList.add('hidden');
                    }
                });

                // Scroll to form if a user is selected via the table
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.has('user_id')) {
                    const editForm = document.getElementById('edit-user-form');
                    if(editForm) {
                        editForm.scrollIntoView({ behavior: 'smooth' });
                    }
                }

                // ── Special permissions: show (and submit) only the selected role's tick boxes ──
                const roleSelect = document.getElementById('role');
                const rolesForm = document.getElementById('roles-form');
                const sections = document.querySelectorAll('[data-special-role]');
                const financialBox = document.querySelector(
                    '[data-special-role="branch_db_administrator"] input[data-special-key="approve_financial_records"]');
                const financialWarning = document.getElementById('financial-approval-warning');

                function financialApprovalOff() {
                    return !!(roleSelect && financialWarning && financialBox
                        && roleSelect.value === 'branch_db_administrator' && !financialBox.checked);
                }

                function syncSpecialPermissions() {
                    const role = roleSelect ? roleSelect.value : '';
                    sections.forEach(section => {
                        const shown = section.dataset.specialRole === role;
                        section.classList.toggle('hidden', !shown);
                        section.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                            cb.disabled = !shown || cb.dataset.editable !== '1';
                        });
                    });
                    if (financialWarning) {
                        financialWarning.classList.toggle('hidden', !financialApprovalOff());
                    }
                }

                if (roleSelect) {
                    roleSelect.addEventListener('change', syncSpecialPermissions);
                    syncSpecialPermissions();
                }
                if (financialBox) {
                    financialBox.addEventListener('change', syncSpecialPermissions);
                }

                // Ask before saving a branch DB administrator without financial approval —
                // only when that is a change (not on every save while it stays off).
                const financialWasOff = financialApprovalOff();
                if (rolesForm) {
                    rolesForm.addEventListener('submit', function (event) {
                        if (event.defaultPrevented || financialWasOff || !financialApprovalOff()) {
                            return;
                        }
                        const text = financialWarning.innerText.replace(/\s+/g, ' ').trim();
                        if (!confirm(text + '\n\nSave anyway?')) {
                            event.preventDefault();
                        }
                    });
                }
            });
        </script>
    @endpush
</x-layouts.admin>
