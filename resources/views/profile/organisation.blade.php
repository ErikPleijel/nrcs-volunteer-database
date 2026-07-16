<x-layouts.app title="{{ $organisation->name }}">
    <x-slot name="styles">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    </x-slot>

    <x-slot name="pageHeader">
        <h1 class="text-2xl font-bold text-gray-900">
            <i class="fas fa-building mr-2 text-gray-600"></i>{{ $organisation->name }}
        </h1>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            <div>
                <a href="{{ route('profile.show') }}" class="text-blue-600 hover:text-blue-800 text-sm">
                    <i class="fas fa-arrow-left mr-1"></i>Back to My Profile
                </a>
            </div>

            <!-- 1. Organisation Details -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-building text-gray-600 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">ORGANISATION DETAILS</h2>
                </div>
                <div class="space-y-3 text-sm">
                    <div class="grid grid-cols-3 gap-2">
                        <span class="text-gray-600">Name:</span>
                        <span class="col-span-2 text-gray-900 font-medium">{{ $organisation->name }}</span>
                    </div>
                    @if($organisation->short_name)
                        <div class="grid grid-cols-3 gap-2">
                            <span class="text-gray-600">Short Name:</span>
                            <span class="col-span-2 text-gray-900">{{ $organisation->short_name }}</span>
                        </div>
                    @endif
                    @if($organisation->registration_number)
                        <div class="grid grid-cols-3 gap-2">
                            <span class="text-gray-600">Registration Number:</span>
                            <span class="col-span-2 text-gray-900">{{ $organisation->registration_number }}</span>
                        </div>
                    @endif
                    <div class="grid grid-cols-3 gap-2">
                        <span class="text-gray-600">Branch:</span>
                        <span class="col-span-2 text-gray-900">{{ $organisation->branch->name ?? '—' }}</span>
                    </div>
                    @if($organisation->email)
                        <div class="grid grid-cols-3 gap-2">
                            <span class="text-gray-600">Official Email:</span>
                            <span class="col-span-2 text-gray-900">{{ $organisation->email }}</span>
                        </div>
                    @endif
                    @if($organisation->phone)
                        <div class="grid grid-cols-3 gap-2">
                            <span class="text-gray-600">Phone:</span>
                            <span class="col-span-2 text-gray-900">{{ $organisation->phone }}</span>
                        </div>
                    @endif
                    @if($organisation->address)
                        <div class="grid grid-cols-3 gap-2">
                            <span class="text-gray-600">Address:</span>
                            <span class="col-span-2 text-gray-900 whitespace-pre-line">{{ $organisation->address }}</span>
                        </div>
                    @endif
                </div>
            </div>

            <!-- 2. Linked Persons -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-users text-blue-600 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">LINKED PERSONS</h2>
                </div>

                @if($organisation->users->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($organisation->users as $linkedUser)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-medium text-gray-900">{{ $linkedUser->full_name }}</span>
                                    @if($linkedUser->pivot->is_primary_contact)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <i class="fas fa-star mr-1"></i>Primary Contact
                                        </span>
                                    @endif
                                    @if($linkedUser->id === auth()->id())
                                        <span class="text-xs text-gray-500 italic">(You)</span>
                                    @endif
                                </div>
                                @if($linkedUser->telephone1)
                                    <span class="text-sm text-gray-600 whitespace-nowrap ml-4">
                                        <i class="fas fa-phone text-gray-400 mr-1"></i>{{ $linkedUser->telephone1 }}
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-500 italic text-sm">No persons linked.</p>
                @endif

                <p class="mt-4 text-xs text-gray-400 italic">
                    For corrections or more information, please contact your branch.
                </p>
            </div>

            <!-- 3. Your Membership -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-id-card text-blue-600 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">YOUR MEMBERSHIP</h2>
                </div>

                <div class="mb-6 p-4 border-2 border-dashed border-gray-200 rounded-lg bg-gray-50">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3">Current Membership Status</h3>
                    @if($currentMembership)
                        <div class="flex items-center justify-between flex-wrap gap-4">
                            <div>
                                <p class="text-sm text-gray-600">Current Plan:</p>
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
                                    <i class="fas fa-check-circle mr-1"></i>Active
                                </span>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center justify-center py-4">
                            <div class="text-center">
                                <i class="fas fa-exclamation-triangle text-orange-500 text-2xl mb-2"></i>
                                <p class="text-orange-700 font-medium">No Active Membership</p>
                                <p class="text-sm text-gray-600 mt-1">Please contact your branch to renew the organisation's membership</p>
                            </div>
                        </div>
                    @endif
                </div>

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
                                <th class="text-left py-2 text-gray-600 bg-white">Membership Type</th>
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
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-gray-500 italic">
                                        No membership payments found
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 4. Your Donations -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-hand-holding-heart text-green-600 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">YOUR DONATIONS</h2>
                </div>

                @if($donationsLimitMessage)
                    <div class="mb-2 p-2 bg-green-50 border border-green-200 rounded text-center">
                        <span class="text-green-800 text-xs font-medium">
                            <i class="fas fa-info-circle mr-1"></i>Showing recent donations - scroll to view more
                        </span>
                    </div>
                @endif

                <div class="overflow-x-auto">
                    <div class="@if($donationsLimitMessage) max-h-64 overflow-y-auto @endif">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-white">
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-2 text-gray-600 bg-white">Donation date</th>
                                <th class="text-left py-2 text-gray-600 bg-white">Donated item</th>
                                <th class="text-left py-2 text-gray-600 bg-white">Amount</th>
                                <th class="text-left py-2 text-gray-600 bg-white">Type</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($donations as $donation)
                                <tr class="border-b border-gray-100">
                                    <td class="py-2">{{ $donation['date'] }}</td>
                                    <td class="py-2">
                                        <div class="flex items-center">
                                            @if($donation['type'] === 'in-kind')
                                                <i class="fas fa-box text-green-600 mr-2"></i>
                                            @else
                                                <i class="fas fa-money-bill text-green-600 mr-2"></i>
                                            @endif
                                            {{ $donation['item'] }}
                                        </div>
                                    </td>
                                    <td class="py-2">{{ $donation['amount'] }}</td>
                                    <td class="py-2">
                                        <span class="px-2 py-1 rounded-full text-xs
                                            @if($donation['type'] === 'in-kind')
                                                bg-blue-100 text-blue-800
                                            @else
                                                bg-green-100 text-green-800
                                            @endif">
                                            {{ $donation['type'] === 'in-kind' ? 'In-Kind' : 'Cash' }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-gray-500 italic">
                                        No donations found
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 5. Printed Certificates -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-print text-indigo-600 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">PRINTED CERTIFICATES</h2>
                </div>

                <p class="text-gray-500 text-sm italic mb-4">
                    Upon request, you can obtain membership and donation certificates for this organisation. Please contact your branch.
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
