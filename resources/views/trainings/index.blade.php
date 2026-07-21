<x-layouts.admin title="Trainings Management">
    <x-slot name="pageHeader">
        <i class="fas fa-graduation-cap mr-3"></i> Trainings
    </x-slot>
    <x-slot name="subHeader">
        List of recorded trainings
    </x-slot>

    <x-slot name="button1">
        @can('add_trainings')
            <a href="{{ route('trainings.create') }}" class="btn-add flex flex-col items-center justify-center text-center p-4">
                <span class="flex items-center font-medium">
                    <i class="fas fa-plus mr-2"></i>Register Training
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

                    {{-- Register a training --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'register' ? null : 'register'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-graduation-cap mr-2 text-indigo-400"></i>Register a training</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'register' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'register'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Click <span class="font-semibold">Register Training</span>, then find the person using <span class="font-semibold">Search → Select</span>.</li>
                                <li>Choose a <span class="font-semibold">Training Type</span> — types are grouped by category in the dropdown.</li>
                                <li>Fill in <span class="font-semibold">Training Date</span> and <span class="font-semibold">Duration</span>, plus Reference.</li>
                                <li>Click <span class="font-semibold">Create Training</span> to submit.</li>
                                <li>New trainings go through the approval workflow before they count as active — see "Understand training status" below.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Understand validity & expiry --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'validity' ? null : 'validity'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-hourglass-half mr-2 text-amber-400"></i>Understand validity &amp; expiry</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'validity' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'validity'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>When you pick a Training Type, a hint appears showing whether it <span class="font-semibold">expires after a set number of years</span> or has <span class="font-semibold">no expiry</span>.</li>
                                <li>A training's <span class="font-semibold">Status</span> badge reflects this: Valid, Expiring Soon, Expired, or No expiry.</li>
                                <li>Expiry is calculated from the <span class="font-semibold">Training Date</span>, not the date it was registered.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Understand training status & approvals --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'status' ? null : 'status'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-user-check mr-2 text-violet-400"></i>Understand training status &amp; approvals</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'status' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'status'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Every training starts as <span class="font-semibold">Pending</span> until an approver reviews it.</li>
                                @can('approve_training')
                                    <li>Use the <span class="font-semibold">Records / Approvals</span> tabs at the top to switch between your submitted trainings and trainings awaiting your approval.</li>
                                @endcan
                                <li>If rejected, you'll get a notification, and the reason appears in your entries list.</li>
                                <li>While a training is still <span class="font-semibold">Pending</span>, you can click <span class="font-semibold">Withdraw</span> to cancel it yourself.</li>
                                <li>Once approved, a training cannot be withdrawn — contact an admin to reverse it if needed.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Filter & find trainings --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'filter' ? null : 'filter'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-filter mr-2 text-sky-400"></i>Filter &amp; find trainings</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'filter' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'filter'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Click <span class="font-semibold">Filter &amp; Sort</span> to search by name, reference, or training type.</li>
                                <li>Narrow down by <span class="font-semibold">Branch → Division → Red Cross Unit</span> — each level unlocks the next.</li>
                                <li>Use <span class="font-semibold">Status</span> to isolate Valid, Expiring in 2 Weeks, Expiring in 4 Weeks, or Expired trainings.</li>
                                <li>Sort by <span class="font-semibold">Training Date</span> or <span class="font-semibold">Training Type</span>, ascending or descending.</li>
                                <li>Tick <span class="font-semibold">Entered by me</span> to see trainings you registered that have since been approved.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Find deleted training records --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'deleted' ? null : 'deleted'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-trash-can mr-2 text-red-400"></i>Find deleted training records</span>
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
            :records-route="route('trainings.index')"
            :approvals-route="route('trainings.approvals')"
            permission="approve_training"
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
                <form method="GET" action="{{ route('trainings.index') }}" class="filter-form">

                    <div class="filter-grid filter-grid-5">
                        <!-- Column 1: Search + Sort -->
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
                                       placeholder="Name, ID, Ref, Type..."
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
                                    <option value="training_date_desc" {{ request('sort_by', 'training_date_desc') == 'training_date_desc' ? 'selected' : '' }}>
                                        Training Date (Newest First)
                                    </option>
                                    <option value="training_date_asc" {{ request('sort_by') == 'training_date_asc' ? 'selected' : '' }}>
                                        Training Date (Oldest First)
                                    </option>
                                    <option value="training_type_asc" {{ request('sort_by') == 'training_type_asc' ? 'selected' : '' }}>
                                        Training Type (A-Z)
                                    </option>
                                    <option value="training_type_desc" {{ request('sort_by') == 'training_type_desc' ? 'selected' : '' }}>
                                        Training Type (Z-A)
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Column 2: Branch / Division / Unit -->
                        <div class="flex flex-col gap-2">
                            <!-- Branch -->
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
                                                @if(request('branch_id') == $branch->id)
                                                    selected
                                                @elseif(($accessLevel === 'branch' || $accessLevel === 'division') && (string)$userBranchId === (string)$branch->id)
                                                    selected
                                            @endif>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                    @if (($accessLevel === 'branch' || $accessLevel === 'division') && $branches->isEmpty())
                                        <option value="" selected>No Branch Accessible</option>
                                    @endif
                                </select>
                            </div>

                            <!-- Division -->
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
                                                @if(request('division_id') == $division->id)
                                                    selected
                                                @elseif($accessLevel === 'division' && (string)$userDivisionId === (string)$division->id)
                                                    selected
                                            @endif>
                                            {{ $division->name }}
                                        </option>
                                    @endforeach
                                    @if ($accessLevel === 'division' && $divisions->isEmpty())
                                        <option value="" selected>No Division Accessible</option>
                                    @endif
                                </select>
                            </div>

                            <!-- Red Cross Unit -->
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

                        <!-- Column 3: Other filters -->
                        <div class="flex flex-col gap-2">
                            <!-- Training Type -->
                            <div>
                                <label for="training_type_id" class="filter-label-small">
                                    Training Type
                                </label>
                                <select id="training_type_id"
                                        name="training_type_id"
                                        class="filter-select-small {{ request('training_type_id') ? 'filter-active' : '' }}">
                                    <option value="">All Training Types</option>
                                    @foreach($trainingTypes as $trainingType)
                                        <option value="{{ $trainingType->id }}" {{ request('training_type_id') == $trainingType->id ? 'selected' : '' }}>
                                            {{ $trainingType->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <!-- Status -->
                            <div>
                                <label for="status" class="filter-label-small">
                                    Status
                                </label>
                                <select id="status"
                                        name="status"
                                        class="filter-select-small {{ request('status') ? 'filter-active' : '' }}">
                                    <option value="">All Statuses</option>
                                    <option value="valid" {{ request('status') == 'valid' ? 'selected' : '' }}>Valid</option>
                                    <option value="expiring_2_weeks" {{ request('status') == 'expiring_2_weeks' ? 'selected' : '' }}>Expiring in 2 Weeks</option>
                                    <option value="expiring_4_weeks" {{ request('status') == 'expiring_4_weeks' ? 'selected' : '' }}>Expiring in 4 Weeks</option>
                                    <option value="expired" {{ request('status') == 'expired' ? 'selected' : '' }}>Expired</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <!-- Records (trashed) -->
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

                            <a @if($hasFilters) href="{{ route('trainings.index') }}" @endif
                            class="filter-btn-secondary {{ $hasFilters ? 'filter-btn-secondary-active' : 'filter-btn-disabled' }}">
                                <i class="fas fa-times mr-1"></i>Clear
                            </a>
                        </div>
                    </div>

                </form>
            </div>
        </div>

        <!-- Filter Results Info -->
        @php $totalTrainings = $trainings->total(); @endphp

        @if($hasFilters)
            <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded mb-6">
                <div class="text-sm space-y-1">
                    @if(request('search'))
                        <p>Search: <strong>"{{ request('search') }}"</strong></p>
                    @endif
                    @if(request('branch_id'))
                        @php $selectedBranch = $branches->firstWhere('id', request('branch_id')); @endphp
                        <p>Branch: <strong>{{ $selectedBranch ? $selectedBranch->name : 'Unknown' }}</strong></p>
                    @endif
                    @if(request('division_id'))
                        @php $selectedDivision = $divisions->firstWhere('id', request('division_id')); @endphp
                        <p>Division: <strong>{{ $selectedDivision ? $selectedDivision->name : 'Unknown' }}</strong></p>
                    @endif
                    @if(request('red_cross_unit_id'))
                        @php $selectedUnit = $redCrossUnits->firstWhere('id', request('red_cross_unit_id')); @endphp
                        <p>Red Cross Unit: <strong>{{ $selectedUnit ? $selectedUnit->name : 'Unknown' }}</strong></p>
                    @endif
                    @if(request('training_type_id'))
                        @php $selectedTrainingType = $trainingTypes->firstWhere('id', request('training_type_id')); @endphp
                        <p>Training Type: <strong>{{ $selectedTrainingType ? $selectedTrainingType->name : 'Unknown' }}</strong></p>
                    @endif
                    @if(request('status'))
                        <p>Status: <strong>{{ ucwords(str_replace('_', ' ', request('status'))) }}</strong></p>
                    @endif
                    @if(request('my_records') == '1')
                        <p>Entered by: <strong>Me</strong></p>
                    @endif
                    @if(request('trashed') === 'only')
                        <p>Records: <strong>Deleted only</strong></p>
                    @elseif(request('trashed') === 'with')
                        <p>Records: <strong>All (including deleted)</strong></p>
                    @endif
                    <p class="mt-1">
                        {{ number_format($totalTrainings) }} {{ Str::plural('result', $totalTrainings) }} found
                    </p>
                </div>
            </div>
        @else
            <div class="bg-gray-50 border border-gray-200 text-gray-700 px-4 py-3 rounded mb-6">
                <div class="text-sm">
                    <p>Showing all training records
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

        <!-- Training List -->
        <div class="table-container">
            @if($trainings->count() > 0)

                <!-- Desktop Table — hidden on mobile -->
                <div class="hidden lg:block table-wrapper">
                    <table class="data-table">
                        <thead class="table-header">
                        <tr class="table-header-row">
                            <th class="table-header-cell">Person</th>
                            <th class="table-header-cell">Location</th>
                            <th class="table-header-cell">Training Type</th>
                            <th class="table-header-cell">Date &amp; Duration</th>
                            <th class="table-header-cell">Status</th>
                            <th class="table-header-cell">Reference</th>
                            <th class="table-header-cell">Submitted By</th>
                            <th class="table-header-cell">Actions</th>
                        </tr>
                        </thead>
                        <tbody class="table-body">
                        @foreach($trainings as $training)
                            <tr class="table-body-row {{ $training->is_deleted ? 'bg-red-50 text-red-900' : '' }}">
                                <td class="table-body-cell">
                                    @if($training->user)
                                        <div class="table-field-main">{{ $training->user->full_name ?: 'No Name' }}</div>
                                        <div class="table-field-sub">{{ $training->user->user_id_reference_short }}</div>
                                    @else
                                        <div class="table-field-main text-red-600">User Not Found</div>
                                        <div class="table-field-sub">ID: {{ $training->user_id }}</div>
                                    @endif
                                </td>
                                <td class="table-body-cell">
                                    @if($training->branch)
                                        <div class="table-field-main">{{ $training->branch->name }}</div>
                                    @endif
                                    @if($training->division)
                                        <div class="table-field-sub">{{ $training->division->name }}</div>
                                    @endif
                                    @if($training->user && $training->user->redCrossUnit)
                                        <div class="table-field-detail">{{ $training->user->redCrossUnit->name }}</div>
                                    @endif
                                </td>
                                <td class="table-body-cell">
                                    <div class="table-field-main">{{ $training->trainingType->name ?? 'N/A' }}</div>
                                </td>

                                <td class="table-body-cell">
                                    <div class="table-field-main"><x-time-ago :date="$training->training_date" :today="true" placeholder="" /></div>
                                    <div class="table-field-sub">
                                        <i class="fas fa-calendar-day mr-1"></i>{{ $training->formatted_duration }}
                                    </div>
                                </td>

                                <td class="table-body-cell">
                                    @if(!$training->is_deleted)
                                        @php
                                            $status = $training->status;
                                            $statusClasses = [
                                                'valid'          => 'bg-green-100 text-green-800',
                                                'expired'        => 'bg-red-100 text-red-800',
                                                'expiring_soon'  => 'bg-yellow-100 text-yellow-800',
                                                'permanent'      => 'bg-blue-100 text-blue-800',
                                            ];
                                        @endphp
                                        @php
                                            $statusLabels = ['permanent' => 'No expiry'];
                                            $statusLabel = $statusLabels[$status] ?? ucwords(str_replace('_', ' ', $status));
                                        @endphp
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $statusClasses[$status] ?? 'bg-gray-100 text-gray-800' }}">
                                            {{ $statusLabel }}
                                        </span>
                                        <div class="table-field-sub">
                                            <i class="fas fa-calendar-day mr-1"></i>{{ $training->expiry_date?->format('d M Y') ?? 'No expiry' }}
                                        </div>
                                    @endif
                                </td>
                                <td class="table-body-cell">
                                    <div class="text-xs font-bold {{ $training->is_deleted ? 'text-red-900' : 'text-gray-900' }}">
                                        {{ $training->getTrainingReferenceAttribute() }}
                                    </div>
                                    @if($training->reference)
                                        <div class="text-xs {{ $training->is_deleted ? 'text-red-800' : 'text-gray-500' }} mt-1">
                                            <i class="fas fa-hashtag mr-1"></i>{{ $training->reference }}
                                        </div>
                                    @endif
                                    @if($training->is_deleted)
                                        <div class="text-xs font-semibold text-red-700 tracking-wide mt-1">
                                            DELETED
                                        </div>
                                    @endif
                                </td>
                                <td class="table-body-cell">
                                    @if($training->submittedByUser)
                                        <div class="table-field-main">
                                            {{ $training->submittedByUser->full_name }}
                                        </div>
                                        <div class="table-field-sub">
                                            {{ $training->submittedByUser->user_id_reference_short }}
                                        </div>

                                    @else
                                        <span class="table-field-sub">N/A</span>
                                    @endif
                                </td>
                                <td class="table-body-cell-nowrap">
                                    <a href="{{ route('trainings.show', $training) }}" class="btn-primary">View</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View — shown only on mobile -->
                <div class="lg:hidden divide-y divide-gray-200">
                    @foreach($trainings as $training)
                        <div class="p-4 hover:bg-gray-50 {{ $training->is_deleted ? 'bg-red-50' : '' }}">
                            <div class="flex items-center mb-3">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-green-400 to-green-600 flex items-center justify-center">
                                        <i class="fas fa-user text-white"></i>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    @if($training->user)
                                        <div class="text-sm font-medium text-gray-900">{{ $training->user->full_name ?: 'No Name' }}</div>
                                        <div class="text-xs text-gray-500">{{ $training->user->user_id_reference }}</div>
                                    @else
                                        <div class="text-sm font-medium text-red-600">User Not Found</div>
                                    @endif
                                </div>
                            </div>

                            <div class="space-y-2 text-sm">
                                <div>
                                    <span class="font-medium text-gray-500">Training Type:</span>
                                    <span class="text-gray-900">{{ $training->trainingType->name ?? 'N/A' }}</span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-500">Date:</span>
                                    <span class="text-gray-900"><x-time-ago :date="$training->training_date" :today="true" placeholder="N/A" /></span>
                                    <span class="text-gray-500 ml-2">
                                        <i class="fas fa-calendar-day mr-1"></i>{{ $training->formatted_duration }}
                                    </span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-500">Reference:</span>
                                    <span class="{{ $training->is_deleted ? 'text-red-900 font-bold' : 'text-gray-900' }}">{{ $training->getTrainingReferenceAttribute() }}</span>
                                    @if($training->reference)
                                        <div class="text-xs {{ $training->is_deleted ? 'text-red-800' : 'text-gray-500' }} mt-0.5">
                                            <i class="fas fa-hashtag mr-1"></i>{{ $training->reference }}
                                        </div>
                                    @endif
                                    @if($training->is_deleted)
                                        <div class="text-xs font-semibold text-red-700 tracking-wide mt-1">DELETED</div>
                                    @endif
                                </div>
                                @if($training->branch || $training->division)
                                    <div>
                                        <span class="font-medium text-gray-500">Location:</span>
                                        <span class="text-gray-900">
                                            {{ $training->branch->name ?? '' }}
                                            @if($training->division) – {{ $training->division->name }} @endif
                                        </span>
                                    </div>
                                @endif
                                @if(!$training->is_deleted)
                                    <div>
                                        @php
                                            $status = $training->status;
                                            $statusClasses = [
                                                'valid'          => 'bg-green-100 text-green-800',
                                                'expired'        => 'bg-red-100 text-red-800',
                                                'expiring_soon'  => 'bg-yellow-100 text-yellow-800',
                                                'permanent'      => 'bg-blue-100 text-blue-800',
                                            ];
                                            $statusLabels = ['permanent' => 'No expiry'];
                                            $statusLabel = $statusLabels[$status] ?? ucwords(str_replace('_', ' ', $status));
                                        @endphp
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $statusClasses[$status] ?? 'bg-gray-100 text-gray-800' }}">
                                            {{ $statusLabel }}
                                        </span>
                                    </div>
                                @endif
                                @if($training->submittedByUser)
                                    <div>
                                        <span class="font-medium text-gray-500">Submitted by:</span>
                                        <span class="text-gray-900">{{ $training->submittedByUser->full_name }}</span>
                                        <div class="text-xs text-gray-500"><x-time-ago :date="$training->submitted_at" placeholder="" /></div>
                                    </div>
                                @endif
                            </div>

                            <div class="mt-3">
                                <a href="{{ route('trainings.show', $training) }}" class="btn-primary inline-block">
                                    View Details
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="table-pagination">
                    {{ $trainings->appends(request()->query())->links() }}
                </div>

            @else
                <div class="table-empty-state">
                    <i class="fas fa-graduation-cap text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No training records found</h3>
                    <p class="text-gray-500 mb-4">Try adjusting your search or filter criteria.</p>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const branchSelect = document.getElementById('branch_id');
                const divisionSelect = document.getElementById('division_id');
                const redCrossUnitSelect = document.getElementById('red_cross_unit_id');

                const accessLevel = "{{ $accessLevel }}";
                const userBranchId = "{{ $userBranchId ?? '' }}";
                const userDivisionId = "{{ $userDivisionId ?? '' }}";

                const initialSelectedBranch = branchSelect.value;
                const initialSelectedDivision = divisionSelect.value;
                const initialSelectedRedCrossUnit = redCrossUnitSelect.value;

                function applyDisabledStyling(el) {
                    if (el.disabled) {
                        el.classList.add('bg-gray-100', 'cursor-not-allowed');
                        el.classList.remove('bg-white');
                    } else {
                        el.classList.remove('bg-gray-100', 'cursor-not-allowed');
                        el.classList.add('bg-white');
                    }
                }

                function resetAndDisableSelect(el, placeholder) {
                    el.innerHTML = `<option value="">${placeholder}</option>`;
                    el.disabled = true;
                    applyDisabledStyling(el);
                }

                async function populateDivisions(branchId, selectedDivisionId = '') {
                    resetAndDisableSelect(redCrossUnitSelect, 'Select Division First');

                    if (divisionSelect.disabled && accessLevel === 'division') return;

                    if (!branchId) {
                        resetAndDisableSelect(divisionSelect, 'Select Branch First');
                        return;
                    }

                    divisionSelect.disabled = false;
                    applyDisabledStyling(divisionSelect);
                    divisionSelect.innerHTML = '<option value="">Loading divisions...</option>';

                    try {
                        const response = await fetch(`/divisions/by-branch?branch_id=${branchId}`);
                        const divisions = await response.json();

                        divisionSelect.innerHTML = accessLevel !== 'division' ? '<option value="">All Divisions</option>' : '';
                        divisions.forEach(division => {
                            const option = document.createElement('option');
                            option.value = division.id;
                            option.textContent = division.name;
                            if (String(division.id) === String(selectedDivisionId) || (accessLevel === 'division' && String(division.id) === userDivisionId)) {
                                option.selected = true;
                            }
                            divisionSelect.appendChild(option);
                        });

                        if (selectedDivisionId && Array.from(divisionSelect.options).some(o => String(o.value) === String(selectedDivisionId))) {
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
                    if (redCrossUnitSelect.disabled && accessLevel === 'division' && String(divisionId) !== userDivisionId) return;

                    if (!divisionId) {
                        resetAndDisableSelect(redCrossUnitSelect, 'Select Division First');
                        return;
                    }

                    redCrossUnitSelect.disabled = false;
                    applyDisabledStyling(redCrossUnitSelect);
                    redCrossUnitSelect.innerHTML = '<option value="">Loading units...</option>';

                    try {
                        const response = await fetch(`/red-cross-units/by-division?division_id=${divisionId}`);
                        const units = await response.json();

                        redCrossUnitSelect.innerHTML = '<option value="">All Units</option>';
                        units.forEach(unit => {
                            const option = document.createElement('option');
                            option.value = unit.id;
                            option.textContent = unit.name;
                            if (String(unit.id) === String(selectedUnitId)) option.selected = true;
                            redCrossUnitSelect.appendChild(option);
                        });
                    } catch (error) {
                        console.error('Error fetching Red Cross Units:', error);
                        resetAndDisableSelect(redCrossUnitSelect, 'Error loading units');
                    }
                }

                // Event listeners
                if (accessLevel === 'national') {
                    branchSelect.addEventListener('change', () => populateDivisions(branchSelect.value));
                    divisionSelect.addEventListener('change', () => populateRedCrossUnits(divisionSelect.value));
                } else if (accessLevel === 'branch') {
                    divisionSelect.addEventListener('change', () => populateRedCrossUnits(divisionSelect.value));
                }

                // Initial disabled styling
                applyDisabledStyling(branchSelect);
                applyDisabledStyling(divisionSelect);
                applyDisabledStyling(redCrossUnitSelect);

                // Initial population
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
    @endpush
</x-layouts.admin>
