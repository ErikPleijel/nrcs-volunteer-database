<x-layouts.app title="{{ $redCrossUnit->name }}">
    <x-slot name="pageHeader">
        <h1 class="text-2xl font-bold text-gray-900">
            <i class="fas fa-people-group mr-2 text-gray-600"></i>{{ $redCrossUnit->name }}
        </h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            <div>
                <a href="{{ route('profile.show') }}" class="text-blue-600 hover:text-blue-800 text-sm">
                    <i class="fas fa-arrow-left mr-1"></i>Back to My Profile
                </a>
            </div>

            <!-- 1. Unit Details -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-people-group text-gray-600 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">RED CROSS UNIT DETAILS</h2>
                </div>
                <div class="space-y-3 text-sm">
                    <div class="grid grid-cols-3 gap-2">
                        <span class="text-gray-600">Name:</span>
                        <span class="col-span-2 text-gray-900 font-medium">
                            {{ $redCrossUnit->name }}
                            @unless($redCrossUnit->is_active)
                                <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Archived</span>
                            @endunless
                        </span>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <span class="text-gray-600">Branch:</span>
                        <span class="col-span-2 text-gray-900">{{ $redCrossUnit->division->branch->name ?? '—' }}</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <span class="text-gray-600">Division:</span>
                        <span class="col-span-2 text-gray-900">{{ $redCrossUnit->division->name ?? '—' }}</span>
                    </div>
                    @foreach(['Team Leader' => $redCrossUnit->teamLeader, 'Assistant Team Leader' => $redCrossUnit->assistantTeamLeader] as $roleLabel => $leader)
                        <div class="grid grid-cols-3 gap-2">
                            <span class="text-gray-600">{{ $roleLabel }}:</span>
                            <span class="col-span-2 text-gray-900">
                                {{ $leader->full_name ?? '—' }}
                                @if($leader && $leader->id === auth()->id())
                                    <span class="text-xs text-gray-500 italic">(You)</span>
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>

                <p class="mt-4 text-xs text-gray-400 italic">
                    For corrections or more information, please contact your branch.
                </p>
            </div>

            <!-- 2. Annual Fee -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-id-card text-blue-600 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">ANNUAL FEE</h2>
                </div>

                <div class="mb-6 p-4 border-2 border-dashed border-gray-200 rounded-lg bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Current Status</h3>
                    @if($currentMembership)
                        <div class="flex items-center justify-between flex-wrap gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Fee:</p>
                                <p class="font-medium text-gray-900">{{ $currentMembership['membership_type'] }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Amount Paid:</p>
                                <p class="font-medium text-gray-900">{{ $currentMembership['formatted_amount'] }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Valid Until:</p>
                                <p class="font-medium text-green-600">{{ $currentMembership['expiry_date'] }}</p>
                            </div>
                            <div>
                                <span class="bg-green-100 text-green-800 px-3 py-2 rounded-full text-sm font-medium">
                                    <i class="fas fa-check-circle mr-1"></i>Paid
                                </span>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center justify-center py-4">
                            <div class="text-center">
                                <i class="fas fa-exclamation-triangle text-orange-500 text-2xl mb-2"></i>
                                <p class="text-orange-700 font-medium">Annual Fee Not Paid</p>
                            </div>
                        </div>
                    @endif
                </div>

                @php
                    // Same subcases as profile/organisation.blade.php's CTA.
                    $membershipCtaSubcase = null;
                    if (! $currentMembership) {
                        $membershipCtaSubcase = $hasEverHadRcuPayment ? 'lapsed' : 'new';
                    } elseif ($currentMembership['expiring_soon'] ?? false) {
                        $membershipCtaSubcase = 'expiring_soon';
                    } else {
                        $membershipCtaSubcase = 'valid';
                    }
                    $paymentUrl = route('make-payment.show', ['payment_type' => 'membership', 'red_cross_unit_id' => $redCrossUnit->id]);
                @endphp

                @if(! $redCrossUnit->is_active)
                    <p class="text-gray-600 text-sm mb-6">This Red Cross Unit is archived, so its annual fee can no longer be paid. Please contact your branch if you think this is a mistake.</p>
                @elseif($membershipCtaSubcase === 'new')
                    @if($canPayOnline)
                        <p class="text-gray-600 text-sm mb-2">Pay the unit's annual fee online.</p>
                        <div class="mb-6 flex justify-start">
                            <a href="{{ $paymentUrl }}" class="btn-primary">
                                <i class="fas fa-credit-card mr-1"></i>Make a Payment
                            </a>
                        </div>
                    @else
                        <p class="text-gray-600 text-sm mb-6">Add an email address to your profile to pay online, or contact your branch to pay directly.</p>
                    @endif
                @elseif($membershipCtaSubcase === 'lapsed')
                    @if($canPayOnline)
                        <p class="text-gray-600 text-sm mb-2">The unit's annual fee has expired. Renew online to continue.</p>
                        <div class="mb-6 flex justify-start">
                            <a href="{{ $paymentUrl }}" class="btn-primary">
                                <i class="fas fa-credit-card mr-1"></i>Renew Annual Fee
                            </a>
                        </div>
                    @else
                        <p class="text-gray-600 text-sm mb-6">Add an email address to your profile to renew online, or contact your branch to renew directly.</p>
                    @endif
                @elseif($membershipCtaSubcase === 'expiring_soon')
                    @if($canPayOnline)
                        <p class="text-gray-600 text-sm mb-2">The unit's annual fee expires in {{ floor($currentMembership['days_until_expiry']) }} days. Renew now to avoid a lapse.</p>
                        <div class="mb-6 flex justify-start">
                            <a href="{{ $paymentUrl }}" class="btn-primary">
                                <i class="fas fa-credit-card mr-1"></i>Renew Early
                            </a>
                        </div>
                    @else
                        <p class="text-gray-600 text-sm mb-6">The unit's annual fee expires in {{ floor($currentMembership['days_until_expiry']) }} days. Add an email address to your profile to renew online, or contact your branch to renew directly.</p>
                    @endif
                @else
                    <p class="text-3xl font-bold text-center text-gray-900 mb-6">
                        {{ floor($currentMembership['days_until_expiry']) }} days to renewal
                    </p>
                @endif

                @if($showingLimitMessage)
                    <div class="mb-2 p-2 bg-blue-50 border border-blue-200 rounded text-center">
                        <span class="text-blue-800 text-xs font-medium">
                            <i class="fas fa-info-circle mr-1"></i>Showing recent payments - scroll to view more
                        </span>
                    </div>
                @endif

                <div class="overflow-x-auto">
                    <div class="@if($showingLimitMessage) max-h-64 overflow-y-auto @endif">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-white">
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-2 text-gray-600 bg-white">Payment date</th>
                                <th class="text-left py-2 text-gray-600 bg-white">Fee</th>
                                <th class="text-left py-2 text-gray-600 bg-white">Amount</th>
                                <th class="text-left py-2 text-gray-600 bg-white">Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($membershipPayments as $payment)
                                <tr class="border-b border-gray-100">
                                    <td class="py-2">{{ $payment['payment_date'] }}</td>
                                    <td class="py-2">{{ $payment['membership_type'] }}</td>
                                    <td class="py-2">{{ $payment['formatted_amount'] }}</td>
                                    <td class="py-2">
                                        <span class="{{ $payment['status']['class'] }} px-2 py-1 rounded-full text-xs">
                                            {{ $payment['status']['text'] }}
                                        </span>
                                        @if($payment['is_pending'])
                                            <x-approval-status-badge status="pending" class="ml-1" />
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-gray-500 italic">
                                        No annual fee payments found
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 3. Printed Certificates -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-print text-indigo-600 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">PRINTED CERTIFICATES</h2>
                </div>

                <p class="text-gray-500 text-sm italic mb-4">
                    While the annual fee is paid, you can obtain a membership certificate for this unit upon request. Please contact your branch.
                </p>

                @if($certificatePrintsLimitMessage)
                    <div class="mb-2 p-2 bg-indigo-50 border border-indigo-200 rounded text-center">
                        <span class="text-indigo-800 text-xs font-medium">
                            <i class="fas fa-info-circle mr-1"></i>Showing recent printed certificates - scroll to view more
                        </span>
                    </div>
                @endif

                <div class="overflow-x-auto">
                    <div class="@if($certificatePrintsLimitMessage) max-h-64 overflow-y-auto @endif">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-white">
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-2 text-gray-600 bg-white">Printed at</th>
                                <th class="text-left py-2 text-gray-600 bg-white">Certificate type</th>
                                <th class="text-left py-2 text-gray-600 bg-white">Printed by</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($certificatePrints as $print)
                                <tr class="border-b border-gray-100">
                                    <td class="py-2">{{ $print['printed_at'] }}</td>
                                    <td class="py-2">
                                        <div class="flex items-center">
                                            <i class="fas fa-certificate text-indigo-600 mr-2"></i>
                                            {{ $print['certificate_type'] }}
                                        </div>
                                    </td>
                                    <td class="py-2">{{ $print['printed_by'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-4 text-center text-gray-500 italic">
                                        No printed certificates found
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="pb-4">
                <a href="{{ route('profile.show') }}"
                   class="inline-flex items-center text-blue-600 hover:text-blue-800 text-sm">
                    <i class="fas fa-arrow-left mr-2"></i>Back to My Profile
                </a>
            </div>

        </div>
    </div>
</x-layouts.app>
