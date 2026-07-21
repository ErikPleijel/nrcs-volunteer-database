<x-layouts.admin title="Payments">
    <x-slot name="pageHeader">
        <i class="fas fa-hand-holding-dollar mr-3"></i> Payments
    </x-slot>
    <x-slot name="subHeader">
        Register a new payment
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
                    <div id="user-search-section">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            Search for person
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

                    <!-- Payment Form Section (Initially Hidden) -->
                    <div id="payment-form-section" class="hidden">
                        <div class="border-t pt-6 mt-6">
                            <div class="max-w-2xl mx-auto">
                                <div class="flex justify-between items-center mb-6">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">Person Details</h3>
                                        <p class="text-sm text-gray-600">Selected: <span id="selected-user-name" class="font-medium"></span></p>
                                        <p class="text-sm text-gray-600">DB Reference: <span id="selected-user-reference" class="font-medium"></span></p>
                                        <p id="selected-location-line" class="text-sm text-gray-600">
                                            Branch: <span id="selected-user-branch" class="font-medium"></span>
                                        </p>
                                        <p class="text-sm text-gray-600" id="selected-division-line">Division: <span id="selected-user-division" class="font-medium"></span></p>
                                        <p id="selected-rcu-line" class="hidden text-base font-semibold text-red-600">
                                            RC Unit: <span id="selected-user-rcu"></span>
                                        </p>
                                    </div>
                                    <button type="button"
                                            id="change-user-btn"
                                            class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        New Search
                                    </button>
                                </div>

                                <div id="volunteer-interest-warning" class="hidden mb-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900">
                                    <i class="fas fa-triangle-exclamation mr-1"></i>
                                    <span id="volunteer-interest-warning-text"></span>
                                </div>

                                {{-- Current membership info panel (shown after user selected) --}}
                                <div id="current-membership-panel" class="hidden mb-6 rounded-lg border p-4 text-sm">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">Current Payment</p>
                                    <div id="current-membership-content"></div>
                                </div>

                                <form method="POST" action="{{ route('membership-payments.store') }}">
                                    @csrf

                                    <!-- Hidden User ID -->
                                    <input type="hidden" name="user_id" id="selected-user-id">
                                    <!-- Hidden Branch ID -->
                                    <input type="hidden" name="branch_id" id="selected-branch-id">
                                    <!-- Hidden Division ID -->
                                    <input type="hidden" name="division_id" id="selected-division-id">

                                    <div class="entry-card">
                                        <h4 class="entry-card-title">Enter payment details</h4>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <!-- Membership Fee Selection -->
                                            <div>
                                                <label for="membership_fee_id" id="fee-label" class="block text-sm font-medium text-gray-700">Fee</label>
                                                <select name="membership_fee_id" id="membership_fee_id" class="entry-field" required>
                                                    <option value="">Select a fee</option>
                                                    @foreach($membershipFees as $fee)
                                                        <option value="{{ $fee->id }}"
                                                                data-validity="{{ $fee->validity_years }}"
                                                                data-amount="{{ $fee->amount }}"
                                                                data-id-card-fee="{{ $fee->id_card_fee }}"
                                                                data-volunteer-fee="{{ $fee->is_volunteer_fee ? '1' : '0' }}"
                                                                >
                                                            {{ $fee->name }} - ₦{{ number_format($fee->amount, 2) }} ({{ $fee->validity_years }} years)
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('membership_fee_id')
                                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <!-- ID Card Included -->
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700 mb-3">ID Card</label>
                                                <div class="flex items-center">
                                                    <input type="checkbox" name="id_card_included" id="id_card_included"
                                                           class="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                                           value="1" {{ old('id_card_included') ? 'checked' : '' }}>
                                                    <label for="id_card_included" class="ml-2 block text-sm text-gray-700">
                                                        ID Card Included
                                                    </label>
                                                </div>
                                                <p id="id-card-member-hint" class="hidden mt-1 text-xs text-gray-400">ID cards apply to volunteers</p>
                                                @error('id_card_included')
                                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <!-- Payment Date -->
                                            <div>
                                                <label for="payment_date" class="block text-sm font-medium text-gray-700">Payment Date</label>
                                                <input type="date" name="payment_date" id="payment_date"
                                                       class="entry-field"
                                                       value="{{ old('payment_date', date('Y-m-d')) }}" required>
                                                <p id="payment-date-overlap-note" class="hidden mt-1 text-xs text-blue-600">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    <span id="payment-date-overlap-text"></span>
                                                </p>
                                                @error('payment_date')
                                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <!-- Reference -->
                                            <div>
                                                <label for="reference" class="block text-sm font-medium text-gray-700">Reference</label>
                                                <input type="text" name="reference" id="reference"
                                                       class="entry-field"
                                                       value="{{ old('reference') }}" placeholder="Payment reference">
                                                @error('reference')
                                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Payment Summary Table -->
                                    <div id="payment-summary" class="entry-summary">
                                        <h4 class="text-md font-medium text-gray-900 mb-3">Payment Summary</h4>
                                        <div class="max-w-md bg-white border border-gray-300 rounded-md overflow-hidden">
                                            <table class="w-full">
                                                <tbody>
                                                    <tr class="border-b border-gray-200">
                                                        <td class="px-3 py-2 text-sm text-gray-900">Fee</td>
                                                        <td class="px-3 py-2 text-sm text-gray-900" id="membership-fee-amount">₦0.00</td>
                                                    </tr>
                                                    <tr id="id-card-row" class="border-b border-gray-200">
                                                        <td class="px-3 py-2 text-sm text-gray-900">ID Card Fee</td>
                                                        <td class="px-3 py-2 text-sm text-gray-900" id="id-card-fee-amount">₦0.00</td>
                                                    </tr>
                                                    <tr class="bg-gray-50 font-medium">
                                                        <td class="px-3 py-2 text-sm text-gray-900">Total Amount</td>
                                                        <td class="px-3 py-2 text-sm text-gray-900" id="total-amount">₦0.00</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    @if (session('overlap_confirmation_needed'))
                                        <div class="mt-4 flex items-start gap-2 rounded-md border border-yellow-300 bg-yellow-50 px-3 py-2 text-sm text-yellow-800">
                                            <input type="checkbox" name="confirm_overlap" id="confirm_overlap" value="1"
                                                   class="mt-1 h-4 w-4 text-yellow-600 border-gray-300 rounded focus:ring-yellow-500">
                                            <label for="confirm_overlap">
                                                I understand this overlaps an existing membership payment for this
                                                member — proceed anyway.
                                            </label>
                                        </div>
                                    @endif

                                    <!-- Form Actions -->
                                    <div class="flex items-center justify-end space-x-4 mt-8">
                                        <a href="{{ route('membership-payments.index') }}"
                                           class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                            Cancel
                                        </a>
                                        <button type="submit"
                                                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                                            Register Payment
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
                        <h3 class="text-lg font-medium text-gray-900">Recent Payments Registered by {{ auth()->user()->full_name }}</h3>
                        <span class="text-sm text-gray-500">Latest first</span>
                    </div>

                        @php
                            // Show ALL approval statuses so the submitter can withdraw pending
                            // entries and see rejection reasons (default scope is approved-only).
                            $myRecentPayments = \App\Models\MembershipPayment::withAnyApprovalStatus()
                                ->with(['user', 'membershipFee', 'organisation'])
                                ->where('submitted_by_user_id', auth()->id())
                                ->where('is_deleted', false)
                                ->whereHas('user')
                                ->whereHas('membershipFee')
                                ->orderBy('created_at', 'desc')
                                ->orderBy('payment_date', 'desc')
                                ->paginate(10, ['*'], 'my_payments');
                        @endphp

                        @if($myRecentPayments->count() > 0)
                            <!-- Mobile Card List -->
                            <div class="md:hidden space-y-3">
                                @foreach($myRecentPayments as $payment)
                                    @php
                                        $membershipFeeAmount = $payment->membershipFee->amount ?? 0;
                                        $idCardFeeAmount = $payment->id_card_included ? ($payment->membershipFee->id_card_fee ?? 0) : 0;
                                        $totalAmount = $membershipFeeAmount + $idCardFeeAmount;
                                    @endphp
                                    <div class="border border-gray-200 rounded-lg bg-white p-4">
                                        <div class="flex justify-between items-start gap-2">
                                            <div class="min-w-0">
                                                <div class="font-medium text-gray-900 truncate">{{ $payment->user->full_name ?? 'No Name' }}</div>
                                                <div class="text-xs text-gray-500">{!! $payment->user->getUserIdReferenceLinkAttribute() !!}</div>
                                                @if($payment->organisation)
                                                    <div class="mt-1">
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                            {{ $payment->organisation->name }}
                                                        </span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                        <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
                                            <div>
                                                <dt class="text-xs uppercase text-gray-400">Payment Date</dt>
                                                <dd class="text-gray-900">{{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs uppercase text-gray-400">Fee</dt>
                                                <dd class="text-gray-900">{{ $payment->membershipFee->name ?? 'N/A' }} <span class="text-xs text-gray-500">(₦{{ number_format($totalAmount, 2) }})</span></dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs uppercase text-gray-400">ID Card</dt>
                                                <dd class="text-gray-900">{{ $payment->id_card_included ? 'Yes' : 'No' }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs uppercase text-gray-400">Reference</dt>
                                                <dd class="text-gray-900">
                                                    <div>{{ $payment->getPaymentReferenceAttribute() }}</div>
                                                    @if($payment->reference)
                                                        <div class="text-xs text-gray-500"><i class="fas fa-hashtag mr-1"></i>{{ $payment->reference }}</div>
                                                    @endif
                                                </dd>
                                            </div>
                                        </dl>
                                        <div class="mt-3">
                                            <x-recent-log-actions
                                                :status="$payment->approval_status"
                                                :rejection-reason="$payment->rejection_reason"
                                                :review-url="route('membership-payments.review', $payment->id)"
                                                :withdraw-url="route('membership-payments.withdraw', $payment->id)" />
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Desktop Table -->
                            <div class="hidden md:block bg-white border border-gray-200 rounded-lg shadow-sm overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payer</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">DB-Number</th>

                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Date</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Membership Fee</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID Card</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach($myRecentPayments as $payment)
                                            @php
                                                $branchCode = $payment->user->branch && $payment->user->branch->code
                                                    ? $payment->user->branch->code
                                                    : ($payment->user->branch && $payment->user->branch->name
                                                        ? strtoupper(substr($payment->user->branch->name, 0, 3))
                                                        : 'UNK');
                                                // Calculate total amount
                                                $membershipFeeAmount = $payment->membershipFee->amount ?? 0;
                                                $idCardFeeAmount = $payment->id_card_included ? ($payment->membershipFee->id_card_fee ?? 0) : 0;
                                                $totalAmount = $membershipFeeAmount + $idCardFeeAmount;
                                            @endphp
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-3 py-2 text-sm text-gray-900">
                                                    {{ $payment->user->full_name ?? 'No Name' }}
                                                    @if($payment->organisation)
                                                        <div class="mt-1">
                                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                                {{ $payment->organisation->name }}
                                                            </span>
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-sm">
                                                    {!! $payment->user->getUserIdReferenceLinkAttribute() !!}
                                                </td>

                                                <td class="px-3 py-2 text-sm text-gray-900">
                                                    {{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}
                                                </td>
                                                <td class="px-3 py-2 text-sm text-gray-900">
                                                    <div class="font-medium">{{ $payment->membershipFee->name ?? 'N/A' }}</div>
                                                    <div class="text-xs text-gray-500">₦{{ number_format($totalAmount, 2) }}</div>
                                                </td>
                                                <td class="px-3 py-2 text-sm text-center">
                                                    @if($payment->id_card_included)
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                            ✓ Yes
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                            ✗ No
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-sm text-gray-900">
                                                    <div>{{ $payment->getPaymentReferenceAttribute() }}</div>
                                                    @if($payment->reference)
                                                        <div class="text-xs text-gray-500"><i class="fas fa-hashtag mr-1"></i>{{ $payment->reference }}</div>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-sm">
                                                    <x-approval-status-badge :status="$payment->approval_status" />
                                                    @if($payment->approval_status === 'rejected' && $payment->rejection_reason)
                                                        <div class="text-xs text-red-600 mt-1"><i class="fas fa-comment-dots mr-1"></i>{{ $payment->rejection_reason }}</div>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-2 text-sm text-gray-900">
                                                    <div class="flex items-center gap-3">
                                                        <a href="{{ route('membership-payments.review', $payment->id) }}"
                                                           class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs font-medium transition-colors">
                                                            View
                                                        </a>
                                                        @if($payment->approval_status === 'pending')
                                                            <x-withdraw-button :url="route('membership-payments.withdraw', $payment->id)" />
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            @if($myRecentPayments->hasPages())
                                <div class="mt-4">
                                    {{ $myRecentPayments->links() }}
                                </div>
                            @endif
                        @else
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center">
                                <div class="text-gray-400 mb-2">
                                    <svg class="mx-auto h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <p class="text-gray-600 text-sm">No payments registered by you yet.</p>
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
            const paymentFormSection = document.getElementById('payment-form-section');
            const selectedUserName = document.getElementById('selected-user-name');
            const selectedUserId = document.getElementById('selected-user-id');
            const selectedUserReference = document.getElementById('selected-user-reference');
            const selectedUserBranch = document.getElementById('selected-user-branch');
            const selectedUserDivision = document.getElementById('selected-user-division');
            const selectedBranchId = document.getElementById('selected-branch-id');
            const selectedDivisionId = document.getElementById('selected-division-id');
            const changeUserBtn = document.getElementById('change-user-btn');
            const membershipFeeSelect = document.getElementById('membership_fee_id');
            const idCardCheckbox = document.getElementById('id_card_included');
            const paymentSummary = document.getElementById('payment-summary');
            const membershipFeeAmount = document.getElementById('membership-fee-amount');
            const idCardFeeAmount = document.getElementById('id-card-fee-amount');
            const idCardRow = document.getElementById('id-card-row');
            const totalAmount = document.getElementById('total-amount');

            // The user object if pre-selected via URL parameter
            const preselectedUser = @json($user);

            // Update payment summary
            function updatePaymentSummary() {
                const selectedOption = membershipFeeSelect.options[membershipFeeSelect.selectedIndex];

                if (membershipFeeSelect.value && selectedOption) {
                    const membershipFee = parseFloat(selectedOption.dataset.amount) || 0;
                    const idCardFee = parseFloat(selectedOption.dataset.idCardFee) || 0;
                    const isIdCardIncluded = idCardCheckbox.checked;

                    // Update membership fee amount
                    membershipFeeAmount.textContent = `₦${membershipFee.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;

                    // Update ID card fee - show fee amount only if checked, otherwise show zero
                    if (isIdCardIncluded) {
                        idCardFeeAmount.textContent = `₦${idCardFee.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    } else {
                        idCardFeeAmount.textContent = '₦0.00';
                    }

                    // Calculate total
                    const total = membershipFee + (isIdCardIncluded ? idCardFee : 0);
                    totalAmount.textContent = `₦${total.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                } else {
                    // Reset to zero values if no fee selected
                    membershipFeeAmount.textContent = '₦0.00';
                    idCardFeeAmount.textContent = '₦0.00';
                    totalAmount.textContent = '₦0.00';
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

                fetch(`{{ route('membership-payments.search-users') }}?query=${encodeURIComponent(query)}`)
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

                            const branchCode = user.branch && user.branch.code ? user.branch.code.toUpperCase() :
                                (user.branch && user.branch.name ? user.branch.name.substring(0, 3).toUpperCase() : 'UNK');
                            const dbCode = `DB-${user.id}/${branchCode}`;

                            // IMPORTANT: safely embed JSON into an HTML attribute
                            const userJson = JSON.stringify(user).replaceAll('"', '&quot;');

                            const actionHtml = user.lifecycle_status === 'archived'
                                ? `<span class="text-red-600 font-semibold text-sm px-4 py-2">Archived</span>`
                                : `<button type="button" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium select-user-btn" data-user="${userJson}" data-fullname="${fullName.replaceAll('"', '&quot;')}">Select</button>`;

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

        const currentMembershipPanel  = document.getElementById('current-membership-panel');
        const currentMembershipContent = document.getElementById('current-membership-content');
        const paymentDateInput         = document.getElementById('payment_date');
        const overlapNote              = document.getElementById('payment-date-overlap-note');
        const overlapText              = document.getElementById('payment-date-overlap-text');

        function applyCurrentMembership(membership) {
            // Hide panel and reset
            currentMembershipPanel.classList.add('hidden');
            currentMembershipPanel.className = currentMembershipPanel.className
                .replace(/border-\w+-\d+/g, '').replace(/bg-\w+-\d+/g, '').trim();
            overlapNote.classList.add('hidden');

            // Treat null, undefined, or object with no membership_fee_id as "no membership"
            if (!membership || !membership.membership_fee_id) {
                currentMembershipContent.innerHTML = '<p class="text-sm text-gray-400 italic">None</p>';
                currentMembershipPanel.classList.add('border-gray-200', 'bg-gray-50');
                currentMembershipPanel.classList.remove('hidden');
                return;
            }

            // Build membership info HTML
            const daysLeft    = membership.expires_in_days;
            const isExpired   = membership.is_expired;
            const statusColor = isExpired ? 'text-red-600' : (daysLeft > 30 ? 'text-green-700' : 'text-amber-600');
            const statusText  = isExpired
                ? `Expired on ${membership.expiry_date_display}`
                : `Valid until ${membership.expiry_date_display} (${daysLeft} days left)`;

            currentMembershipContent.innerHTML = `
                <div class="flex flex-wrap gap-4 text-sm">
                    <div><span class="text-gray-500">Type:</span> <span class="font-medium">${membership.membership_fee_name ?? '—'}</span></div>
                    <div><span class="text-gray-500">Paid:</span> <span class="font-medium">${membership.payment_date ?? '—'}</span></div>
                    <div><span class="text-gray-500">Status:</span> <span class="font-medium ${statusColor}">${statusText}</span></div>
                </div>
                ${!isExpired && daysLeft > 30 ? `
                <div class="mt-2 flex items-start gap-2 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                    <i class="fas fa-triangle-exclamation mt-0.5"></i>
                    <span><strong>Note:</strong> The current membership is still valid for more than 1 month. Are you sure you want to register a new payment now?</span>
                </div>` : ''}
            `;

            // Style the panel
            if (isExpired) {
                currentMembershipPanel.classList.add('border-gray-200', 'bg-gray-50');
            } else if (daysLeft > 30) {
                currentMembershipPanel.classList.add('border-amber-300', 'bg-amber-50');
            } else {
                currentMembershipPanel.classList.add('border-blue-200', 'bg-blue-50');
            }
            currentMembershipPanel.classList.remove('hidden');

            // Adjust payment date to avoid overlap if membership is still valid
            if (!isExpired && membership.expiry_date) {
                // Set payment date to the day after expiry
                const expiryDate  = new Date(membership.expiry_date);
                const nextDay     = new Date(expiryDate);
                nextDay.setDate(nextDay.getDate() + 1);
                const yyyy = nextDay.getFullYear();
                const mm   = String(nextDay.getMonth() + 1).padStart(2, '0');
                const dd   = String(nextDay.getDate()).padStart(2, '0');
                paymentDateInput.value = `${yyyy}-${mm}-${dd}`;

                overlapText.textContent =
                    `Date set to the day after current membership expires (${membership.expiry_date_display}) to avoid overlap.`;
                overlapNote.classList.remove('hidden');
            }
        }

        // Filter fee dropdown based on whether user is a volunteer
        function filterFeesByUserType(isVolunteer) {
            const feeSelect = document.getElementById('membership_fee_id');
            const currentVal = feeSelect.value;

            Array.from(feeSelect.options).forEach(option => {
                if (!option.value) return; // keep the placeholder
                const isVolFee = option.dataset.volunteerFee === '1';
                const show = isVolunteer ? isVolFee : !isVolFee;
                option.hidden = !show;
                option.disabled = !show;
            });

            // Reset selection if current selection is now hidden
            const currentOption = feeSelect.options[feeSelect.selectedIndex];
            if (currentOption && currentOption.hidden) {
                feeSelect.value = '';
                updatePaymentSummary();
            }
        }

        // Select user function
        function selectUser(user, fullName) {
            selectedUserId.value = user.id;
            selectedUserName.textContent = fullName;

            // Generate user ID reference
            const branchCode = user.branch && user.branch.code ? user.branch.code.toUpperCase() :
                (user.branch && user.branch.name ? user.branch.name.substring(0, 3).toUpperCase() : 'UNK');
            selectedUserReference.textContent = `DB-${user.id}/${branchCode}`;

            // Display branch/division or RC unit
            const rcuLine      = document.getElementById('selected-rcu-line');
            const locationLine = document.getElementById('selected-location-line');
            const divisionLine = document.getElementById('selected-division-line');
            const rcuNameEl    = document.getElementById('selected-user-rcu');

            const isVolunteer = !!(user.red_cross_unit_id);

            if (isVolunteer) {
                rcuLine.classList.remove('hidden');
                locationLine.classList.add('hidden');
                divisionLine.classList.add('hidden');
                rcuNameEl.textContent = user.rcu_name || `Unit #${user.red_cross_unit_id}`;

                // Enable ID card checkbox for volunteers
                idCardCheckbox.disabled = false;
                idCardCheckbox.classList.remove('opacity-50', 'cursor-not-allowed');
                document.getElementById('id-card-member-hint').classList.add('hidden');
                document.getElementById('fee-label').innerHTML = 'Fee <span class="font-normal text-gray-500 text-xs">(showing volunteer fee options)</span>';
            } else {
                rcuLine.classList.add('hidden');
                locationLine.classList.remove('hidden');
                divisionLine.classList.remove('hidden');
                selectedUserBranch.textContent = user.branch ? user.branch.name : 'No branch assigned';
                selectedUserDivision.textContent = user.division ? user.division.name : 'No division assigned';

                // Disable ID card checkbox for members
                idCardCheckbox.disabled = true;
                idCardCheckbox.checked = false;
                idCardCheckbox.classList.add('opacity-50', 'cursor-not-allowed');
                document.getElementById('id-card-member-hint').classList.remove('hidden');
                document.getElementById('fee-label').innerHTML = 'Fee <span class="font-normal text-gray-500 text-xs">(showing member fee options)</span>';
            }

            const volunteerWarning = document.getElementById('volunteer-interest-warning');
            const volunteerWarningText = document.getElementById('volunteer-interest-warning-text');

            if (!isVolunteer && user.can_contribute_volunteering) {
                volunteerWarningText.innerHTML =
                    `<strong>${fullName}</strong> expressed interest in <strong>volunteering</strong> at registration. ` +
                    `Only <strong>membership-type fees</strong> are shown, since they aren't assigned to a unit yet. ` +
                    `If they still want to volunteer, <strong>assign them to a Red Cross Unit</strong> first. ` +
                    `If they've changed their mind, you can proceed with a membership payment.`;
                volunteerWarning.classList.remove('hidden');
            } else {
                volunteerWarning.classList.add('hidden');
            }

            // Set hidden branch and division IDs
            selectedBranchId.value = user.branch_id || '';
            selectedDivisionId.value = user.division_id || '';

            // Filter fees by user type
            filterFeesByUserType(isVolunteer);

            // Show payment form, hide search
            userSearchSection.classList.add('hidden');
            paymentFormSection.classList.remove('hidden');

            // Fetch current membership for this user
            fetch(`/membership-payments/user/${user.id}/current-membership`)
                .then(r => r.json())
                .then(membership => applyCurrentMembership(membership))
                .catch(() => applyCurrentMembership(null));
        }

        // Change user function
        function changeUser() {
            userSearchSection.classList.remove('hidden');
            paymentFormSection.classList.add('hidden');
            userSearch.value = '';
            searchResults.classList.add('hidden');
            noResults.classList.add('hidden');

            // Reset membership panel and payment date
            currentMembershipPanel.classList.add('hidden');
            document.getElementById('volunteer-interest-warning').classList.add('hidden');
            overlapNote.classList.add('hidden');
            paymentDateInput.value = new Date().toISOString().split('T')[0];
        }

        // Event delegation for dynamically created select buttons
        resultsList.addEventListener('click', function(e) {
            if (e.target.classList.contains('select-user-btn')) {
                try {
                    const user = JSON.parse(e.target.getAttribute('data-user'));
                    const fullName = e.target.getAttribute('data-fullname');
                    selectUser(user, fullName);
                } catch (err) {
                    console.error('Failed to parse selected user payload:', err);
                }
            }
        });

        // Event listeners
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

        // Payment summary event listeners
        membershipFeeSelect.addEventListener('change', updatePaymentSummary);
        idCardCheckbox.addEventListener('change', updatePaymentSummary);

        // If a user was pre-selected, populate the form right away
        if (preselectedUser) {
            const fullName = [preselectedUser.first_name, preselectedUser.middle_name, preselectedUser.last_name]
                .filter(Boolean).join(' ');
            selectUser(preselectedUser, fullName);
        }
    });
</script>
</x-layouts.admin>
