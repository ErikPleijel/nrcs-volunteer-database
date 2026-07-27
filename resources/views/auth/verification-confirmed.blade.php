<x-layouts.app title="Email Verified">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-md mx-auto">
            <div class="bg-white shadow-md rounded-lg p-8">
                <h1 class="text-2xl font-semibold text-gray-800 mb-4">Email Verified!</h1>

                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                    <p class="text-green-800">Your email address has been verified. You now have full access to your account.</p>
                </div>

                <div class="text-center">
                    <a href="{{ route('profile.show') }}" class="inline-block bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200">
                        Continue to Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
