<x-layouts.app title="Your Journey to Become a Red Cross Volunteer">
    <!-- Journey Steps Section -->
    <section class="py-8 lg:py-24 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Your Journey to Become a
                    <span class="text-red-600">Red Cross Volunteer</span></h2>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                    Serve hands-on with your local Red Cross unit — emergency response, first aid, community programs. No membership fee, no payment: just your time and skills.<br>
                    Here's how to get started:
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
                        <p class="text-gray-600 mb-6">Create your volunteer profile and provide your basic information</p>
                    </div>
                </div>

                <!-- Step 2: Connect with Branch -->
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                    <div class="text-center mb-6">
                        <div class="bg-red-600 text-white rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 text-2xl font-bold shadow-lg">
                            2
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Link with Your Branch</h3>
                        <p class="text-gray-600 mb-6">Connect with your local Red Cross branch for guidance and support</p>
                    </div>
                </div>

                <!-- Step 3: Branch Assignment -->
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                    <div class="text-center mb-6">
                        <div class="bg-red-600 text-white rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 text-2xl font-bold shadow-lg">
                            3
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Red Cross Unit Assignment</h3>
                        <p class="text-gray-600 mb-6">Your branch assigns you to a specific Red Cross Unit based on your skills</p>
                    </div>
                </div>

                <!-- Step 4: Start Volunteering -->
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                    <div class="text-center mb-6">
                        <div class="bg-red-600 text-white rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 text-2xl font-bold shadow-lg">
                            4
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Begin Your Service</h3>
                        <p class="text-gray-600 mb-6">Start volunteering and receive specialized training like first aid</p>
                    </div>
                </div>
            </div>

            <!-- Register Button directly under steps -->
            <div class="text-center mt-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Begin Your Journey Today</h2>
                <p class="text-xl text-gray-600 mb-8">
                    Take the first step towards making a meaningful impact in your community
                </p>
                <a href="{{ route('register') }}" class="inline-block bg-red-600 text-white px-12 py-4 rounded-lg font-semibold hover:bg-red-700 transition duration-300 shadow-lg text-lg">
                    <i class="fas fa-user-plus mr-2"></i>Register Now
                </a>
            </div>
        </div>
    </section>

</x-layouts.app>
