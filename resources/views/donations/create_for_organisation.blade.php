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
