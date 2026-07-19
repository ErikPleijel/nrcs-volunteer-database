<x-layouts.admin title="Add Donation">
    <x-slot name="pageHeader">
        <i class="fas fa-hand-holding-heart mr-3"></i> Donations
    </x-slot>
    <x-slot name="subHeader">
        ADD ORGANISATION DONATION
    </x-slot>

    <x-slot name="backLink">
        <a href="{{ route('organisations.show', $organisation) }}" class="btn-backlink">
            ← Back to {{ $organisation->name }}
        </a>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    @if(session('success'))
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                            <div class="font-medium">Please fix the following errors:</div>
                            <ul class="list-disc list-inside mt-2">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Organisation Info -->
                    <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-md">
                        <div class="text-xs font-medium text-blue-600 uppercase tracking-wide mb-1">Organisation</div>
                        <div class="text-lg font-semibold text-blue-900">{{ $organisation->name }}</div>
                        @if($organisation->branch)
                            <div class="text-sm text-blue-700 mt-0.5">{{ $organisation->branch->name }}</div>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('donations.store') }}">
                        @csrf
                        <input type="hidden" name="organisation_id" value="{{ $organisation->id }}">
                        <input type="hidden" name="branch_id" value="{{ $organisation->branch_id }}">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                            <!-- Person -->
                            <div>
                                <label for="user_id" class="block text-sm font-medium text-gray-700">
                                    Person (Donation Made By) <span class="text-red-500">*</span>
                                </label>
                                <select name="user_id" id="user_id"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        required>
                                    <option value="">Select a person</option>
                                    @foreach($organisation->users as $linkedUser)
                                        <option value="{{ $linkedUser->id }}"
                                                {{ old('user_id') == $linkedUser->id ? 'selected' : '' }}>
                                            {{ $linkedUser->first_name }} {{ $linkedUser->last_name }} (DB-{{ $linkedUser->id }})
                                            @if($linkedUser->pivot->is_primary_contact) — Primary Contact @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('user_id')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                @if($organisation->users->isEmpty())
                                    <p class="mt-1 text-sm text-orange-600">No persons linked to this organisation yet.</p>
                                @endif
                            </div>

                            <!-- Donation Date -->
                            <div>
                                <label for="date_donation" class="block text-sm font-medium text-gray-700">
                                    Donation Date <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="date_donation" id="date_donation"
                                       value="{{ old('date_donation', date('Y-m-d')) }}" required
                                       class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('date_donation') border-red-500 @enderror">
                                @error('date_donation')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- In-Kind toggle -->
                            <div class="flex items-center">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="in_kind_donation" value="1"
                                           id="in_kind_donation" {{ old('in_kind_donation') ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                    <span class="text-sm text-gray-700">In-Kind Donation</span>
                                </label>
                            </div>

                            <!-- Amount (cash) -->
                            <div id="cash_fields">
                                <label for="amount" id="amount-label" class="block text-sm font-medium text-gray-700">
                                    Amount <span class="text-red-500">*</span>
                                </label>
                                <input type="number" name="amount" id="amount" step="1" min="0"
                                       value="{{ old('amount') }}"
                                       class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('amount') border-red-500 @enderror">
                                @error('amount')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Donation Item (in-kind) -->
                            <div id="inkind_fields">
                                <label for="donation_item" class="block text-sm font-medium text-gray-700">
                                    Donation Item <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="donation_item" id="donation_item"
                                       value="{{ old('donation_item') }}"
                                       class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('donation_item') border-red-500 @enderror">
                                @error('donation_item')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Purpose -->
                            <div>
                                <label for="purpose" class="block text-sm font-medium text-gray-700">Purpose</label>
                                <input type="text" name="purpose" id="purpose"
                                       value="{{ old('purpose') }}"
                                       class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                @error('purpose')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Reference -->
                            <div>
                                <label for="reference" class="block text-sm font-medium text-gray-700">Reference Number</label>
                                <input type="text" name="reference" id="reference"
                                       value="{{ old('reference') }}"
                                       class="mt-1 block w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                @error('reference')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                        </div>

                        <div class="flex items-center justify-end space-x-4 mt-8">
                            <a href="{{ route('organisations.show', $organisation) }}"
                               class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                Cancel
                            </a>
                            <button type="submit"
                                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Record Donation
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>

        <div class="w-full sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-8">
                <div class="p-6 text-gray-900">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Recent Donations Registered by {{ auth()->user()->full_name }}</h3>
                        <span class="text-sm text-gray-500">Latest first</span>
                    </div>

                        @php
                            // Show ALL approval statuses so the submitter can withdraw pending
                            // entries and see rejection reasons (default scope is approved-only).
                            $myRecentOrgDonations = \App\Models\Donation::withAnyApprovalStatus()
                                ->with(['user', 'organisation'])
                                ->organisational()
                                ->where('entered_by_user_id', auth()->id())
                                ->where('is_deleted', false)
                                ->whereHas('user')
                                ->orderBy('created_at', 'desc')
                                ->orderBy('date_donation', 'desc')
                                ->paginate(10, ['*'], 'my_org_donations');
                        @endphp

                        @if($myRecentOrgDonations->count() > 0)
                            <!-- Mobile Card List -->
                            <div class="md:hidden space-y-3">
                                @foreach($myRecentOrgDonations as $donation)
                                    <div class="border border-gray-200 rounded-lg bg-white p-4">
                                        <div class="flex justify-between items-start gap-2">
                                            <div class="min-w-0">
                                                <div class="font-medium text-gray-900 truncate">{{ $donation->user->full_name ?? 'No Name' }}</div>
                                                <div class="text-xs text-gray-500">{!! $donation->user->getUserIdReferenceLinkAttribute() !!}</div>
                                            </div>
                                        </div>
                                        <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
                                            <div>
                                                <dt class="text-xs uppercase text-gray-400">Organisation</dt>
                                                <dd class="text-gray-900">{{ $donation->organisation->name ?? 'N/A' }}</dd>
                                            </div>
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
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Organisation</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount / Item</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($myRecentOrgDonations as $donation)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                {{ $donation->user->full_name ?? 'No Name' }}
                                            </td>
                                            <td class="px-3 py-2 text-sm">
                                                {!! $donation->user->getUserIdReferenceLinkAttribute() !!}
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                {{ $donation->organisation->name ?? 'N/A' }}
                                            </td>
                                            <td class="px-3 py-2 text-sm text-gray-900">
                                                {{ \Carbon\Carbon::parse($donation->date_donation)->format('M d, Y') }}
                                            </td>
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
                            @if($myRecentOrgDonations->hasPages())
                                <div class="mt-4">
                                    {{ $myRecentOrgDonations->links() }}
                                </div>
                            @endif
                        @else
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-6 text-center">
                                <div class="text-gray-400 mb-2">
                                    <svg class="mx-auto h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <p class="text-gray-600 text-sm">No organisation donations registered by you yet.</p>
                            </div>
                        @endif
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const inKindCheckbox  = document.getElementById('in_kind_donation');
            const amountLabel     = document.getElementById('amount-label');
            const amountInput     = document.getElementById('amount');
            const donationItemInput = document.getElementById('donation_item');

            function toggleDonationFields() {
                if (inKindCheckbox.checked) {
                    amountLabel.innerHTML = 'Number of Items <span class="text-red-500">*</span>';
                    donationItemInput.value = '';
                    donationItemInput.removeAttribute('disabled');
                    donationItemInput.setAttribute('required', 'required');
                    amountInput.removeAttribute('required');
                } else {
                    amountLabel.innerHTML = 'Amount <span class="text-red-500">*</span>';
                    donationItemInput.value = 'Naira';
                    donationItemInput.setAttribute('disabled', 'disabled');
                    amountInput.setAttribute('required', 'required');
                }
            }

            inKindCheckbox.addEventListener('change', toggleDonationFields);
            toggleDonationFields();
        });
    </script>
</x-layouts.admin>
