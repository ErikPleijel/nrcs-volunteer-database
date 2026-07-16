<x-layouts.app>
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto text-center">
            <div class="bg-green-50 border border-green-200 rounded-lg p-8">
                <div class="text-green-600 text-6xl mb-4">✓</div>
                <h1 class="text-3xl font-bold text-gray-800 mb-4">Registration Successful!</h1>

                <p class="text-lg text-gray-600 mb-6">
                    Thank you {{ session('user_name') }} for registering with the Red Cross.
                </p>

                <!-- User ID Section -->
                <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-red-800 mb-3">📝 Your Database Code</h2>
                    <div class="bg-white border-2 border-red-300 rounded-lg p-4 mb-4">
                        <div class="text-3xl font-bold text-red-600 mb-2">
                            #{{ session('user_id') }}
                        </div>
                        <p class="text-sm text-gray-600">Your unique member ID</p>
                    </div>
                    <p class="text-sm text-red-800">
                        <strong>Important:</strong> This is your database code. You’ll need it for payments and other activities. Please keep it handy.
                    </p>
                </div>

                <div class="bg-white border border-gray-200 rounded-lg p-6 mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-3">What's Next?</h2>
                    <div class="space-y-4">
                        <div>
                            <p class="flex items-start">
                                <span class="text-blue-600 mr-3 text-xl">📧</span>
                                <span>Check your email <strong>({{ session('email') }})</strong> and click the verification link</span>
                            </p>
                            <div class="mt-2 text-center">
                                <a href="{{ route('verification.notice') }}" class="inline-block text-sm text-gray-500 hover:text-gray-700 underline underline-offset-2 transition duration-200">
                                    Resend verification email
                                </a>
                            </div>
                        </div>

                        <div>
                            <p class="flex items-start">
                                <span class="text-blue-600 mr-3 text-xl">⏰</span>
                                <span>Check out your profile to see your membership status and next steps.</span>
                            </p>
                            <div class="mt-2 text-center">
                                <a href="{{ route('profile.show') }}" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200">
                                    View My Profile
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-layouts.app>
