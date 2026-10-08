<x-layouts.app title="Confirm the Code of Conduct">
    <div class="container mx-auto px-4 py-12">
        <div class="max-w-3xl mx-auto">
            <div class="bg-white shadow-md rounded-lg overflow-hidden" x-data="consentConfirmFlow()">
                <div class="px-8 py-6 border-b border-gray-200 bg-gray-50">
                    <h1 class="text-2xl font-bold text-gray-900">Code of Conduct and consent</h1>
                </div>

                <div class="px-8 py-6">
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

                        <div
                            id="coc-scroll-container"
                            class="border border-gray-200 rounded-md bg-gray-50 px-2 sm:px-4 py-3 max-h-72 overflow-y-auto focus:outline-none focus:ring-2 focus:ring-blue-500"
                            tabindex="0"
                            role="region"
                            aria-label="Code of Conduct"
                            @scroll="onScroll($event)"
                        >
                            @include('policies.code-of-conduct')
                        </div>

                        <p class="mt-2 text-xs text-gray-500"
                           x-show="!hasScrolledToBottom"
                           x-cloak>
                            Scroll to the bottom of the Code of Conduct to enable the confirmation checkboxes.
                        </p>

                        @include('policies.code-of-conduct-commitments')

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

    <script>
        function consentConfirmFlow() {
            return {
                hasScrolledToBottom: {{ old('coc_commitment_1') || old('coc_commitment_2') || old('coc_commitment_3') || old('coc_commitment_4') ? 'true' : 'false' }},
                commitment1: {{ old('coc_commitment_1') ? 'true' : 'false' }},
                commitment2: {{ old('coc_commitment_2') ? 'true' : 'false' }},
                commitment3: {{ old('coc_commitment_3') ? 'true' : 'false' }},
                commitment4: {{ old('coc_commitment_4') ? 'true' : 'false' }},

                onScroll(event) {
                    const target = event.target;
                    if (target.scrollHeight - target.scrollTop - target.clientHeight <= 4) {
                        this.hasScrolledToBottom = true;
                    }
                },

                get canSubmit() {
                    return this.hasScrolledToBottom &&
                        this.commitment1 && this.commitment2 &&
                        this.commitment3 && this.commitment4;
                }
            }
        }
    </script>
</x-layouts.app>
