<x-layouts.admin title="Activities Management">
    <x-slot name="pageHeader">
        <i class="fas fa-hands-helping mr-3"></i> Volunteer Activity Log
    </x-slot>
    <x-slot name="subHeader">
        List of recorded volunteering hours
    </x-slot>

    <x-slot name="button1">
        @can('add_volunteering')
            <a href="{{ route('activities.create') }}" class="btn-add flex flex-col items-center justify-center text-center p-4">
                <span class="flex items-center font-medium">
                    <i class="fas fa-plus mr-2"></i>Add Volunteer Log
                </span>
                <span class="text-xs font-normal opacity-80 uppercase  tracking-wider">
                      View Your Entries
                </span>
            </a>
        @endcan
    </x-slot>

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

                    {{-- Log volunteering hours --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'register' ? null : 'register'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-hands-helping mr-2 text-indigo-400"></i>Log volunteering hours</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'register' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'register'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Click <span class="font-semibold">Add Volunteer Log</span>, then find the person using <span class="font-semibold">Search → Select</span>.</li>
                                <li>Only persons assigned to a <span class="font-semibold">Red Cross Unit</span> can appear in search results.</li>
                                <li>Fill in <span class="font-semibold">Activity Type</span>, <span class="font-semibold">Date</span>, and <span class="font-semibold">Hours</span>, plus a Reference.</li>
                                <li>Click <span class="font-semibold">Create Activity Log</span> to submit.</li>
                                <li>New logs go through the approval workflow before they count as active — see "Understand log status" below.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Assign to a Red Cross Unit or Task Force --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'assign' ? null : 'assign'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-people-group mr-2 text-amber-400"></i>Assign to a Red Cross Unit or Task Force</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'assign' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'assign'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>If the person has a Red Cross Unit, the log is <span class="font-semibold">pre-assigned to that unit by default</span>.</li>
                                <li>To assign to a <span class="font-semibold">Task Force</span> instead, tick <span class="font-semibold">Assign to Task Force</span> and pick one from the dropdown.</li>
                                <li>Tick <span class="font-semibold">Do not assign this to a RC unit or task force</span> if neither applies.</li>

                            </ul>
                        </div>
                    </div>

                    {{-- Understand log status & approvals --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'status' ? null : 'status'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-user-check mr-2 text-violet-400"></i>Understand log status &amp; approvals</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'status' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'status'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Every log starts as <span class="font-semibold">Pending</span> until an approver reviews it.</li>
                                @can('approve_volunteering')
                                    <li>Use the <span class="font-semibold">Records / Approvals</span> tabs at the top to switch between your submitted logs and logs awaiting your approval.</li>
                                @endcan
                                <li>If rejected, you'll get a notification, and the reason appears in your entries list.</li>
                                <li>While a log is still <span class="font-semibold">Pending</span>, you can click <span class="font-semibold">Withdraw</span> to cancel it yourself.</li>
                                <li>Once approved, a log cannot be withdrawn — contact an admin to reverse it if needed.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Filter & find volunteering records --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'filter' ? null : 'filter'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-filter mr-2 text-sky-400"></i>Filter &amp; find volunteering records</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'filter' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'filter'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Click <span class="font-semibold">Filter &amp; Sort</span> to search by name or reference.</li>
                                <li>Narrow down by <span class="font-semibold">Branch → Division → Red Cross Unit</span> — each level unlocks the next.</li>
                                <li>Use <span class="font-semibold">Activity Type</span> to isolate a specific kind of volunteering.</li>
                                <li>Sort by <span class="font-semibold">Date</span>, <span class="font-semibold">Hours</span>, or <span class="font-semibold">Activity Type</span>, ascending or descending.</li>
                                <li>Tick <span class="font-semibold">Entered by me</span> to see logs you registered that have since been approved.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Find deleted volunteering records --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'deleted' ? null : 'deleted'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-trash-can mr-2 text-red-400"></i>Find deleted volunteering records</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'deleted' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'deleted'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Open <span class="font-semibold">Filter &amp; Sort → Include deleted?</span> and choose <span class="font-semibold">Deleted</span> or <span class="font-semibold">All</span>.</li>
                                <li>Deleted rows are highlighted in red with a <span class="font-semibold">DELETED</span> tag next to the reference.</li>
                            </ul>
                        </div>
                    </div>

                </div>{{-- end accordion --}}
            </div>{{-- end max-w-3xl --}}

        </x-help-popup>
    </div>



    <div class="container mx-auto px-4 py-6">

        <x-approval-tabs
            active="records"
            :records-route="route('activities.index')"
            :approvals-route="route('activities.approvals')"
            permission="approve_volunteering"
            :pending-count="$pendingApprovalCount ?? 0" />

        <!-- Filter Toggle Button -->
        <div class="mb-3">
            <button type="button" id="filterToggleBtn"
                    class="inline-flex items-center gap-2 bg-white border border-gray-300 rounded-lg px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <i class="fas fa-filter text-gray-500"></i>
                Filter &amp; Sort
                @if($hasFilters)
                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">Active</span>
                @endif
                <svg id="filterChevron" class="w-4 h-4 text-gray-500 transition-transform duration-200 {{ $hasFilters ? 'rotate-180' : '' }}"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
        </div>

        <!-- Search and Filter Section -->
        <div class="filter-container overflow-hidden transition-all duration-300 {{ $hasFilters ? '' : 'hidden' }}" id="filterContainer">
            <div class="filter-form-content">
                <form method="GET" action="{{ route('activities.index') }}" class="filter-form">
                    <div class="filter-grid filter-grid-5">
                        <div class="flex flex-col gap-2">
                            <!-- Search -->
                            <div>
                                <label for="search" class="filter-label">
                                    Search
                                </label>
                                <input type="text"
                                       id="search"
                                       name="search"
                                       value="{{ request('search') }}"
                                       placeholder="Name, ID, Ref..."
                                       class="filter-input {{ request('search') ? 'filter-active' : '' }}">
                            </div>

                            <!-- Sort By -->
                            <div>
                                <label for="sort_by" class="filter-label">
                                    Sort By
                                </label>
                                <select name="sort_by"
                                        id="sort_by"
                                        class="filter-select">
                                    <option value="date_desc" {{ request('sort_by', 'date_desc') == 'date_desc' ? 'selected' : '' }}>
                                        Activity Date (Newest First)
                                    </option>
                                    <option value="date_asc" {{ request('sort_by') == 'date_asc' ? 'selected' : '' }}>
                                        Activity Date (Oldest First)
                                    </option>
                                    <option value="hours_asc" {{ request('sort_by') == 'hours_asc' ? 'selected' : '' }}>
                                        Hours (Low to High)
                                    </option>
                                    <option value="hours_desc" {{ request('sort_by') == 'hours_desc' ? 'selected' : '' }}>
                                        Hours (High to Low)
                                    </option>
                                    <option value="activity_type_asc" {{ request('sort_by') == 'activity_type_asc' ? 'selected' : '' }}>
                                        Activity Type (A-Z)
                                    </option>
                                    <option value="activity_type_desc" {{ request('sort_by') == 'activity_type_desc' ? 'selected' : '' }}>
                                        Activity Type (Z-A)
                                    </option>
                                </select>
                            </div>


                        </div>

                        <div class="flex flex-col gap-2">
                            <!-- Branch Filter -->
                            <div>
                                <label for="branch_id" class="filter-label-small">
                                    Branch
                                </label>
                                <select id="branch_id"
                                        name="branch_id"
                                        class="filter-select-small
                            @if($accessLevel === 'branch' || $accessLevel === 'division') bg-gray-100 cursor-not-allowed @else {{ request('branch_id') ? 'filter-active' : '' }} @endif"
                                        @if($accessLevel === 'branch' || $accessLevel === 'division') disabled @endif>
                                    @if ($accessLevel === 'national')
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
                                    @if (($accessLevel === 'branch' || $accessLevel === 'division') && $branches->isEmpty())
                                        <option value="" selected>No Branch Accessible</option>
                                    @endif
                                </select>
                            </div>

                            <!-- Division Filter -->
                            <div>
                                <label for="division_id" class="filter-label-small">
                                    Division
                                </label>
                                <select id="division_id"
                                        name="division_id"
                                        class="filter-select-small
                            @if($accessLevel === 'division' || ((!request('branch_id') && !$userBranchId) && $accessLevel !== 'national')) bg-gray-100 cursor-not-allowed @else {{ request('division_id') ? 'filter-active' : '' }} @endif"
                                        @if($accessLevel === 'division' || ((!request('branch_id') && !$userBranchId) && $accessLevel !== 'national')) disabled @endif>
                                    @if ($accessLevel !== 'division')
                                        <option value="">
                                            {{ (request('branch_id') || $userBranchId) ? 'All Divisions' : 'Select Branch First' }}
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
                                    @if ($accessLevel === 'division' && $divisions->isEmpty())
                                        <option value="" selected>No Division Accessible</option>
                                    @endif
                                </select>
                            </div>

                            <!-- Red Cross Unit Filter -->
                            <div>
                                <label for="red_cross_unit_id" class="filter-label-small">
                                    Red Cross Unit
                                </label>
                                <select id="red_cross_unit_id"
                                        name="red_cross_unit_id"
                                        class="filter-select-small
                            @if(!request('division_id') && !($accessLevel === 'division' && $userDivisionId)) bg-gray-100 cursor-not-allowed @else {{ request('red_cross_unit_id') ? 'filter-active' : '' }} @endif"
                                    {{ !request('division_id') && !($accessLevel === 'division' && $userDivisionId) ? 'disabled' : '' }}>
                                    @if (!request('division_id') && !($accessLevel === 'division' && $userDivisionId))
                                        <option value="">Select Division First</option>
                                    @else
                                        <option value="">All Units</option>
                                    @endif
                                    @foreach($redCrossUnits as $unit)
                                        <option value="{{ $unit->id }}" {{ request('red_cross_unit_id') == $unit->id ? 'selected' : '' }}>
                                            {{ $unit->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <!-- Activity Type Filter -->
                            <div>
                                <label for="activity_type_id" class="filter-label-small">
                                    Activity Type
                                </label>
                                <select id="activity_type_id"
                                        name="activity_type_id"
                                        class="filter-select-small {{ request('activity_type_id') ? 'filter-active' : '' }}">
                                    <option value="">All Activity Types</option>
                                    @foreach($activityTypes as $activityType)
                                        <option value="{{ $activityType->id }}" {{ request('activity_type_id') == $activityType->id ? 'selected' : '' }}>
                                            {{ $activityType->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="trashed" class="filter-label-small">
                                    Include deleted?
                                </label>
                                <select name="trashed"
                                        id="trashed"
                                        class="filter-select-small {{ request('trashed') ? 'filter-active' : '' }}">
                                    <option value="" {{ request('trashed') === null || request('trashed') === '' ? 'selected' : '' }}>Active</option>
                                    <option value="only" {{ request('trashed') === 'only' ? 'selected' : '' }}>Deleted</option>
                                    <option value="with" {{ request('trashed') === 'with' ? 'selected' : '' }}>All</option>
                                </select>
                            </div>


                            <!-- My Records Filter -->
                            <div>

                                <div class="space-y-1 {{ request('my_records') == '1' ? 'filter-active' : '' }}">
                                    <div class="flex items-center">
                                        <input type="radio" id="my_records_1" name="my_records" value="1"
                                               {{ request('my_records') == '1' ? 'checked' : '' }}
                                               class="btn-radio">
                                        <label for="my_records_1">Entered by me</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="radio" id="my_records_0" name="my_records" value="0"
                                               {{ request('my_records') === null || request('my_records') == '0' ? 'checked' : '' }}
                                               class="btn-radio">
                                        <label for="my_records_0">Entered by all</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="filter-actions">
                        <div class="filter-button-group">
                            <button type="submit" class="filter-btn-primary">
                                <i class="fas fa-search mr-1"></i>Filter
                            </button>

                            <a @if($hasFilters) href="{{ route('activities.index') }}" @endif
                            class="filter-btn-secondary {{ $hasFilters ? 'filter-btn-secondary-active' : 'filter-btn-disabled' }}">
                                <i class="fas fa-times mr-1"></i>Clear
                            </a>
                        </div>

                    </div>
                </form>
            </div>
        </div>

        <!-- Filter Results Info -->

        <div>

            @if($hasFilters)
                <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded mb-6">
                    <div class="flex items-center justify-between">
                        <div class="text-sm">
                            @if(request('search') && request('my_records'))
                                <p>Showing your records matching: <strong>"{{ request('search') }}"</strong></p>
                            @elseif(request('search'))
                                <p>Showing results for: <strong>"{{ request('search') }}"</strong></p>
                            @elseif(request('my_records'))
                                <p>Showing only records <b>submitted by you</b></p>
                            @endif

                            @if(request('activity_type_id'))
                                @php
                                    $selectedActivityType = $activityTypes->firstWhere('id', request('activity_type_id'));
                                @endphp
                                <p class="mt-1">
                                    Filtered by activity type: <strong>{{ $selectedActivityType ? $selectedActivityType->name : 'Unknown' }}</strong>
                                </p>
                            @endif

                            @if($accessLevel === 'national' && request('branch_id'))
                                @php
                                    $selectedBranch = $branches->firstWhere('id', request('branch_id'));
                                @endphp
                                <p class="mt-1">
                                    Filtered by branch: <strong>{{ $selectedBranch ? $selectedBranch->name : 'Unknown' }}</strong>
                                </p>
                            @endif

                            @if(in_array($accessLevel, ['national', 'branch']) && request('division_id'))
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
                                    Filtered by unit: <strong>{{ $selectedUnit ? $selectedUnit->name : 'Unknown' }}</strong>
                                </p>
                            @endif

                            @if(request('sort_by', 'date_desc') != 'date_desc')
                                <p class="mt-1">
                                    Sorted by:
                                    <strong>
                                        @switch(request('sort_by'))
                                            @case('date_asc')
                                                Date (Oldest First)
                                                @break
                                            @case('hours_asc')
                                                Hours (Lowest First)
                                                @break
                                            @case('hours_desc')
                                                Hours (Highest First)
                                                @break
                                            @case('activity_type_asc')
                                                Activity Type (A-Z)
                                                @break
                                            @case('activity_type_desc')
                                                Activity Type (Z-A)
                                                @break
                                            @default
                                                Date (Newest First)
                                        @endswitch
                                    </strong>
                                </p>
                            @endif

                            @if(request('trashed') === 'only')
                                <p class="mt-1">Showing only deleted records.</p>
                            @elseif(request('trashed') === 'with')
                                <p class="mt-1">Showing all records including deleted ones.</p>
                            @endif

                            <p class="mt-1 text-xl ">
                                {{ $activities->total() }} {{ Str::plural('result', $activities->total()) }} found
                            </p>
                        </div>
                    </div>
                </div>


            @else
                <div class="bg-gray-50 border border-gray-200 text-gray-700 px-4 py-3 rounded mb-6">
                    <div class="text-sm">
                        <p>Showing all activity records
                            @if($accessLevel === 'branch')
                                for your branch
                            @elseif($accessLevel === 'division')
                                for your division
                            @endif
                        </p>
                        <p class="mt-1">({{ number_format($totalRecords) }} total {{ Str::plural('record', $totalRecords) }})</p>
                    </div>
                </div>
            @endif
        </div>

        <!-- Activities Table -->
        <div class="table-container">
            @if($activities->count() > 0)
                <!-- Desktop Table View - hidden on mobile -->
                <div class="hidden lg:block table-wrapper">
                    <table class="data-table">
                        <thead class="table-header">
                        <tr class="table-header-row">
                            <th class="table-header-cell">
                                Person
                            </th>
                            <th class="table-header-cell">
                                Location
                            </th>
                            <th class="table-header-cell">
                                Activity
                            </th>
                            <th class="table-header-cell">
                                Date & Hours
                            </th>
                            <th class="table-header-cell">
                                Reference
                            </th>
                            <th class="table-header-cell">
                                Submitted By
                            </th>
                            <th class="table-header-cell">
                                Actions
                            </th>
                        </tr>
                        </thead>
                        <tbody class="table-body">
                        @foreach($activities as $activity)
                            <tr class="table-body-row {{ $activity->is_deleted ? 'bg-red-50 text-red-900' : '' }}">
                                <td class="table-body-cell">
                                    <div class="flex items-center">
                                        <div class="ml-3">
                                            @if($activity->user)
                                                <div class="table-field-main">
                                                    {{ $activity->user->full_name }}
                                                </div>
                                                <div class="table-field-sub">
                                                    {{ $activity->user->user_id_reference_short }}
                                                </div>
                                            @else
                                                <div class="table-field-main text-red-600">
                                                    User Not Found
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    ID: {{ $activity->user_id }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="table-body-cell">
                                    @if($activity->division)
                                        <div class="table-field-main">{{ $activity->division->name }}</div>
                                    @endif
                                    @if($activity->user && $activity->user->redCrossUnit)
                                        <div class="table-field-sub">{{ $activity->user->redCrossUnit->name }}</div>
                                    @endif
                                </td>
                                <td class="table-body-cell">
                                    <div class="table-field-main">
                                        {{ $activity->activityType->name ?? 'N/A' }}
                                    </div>
                                    @if($activity->assignable)
                                        <div class="table-field-sub">
                                            <i class="fas fa-shield-alt mr-1"></i>
                                            {{ $activity->assignable->name }}
                                            @if($activity->unit_type)
                                                ({{ ucwords(str_replace('_', ' ', str_replace('red_cross', 'RC', $activity->unit_type))) }}
                                                )
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="table-body-cell">
                                    <div class="table-field-main">
                                        <i class="fas fa-clock mr-1"></i>
                                        {{ $activity->hours }} {{ Str::plural('hour', $activity->hours) }}
                                    </div>
                                    <div class="table-field-sub">
                                        <x-time-ago :date="$activity->date" :today="true" placeholder="N/A" />
                                    </div>

                                </td>
                                <td class="table-body-cell">
                                    <div class="text-xs font-bold {{ $activity->is_deleted ? 'text-red-900' : 'text-gray-900' }}">
                                        {{ $activity->getActivityReferenceAttribute() }}
                                    </div>
                                    @if($activity->reference)
                                        <div class="text-xs {{ $activity->is_deleted ? 'text-red-800' : 'text-gray-500' }} mt-1">
                                            <i class="fas fa-hashtag mr-1"></i>{{ $activity->reference }}
                                        </div>
                                    @endif
                                    @if($activity->is_deleted)
                                        <div class="text-xs font-semibold text-red-700 tracking-wide mt-1">
                                            DELETED
                                        </div>
                                    @endif
                                </td>


                                <td class="table-body-cell">
                                    @if($activity->submittedByUser)
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $activity->submittedByUser->full_name ?? ($activity->submittedByUser->first_name . ' ' . $activity->submittedByUser->last_name) }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ $activity->submittedByUser->user_id_reference_short }}
                                        </div>

                                    @else
                                        <span class="text-gray-500">N/A</span>
                                    @endif
                                </td>
                                <td class="table-body-cell-no-wrap">
                                    <a href="{{ route('activities.show', $activity) }}"
                                       class="btn-primary">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View - shown only on mobile -->
                <div class="lg:hidden divide-y divide-gray-200">
                    @foreach($activities as $activity)
                        <div class="p-4 hover:bg-gray-50 {{ $activity->is_deleted ? 'bg-red-50' : '' }}">
                            <!-- Volunteer -->
                            <div class="flex items-center mb-3">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center">
                                        <i class="fas fa-user text-white"></i>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    @if($activity->user)
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $activity->user->first_name }} {{ $activity->user->last_name }}
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            {{ $activity->user->user_id_reference }}
                                        </div>
                                    @else
                                        <div class="text-sm font-medium text-red-600">
                                            User Not Found
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Activity Details -->
                            <div class="space-y-2 text-sm">
                                <div>
                                    <span class="font-medium text-gray-500">Activity:</span>
                                    <span class="text-gray-900">{{ $activity->activityType->name ?? 'N/A' }}</span>
                                    @if($activity->assignable)
                                        <div class="text-xs text-gray-500 mt-1">
                                            <i class="fas fa-shield-alt mr-1"></i>{{ $activity->assignable->name }}
                                        </div>
                                    @endif
                                </div>

                                <div>
                                    <span class="font-medium text-gray-500">Reference:</span>
                                    <span class="text-gray-900">{{ $activity->getActivityReferenceAttribute() }}</span>
                                    @if($activity->is_deleted)
                                        <div class="text-xs font-semibold text-red-700 tracking-wide mt-1">DELETED</div>
                                    @endif
                                </div>

                                <div>
                                    <span class="font-medium text-gray-500">Date:</span>
                                    <span class="text-gray-900"><x-time-ago :date="$activity->date" :today="true" placeholder="N/A" /></span>
                                </div>

                                <div>
                                    <span class="font-medium text-gray-500">Hours:</span>
                                    <span class="text-gray-900">{{ $activity->hours }} {{ Str::plural('hour', $activity->hours) }}</span>
                                </div>

                                @if($activity->branch || $activity->division)
                                    <div>
                                        <span class="font-medium text-gray-500">Location:</span>
                                        <span class="text-gray-900">
                                {{ $activity->branch->name ?? '' }}
                                            @if($activity->division)
                                                - {{ $activity->division->name }}
                                            @endif
                            </span>
                                    </div>
                                @endif

                                @if($activity->submittedByUser)
                                    <div>
                                        <span class="font-medium text-gray-500">Submitted by:</span>
                                        <span class="text-gray-900">
                                {{ $activity->submittedByUser->full_name ?? ($activity->submittedByUser->first_name . ' ' . $activity->submittedByUser->last_name) }}
                            </span>
                                        <div class="text-xs text-gray-500">
                                            <x-time-ago :date="$activity->submitted_at" placeholder="" />
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <!-- Action Button -->
                            <div class="mt-3 ">
                                <a href="{{ route('activities.show', $activity) }}"
                                   class="btn-primary inline-block">
                                    Show Details
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="table-pagination">
                    {{ $activities->appends(request()->query())->links() }}
                </div>
            @else
                <div class="table-empty-state">
                    <i class="fas fa-clipboard-list text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No activities found</h3>
                    <p class="text-gray-500 mb-4">Try adjusting your search or filter criteria.</p>
                </div>
            @endif
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const branchSelect = document.getElementById('branch_id');
            const divisionSelect = document.getElementById('division_id');
            const redCrossUnitSelect = document.getElementById('red_cross_unit_id');

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
                selectElement.classList.add('bg-gray-100', 'cursor-not-allowed'); // Add styling
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
                divisionSelect.classList.remove('bg-gray-100', 'cursor-not-allowed'); // Remove styling
                divisionSelect.innerHTML = '<option value="">Loading divisions...</option>';

                try {
                    const response = await fetch(`/divisions/by-branch?branch_id=${branchId}`);
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
                redCrossUnitSelect.classList.remove('bg-gray-100', 'cursor-not-allowed'); // Remove styling
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
            // Apply disabled styling initially if elements are disabled by Blade.
            if (branchSelect.disabled) {
                branchSelect.classList.add('bg-gray-100', 'cursor-not-allowed');
            }
            if (divisionSelect.disabled) {
                divisionSelect.classList.add('bg-gray-100', 'cursor-not-allowed');
            }
            if (redCrossUnitSelect.disabled) {
                redCrossUnitSelect.classList.add('bg-gray-100', 'cursor-not-allowed');
            }

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

            // Filter toggle
            const filterToggleBtn = document.getElementById('filterToggleBtn');
            const filterContainer = document.getElementById('filterContainer');
            const filterChevron = document.getElementById('filterChevron');

            filterToggleBtn.addEventListener('click', function () {
                const isHidden = filterContainer.classList.contains('hidden');
                filterContainer.classList.toggle('hidden', !isHidden);
                filterChevron.classList.toggle('rotate-180', isHidden);
            });
        });
    </script>
</x-layouts.admin>
