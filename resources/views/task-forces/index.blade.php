<x-layouts.admin title="Task Forces">


    <x-slot name="pageHeader">
        <i class="fas fa-users-gear mr-3"></i>  Task Forces
    </x-slot>

    <x-slot name="subHeader">
        FIND & FILTER
    </x-slot>


    <x-slot name="button1">
        <div class="flex items-center gap-2">
            @can('add_task_force')
                <a href="{{ route('task-forces.create') }}" class="btn-add">
                    <i class="fas fa-plus mr-2"></i>Add New Task Force
                </a>
            @endcan

        </div>
    </x-slot>

    {{-- ── Guide BUTTON ───────────────────────────────────────────── --}}
    <div class="flex justify-center mb-4">
        <x-help-popup trigger-class="help-btn">
            <x-slot:trigger><i class="fas fa-question-circle text-base mr-1"></i>Guide</x-slot:trigger>

            {{-- Header --}}
            <div class="-mt-8 mb-4 text-center">
                <i class="fas fa-circle-question text-xl text-blue-500"></i>
                <h3 class="mt-1 text-base font-semibold text-gray-900">Task Force Guidelines</h3>
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
                                <li>A <span class="font-semibold">task force</span> is a focused team created to carry out a specific mission or activity.</li>
                                <li>It may be <span class="font-semibold">temporary</span> (for a one-time project) or <span class="font-semibold">permanent</span> (for ongoing work).</li>

                                <li>A person can belong to more than one task force at the same time — there is no limit.</li>
                                <li>Any registered volunteer can join any task force — across branches or divisions. For example, a person from Borno can join a task force based in Lagos.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Add / edit task force --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'add_edit' ? null : 'add_edit'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-plus mr-2 text-indigo-400"></i>Add / edit a task force</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'add_edit' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'add_edit'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Press <span class="font-semibold">Add New Task Force</span> to create one.</li>
                                <li>Press <span class="font-semibold">>View → Edit</span> on an existing task force to change its details.</li>
                                <li>Use clear names and descriptions so others understand the team's purpose.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Add / remove members --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'members' ? null : 'members'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-people-group mr-2 text-sky-400"></i>Add / remove members</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'members' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'members'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Open the task force's <span class="font-semibold">>View → Edit</span> page.</li>
                                <li>Use the search box to find and add a person as a member.</li>
                                <li>Only active volunteers show up in search — persons assigned to an active Red Cross Unit. Members who haven't been assigned to a unit, or whose unit is inactive, won't appear.</li>
                                <li>Click <span class="font-semibold">Remove</span> next to a member's name to take them off the task force.</li>
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
                                <li>Open the task force's <span class="font-semibold">>View → Edit</span> page.</li>
                                <li>Set the <span class="font-semibold">Team Leader</span> and <span class="font-semibold">Assistant Team Leader</span> from the members already assigned.</li>
                                <li>Only members of the task force can be set as leaders.</li>
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

                                <li>Archive task forces that are inactive. Use the 'Archive' button. </li>
                                <li>An archived task force can be reactivated at any time. Set filter  <span class="font-semibold">Status → Archived </span> to find them. </li>
                                <li>Deactivate inactive team members to keep the database organised. Use  <span class="font-semibold">>View → Edit → Task Force Members → Remove.</span>  </li>
                            </ul>
                        </div>
                    </div>

                </div>{{-- end accordion --}}
            </div>{{-- end max-w-3xl --}}

        </x-help-popup>
    </div>


    <!-- Filters -->
    <div class="container mx-auto px-4 py-6">
        <div class="filter-container">
            <div class="filter-form-content">
                <form method="GET" action="{{ route('task-forces.index') }}" id="filterForm" class="filter-form">
                    <div class="filter-grid filter-grid-4">
                        <div>
                            <label for="search" class="filter-label">Search</label>
                            <input type="text" name="search" id="search" value="{{ request('search') }}"
                                   placeholder="Search by task force name..."
                                   class="filter-input {{ request('search') ? 'filter-active' : '' }}">
                        </div>

                        <x-filters.branch-select
                            :branches="$branches"
                            :access-level="$accessLevel"
                            :user-branch-id="$userBranchId"
                            field="branch_id"
                            label="Branch"
                            :value="request('branch_id')"
                            :filter-active="(bool)request('branch_id')"
                        />

                        <div>
                            <label for="status" class="filter-label">Status</label>
                            <select name="status" id="status"
                                    class="filter-select {{ request('status', 'active') !== 'active' ? 'filter-active' : '' }}">
                                <option value="active" {{ request('status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="archived" {{ request('status') === 'archived' ? 'selected' : '' }}>Archived</option>
                            </select>
                        </div>

                        <div>
                            <label for="task_force_type_id" class="filter-label">Type</label>
                            <select name="task_force_type_id" id="task_force_type_id"
                                    class="filter-select {{ request('task_force_type_id') ? 'filter-active' : '' }}">
                                <option value="">All Types</option>
                                @foreach($taskForceTypes as $type)
                                    <option value="{{ $type->id }}" {{ request('task_force_type_id') == $type->id ? 'selected' : '' }}>
                                        {{ $type->name }}
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
                            <a href="{{ route('task-forces.index') }}"
                               class="filter-btn-secondary filter-btn-secondary-active">
                                <i class="fas fa-times mr-1"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 pb-6">

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        <div class="table-container">
            @if($taskForces->count() > 0)
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead class="table-header">
                        <tr class="table-header-row">
                            <th class="table-header-cell">Name</th>
                            <th class="table-header-cell">Branch</th>
                            <th class="table-header-cell">Type</th>
                            <th class="table-header-cell">Members</th>
                            <th class="table-header-cell">Leadership</th>
                            <th class="table-header-cell">Actions</th>
                        </tr>
                        </thead>
                        <tbody class="table-body">
                        @foreach($taskForces as $taskForce)
                            <tr class="table-body-row">

                                <td class="table-body-cell" style="white-space: normal; word-break: break-word; max-width: 220px;">
                                    <div class="table-field-main">{{ $taskForce->name }}</div>
                                </td>

                                <td class="table-body-cell">
                                    <div class="table-field-main">{{ $taskForce->branch->name ?? 'N/A' }}</div>
                                </td>

                                <td class="table-body-cell">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        {{ $taskForce->taskForceType->name ?? 'N/A' }}
                                    </span>
                                </td>

                                <td class="table-body-cell">
                                    <div class="table-field-main">{{ $taskForce->active_users_count }}</div>
                                </td>

                                <td class="table-body-cell">
                                    <div class="table-field-main">
                                        {{ $taskForce->teamLeader->full_name ?? '—' }}
                                        @if($taskForce->teamLeader && $taskForce->teamLeader->lifecycle_status === 'archived')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 ml-1">Archived</span>
                                        @endif
                                    </div>
                                    @if($taskForce->assistantTeamLeader)
                                        <div class="table-field-sub">
                                            {{ $taskForce->assistantTeamLeader->full_name }}
                                            @if($taskForce->assistantTeamLeader->lifecycle_status === 'archived')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 ml-1">Archived</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>

                                <td class="table-body-cell-no-wrap">
                                    <div class="flex gap-2 items-center">
                                        <a href="{{ route('task-forces.show', $taskForce) }}"
                                           class="btn-primary whitespace-nowrap">
                                            <i class="fas fa-eye mr-1"></i>View
                                        </a>

                                        @can('campaign_request_create')
                                            @if(! $taskForce->inactive)
                                                <form method="POST" action="{{ route('campaigns.wizard.start') }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="filter_json[task_force_id]" value="{{ $taskForce->id }}">
                                                    <button type="submit" class="inline-flex items-center rounded-md px-3 py-1.5 text-sm font-semibold text-white transition-colors bg-slate-700 hover:bg-slate-800">
                                                        <i class="fas fa-envelope mr-1"></i>Msg
                                                    </button>
                                                </form>
                                            @endif
                                        @endcan

                                        @if($taskForce->inactive)
                                            @can('edit_task_force')
                                                <form action="{{ route('task-forces.reactivate', $taskForce) }}"
                                                      method="POST" class="inline"
                                                      onsubmit="return confirm('Are you sure you want to reactivate this task force?')">
                                                    @csrf
                                                    @method('PUT')
                                                    <button type="submit"
                                                            class="inline-flex items-center px-3 py-1.5 rounded text-sm font-medium bg-yellow-100 text-yellow-800 hover:bg-yellow-200">
                                                        Reactivate
                                                    </button>
                                                </form>
                                            @endcan
                                        @else
                                            @can('remove_task_force')
                                                <form action="{{ route('task-forces.destroy', $taskForce) }}"
                                                      method="POST" class="inline"
                                                      onsubmit="return confirm('Are you sure you want to archive this task force?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-sm text-gray-500 hover:text-red-600 underline">Archive</button>
                                                </form>
                                            @endcan
                                        @endif


                                    </div>
                                </td>

                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="table-pagination">
                    {{ $taskForces->appends(request()->query())->links() }}
                </div>

            @else
                <div class="table-empty-state">
                    <i class="fas fa-users-gear text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No task forces found.</h3>
                    @can('add_task_force')
                        <a href="{{ route('task-forces.create') }}" class="btn-add mt-2">
                            <i class="fas fa-plus mr-2"></i>Create First Task Force
                        </a>
                    @endcan
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
