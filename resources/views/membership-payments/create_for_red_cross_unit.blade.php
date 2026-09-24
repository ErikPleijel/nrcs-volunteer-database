<x-layouts.admin title="Add Payment">
    <x-slot name="pageHeader">
        <i class="fas fa-users mr-3"></i> Membership
    </x-slot>
    <x-slot name="subHeader">
        ADD RED CROSS UNIT PAYMENT
    </x-slot>

    <x-slot name="backLink">
        <a href="{{ route('red-cross-units.show', $redCrossUnit) }}" class="btn-backlink">
            ← Back to {{ $redCrossUnit->name }}
        </a>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

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

                    <!-- Red Cross Unit Info -->
                    <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-md">
                        <div class="text-xs font-medium text-blue-600 uppercase tracking-wide mb-1">Red Cross Unit</div>
                        <div class="text-lg font-semibold text-blue-900">{{ $redCrossUnit->name }}</div>
                        <div class="text-sm text-blue-700 mt-0.5">
                            {{ $redCrossUnit->division->branch->name ?? '—' }}@if($redCrossUnit->division) – {{ $redCrossUnit->division->name }}@endif
                        </div>
                    </div>

                    <form method="POST" action="{{ route('membership-payments.store') }}">
                        @csrf
                        <input type="hidden" name="red_cross_unit_id" value="{{ $redCrossUnit->id }}">
                        <input type="hidden" name="branch_id" value="{{ $redCrossUnit->division->branch_id ?? '' }}">
                        <input type="hidden" name="division_id" value="{{ $redCrossUnit->division_id }}">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                            <!-- Payer: the unit's leaders only -->
                            <div>
                                <label for="user_id" class="block text-sm font-medium text-gray-700">
                                    Person (Payment Made By)
                                </label>
                                <select name="user_id" id="user_id"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        required>
                                    <option value="">Select a person</option>
                                    @foreach($payers as $role => $payer)
                                        <option value="{{ $payer->id }}"
                                                {{ old('user_id') == $payer->id ? 'selected' : '' }}>
                                            {{ $payer->first_name }} {{ $payer->last_name }} (DB-{{ $payer->id }}) — {{ $role }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('user_id')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                @error('red_cross_unit_id')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Fee -->
                            <div>
                                <label for="membership_fee_id" class="block text-sm font-medium text-gray-700">
                                    Annual Fee
                                </label>
                                <select name="membership_fee_id" id="membership_fee_id"
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        required>
                                    <option value="">Select a fee</option>
                                    @foreach($membershipFees as $fee)
                                        <option value="{{ $fee->id }}"
                                                data-validity="{{ $fee->validity_years }}"
                                                data-amount="{{ $fee->amount }}"
                                                {{ old('membership_fee_id') == $fee->id ? 'selected' : '' }}>
                                            {{ $fee->name }} — ₦{{ number_format($fee->amount, 2) }} ({{ $fee->validity_years }} {{ $fee->validity_years === 1 ? 'year' : 'years' }})
                                        </option>
                                    @endforeach
                                </select>
                                @error('membership_fee_id')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                @if($membershipFees->isEmpty())
                                    <p class="mt-1 text-sm text-orange-600">No Red Cross Unit fees are active. Add one under Membership Fees.</p>
                                @endif
                            </div>
                        </div>

                        <!-- Payment Summary -->
                        <div id="payment-summary" class="mt-6">
                            <h4 class="text-md font-medium text-gray-900 mb-3">Payment Summary</h4>
                            <div class="max-w-md bg-white border border-gray-300 rounded-md overflow-hidden">
                                <table class="w-full">
                                    <tbody>
                                        <tr class="border-b border-gray-200">
                                            <td class="px-3 py-2 text-sm text-gray-900">Annual Fee</td>
                                            <td class="px-3 py-2 text-sm text-gray-900" id="membership-fee-amount">₦0.00</td>
                                        </tr>
                                        <tr class="bg-gray-50 font-medium">
                                            <td class="px-3 py-2 text-sm text-gray-900">Total Amount</td>
                                            <td class="px-3 py-2 text-sm text-gray-900" id="total-amount">₦0.00</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                            <!-- Payment Date -->
                            <div>
                                <label for="payment_date" class="block text-sm font-medium text-gray-700">Payment Date</label>
                                <input type="date" name="payment_date" id="payment_date"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                       value="{{ old('payment_date', date('Y-m-d')) }}" required>
                                @error('payment_date')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Reference -->
                            <div>
                                <label for="reference" class="block text-sm font-medium text-gray-700">Reference</label>
                                <input type="text" name="reference" id="reference"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                       value="{{ old('reference') }}" placeholder="Payment reference">
                                @error('reference')
                                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex items-center justify-end space-x-4 mt-8">
                            <a href="{{ route('red-cross-units.show', $redCrossUnit) }}"
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

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const feeSelect = document.getElementById('membership_fee_id');
            const feeAmountCell = document.getElementById('membership-fee-amount');
            const totalCell = document.getElementById('total-amount');

            function updateSummary() {
                const opt = feeSelect.options[feeSelect.selectedIndex];
                if (feeSelect.value && opt) {
                    const amount = parseFloat(opt.dataset.amount) || 0;
                    const fmt = v => `₦${v.toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                    feeAmountCell.textContent = fmt(amount);
                    totalCell.textContent = fmt(amount);
                } else {
                    feeAmountCell.textContent = '₦0.00';
                    totalCell.textContent = '₦0.00';
                }
            }

            feeSelect.addEventListener('change', updateSummary);
            updateSummary();
        });
    </script>
</x-layouts.admin>
