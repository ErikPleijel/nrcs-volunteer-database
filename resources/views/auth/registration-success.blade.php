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
                    <div class="space-y-3">
                        <p class="flex items-start">
                            <span class="text-blue-600 mr-3 text-xl">📧</span>
                            <span>Check your email <strong>({{ session('email') }})</strong> and click the verification link</span>
                        </p>
                        <p class="flex items-start">
                            <span class="text-blue-600 mr-3 text-xl">⏰</span>
                            <span>Log in and check your profile once your email is verified</span>
                        </p>
                        <p class="flex items-start">
                            <span class="text-blue-600 mr-3 text-xl">📩</span>
                            <span>[---------]</span>
                        </p>
                    </div>
                </div>


                <div class="space-x-4">
                    <a href="{{ route('login') }}" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200">
                        Go to Login
                    </a>
                    <a href="{{ route('verification.notice') }}" class="inline-block bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 transition duration-200">
                        Resend Verification Email
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
