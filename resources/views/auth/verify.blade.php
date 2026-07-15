<x-layouts.app>
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-md mx-auto">
            <div class="bg-white shadow-md rounded-lg p-8">
                <h1 class="text-2xl font-semibold text-gray-800 mb-4">Verify Your Email Address</h1>

                @if (session('resent'))
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                        <p class="text-green-800">A fresh verification link has been sent to your email address.</p>
                    </div>
                @endif

                <p class="text-gray-600 mb-6">
                    Before proceeding, please check your email for a verification link.
                    If you did not receive the email, click the button below to request another.
                </p>

                <form method="POST" action="{{ route('verification.resend') }}">
                    @csrf
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-200">
                        Resend Verification Email
                    </button>
                </form>

                <div class="mt-4 text-center">
                    <a href="{{ route('login') }}" class="text-blue-600 hover:text-blue-800 text-sm">
                        Back to Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
