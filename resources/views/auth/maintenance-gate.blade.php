<x-layouts.app title="Temporarily Unavailable">
    <div class="min-h-screen flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-lg">

            {{-- Icon + heading --}}
            <div class="text-center mb-8">
                <i class="fas fa-tools text-6xl text-gray-300 mb-4"></i>
                <h1 class="text-2xl font-bold text-gray-800">Temporarily unavailable</h1>
            </div>

            {{-- Body card --}}
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <p class="text-gray-700 leading-relaxed mb-3">
                    The system is temporarily unavailable for scheduled maintenance. Please try again later.
                </p>
                <p class="text-gray-700 leading-relaxed">
                    We apologise for the inconvenience.
                </p>
            </div>

            {{-- Button row --}}
            <div class="text-center">
                <a href="{{ route('welcome') }}"
                   class="inline-flex items-center px-5 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Home
                </a>
            </div>

        </div>
    </div>
</x-layouts.app>
