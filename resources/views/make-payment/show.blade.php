<x-layouts.app title="Make a Payment">
    <x-slot name="pageHeader">
        <h1 class="text-2xl font-bold text-gray-900">
            <i class="fas fa-credit-card mr-3"></i> Make a Payment
        </h1>
        <p class="mt-1 text-sm text-gray-600">
            Pay your membership fee or make a donation online via Paystack
        </p>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    @if($lockedPaymentType)
                        <div class="mb-4">
                            <a href="{{ route('profile.show') }}" class="text-base text-gray-700 hover:text-gray-900 underline">
                                &larr; Back to your profile
                            </a>
                        </div>
                    @endif

                    <!-- Success Message -->
                    @if (session('success'))
                        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                            {{ session('success') }}
                        </div>
                    @endif

                    <!-- Warning -->
                    @if (session('warning'))
                        <div class="mb-6 flex items-center gap-3 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                            <i class="fas fa-triangle-exclamation text-yellow-500"></i>{{ session('warning') }}
                        </div>
                    @endif

                    <!-- Error (session) -->
                    @if (session('error'))
                        <div class="mb-6 flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            <i class="fas fa-circle-exclamation text-red-500"></i>{{ session('error') }}
                        </div>
                    @endif

                    <!-- Error Messages (validation) -->
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

                    <form method="POST" action="{{ route('make-payment.initiate') }}">
                        @csrf

                        @if($lockedPaymentType)
                            <input type="hidden" name="payment_type" value="{{ $lockedPaymentType }}">
                        @else
                            <!-- Payment type toggle -->
                            <div class="entry-card">
                                <h4 class="entry-card-title">What are you paying for?</h4>
                                <div class="flex flex-col sm:flex-row gap-4">
                                    <label class="flex items-center gap-2">
                                        <input type="radio" name="payment_type" value="membership" id="type_membership"
                                               {{ old('payment_type', 'membership') === 'membership' ? 'checked' : '' }}
                                               class="text-blue-600 focus:ring-blue-500">
                                        <span class="text-sm font-medium text-gray-700">Membership Renewal</span>
                                    </label>
                                    <label class="flex items-center gap-2">
                                        <input type="radio" name="payment_type" value="donation" id="type_donation"
                                               {{ old('payment_type') === 'donation' ? 'checked' : '' }}
                                               class="text-blue-600 focus:ring-blue-500">
                                        <span class="text-sm font-medium text-gray-700">Donation</span>
                                    </label>
                                </div>
                                @error('payment_type')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        @if($lockedOrganisation)
                            <input type="hidden" name="organisation_id" value="{{ $lockedOrganisation->id }}">
                            <p class="text-sm text-gray-600 mb-4">
                                Paying on behalf of <strong>{{ $lockedOrganisation->name }}</strong>
                            </p>
                        @elseif($organisations->isNotEmpty())
                            <!-- Paying as -->
                            <div class="entry-card">
                                <h4 class="entry-card-title">Paying as</h4>
                                <div class="flex flex-col gap-3">
                                    <label class="flex items-center gap-2">
                                        <input type="radio" name="organisation_id" value="" id="paying_as_self"
                                               {{ old('organisation_id') ? '' : 'checked' }}
                                               class="text-blue-600 focus:ring-blue-500">
                                        <span class="text-sm font-medium text-gray-700">Myself</span>
                                    </label>
                                    @foreach($organisations as $organisation)
                                        <label class="flex items-center gap-2">
                                            <input type="radio" name="organisation_id" value="{{ $organisation->id }}"
                                                   id="paying_as_org_{{ $organisation->id }}"
                                                   {{ (string) old('organisation_id') === (string) $organisation->id ? 'checked' : '' }}
                                                   class="text-blue-600 focus:ring-blue-500">
                                            <span class="text-sm font-medium text-gray-700">On behalf of {{ $organisation->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @error('organisation_id')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        @if(!$lockedPaymentType || $lockedPaymentType === 'membership')
                            <!-- Membership Renewal section -->
                            <div id="membership_fields" class="entry-card">
                                <h4 class="entry-card-title">Membership Details</h4>

                                @if($lockedOrganisation)
                                    {{-- Locked to a single organisation: no personal/org toggle to
                                         speak of, just the one relevant fee list, always visible and
                                         enabled. Not wired into updateMembershipFeeSelects() — that
                                         function exists purely for the personal-vs-org toggle below,
                                         which doesn't apply here. --}}
                                    <div>
                                        <label for="membership_fee_id" class="block text-sm font-medium text-gray-700 mb-2">
                                            Membership Fee <span class="text-red-500">*</span>
                                        </label>
                                        <select name="membership_fee_id" id="membership_fee_id" required
                                                class="entry-field @error('membership_fee_id') border-red-500 @enderror">
                                            <option value="">Select a membership fee</option>
                                            @foreach($organisationMembershipFees as $fee)
                                                <option value="{{ $fee->id }}" {{ (string) old('membership_fee_id') === (string) $fee->id ? 'selected' : '' }}>
                                                    {{ $fee->name }} — ₦{{ number_format($fee->amount, 2) }} ({{ $fee->validity_years }} years)
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('membership_fee_id')
                                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @else
                                    {{-- Personal fee list — visible when "Paying as: Myself" (or no linked orgs at all). --}}
                                    <div id="personal_fee_wrapper">
                                        <label for="personal_membership_fee_id" class="block text-sm font-medium text-gray-700 mb-2">
                                            Membership Fee <span class="text-red-500">*</span>
                                        </label>
                                        <select name="membership_fee_id" id="personal_membership_fee_id"
                                                class="entry-field @error('membership_fee_id') border-red-500 @enderror">
                                            <option value="">Select a membership fee</option>
                                            @foreach($personalMembershipFees as $fee)
                                                <option value="{{ $fee->id }}" {{ (string) old('membership_fee_id') === (string) $fee->id ? 'selected' : '' }}>
                                                    {{ $fee->name }} — ₦{{ number_format($fee->amount, 2) }} ({{ $fee->validity_years }} years)
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('membership_fee_id')
                                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    {{-- Organisation fee list — visible when "Paying as: On behalf of ..." is selected.
                                         Starts hidden+disabled so its value never gets submitted alongside the personal
                                         select's; the JS below flips which one is enabled to match the "Paying as" choice. --}}
                                    <div id="organisation_fee_wrapper" class="hidden">
                                        <label for="organisation_membership_fee_id" class="block text-sm font-medium text-gray-700 mb-2">
                                            Membership Fee <span class="text-red-500">*</span>
                                        </label>
                                        <select name="membership_fee_id" id="organisation_membership_fee_id" disabled
                                                class="entry-field @error('membership_fee_id') border-red-500 @enderror">
                                            <option value="">Select a membership fee</option>
                                            @foreach($organisationMembershipFees as $fee)
                                                <option value="{{ $fee->id }}" {{ (string) old('membership_fee_id') === (string) $fee->id ? 'selected' : '' }}>
                                                    {{ $fee->name }} — ₦{{ number_format($fee->amount, 2) }} ({{ $fee->validity_years }} years)
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('membership_fee_id')
                                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if(!$lockedPaymentType || $lockedPaymentType === 'donation')
                            <!-- Donation section -->
                            <div id="donation_fields" class="entry-card{{ $lockedPaymentType ? '' : ' hidden' }}">
                                <h4 class="entry-card-title">Donation Details</h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
                                            Amount (₦) <span class="text-red-500">*</span>
                                        </label>
                                        <input type="number" name="amount" id="amount" step="1" min="1"
                                               value="{{ old('amount') }}"
                                               @if($lockedPaymentType === 'donation') required @endif
                                               class="entry-field @error('amount') border-red-500 @enderror">
                                        @error('amount')
                                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="purpose" class="block text-sm font-medium text-gray-700 mb-2">
                                            Purpose (optional)
                                        </label>
                                        <input type="text" name="purpose" id="purpose"
                                               value="{{ old('purpose') }}"
                                               class="entry-field @error('purpose') border-red-500 @enderror">
                                        @error('purpose')
                                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <label class="flex items-center gap-2">
                                        <input type="checkbox" name="anonymous" value="1"
                                               {{ old('anonymous') ? 'checked' : '' }}
                                               class="text-blue-600 focus:ring-blue-500">
                                        <span class="text-sm font-medium text-gray-700">Make this an anonymous donation</span>
                                    </label>
                                </div>
                            </div>
                        @endif

                        <p class="text-sm text-gray-500 text-right mt-8 mb-2">
                            You'll be redirected to Paystack's secure payment page to complete this transaction.
                        </p>

                        <div class="flex justify-end space-x-4">
                            <a href="{{ route('profile.show') }}"
                               class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                Cancel
                            </a>
                            <button type="submit"
                                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Proceed to Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const typeMembership = document.getElementById('type_membership');
            const typeDonation = document.getElementById('type_donation');
            const membershipFields = document.getElementById('membership_fields');
            const donationFields = document.getElementById('donation_fields');
            const amountInput = document.getElementById('amount');

            // Absent entirely when the payer has no linked organisations (the
            // "Paying as" section is skipped server-side in that case) —
            // querySelectorAll then just returns an empty list, which is fine.
            const payingAsRadios = document.querySelectorAll('input[name="organisation_id"]');
            const personalFeeWrapper = document.getElementById('personal_fee_wrapper');
            const organisationFeeWrapper = document.getElementById('organisation_fee_wrapper');
            const personalFeeSelect = document.getElementById('personal_membership_fee_id');
            const organisationFeeSelect = document.getElementById('organisation_membership_fee_id');

            function isPayingForOrganisation() {
                const checked = document.querySelector('input[name="organisation_id"]:checked');
                return !!(checked && checked.value !== '');
            }

            // Keeps the personal/org fee selects in sync with both the
            // payment-type toggle (only relevant while "Membership Renewal" is
            // selected) and the "Paying as" toggle (which of the two selects
            // is the active one). Disabling the inactive select is what stops
            // its value from being submitted alongside the active one.
            //
            // No-ops entirely when the fee-select elements aren't in the DOM —
            // true on a donation-locked page, which has no membership section
            // to sync at all.
            function updateMembershipFeeSelects() {
                if (!personalFeeWrapper || !organisationFeeWrapper || !personalFeeSelect || !organisationFeeSelect) {
                    return;
                }

                // typeDonation is null on a locked page (no toggle rendered) —
                // this function is only ever reached on a locked page when
                // locked to 'membership' (guarded above otherwise), so fix
                // isMembership to true in that case.
                const isMembership = typeDonation ? !typeDonation.checked : true;
                const isOrgPayment = isPayingForOrganisation();

                if (isOrgPayment) {
                    personalFeeWrapper.classList.add('hidden');
                    personalFeeSelect.setAttribute('disabled', 'disabled');
                    personalFeeSelect.removeAttribute('required');

                    organisationFeeWrapper.classList.remove('hidden');
                    organisationFeeSelect.removeAttribute('disabled');
                    if (isMembership) organisationFeeSelect.setAttribute('required', 'required');
                    else organisationFeeSelect.removeAttribute('required');
                } else {
                    organisationFeeWrapper.classList.add('hidden');
                    organisationFeeSelect.setAttribute('disabled', 'disabled');
                    organisationFeeSelect.removeAttribute('required');

                    personalFeeWrapper.classList.remove('hidden');
                    personalFeeSelect.removeAttribute('disabled');
                    if (isMembership) personalFeeSelect.setAttribute('required', 'required');
                    else personalFeeSelect.removeAttribute('required');
                }
            }

            function togglePaymentType() {
                if (typeDonation.checked) {
                    membershipFields.classList.add('hidden');
                    donationFields.classList.remove('hidden');
                    amountInput.setAttribute('required', 'required');
                } else {
                    donationFields.classList.add('hidden');
                    membershipFields.classList.remove('hidden');
                    amountInput.removeAttribute('required');
                }

                updateMembershipFeeSelects();
            }

            payingAsRadios.forEach(function (radio) {
                radio.addEventListener('change', updateMembershipFeeSelects);
            });

            // --- Initialisation ---
            if (typeMembership && typeDonation) {
                // Unlocked page: full payment-type toggle wiring, exactly as before.
                typeMembership.addEventListener('change', togglePaymentType);
                typeDonation.addEventListener('change', togglePaymentType);
                togglePaymentType();
            } else {
                // Locked page: no toggle to wire up (the radios don't exist).
                // Still need the initial fee-select sync when locked to
                // 'membership' — it no-ops harmlessly when locked to
                // 'donation', since the fee-select elements aren't in the DOM.
                updateMembershipFeeSelects();
            }
        });
    </script>
</x-layouts.app>
