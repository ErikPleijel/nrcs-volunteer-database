
<x-layouts.admin title="Payments">
    <x-slot name="pageHeader">
        <i class="fas fa-hand-holding-dollar mr-3"></i> Payments
    </x-slot>
    <x-slot name="subHeader">
        List of recorded payments
    </x-slot>

    <x-slot name="button1">
        @can('add_payments')
            <a href="{{ route('membership-payments.create') }}" class="btn-add flex flex-col items-center justify-center text-center p-4">
                <span class="flex items-center font-medium">
                    <i class="fas fa-plus mr-2"></i>Add Payments
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

                    {{-- Register a payment --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'register' ? null : 'register'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-hand-holding-dollar mr-2 text-indigo-400"></i>Register a payment</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'register' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'register'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Click <span class="font-semibold">Add Payments</span>, then find the person using <span class="font-semibold">Search → Select</span>.</li>
                                <li>Volunteers (assigned to a Red Cross Unit) see <span class="font-semibold">volunteer fees only</span>. </li>
                                <li>Others (NOT assigned to a Red Cross Unit) see <span class="font-semibold">member fees only.</span>. </li>
                                <li>Fill in <span class="font-semibold">Payment Date</span> and <span class="font-semibold">Reference</span>, then click <span class="font-semibold">Register Payment</span>.</li>
                                <li>🔶 New payments go through the approval workflow before they count as active — see "Understand payment status" below.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Understand payment status & approvals --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'status' ? null : 'status'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-user-check mr-2 text-violet-400"></i>Understand payment status &amp; approvals</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'status' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'status'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Every payment starts as <span class="font-semibold">Pending</span> until an approver reviews it.</li>
                                @can('approve_payments')
                                    <li>Use the <span class="font-semibold">Records / Approvals</span> tabs at the top to switch between your submitted payments and payments awaiting your approval.</li>
                                @endcan
                                <li>If rejected, you'll get a notification, and the reason appears in your entries list.</li>
                                <li>While a payment is still <span class="font-semibold">Pending</span>, you can click <span class="font-semibold">Withdraw</span> to cancel it yourself.</li>
                                <li>Once approved, a payment cannot be withdrawn — contact an admin to reverse it if needed.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Filter & find payments --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'filter' ? null : 'filter'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-filter mr-2 text-sky-400"></i>Filter &amp; find payments</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'filter' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'filter'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Click <span class="font-semibold">Filter &amp; Sort</span> to search by name, DB-code, or reference.</li>
                                <li>Narrow down by <span class="font-semibold">Branch → Division → Red Cross Unit</span> — each level unlocks the next.</li>
                                <li>Use <span class="font-semibold">Status</span> to isolate Valid, Expiring Within 30 Days, or Expired memberships.</li>
                                <li>Tick <span class="font-semibold">Entered by me</span> to see payments you registered that have since been approved.</li>

                            </ul>
                        </div>
                    </div>




                    {{-- Find deleted payment records --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'deleted' ? null : 'deleted'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-trash-can mr-2 text-red-400"></i>Find deleted payment records</span>
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
            :records-route="route('membership-payments.index')"
            :approvals-route="route('membership-payments.approvals')"
            permission="approve_payments"
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

        <!-- Search, Filter, and Sort Form -->
        <div class="filter-container overflow-hidden transition-all duration-300 {{ $hasFilters ? '' : 'hidden' }}" id="filterContainer">
            <div class="filter-form-content">
                <form method="GET" action="{{ route('membership-payments.index') }}" id="filterForm" class="filter-form">

                    <div class="filter-grid filter-grid-5">
                        <div class="flex flex-col gap-2">
                            <!-- Search -->
                            <div>
                                <label for="search" class="filter-label">Search</label>
                                <input type="text"
                                       id="search"
                                       name="search"
                                       value="{{ request('search') }}"
                                       placeholder="Name, ID, Ref..."
                                       class="filter-input {{ request('search') ? 'filter-active' : '' }}">
                            </div>

                            <!-- Sort By -->
                            <div>
                                <label for="sort_by" class="filter-label">Sort By</label>
                                <select name="sort_by"
                                        id="sort_by"
                                        class="filter-select">
                                    <option value="payment_date_desc" {{ request('sort_by', 'payment_date_desc') == 'payment_date_desc' ? 'selected' : '' }}>
                                        Payment Date (Newest First)
                                    </option>
                                    <option value="payment_date_asc" {{ request('sort_by') == 'payment_date_asc' ? 'selected' : '' }}>
                                        Payment Date (Oldest First)
                                    </option>
                                    <option value="expiry_date_asc" {{ request('sort_by') == 'expiry_date_asc' ? 'selected' : '' }}>
                                        Expiry Date (Oldest First)
                                    </option>
                                    <option value="expiry_date_desc" {{ request('sort_by') == 'expiry_date_desc' ? 'selected' : '' }}>
                                        Expiry Date (Newest First)
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <!-- Branch Filter -->
                            <div>
                                <label for="branch_id" class="filter-label-small">Branch</label>
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
                                            @if(($accessLevel === 'branch' || $accessLevel === 'division') && (string)$scopedId === (string)$branch->id)
                                                selected
                                            @elseif(request('branch_id') == $branch->id)
                                                selected
                                            @endif>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Division Filter -->
                            <div>
                                <label for="division_id" class="filter-label-small">Division</label>
                                <select name="division_id"
                                        id="division_id"
                                        class="filter-select-small
                                    @if($accessLevel === 'division' || (!request('branch_id') && $accessLevel === 'national')) bg-gray-100 cursor-not-allowed @else {{ request('division_id') ? 'filter-active' : '' }} @endif"
                                        @if($accessLevel === 'division') disabled @endif>
                                    @if ($accessLevel === 'national' || $accessLevel === 'branch')
                                        <option value="">
                                            {{ (!request('branch_id') && $accessLevel === 'national') ? 'Select Branch First' : 'All Divisions' }}
                                        </option>
                                    @endif
                                    @foreach($divisions as $division)
                                        <option value="{{ $division->id }}"
                                            {{ $accessLevel === 'division' && $scopedId == $division->id ? 'selected' : (request('division_id') == $division->id ? 'selected' : '') }}>
                                            {{ $division->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Red Cross Unit Filter -->
                            <div>
                                <label for="red_cross_unit_id" class="filter-label-small">Red Cross Unit</label>
                                <select name="red_cross_unit_id"
                                        id="red_cross_unit_id"
                                        class="filter-select-small
                                    @if(!request('division_id') && $accessLevel === 'national') bg-gray-100 cursor-not-allowed @else {{ request('red_cross_unit_id') ? 'filter-active' : '' }} @endif"
                                        {{ (!request('division_id') && $accessLevel === 'national') ? 'disabled' : '' }}>
                                    <option value="">
                                        {{ !request('division_id') ? 'Select Division First' : 'All Units' }}
                                    </option>
                                    @foreach($redCrossUnits as $unit)
                                        <option value="{{ $unit->id }}" {{ request('red_cross_unit_id') == $unit->id ? 'selected' : '' }}>
                                            {{ $unit->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <!-- Membership Type Filter -->
                            <div>
                                <label for="membership_fee_name" class="filter-label-small">Membership Type</label>
                                <select name="membership_fee_name"
                                        id="membership_fee_name"
                                        class="filter-select-small {{ request('membership_fee_name') ? 'filter-active' : '' }}">
                                    <option value="">All Membership Types</option>
                                    @foreach($membershipFees as $fee)
                                        <option value="{{ $fee->name }}" {{ request('membership_fee_name') == $fee->name ? 'selected' : '' }}>
                                            {{ $fee->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Validity Status Filter -->
                            <div>
                                <label for="validity_status" class="filter-label-small">Status</label>
                                <select name="validity_status"
                                        id="validity_status"
                                        class="filter-select-small {{ request('validity_status') ? 'filter-active' : '' }}">
                                    <option value="">All Memberships</option>
                                    <option value="valid" {{ request('validity_status') == 'valid' ? 'selected' : '' }}>Valid</option>
                                    <option value="expiring_soon" {{ request('validity_status') == 'expiring_soon' ? 'selected' : '' }}>Expiring Within 30 Days</option>
                                    <option value="expired" {{ request('validity_status') == 'expired' ? 'selected' : '' }}>Expired</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex flex-col gap-2">
                            <!-- Records (trashed) -->
                            <div>
                                <label for="trashed" class="filter-label-small">Include deleted?</label>
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
                            <a @if($hasFilters) href="{{ route('membership-payments.index') }}" @endif
                            class="filter-btn-secondary {{ $hasFilters ? 'filter-btn-secondary-active' : 'filter-btn-disabled' }}">
                                <i class="fas fa-times mr-1"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Display messages -->
        @if (session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                {{ session('error') }}
            </div>
        @endif

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
                    @if(request('membership_fee_name'))
                        <p>Membership Type: <strong>{{ request('membership_fee_name') }}</strong></p>
                    @endif
                    @if(request('validity_status'))
                        <p>Status:
                            <strong class="{{ request('validity_status') == 'valid' ? 'text-green-700' : (request('validity_status') == 'expiring_soon' ? 'text-orange-700' : 'text-red-700') }}">
                                @switch(request('validity_status'))
                                    @case('valid') Valid @break
                                    @case('expiring_soon') Expiring Within 30 Days @break
                                    @case('expired') Expired @break
                                @endswitch
                            </strong>
                        </p>
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
                        {{ $membershipPayments->total() }} {{ Str::plural('result', $membershipPayments->total()) }} found
                    </p>
                </div>
            </div>
        @else
            <div class="bg-gray-50 border border-gray-200 text-gray-700 px-4 py-3 rounded mb-6">
                <div class="text-sm">
                    <p>Showing all membership payment records
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

        <!-- Payments Table -->
        <div class="table-container">
            @if($membershipPayments->count() > 0)

                <!-- Desktop Table — hidden on mobile -->
                <div class="hidden lg:block table-wrapper">
                    <table class="data-table">
                        <thead class="table-header">
                        <tr class="table-header-row">
                            <th class="table-header-cell">Person</th>
                            <th class="table-header-cell">Location</th>
                            <th class="table-header-cell">Membership</th>
                            <th class="table-header-cell">
                                <div class="flex items-center gap-1">
                                    Payment Date
                                    @if(request('sort_by', 'payment_date_desc') == 'payment_date_desc')
                                        <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                                    @elseif(request('sort_by') == 'payment_date_asc')
                                        <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"></path></svg>
                                    @endif
                                </div>
                            </th>
                            <th class="table-header-cell">
                                <div class="flex items-center gap-1">
                                    Expiry Date
                                    @if(request('sort_by') == 'expiry_date_desc')
                                        <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                                    @elseif(request('sort_by') == 'expiry_date_asc')
                                        <svg class="w-4 h-4 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"></path></svg>
                                    @endif
                                </div>
                            </th>
                            <th class="table-header-cell">Reference</th>
                            <th class="table-header-cell">Submitted By</th>
                            <th class="table-header-cell">Actions</th>
                        </tr>
                        </thead>
                        <tbody class="table-body">
                        @foreach($membershipPayments as $payment)
                            <tr class="table-body-row {{ $payment->is_deleted ? 'bg-red-50 text-red-900' : '' }}">
                                <td class="table-body-cell">
                                    @if($payment->user)
                                        <div class="table-field-main">{{ $payment->user->first_name }} {{ $payment->user->last_name }}</div>
                                        <div class="table-field-sub">{{ $payment->user->user_id_reference_short }}</div>
                                    @else
                                        <div class="table-field-main text-red-600">User Not Found</div>
                                        <div class="table-field-sub">ID: {{ $payment->user_id }}</div>
                                    @endif
                                </td>
                                <td class="table-body-cell">
                                    @if($payment->division)
                                        <div class="table-field-main">{{ $payment->division->name }}</div>
                                    @elseif($payment->division_id)
                                        <div class="table-field-main text-red-600">Division ID: {{ $payment->division_id }}</div>
                                    @elseif($payment->branch)
                                        <div class="table-field-main">{{ $payment->branch->name }}</div>
                                    @elseif($payment->branch_id)
                                        <div class="table-field-main text-red-600">Branch ID: {{ $payment->branch_id }}</div>
                                    @endif
                                    @if($payment->user && $payment->user->redCrossUnit)
                                        <div class="table-field-sub">{{ $payment->user->redCrossUnit->name }}</div>
                                    @endif
                                    @if(!$payment->branch_id && !$payment->division_id)
                                        <span class="table-field-sub">-</span>
                                    @endif
                                </td>
                                <td class="table-body-cell">
                                    @if($payment->membershipFee)
                                        <div class="table-field-main">
                                            {{ $payment->membershipFee->name }}
                                            @if(method_exists($payment, 'isExpired') && $payment->isExpired())
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Expired</span>
                                            @elseif(method_exists($payment, 'expiresSoon') && $payment->expiresSoon())
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800">Expires Soon</span>
                                            @else
                                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Valid</span>
                                            @endif
                                        </div>
                                        <div class="table-field-sub">
                                            ₦{{ number_format($payment->membershipFee->amount, 2) }}
                                            ({{ $payment->membershipFee->validity_years }} {{ Str::plural('Year', $payment->membershipFee->validity_years) }})
                                        </div>
                                    @else
                                        <div class="table-field-main text-red-600">Fee Not Found</div>
                                        <div class="table-field-sub">ID: {{ $payment->membership_fee_id }}</div>
                                    @endif
                                </td>
                                <td class="table-body-cell">
                                    <div class="table-field-main">
                                        <x-time-ago :date="$payment->payment_date" :today="true" placeholder="-" />
                                    </div>
                                </td>
                                <td class="table-body-cell">
                                    <div class="table-field-main">
                                        <x-time-ago :date="$payment->expiry_date" :today="true" placeholder="-" />
                                    </div>
                                </td>
                                <td class="table-body-cell">
                                    <div class="text-xs font-bold {{ $payment->is_deleted ? 'text-red-900' : 'text-gray-900' }}">
                                        {{ $payment->getPaymentReferenceAttribute() }}
                                    </div>
                                    @if($payment->reference)
                                        <div class="text-xs {{ $payment->is_deleted ? 'text-red-800' : 'text-gray-500' }} mt-1">
                                            <i class="fas fa-hashtag mr-1"></i>{{ $payment->reference }}
                                        </div>
                                    @endif
                                    @if($payment->is_deleted)
                                        <div class="text-xs font-semibold text-red-700 tracking-wide mt-1">
                                            DELETED
                                        </div>
                                    @endif
                                </td>
                                <td class="table-body-cell">
                                    @if($payment->submittedByUser)
                                        <div class="table-field-main">{{ $payment->submittedByUser->first_name }} {{ $payment->submittedByUser->last_name }}</div>
                                        <div class="table-field-sub">{{ $payment->submittedByUser->user_id_reference_short }}</div>
                                    @else
                                        <span class="table-field-sub">-</span>
                                    @endif
                                </td>
                                <td class="table-body-cell">
                                    @if($payment->user && $payment->membershipFee)
                                        <a href="{{ route('membership-payments.show', $payment) }}" class="btn-primary whitespace-nowrap">View</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View — shown only on mobile -->
                <div class="lg:hidden divide-y divide-gray-200">
                    @foreach($membershipPayments as $payment)
                        <div class="p-4 hover:bg-gray-50 {{ $payment->is_deleted ? 'bg-red-50' : '' }}">
                            <div class="flex items-center mb-3">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center">
                                        <i class="fas fa-user text-white"></i>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    @if($payment->user)
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $payment->user->first_name }} {{ $payment->user->last_name }}
                                        </div>
                                        <div class="text-xs text-gray-500">{{ 'DB-' . $payment->user->id }}</div>
                                    @else
                                        <div class="text-sm font-medium text-red-600">User Not Found</div>
                                    @endif
                                </div>
                            </div>

                            <div class="space-y-2 text-sm">
                                @if($payment->membershipFee)
                                    <div>
                                        <span class="font-medium text-gray-500">Membership:</span>
                                        <span class="text-gray-900">{{ $payment->membershipFee->name }}</span>
                                        @if(method_exists($payment, 'isExpired') && $payment->isExpired())
                                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-800 ml-1">Expired</span>
                                        @elseif(method_exists($payment, 'expiresSoon') && $payment->expiresSoon())
                                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-800 ml-1">Expires Soon</span>
                                        @else
                                            <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-800 ml-1">Valid</span>
                                        @endif
                                    </div>
                                @endif
                                <div>
                                    <span class="font-medium text-gray-500">Payment Date:</span>
                                    <span class="text-gray-900">
                                        <x-time-ago :date="$payment->payment_date" :today="true" placeholder="-" />
                                    </span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-500">Expiry Date:</span>
                                    <span class="text-gray-900">
                                        <x-time-ago :date="$payment->expiry_date" :today="true" placeholder="-" />
                                    </span>
                                </div>
                                <div>
                                    <span class="font-medium text-gray-500">Reference:</span>
                                    <span class="{{ $payment->is_deleted ? 'text-red-900 font-bold' : 'text-gray-900' }}">{{ $payment->getPaymentReferenceAttribute() }}</span>
                                    @if($payment->reference)
                                        <div class="text-xs {{ $payment->is_deleted ? 'text-red-800' : 'text-gray-500' }} mt-0.5">
                                            <i class="fas fa-hashtag mr-1"></i>{{ $payment->reference }}
                                        </div>
                                    @endif
                                    @if($payment->is_deleted)
                                        <div class="text-xs font-semibold text-red-700 tracking-wide mt-1">
                                            DELETED
                                        </div>
                                    @endif
                                </div>
                                @if($payment->branch || $payment->division)
                                    <div>
                                        <span class="font-medium text-gray-500">Location:</span>
                                        <span class="text-gray-900">
                                            {{ $payment->branch->name ?? '' }}
                                            @if($payment->division) – {{ $payment->division->name }} @endif
                                        </span>
                                    </div>
                                @endif
                                @if($payment->submittedByUser)
                                    <div>
                                        <span class="font-medium text-gray-500">Submitted by:</span>
                                        <span class="text-gray-900">
                                            {{ $payment->submittedByUser->first_name }} {{ $payment->submittedByUser->last_name }}
                                        </span>
                                    </div>
                                @endif
                            </div>

                            <div class="mt-3">
                                @if($payment->user && $payment->membershipFee)
                                    <a href="{{ route('membership-payments.show', $payment) }}" class="btn-primary inline-block">
                                        View Details
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="table-pagination">
                    {{ $membershipPayments->appends(request()->query())->links() }}
                </div>

            @else
                <div class="table-empty-state">
                    <i class="fas fa-file-invoice-dollar text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No membership payments found</h3>
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
                const scopedId = "{{ $scopedId }}";

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
                            if (String(division.id) === String(selectedDivisionId) || (accessLevel === 'division' && String(division.id) === scopedId)) {
                                option.selected = true;
                            }
                            divisionSelect.appendChild(option);
                        });

                        if (selectedDivisionId && Array.from(divisionSelect.options).some(o => String(o.value) === String(selectedDivisionId))) {
                            populateRedCrossUnits(selectedDivisionId, initialSelectedRedCrossUnit);
                        } else if (accessLevel === 'division' && scopedId) {
                            populateRedCrossUnits(scopedId, initialSelectedRedCrossUnit);
                        } else {
                            resetAndDisableSelect(redCrossUnitSelect, 'Select Division First');
                        }
                    } catch (error) {
                        console.error('Error fetching divisions:', error);
                        resetAndDisableSelect(divisionSelect, 'Error loading divisions');
                    }
                }

                async function populateRedCrossUnits(divisionId, selectedUnitId = '') {
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
                } else if (accessLevel === 'division' && scopedId) {
                    populateRedCrossUnits(scopedId, initialSelectedRedCrossUnit);
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
