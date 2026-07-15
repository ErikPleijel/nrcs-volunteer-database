<x-layouts.app title="Your Journey to Become a Red Cross Member">
    <!-- Membership Steps Section -->
    <section class="py-8 lg:py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Your Journey to Become a
                    <span class="text-red-600">Red Cross Member</span></h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    Join our community as an official member and support the humanitarian work of the Nigerian Red Cross Society.
                    Follow these simple steps to complete your membership registration and start making a difference.
                </p>
            </div>




            <!-- Steps Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 lg:gap-12">
                <!-- Step 1: Register Account -->
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                    <div class="text-center mb-6">
                        <div class="bg-red-600 text-white rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 text-2xl font-bold shadow-lg">
                            1
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Register Your Account</h3>
                        <p class="text-gray-600 mb-6">Create your member profile and provide your basic information</p>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            Personal information
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            Contact details
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            Email verification
                        </div>
                    </div>
                </div>

                <!-- Step 2: Note DB Code -->
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                    <div class="text-center mb-6">
                        <div class="bg-red-600 text-white rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 text-2xl font-bold shadow-lg">
                            2
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Note Your <nobr>DB-Code</nobr></h3>
                        <p class="text-gray-600 mb-6">Your unique database code will be displayed when registration is completed</p>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            Unique identifier code
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            Branch reference
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            Record keeping
                        </div>
                    </div>
                </div>

                <!-- Step 3: Select Membership Type -->
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                    <div class="text-center mb-6">
                        <div class="bg-red-600 text-white rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 text-2xl font-bold shadow-lg">
                            3
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Select Membership Type</h3>
                        <p class="text-gray-600 mb-6">Choose the membership plan that best suits your commitment level</p>
                    </div>
                    <div class="space-y-3 text-center">
                        See table below.

                    </div>
                </div>

                <!-- Step 4: Make Payment -->
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                    <div class="text-center mb-6">
                        <div class="bg-red-600 text-white rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 text-2xl font-bold shadow-lg">
                            4
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Make Payment</h3>
                        <p class="text-gray-600 mb-6">Complete your membership by making payment through your preferred method</p>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            Bank transfer
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            Branch payment
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            Payment confirmation
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-center w-full mt-4">
                <table class="max-w-md w-full mb-8 text-sm">
                    <thead>
                    <tr class="border-b-2 border-gray-100">
                        <th class="font-bold text-gray-900 py-2 text-left">Membership</th>
                        <th class="font-bold text-gray-900 py-2 text-right">Yearly Fee</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach(\App\Models\MembershipFee::getActiveOneYearMemberships() as $membership)
                        <tr class="border-t border-gray-100">
                            <td class="py-2 pr-4">
                                <div class="font-medium text-gray-900">{{ $membership->name }}</div>
                                <div class="text-xs text-gray-500">{{ $membership->description }}</div>
                            </td>
                            <td class="py-2 text-red-600 font-bold text-right whitespace-nowrap">
                                ₦{{ number_format($membership->amount) }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Register Button directly under steps -->
            <div class="text-center mt-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Start Your Membership Today</h2>
                <p class="text-xl text-gray-600 mb-8">
                    Join the Nigerian Red Cross Society and become part of a humanitarian movement that saves lives
                </p>
                <a href="{{ route('register') }}" class="inline-block bg-red-600 text-white px-12 py-4 rounded-lg font-semibold hover:bg-red-700 transition duration-300 shadow-lg text-lg">
                    <i class="fas fa-user-plus mr-2"></i>Register Now
                </a>
            </div>
        </div>
    </section>

</x-layouts.app>
