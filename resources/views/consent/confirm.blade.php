<x-layouts.app title="Confirm the Code of Conduct">
    <div class="container mx-auto px-4 py-12">
        <div class="max-w-3xl mx-auto">
            <div class="bg-white shadow-md rounded-lg overflow-hidden" x-data="cocReader()">
                <div class="px-4 sm:px-8 py-6 border-b border-gray-200 bg-gray-50">
                    <h1 class="text-2xl font-bold text-gray-900">Code of Conduct and consent</h1>
                </div>

                <div class="px-4 sm:px-8 py-6">
                    <p class="text-gray-700 mb-6">
                        Welcome to the new Nigerian Red Cross membership database. Before you continue, please read
                        the Code of Conduct and confirm the statements below. You only need to do this once.
                    </p>

                    @if ($errors->any())
                        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700">
                            Please tick all four boxes to continue.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('consent.confirm.store') }}">
                        @csrf

                        @include('policies.code-of-conduct-reader')

                        <p class="mt-4 text-sm">
                            <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener" class="text-blue-700 underline hover:text-blue-900">
                                Read our Privacy Policy
                            </a>
                        </p>

                        <div class="flex justify-end pt-6 mt-6 border-t border-gray-200">
                            <button type="submit"
                                    class="px-6 py-2.5 text-white font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200
                                           bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:text-gray-600 disabled:cursor-not-allowed"
                                    :disabled="!canSubmit">
                                Confirm and Continue
                            </button>
                        </div>
                    </form>

                    <div class="mt-6 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <p class="text-sm text-gray-700">
                            If you do not agree, please log out and contact your branch.
                        </p>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 rounded-md border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2"></i>Log out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
