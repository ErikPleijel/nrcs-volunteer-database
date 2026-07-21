<x-layouts.admin title="Donations Management">
    <x-slot name="pageHeader">
        <i class="fas fa-heart mr-3"></i> Donations
    </x-slot>
    <x-slot name="subHeader">
        List of recorded donations
    </x-slot>

    <x-slot name="button1">
        @can('add_donations')
            <a href="{{ route('donations.create') }}" class="btn-add flex flex-col items-center justify-center text-center p-4">
                <span class="flex items-center font-medium">
                    <i class="fas fa-plus mr-2"></i>Add Donation
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

                    {{-- Register a donation --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'register' ? null : 'register'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-heart mr-2 text-indigo-400"></i>Register a donation</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'register' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'register'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Click <span class="font-semibold">Add Donation</span>, then find the donor using <span class="font-semibold">Search → Select</span>.</li>
                                <li>Fill in <span class="font-semibold">Donation Date</span>, and add <span class="font-semibold">Reference</span> and <span class="font-semibold">Purpose</span>.</li>
                                <li>Click <span class="font-semibold">Create Donation</span> to submit.</li>
                                <li>New donations go through the approval workflow before they count as active — see "Understand donation status" below.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Cash vs In-Kind donations --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'type' ? null : 'type'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-box-open mr-2 text-amber-400"></i>Log a cash or in-kind donation</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'type' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'type'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>By default, donations are logged as <span class="font-semibold">Cash</span> — enter the <span class="font-semibold">Amount</span> in Naira.</li>
                                <li>Tick <span class="font-semibold">In-Kind Donation</span> to switch the form: enter the <span class="font-semibold">Donation Item</span> and the number of items instead.</li>
                                <li>The donation type shown in the records list (Cash / In Kind) reflects this choice.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Log an anonymous donation --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'anonymous' ? null : 'anonymous'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-user-secret mr-2 text-slate-400"></i>Log an anonymous donation</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'anonymous' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'anonymous'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Tick <span class="font-semibold">Anonymous donation</span> to hide the donor's DB reference in the records list.</li>
                                <li>Anonymity only affects what's displayed afterward, not who is on file.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Understand donation status & approvals --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'status' ? null : 'status'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-user-check mr-2 text-violet-400"></i>Understand donation status &amp; approvals</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'status' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'status'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Every donation starts as <span class="font-semibold">Pending</span> until an approver reviews it.</li>
                                @can('approve_donations')
                                    <li>Use the <span class="font-semibold">Records / Approvals</span> tabs at the top to switch between your submitted donations and donations awaiting your approval.</li>
                                @endcan
                                <li>If rejected, you'll get a notification, and the reason appears in your entries list.</li>
                                <li>While a donation is still <span class="font-semibold">Pending</span>, you can click <span class="font-semibold">Withdraw</span> to cancel it yourself.</li>
                                <li>Once approved, a donation cannot be withdrawn — contact an admin to reverse it if needed.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Filter & find donations --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'filter' ? null : 'filter'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-filter mr-2 text-sky-400"></i>Filter &amp; find donations</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'filter' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'filter'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Click <span class="font-semibold">Filter &amp; Sort</span> to search by donor name, reference, or purpose.</li>
                                <li>Narrow down by <span class="font-semibold">Branch → Division → Red Cross Unit</span> — each level unlocks the next.</li>
                                <li>Sort by <span class="font-semibold">Date</span> or <span class="font-semibold">Amount</span>, ascending or descending.</li>
                                <li>Tick <span class="font-semibold">Entered by me</span> to see donations you registered that have since been approved.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Find deleted donation records --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'deleted' ? null : 'deleted'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-trash-can mr-2 text-red-400"></i>Find deleted donation records</span>
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
            :records-route="route('donations.index')"
            :approvals-route="route('donations.approvals')"
            permission="approve_donations"
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

        <!-- Search and Basic Filters -->
        <div class="filter-container overflow-hidden transition-all duration-300 {{ $hasFilters ? '' : 'hidden' }}" id="filterContainer">
            <div class="filter-form-content">
                <form method="GET" action="{{ route('donations.index') }}" class="filter-form">

                    <div class="filter-grid filter-grid-5">
                        <!-- Column 1: Search + Sort -->
                        <div class="flex flex-col gap-2">
                            <!-- Search -->
                            <div>
                                <label for="search" class="filter-label">
                                    Search
                                </label>
                                <input type="text"
                                       name="search"
                                       id="search"
                                       value="{{ request('search') }}"
                                       placeholder="Name, ID, ref, purpose…"
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
                                        Date (Newest First)
                                    </option>
                                    <option value="date_asc" {{ request('sort_by') == 'date_asc' ? 'selected' : '' }}>
                                        Date (Oldest First)
                                    </option>
                                    <option value="amount_desc" {{ request('sort_by') == 'amount_desc' ? 'selected' : '' }}>
                                        Amount (Highest First)
                                    </option>
                                    <option value="amount_asc" {{ request('sort_by') == 'amount_asc' ? 'selected' : '' }}>
                                        Amount (Lowest First)
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
                                <select name="branch_id"
                                        id="branch_id"
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
                                <select name="division_id"
                                        id="division_id"
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
                                <select name="red_cross_unit_id"
                                        id="red_cross_unit_id"
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

                        <!-- My Records Filter -->
                        <div class="flex flex-col gap-2">
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
                                        <input type="radio"
                                               id="my_records_1"
                                               name="my_records"
                                               value="1"
                                               {{ request('my_records') == '1' ? 'checked' : '' }}
                                               class="btn-radio">
                                        <label for="my_records_1">Entered by me</label>
                                    </div>
                                    <div class="flex items-center">
                                        <input type="radio"
                                               id="my_records_0"
                                               name="my_records"
                                               value="0"
                                               {{ request('my_records') === null || request('my_records') == '0' ? 'checked' : '' }}
                                               class="btn-radio">
                                        <label for="my_records_0">Entered by all</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="filter-actions">
                        <div class="filter-button-group">
                            <button type="submit" class="filter-btn-primary">
                                <i class="fas fa-search mr-1"></i>Filter
                            </button>

                            <a @if($hasFilters) href="{{ route('donations.index') }}" @endif
                            class="filter-btn-secondary {{ $hasFilters ? 'filter-btn-secondary-active' : 'filter-btn-disabled' }}">
                                <i class="fas fa-times mr-1"></i>Clear
                            </a>
                        </div>
                    </div>

                </form>
            </div>
        </div>

        <!-- Filter Results Info -->
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
                    @if(request('my_records') == '1')
                        <p>Entered by: <strong>Me</strong></p>
                    @endif
                    @if(request('trashed') === 'only')
                        <p>Records: <strong>Deleted only</strong></p>
                    @elseif(request('trashed') === 'with')
                        <p>Records: <strong>All (including deleted)</strong></p>
                    @endif
                    <p class="mt-1">
                        {{ $donations->total() }} {{ Str::plural('result', $donations->total()) }} found
                    </p>
                </div>
            </div>
        @else
            <div class="bg-gray-50 border border-gray-200 text-gray-700 px-4 py-3 rounded mb-6">
                <div class="text-sm">
                    <p>Showing all donation records
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

        <!-- Donations Table -->
        <div class="table-container">
            @if($donations->count() > 0)

                <!-- Desktop Table — hidden on mobile -->
                <div class="hidden lg:block table-wrapper">
                    <table class="data-table">
                        <thead class="table-header">
                        <tr class="table-header-row">
                            <th class="table-header-cell">Person</th>
                            <th class="table-header-cell">Location</th>
                            <th class="table-header-cell">Type</th>
                            <th class="table-header-cell">Quantity</th>
                            <th class="table-header-cell">Purpose</th>
                            <th class="table-header-cell">Date</th>
                            <th class="table-header-cell">Reference</th>
                            <th class="table-header-cell">Submitted By</th>
                            <th class="table-header-cell">Actions</th>
                        </tr>
                        </thead>
                        <tbody class="table-body">
                        @foreach($donations as $donation)
                            @if(is_object($donation) && !is_null($donation->id))
                                <tr class="table-body-row {{ $donation->is_deleted ? 'bg-red-50 text-red-900' : '' }}">
                                    <td class="table-body-cell">
                                        <div class="table-field-main">{{ $donation->donor_full_name }}</div>
                                        @if(!$donation->anonymous && $donation->user)
                                            <div class="table-field-sub">{{ $donation->user->user_id_reference_short }}</div>
                                        @endif
                                    </td>
                                    <td class="table-body-cell">
                                        @if($donation->division)
                                            <div class="table-field-main">{{ $donation->division->name }}</div>
                                        @endif
                                        @if($donation->user && $donation->user->redCrossUnit)
                                            <div class="table-field-sub">{{ $donation->user->redCrossUnit->name }}</div>
                                        @endif
                                    </td>
                                    <td class="table-body-cell">
                                        @if($donation->in_kind_donation)
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                                In Kind
                                            </span>
                                        @else
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Cash</span>
                                        @endif
                                    </td>
                                    <td class="table-body-cell">
                                        <div class="table-field-main w-24 break-words @if($donation->in_kind_donation) text-blue-600 @endif">
                                            {{ $donation->formatted_donation }}
                                        </div>
                                    </td>
                                    <td class="table-body-cell">
                                        <div class="table-field-main w-24 break-words">
                                            {{ $donation->purpose ?? '-' }}
                                        </div>
                                    </td>
                                    <td class="table-body-cell">
                                        <div class="table-field-main">
                                            <x-time-ago :date="$donation->date_donation" :today="true" placeholder="N/A" />
                                        </div>
                                    </td>
                                    <td class="table-body-cell">
                                        <div class="table-field-main {{ $donation->is_deleted ? 'text-red-900' : 'text-gray-900' }}">
                                            {{ $donation->getDonationReferenceAttribute() }}
                                        </div>
                                        @if($donation->reference)
                                            <div class="text-xs {{ $donation->is_deleted ? 'text-red-800' : 'text-gray-500' }} mt-1">
                                                <i class="fas fa-hashtag mr-1"></i>{{ $donation->reference }}
                                            </div>
                                        @endif
                                        @if($donation->is_deleted)
                                            <div class="text-xs font-semibold text-red-700 tracking-wide mt-1">
                                                DELETED
                                            </div>
                                        @endif
                                    </td>
                                    <td class="table-body-cell">
                                        @if($donation->submittedByUser)
                                            <div class="table-field-main">
                                                {{ $donation->submittedByUser->full_name }}
                                            </div>
                                            <div class="table-field-sub">
                                                {{ $donation->submittedByUser->user_id_reference_short }}
                                            </div>
                                        @else
                                            <span class="table-field-sub">N/A</span>
                                        @endif
                                    </td>

                                    <td class="table-body-cell-nowrap">
                                        <a href="{{ route('donations.show', $donation) }}" class="btn-primary">View</a>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View — shown only on mobile -->
                <div class="lg:hidden divide-y divide-gray-200">
                    @foreach($donations as $donation)
                        @if(is_object($donation) && !is_null($donation->id))
                            <div class="p-4 hover:bg-gray-50 {{ $donation->is_deleted ? 'bg-red-50' : '' }}">
                                <div class="flex items-center mb-3">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-red-400 to-red-600 flex items-center justify-center">
                                            <i class="fas fa-heart text-white"></i>
                                        </div>
                                    </div>
                                    <div class="ml-3">
                                        <div class="text-sm font-medium text-gray-900">{{ $donation->donor_full_name }}</div>
                                        @if(!$donation->anonymous && $donation->user)
                                            <div class="text-xs text-gray-500">{{ $donation->user->user_id_reference }}</div>
                                        @endif
                                    </div>
                                </div>

                                <div class="space-y-2 text-sm">
                                    <div>
                                        <span class="font-medium text-gray-500">Type:</span>
                                        @if($donation->in_kind_donation)
                                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 ml-1">
                                                {{ $donation->donation_item ?: 'In-Kind' }}
                                            </span>
                                        @else
                                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800 ml-1">Cash</span>
                                        @endif
                                    </div>
                                    <div>
                                        <span class="font-medium text-gray-500">Amount:</span>
                                        <span class="@if($donation->in_kind_donation) text-blue-600 @else text-gray-900 @endif">
                                            {{ $donation->formatted_donation }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="font-medium text-gray-500">Date:</span>
                                        <span class="text-gray-900">
                                            <x-time-ago :date="$donation->date_donation" :today="true" placeholder="N/A" />
                                        </span>
                                    </div>
                                    <div>
                                        <span class="font-medium text-gray-500">Reference:</span>
                                        <span class="{{ $donation->is_deleted ? 'text-red-900 font-bold' : 'text-gray-900' }}">{{ $donation->getDonationReferenceAttribute() }}</span>
                                        @if($donation->reference)
                                            <div class="text-xs {{ $donation->is_deleted ? 'text-red-800' : 'text-gray-500' }} mt-0.5">
                                                <i class="fas fa-hashtag mr-1"></i>{{ $donation->reference }}
                                            </div>
                                        @endif
                                        @if($donation->is_deleted)
                                            <div class="text-xs font-semibold text-red-700 tracking-wide mt-1">DELETED</div>
                                        @endif
                                    </div>
                                    @if($donation->purpose)
                                        <div>
                                            <span class="font-medium text-gray-500">Purpose:</span>
                                            <span class="text-gray-900">{{ $donation->purpose }}</span>
                                        </div>
                                    @endif
                                    @if($donation->branch || $donation->division)
                                        <div>
                                            <span class="font-medium text-gray-500">Location:</span>
                                            <span class="text-gray-900">
                                                {{ $donation->branch->name ?? '' }}
                                                @if($donation->division) – {{ $donation->division->name }} @endif
                                            </span>
                                        </div>
                                    @endif
                                    @if($donation->submittedByUser)
                                        <div>
                                            <span class="font-medium text-gray-500">Submitted by:</span>
                                            <span class="text-gray-900">{{ $donation->submittedByUser->full_name }}</span>
                                            <div class="text-xs text-gray-500">
                                                {{ $donation->submittedByUser->user_id_reference_short }}
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div class="mt-3">
                                    <a href="{{ route('donations.show', $donation) }}" class="btn-primary inline-block">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="table-pagination">
                    {{ $donations->appends(request()->query())->links() }}
                </div>

            @else
                <div class="table-empty-state">
                    <i class="fas fa-hand-holding-heart text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No donation records found</h3>
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
