<x-layouts.app>
    <div class="container mx-auto px-4 py-12">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white shadow-md rounded-lg overflow-hidden">

                <div class="px-8 py-6 border-b border-gray-200 bg-gray-50">
                    <h1 class="text-2xl font-bold text-gray-900">Data Handling Commitment</h1>
                </div>

                <div class="px-8 py-6">
                    <p class="text-gray-700 mb-6">
                        Before you continue, please read and confirm the following commitment.
                        This is required for all staff and administrators of the Nigerian Red Cross
                        Society information system.
                    </p>

                    <div class="border border-gray-300 rounded-md bg-gray-50 px-6 py-5 mb-6">
                        <ul class="space-y-3 text-sm text-gray-800">
                            <li class="flex items-start gap-2">
                                <span class="mt-0.5 text-gray-500 shrink-0">•</span>
                                <span>I will only access personal data that is necessary for my role.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="mt-0.5 text-gray-500 shrink-0">•</span>
                                <span>I will not share, export, or disclose volunteer or member data outside authorised NRCS channels.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="mt-0.5 text-gray-500 shrink-0">•</span>
                                <span>I understand that all access to sensitive data is logged and may be audited.</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="mt-0.5 text-gray-500 shrink-0">•</span>
                                <span>I am aware that the Nigerian Red Cross Society processes personal data under the Nigeria Data Protection Act 2023, and I will handle that data accordingly.</span>
                            </li>
                        </ul>
                    </div>

                    <form method="POST" action="{{ route('policy.accept.store') }}">
                        @csrf

                        @if ($errors->any())
                            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        <div class="flex items-start mb-6">
                            <div class="flex items-center h-5">
                                <input
                                    id="policy_accepted"
                                    name="policy_accepted"
                                    type="checkbox"
                                    value="1"
                                    class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                    required
                                >
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="policy_accepted" class="font-medium text-gray-800 cursor-pointer">
                                    I have read and agree to the above commitment.
                                </label>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                    class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200">
                                Confirm and Continue
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</x-layouts.app>
