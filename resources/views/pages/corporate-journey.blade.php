<x-layouts.app title="Corporate Membership & Partnership">
    <section class="py-8 lg:py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Section 1: Hero --}}
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Corporate Membership &amp;
                    <span class="text-red-600">Partnership</span></h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    Your organisation can make a real difference by supporting the Nigerian Red Cross Society. Through corporate membership and donations, you help fund humanitarian work, disaster relief, and community support across Nigeria.
                </p>
            </div>

            {{-- Section 2: Two ways to support --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 mb-16">
                {{-- Card 1: Corporate Membership --}}
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                    <div class="text-center mb-6">
                        <div class="bg-red-600 text-white rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 text-2xl shadow-lg">
                            <i class="fas fa-building"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Corporate Membership</h3>
                        <p class="text-gray-600 mb-6">Become an official member organisation of the Nigerian Red Cross. Receive formal recognition, a membership certificate, and demonstrate your organisation's commitment to humanitarian values.</p>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            Annual membership fee
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            Official membership certificate
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            Recognition as a Red Cross partner
                        </div>
                    </div>
                </div>

                {{-- Card 2: Donations --}}
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                    <div class="text-center mb-6">
                        <div class="bg-red-600 text-white rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 text-2xl shadow-lg">
                            <i class="fas fa-hand-holding-heart"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Donations</h3>
                        <p class="text-gray-600 mb-6">Support our work through cash or in-kind donations. Every contribution helps us reach more people in need.</p>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            Cash donations
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            In-kind donations (goods, materials, equipment)
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            Donation certificate upon request
                        </div>
                    </div>
                </div>
            </div>

            {{-- Section 3: How to get started (auth-conditional) --}}
            <div class="mb-16">
                @if(!$isAuthenticated)
                    <div class="text-center mb-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-4">Getting Started</h2>
                        <p class="text-lg text-gray-600 max-w-2xl mx-auto mb-8">
                            To register your organisation, one representative needs to have a personal account in our system. If you don't have one yet, register below. Then contact your local branch to complete the organisation registration.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 mb-8">
                        <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                            <div class="text-center mb-6">
                                <div class="bg-red-600 text-white rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 text-2xl font-bold shadow-lg">
                                    1
                                </div>
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">Register a Personal Account</h3>
                                <p class="text-gray-600">Create your personal account in our system to get started.</p>
                            </div>
                        </div>
                        <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                            <div class="text-center mb-6">
                                <div class="bg-red-600 text-white rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 text-2xl font-bold shadow-lg">
                                    2
                                </div>
                                <h3 class="text-2xl font-bold text-gray-900 mb-2">Contact Your Branch</h3>
                                <p class="text-gray-600">Reach out to your local Red Cross branch to complete the organisation registration.</p>
                            </div>
                        </div>
                    </div>

                    <div class="text-center">
                        <a href="{{ route('register') }}" class="inline-block bg-red-600 text-white px-12 py-4 rounded-lg font-semibold hover:bg-red-700 transition duration-300 shadow-lg text-lg">
                            <i class="fas fa-user-plus mr-2"></i>Register Now
                        </a>
                        <p class="mt-4 text-sm text-gray-500">Already have an account? Log in and return to this page for branch contact details.</p>
                    </div>
                @else
                    <div class="text-center mb-8">
                        <h2 class="text-3xl font-bold text-gray-900 mb-4">Contact Your Branch</h2>
                        <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                            You're already registered. To register your organisation as a corporate member or to make a donation on behalf of your organisation, please contact your local branch directly.
                        </p>
                    </div>

                    @if($branch)
                        <div class="max-w-2xl mx-auto">
                            <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                                <h3 class="text-xl font-bold text-gray-900 mb-4">{{ $branch->name }} Branch Contact Details</h3>

                                <div class="space-y-3 text-sm mb-6">
                                    @if($branch->physical_address)
                                        <div class="grid grid-cols-3 gap-2">
                                            <span class="text-gray-600">Physical Address:</span>
                                            <span class="col-span-2 text-gray-900">{{ $branch->physical_address }}</span>
                                        </div>
                                    @endif
                                    @if($branch->telephone)
                                        <div class="grid grid-cols-3 gap-2">
                                            <span class="text-gray-600">Telephone:</span>
                                            <span class="col-span-2 text-gray-900">{{ $branch->telephone }}</span>
                                        </div>
                                    @endif
                                    @if($branch->email)
                                        <div class="grid grid-cols-3 gap-2">
                                            <span class="text-gray-600">Email:</span>
                                            <span class="col-span-2 text-gray-900">{{ $branch->email }}</span>
                                        </div>
                                    @endif
                                </div>

                                @php $branchContacts = $branch->publicContacts(); @endphp

                                @if(!empty($branchContacts))
                                    <div class="border-t border-gray-200 pt-4">
                                        <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                                            <i class="fas fa-users mr-2 text-red-600"></i>
                                            Contact Persons
                                        </h3>
                                        <div class="space-y-3 text-sm">
                                            @foreach($branchContacts as $contact)
                                                @php
                                                    $contactUser = $contact['user'];
                                                @endphp
                                                <div class="flex items-start gap-3">
                                                    <div class="flex-1">
                                                        <div class="flex items-center gap-2">
                                                            <span class="font-semibold text-gray-900">
                                                                {{ $contactUser->full_name ?? 'Unnamed contact' }}
                                                            </span>
                                                            @if(!empty($contact['position']))
                                                                <span class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-800">
                                                                    {{ $contact['position'] }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                        <div class="mt-1 space-y-0.5 text-gray-700">
                                                            @if($contactUser->email)
                                                                <div class="flex items-center gap-2">
                                                                    <i class="fas fa-envelope text-gray-400 text-xs"></i>
                                                                    <span class="break-all">{{ $contactUser->email }}</span>
                                                                </div>
                                                            @endif
                                                            @if($contactUser->telephone1 || $contactUser->telephone2)
                                                                <div class="flex items-center gap-2">
                                                                    <i class="fas fa-phone text-gray-400 text-xs"></i>
                                                                    <span>{{ $contactUser->telephone1 ?? $contactUser->telephone2 }}</span>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @else
                                    <div class="border-t border-gray-200 pt-4">
                                        <p class="text-sm text-gray-500 italic">
                                            Please contact your branch directly.
                                            @if($branch->email)
                                                <a href="mailto:{{ $branch->email }}" class="text-red-600 hover:underline">{{ $branch->email }}</a>
                                            @endif
                                            @if($branch->telephone)
                                                &middot; {{ $branch->telephone }}
                                            @endif
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="text-center">
                            <p class="text-gray-600">Please contact your nearest Red Cross branch.</p>
                        </div>
                    @endif
                @endif
            </div>

            {{-- Section 4: Corporate Membership Categories table --}}
            <div class="mb-16">
                <h2 class="text-3xl font-bold text-gray-900 mb-8 text-center">Corporate Membership Categories</h2>
                <div class="flex justify-center w-full">
                    @if($membershipFees->isEmpty())
                        <p class="text-gray-500 italic">Please contact your branch for current corporate membership rates.</p>
                    @else
                        <table class="max-w-md w-full mb-8 text-sm">
                            <thead>
                            <tr class="border-b-2 border-gray-100">
                                <th class="font-bold text-gray-900 py-2 text-left">Membership</th>
                                <th class="font-bold text-gray-900 py-2 text-right">Yearly Fee</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($membershipFees as $fee)
                                <tr class="border-t border-gray-100">
                                    <td class="py-2 pr-4">
                                        <div class="font-medium text-gray-900">{{ $fee->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $fee->description }}</div>
                                    </td>
                                    <td class="py-2 text-red-600 font-bold text-right whitespace-nowrap">
                                        ₦{{ number_format($fee->amount) }}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            {{-- Section 5: Bottom CTA --}}
            <div class="text-center mt-12">
                @if(!$isAuthenticated)
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Ready to get started?</h2>
                    <a href="{{ route('register') }}" class="inline-block bg-red-600 text-white px-12 py-4 rounded-lg font-semibold hover:bg-red-700 transition duration-300 shadow-lg text-lg">
                        <i class="fas fa-user-plus mr-2"></i>Register Now
                    </a>
                @else
                    <p class="text-xl text-gray-600">Your branch team is ready to help you register your organisation.</p>
                @endif
            </div>

        </div>
    </section>
</x-layouts.app>
