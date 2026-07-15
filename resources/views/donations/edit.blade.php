<x-layouts.admin>
    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold">Edit Donation</h2>
                        <a href="{{ route('donations.index') }}"
                           class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                            Back to List
                        </a>
                    </div>

                    <form action="{{ route('donations.update', $donation) }}" method="POST" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="user_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Donor <span class="text-red-500">*</span>
                                </label>
                                <select name="user_id" id="user_id"
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('user_id') border-red-500 @enderror">
                                    <option value="">Select a donor</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" {{ (old('user_id', $donation->user_id) == $user->id) ? 'selected' : '' }}>
                                            {{ $user->first_name }}
                                            @if($user->middle_name) {{ $user->middle_name }} @endif
                                            {{ $user->last_name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('user_id')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="date_donation" class="block text-sm font-medium text-gray-700 mb-2">
                                    Donation Date <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="date_donation" id="date_donation"
                                       value="{{ old('date_donation', $donation->date_donation ? $donation->date_donation->format('Y-m-d') : '') }}"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('date_donation') border-red-500 @enderror">
                                @error('date_donation')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="flex items-center mb-4">
                                    <input type="checkbox" name="in_kind_donation" value="1"
                                           {{ old('in_kind_donation', $donation->in_kind_donation) ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                           onchange="toggleDonationType()">
                                    <span class="ml-2 text-sm text-gray-700">In-Kind Donation</span>
                                </label>
                            </div>

                            <div id="cash_fields">
                                <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
                                    Amount <span class="text-red-500">*</span>
                                </label>
                                <input type="number" name="amount" id="amount" step="0.01" min="0"
                                       value="{{ old('amount', $donation->amount) }}"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('amount') border-red-500 @enderror">
                                @error('amount')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div id="inkind_fields" style="display: none;">
                                <label for="donation_item" class="block text-sm font-medium text-gray-700 mb-2">
                                    Donation Item
                                </label>
                                <input type="text" name="donation_item" id="donation_item"
                                       value="{{ old('donation_item', $donation->donation_item) }}"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('donation_item') border-red-500 @enderror">
                                @error('donation_item')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="reference" class="block text-sm font-medium text-gray-700 mb-2">
                                    Reference Number
                                </label>
                                <input type="text" name="reference" id="reference"
                                       value="{{ old('reference', $donation->reference) }}"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('reference') border-red-500 @enderror">
                                @error('reference')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="purpose" class="block text-sm font-medium text-gray-700 mb-2">
                                    Purpose
                                </label>
                                <input type="text" name="purpose" id="purpose"
                                       value="{{ old('purpose', $donation->purpose) }}"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('purpose') border-red-500 @enderror">
                                @error('purpose')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="submission_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Submission Name
                                </label>
                                <input type="text" name="submission_name" id="submission_name"
                                       value="{{ old('submission_name', $donation->submission_name) }}"
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('submission_name') border-red-500 @enderror">
                                @error('submission_name')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Branch
                                </label>
                                <select name="branch_id" id="branch_id"
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select a branch</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ old('branch_id', $donation->branch_id) == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="division_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Division
                                </label>
                                <select name="division_id" id="division_id"
                                        class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select a division</option>
                                    @foreach($divisions as $division)
                                        <option value="{{ $division->id }}" {{ old('division_id', $donation->division_id) == $division->id ? 'selected' : '' }}>
                                            {{ $division->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" name="anonymous" value="1"
                                       {{ old('anonymous', $donation->anonymous) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">Anonymous donation</span>
                            </label>
                        </div>

                        <div class="flex justify-end space-x-4">
                            <a href="{{ route('donations.index') }}"
                               class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                Cancel
                            </a>
                            <button type="submit"
                                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Update Donation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleDonationType() {
            const inKindCheckbox = document.querySelector('input[name="in_kind_donation"]');
            const cashFields = document.getElementById('cash_fields');
            const inKindFields = document.getElementById('inkind_fields');
            const amountInput = document.getElementById('amount');

            if (inKindCheckbox.checked) {
                cashFields.style.display = 'none';
                inKindFields.style.display = 'block';
                amountInput.required = false;
            } else {
                cashFields.style.display = 'block';
                inKindFields.style.display = 'none';
                amountInput.required = true;
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleDonationType();
        });
    </script>
</x-layouts.admin>
