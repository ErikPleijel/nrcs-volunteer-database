<x-layouts.app title="Verify Your Email">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-md mx-auto">
            <div class="bg-white shadow-md rounded-lg p-8">
                <h1 class="text-2xl font-semibold text-gray-800 mb-4">Please Verify Your Email</h1>

                @if (session('message'))
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                        <p class="text-green-800">{{ session('message') }}</p>
                    </div>
                @endif

                <p class="text-gray-600 mb-2">
                    Please verify your email to continue. We sent a verification link to:
                </p>
                <p class="text-base font-semibold text-gray-900 mb-6">{{ auth()->user()->email }}</p>

                <p class="text-gray-600 mb-6">
                    Click the link in that email to activate your account. If you did not receive it,
                    you can request a new one below — don't forget to check your spam or junk folder.
                </p>

                <p class="text-gray-600 mb-6">
                    Your reference code: <span class="font-semibold text-gray-900">{{ auth()->user()->getUserIdReferenceShortAttribute() }}</span> — have this ready if you contact your branch for help.
                </p>

                @if (auth()->user()->branch)
                    <p class="text-gray-600 mb-6">
                        @if (auth()->user()->branch->telephone)
                            If you entered the wrong email address, contact your branch to have it corrected: {{ auth()->user()->branch->name }}, {{ auth()->user()->branch->telephone }}.
                        @else
                            If you entered the wrong email address, contact your {{ auth()->user()->branch->name }} branch for help.
                        @endif
                    </p>
                @endif

                <form method="POST" action="{{ route('verification.resend') }}">
                    @csrf
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700 transition duration-200">
                        Resend Verification Email
                    </button>
                </form>

                <form method="POST" action="{{ route('logout') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="w-full bg-gray-100 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-200 transition duration-200">
                        Logout and try a different account
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
