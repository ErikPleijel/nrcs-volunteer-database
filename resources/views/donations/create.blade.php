<x-layouts.admin title="Donations Log Management">
    <x-slot name="pageHeader">
        <i class="fas fa-graduation-cap mr-3"></i> Donations
    </x-slot>
    <x-slot name="subHeader">
        LOG NEW DONATIONS
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
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            Search for Person
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
                            <p class="text-gray-500 text-sm">No donors found. Please refine your search.</p>
                        </div>
                    </div>


                    <!-- Donation Form Section (Initially Hidden) -->
                    <div id="donation-form-section" class="hidden">
                        <div class="border-t pt-6 mt-6">
                            <div class="max-w-2xl mx-auto">
                                <div class="flex justify-between items-center mb-6">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900">Person Details</h3>
                                        <p class="text-sm text-gray-600">Selected Donor: <span id="selected-user-name" class="font-medium"></span></p>
                                        <p class="text-sm text-gray-600">DB Reference: <span id="selected-user-reference" class="font-medium"></span></p>
                                        <p class="text-sm text-gray-600">Branch: <span id="selected-user-branch" class="font-medium"></span></p>
                                        <p class="text-sm text-gray-600">Division: <span id="selected-user-division" class="font-medium"></span></p>
                                        <p class="text-sm text-gray-600">RC Unit: <span id="selected-user-rcu" class="font-medium"></span></p>
                                    </div>
                                    <button type="button"
                                            id="change-user-btn"
                                            class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        Change Donor
                                    </button>
                                </div>

                                <form method="POST" action="{{ route('donations.store') }}">
                                    @csrf

                                    <!-- Hidden User ID -->
                                    <input type="hidden" name="user_id" id="selected-user-id" value="{{ old('user_id') }}">
                                    <!-- Hidden Branch ID -->
                                    <input type="hidden" name="branch_id" id="selected-branch-id" value="{{ old('branch_id') }}">
                                    <!-- Hidden Division ID -->
                                    <input type="hidden" name="division_id" id="selected-division-id" value="{{ old('division_id') }}">

                                    <div class="entry-card">
                                        <h4 class="entry-card-title">Enter donation details</h4>
                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                            <!-- Donation Date -->
                                            <div>
                                                <label for="date_donation" class="block text-sm font-medium text-gray-700 mb-2">
                                                    Donation Date <span class="text-red-500">*</span>
                                                </label>
                                                <input type="date" name="date_donation" id="date_donation"
                                                       value="{{ old('date_donation', date('Y-m-d')) }}" required
                                                       class="entry-field @error('date_donation') border-red-500 @enderror">
                                                @error('date_donation')
                                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <!-- Checkboxes stacked: In-Kind + Anonymous -->
                                            <div class="flex flex-col justify-center gap-3">
                                                <label class="flex items-center">
                                                    <input type="checkbox" name="in_kind_donation" value="1"
                                                           id="in_kind_donation" {{ old('in_kind_donation') ? 'checked' : '' }}
                                                           class="w-5 h-5 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                                    <span class="ml-2 text-sm text-gray-700">In-Kind Donation</span>
                                                </label>

                                                <div>
                                                    <input type="hidden" name="anonymous" value="0">
                                                    <label class="flex items-center">
                                                        <input type="checkbox" name="anonymous" value="1"
                                                               {{ old('anonymous') ? 'checked' : '' }}
                                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                                        <span class="ml-2 text-sm text-gray-700">Anonymous donation</span>
                                                    </label>
                                                </div>
                                            </div>

                                            {{-- Cash fields (Amount) --}}
                                            <div id="cash_fields">
                                                <label for="amount" id="amount-label" class="block text-sm font-medium text-gray-700 mb-2">
                                                    Amount <span class="text-red-500">*</span>
                                                </label>
                                                <input type="number" name="amount" id="amount" step="1" min="0"
                                                       value="{{ old('amount') }}"
                                                       class="entry-field @error('amount') border-red-500 @enderror">
                                                @error('amount')
                                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            {{-- In-Kind fields (Donation Item) --}}
                                            <div id="inkind_fields">
                                                <label for="donation_item" class="block text-sm font-medium text-gray-700 mb-2">
                                                    Donation Item <span class="text-red-500">*</span>
                                                </label>
                                                <input type="text" name="donation_item" id="donation_item"
                                                       value="{{ old('donation_item') }}"
                                                       class="entry-field @error('donation_item') border-red-500 @enderror">
                                                @error('donation_item')
                                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <!-- Reference Number -->
                                            <div>
                                                <label for="reference" class="block text-sm font-medium text-gray-700 mb-2">
                                                    Reference Number
                                                </label>
                                                <input type="text" name="reference" id="reference"
                                                       value="{{ old('reference') }}"
                                                       class="entry-field @error('reference') border-red-500 @enderror">
                                                @error('reference')
                                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <!-- Purpose -->
                                            <div>
                                                <label for="purpose" class="block text-sm font-medium text-gray-700 mb-2">
                                                    Purpose
                                                </label>
                                                <input type="text" name="purpose" id="purpose"
                                                       value="{{ old('purpose') }}"
                                                       class="entry-field @error('purpose') border-red-500 @enderror">
                                                @error('purpose')
                                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex justify-end space-x-4 mt-8">
                                        <a href="{{ route('donations.index') }}"
                                           class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                            Cancel
                                        </a>
                                        <button type="submit"
                                                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                            Create Donation
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if (session('warning'))
                <div class="mt-8 mb-0 flex items-center gap-3 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                    <i class="fas fa-triangle-exclamation text-yellow-500"></i>{{ session('warning') }}
                </div>
            @endif
            @if (session('error'))
                <div class="mt-8 mb-0 flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <i class="fas fa-circle-exclamation text-red-500"></i>{{ session('error') }}
                </div>
            @endif
        </div>
        <div class="w-full sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-8">
                <div class="p-6 text-gray-900">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Recent Donations Registered by {{ auth()->user()->full_name }}</h3>
                        <span class="text-sm text-gray-500">Latest first</span>
                    </div>

                        @if($myRecentDonations->count() > 0)
                            <!-- Mobile Card List -->
                            <div class="md:hidden space-y-3">
                                @foreach($myRecentDonations as $donation)
                                    <div class="border border-gray-200 rounded-lg bg-white p-4">
                                        <div class="flex justify-between items-start gap-2">
                                            <div class="min-w-0">
                                                <div class="font-medium text-gray-900 truncate">{{ $donation->user->full_name ?? 'No Name' }}</div>
                                                <div class="text-xs text-gray-500">{!! $donation->user->getUserIdReferenceLinkAttribute() !!}</div>
                                            </div>
                                        </div>
                                        <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
                                            <div>
                                                <dt class="text-xs uppercase text-gray-400">Date</dt>
                                                <dd class="text-gray-900">{{ \Carbon\Carbon::parse($donation->date_donation)->format('M d, Y') }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs uppercase text-gray-400">Amount / Item</dt>
                                                <dd class="{{ $donation->in_kind_donation ? 'text-blue-600' : 'text-gray-900' }}">{{ $donation->formatted_donation }}</dd>
                                            </div>
                                            <div>
                                                <dt class="text-xs uppercase text-gray-400">Reference</dt>
                                                <dd class="text-gray-900">
                                                    <div>{{ $donation->donation_reference }}</div>
                                                    @if($donation->reference)
                                                        <div class="text-xs text-gray-500"><i class="fas fa-hashtag mr-1"></i>{{ $donation->reference }}</div>
                                                    @endif
                                                </dd>
                                            </div>
                                        </dl>
                                        <div class="mt-3">
                                            <x-recent-log-actions
                                                :status="$donation->approval_status"
                                                :rejection-reason="$donation->rejection_reason"
                                                :review-url="route('donations.review', $donation->id)"
                                                :withdraw-url="route('donations.withdraw', $donation->id)" />
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Desktop Table -->
                            <div class="hidden md:block bg-white border border-gray-200 rounded-lg shadow-sm overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Donor</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">DB-Number</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        {{-- Removed 'Type' column --}}
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount / Item</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($myRecentDonations as $donation)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                {{ $donation->user->full_name ?? 'No Name' }}
                                            </td>
                                            <td class="px-3 py-2 text-sm">
                                                {!! $donation->user->getUserIdReferenceLinkAttribute() !!}
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                {{ \Carbon\Carbon::parse($donation->date_donation)->format('M d, Y') }}
                                            </td>
                                            {{-- Removed 'Type' data cell --}}
                                            <td class="px-3 py-2 text-sm @if($donation->in_kind_donation) text-blue-600 @else text-gray-900 @endif">
                                                {{ $donation->formatted_donation }}
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                <div>{{ $donation->donation_reference }}</div>
                                                @if($donation->reference)
                                                    <div class="text-xs text-gray-500"><i class="fas fa-hashtag mr-1"></i>{{ $donation->reference }}</div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-sm">
                                                <x-approval-status-badge :status="$donation->approval_status" />
                                                @if($donation->approval_status === 'rejected' && $donation->rejection_reason)
                                                    <div class="text-xs text-red-600 mt-1"><i class="fas fa-comment-dots mr-1"></i>{{ $donation->rejection_reason }}</div>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                <div class="flex items-center gap-3">
                                                    <a href="{{ route('donations.review', $donation->id) }}"
                                                       class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs font-medium transition-colors">
                                                        View
                                                    </a>
                                                    @if($donation->approval_status === 'pending')
                                                        <x-withdraw-button :url="route('donations.withdraw', $donation->id)" />
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            @if($myRecentDonations->hasPages())
                                <div class="mt-4">
                                    {{ $myRecentDonations->links() }}
                                </div>
                            @endif
                        @else
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center">
                                <div class="text-gray-400 mb-2">
                                    <svg class="mx-auto h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <p class="text-gray-600 text-sm">No donations registered by you yet.</p>
                            </div>
                        @endif
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Element Definitions ---
            const userSearch = document.getElementById('user-search');
            const searchBtn = document.getElementById('search-btn');
            const searchResults = document.getElementById('search-results');
            const resultsList = document.getElementById('results-list');
            const noResults = document.getElementById('no-results');
            const userSearchSection = document.getElementById('user-search-section');
            const donationFormSection = document.getElementById('donation-form-section');
            const selectedUserName = document.getElementById('selected-user-name');
            const selectedUserId = document.getElementById('selected-user-id');
            const selectedUserReference = document.getElementById('selected-user-reference');
            const selectedUserBranch = document.getElementById('selected-user-branch');
            const selectedUserDivision = document.getElementById('selected-user-division');
            const selectedBranchId = document.getElementById('selected-branch-id');
            const selectedDivisionId = document.getElementById('selected-division-id');
            const changeUserBtn = document.getElementById('change-user-btn');

            // Donation type toggle fields
            const inKindCheckbox = document.getElementById('in_kind_donation');
            const amountInput = document.getElementById('amount');
            const amountLabel = document.getElementById('amount-label');
            const donationItemInput = document.getElementById('donation_item');

            // The user object if pre-selected via URL parameter
            const preselectedUser = @json($user ?? null);


            // --- Functions ---

            function toggleDonationFields() {
                if (!inKindCheckbox || !amountInput || !amountLabel || !donationItemInput) return;

                if (inKindCheckbox.checked) {
                    // In-Kind Donation
                    amountLabel.innerHTML = 'Number of Items <span class="text-red-500">*</span>';
                    donationItemInput.value = '';
                    donationItemInput.removeAttribute('disabled');
                    // The backend requires donation_item if in_kind is true
                    donationItemInput.setAttribute('required', 'required');
                    // Amount becomes optional for in-kind, representing quantity.
                    amountInput.removeAttribute('required');
                } else {
                    // Cash Donation
                    amountLabel.innerHTML = 'Amount <span class="text-red-500">*</span>';
                    donationItemInput.value = 'Naira';
                    donationItemInput.setAttribute('disabled', 'disabled');
                    amountInput.setAttribute('required', 'required');
                }
            }

            function searchUsers() {
                const query = userSearch.value.trim();
                if (query.length < 2) {
                    searchResults.classList.add('hidden');
                    noResults.classList.add('hidden');
                    return;
                }

                fetch(`{{ route('donations.search-users') }}?query=${encodeURIComponent(query)}`)
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
                            const fullName = [user.first_name, user.middle_name, user.last_name].filter(Boolean).join(' ');
                            const branchCode = user.branch?.code?.toUpperCase() || user.branch?.name?.substring(0, 3).toUpperCase() || 'UNK';
                            const dbCode = `DB-${user.id}/${branchCode}`;
                            // Safely embed JSON into an HTML attribute
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

            function selectUser(user, fullName) {
                selectedUserId.value = user.id;
                selectedUserName.textContent = fullName;

                const branchCode = user.branch?.code?.toUpperCase() || user.branch?.name?.substring(0, 3).toUpperCase() || 'UNK';
                selectedUserReference.textContent = `DB-${user.id}/${branchCode}`;
                selectedUserBranch.textContent = user.branch ? user.branch.name : 'No branch assigned';
                selectedUserDivision.textContent = user.division ? user.division.name : 'No division assigned';
                selectedBranchId.value = user.branch_id || '';
                selectedDivisionId.value = user.division_id || '';
                const rcuSpan = document.getElementById('selected-user-rcu');
                if (rcuSpan) rcuSpan.textContent = user.red_cross_unit ? user.red_cross_unit.name : 'N/A';

                // Show donation form, hide search
                userSearchSection.classList.add('hidden');
                donationFormSection.classList.remove('hidden');
            }

            function changeUser() {
                userSearchSection.classList.remove('hidden');
                donationFormSection.classList.add('hidden');
                userSearch.value = '';
                searchResults.classList.add('hidden');
                noResults.classList.add('hidden');

                // Clear selected user details from the form
                selectedUserId.value = '';
            }


            // --- Event Listeners ---
            searchBtn.addEventListener('click', searchUsers);
            userSearch.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchUsers();
                }
            });
            userSearch.addEventListener('input', function() {
                if (this.value.length >= 2) searchUsers();
            });
            changeUserBtn.addEventListener('click', changeUser);

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

            if (inKindCheckbox) {
                inKindCheckbox.addEventListener('change', toggleDonationFields);
            }


            // --- Initialisation ---
            toggleDonationFields();

            if (preselectedUser) {
                const fullName = [preselectedUser.first_name, preselectedUser.middle_name, preselectedUser.last_name]
                    .filter(Boolean).join(' ');
                selectUser(preselectedUser, fullName);
            }
        });
    </script>
</x-layouts.admin>
