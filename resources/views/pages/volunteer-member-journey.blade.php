<x-layouts.app title="Your Journey to Become a Red Cross Volunteer & Member">
    {{-- Placeholder page so the welcome page's "Volunteer & Member" card has a destination; full design deferred. --}}
    <section class="py-8 lg:py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Your Journey to Become a
                    <span class="text-red-600">Red Cross Volunteer &amp; Member</span></h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    Serve as an active volunteer in a Red Cross unit, and register as a paying member too.<br>
                    Here's how to get started:
                </p>
            </div>

            <!-- Steps Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-8 lg:gap-12">
                <!-- Step 1: Register Account -->
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                    <div class="text-center">
                        <div class="bg-red-600 text-white rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 text-2xl font-bold shadow-lg">
                            1
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Register Your Account</h3>
                        <p class="text-gray-600">Create your profile and provide your basic information</p>
                    </div>
                </div>

                <!-- Step 2: Link with Branch -->
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                    <div class="text-center">
                        <div class="bg-red-600 text-white rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 text-2xl font-bold shadow-lg">
                            2
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Link with Your Branch</h3>
                        <p class="text-gray-600">Connect with your local Red Cross branch for guidance and support</p>
                    </div>
                </div>

                <!-- Step 3: Unit Assignment -->
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                    <div class="text-center">
                        <div class="bg-red-600 text-white rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 text-2xl font-bold shadow-lg">
                            3
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Red Cross Unit Assignment</h3>
                        <p class="text-gray-600">Your branch assigns you to a Red Cross Unit</p>
                    </div>
                </div>

                <!-- Step 4: Pay Membership Fee -->
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                    <div class="text-center">
                        <div class="bg-red-600 text-white rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 text-2xl font-bold shadow-lg">
                            4
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Pay Your Membership Fee</h3>
                        <p class="text-gray-600">Pay the fee for your category (see table below) through your branch</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-center w-full mt-8">
                <table class="max-w-md w-full mb-8 text-sm">
                    <thead>
                    <tr class="border-b-2 border-gray-100">
                        <th class="font-bold text-gray-900 py-2 text-left">Membership</th>
                        <th class="font-bold text-gray-900 py-2 text-right">Yearly Fee</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach(\App\Models\MembershipFee::getActiveOneYearMemberships(true) as $membership)
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

            <!-- Register Button -->
            <div class="text-center mt-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Begin Your Journey Today</h2>
                <a href="{{ route('register') }}" class="inline-block bg-red-600 text-white px-12 py-4 rounded-lg font-semibold hover:bg-red-700 transition duration-300 shadow-lg text-lg">
                    <i class="fas fa-user-plus mr-2"></i>Register Now
                </a>
            </div>
        </div>
    </section>

</x-layouts.app>
