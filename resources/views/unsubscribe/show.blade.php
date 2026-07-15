<x-layouts.app title="Communication Preferences">

    <div class="min-h-screen flex items-start justify-center pt-16 pb-12 px-4">
        <div class="w-full max-w-md">

            {{-- Logo --}}
            <div class="flex justify-center mb-8">
                <img src="{{ asset('images/NRCS_logo.jpg') }}" alt="Nigerian Red Cross Society" class="h-20 w-auto">
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-8">

                <h1 class="text-xl font-semibold text-gray-800 text-center mb-6">Communication Preferences</h1>

                @if(!$user)
                    <p class="text-center text-gray-500 text-sm">
                        This unsubscribe link is invalid or has expired.
                    </p>

                @else

                    {{-- Flash success --}}
                    @if(session('success'))
                        <div class="mb-5 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                            {{ session('success') }}
                        </div>
                    @endif

                    <p class="text-sm text-gray-600 text-center mb-6">
                        You are managing communication preferences for your account.
                    </p>

                    {{-- Current status --}}
                    <div class="flex justify-center mb-6">
                        @if($optedOut)
                            <span class="inline-flex items-center gap-2 rounded-full bg-gray-100 text-gray-500 text-xs font-medium px-4 py-2">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                                </svg>
                                You are currently unsubscribed from {{ ucfirst($channel) }} campaigns.
                            </span>
                        @else
                            <span class="inline-flex items-center gap-2 rounded-full bg-green-50 text-green-700 text-xs font-medium px-4 py-2">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                </svg>
                                You are currently subscribed to {{ ucfirst($channel) }} campaigns.
                            </span>
                        @endif
                    </div>

                    {{-- Action button --}}
                    <div class="flex justify-center mb-6">
                        @if($optedOut)
                            <form method="POST" action="{{ $channel === 'email' ? route('unsubscribe.email.handle', $token) : route('unsubscribe.sms.handle', $token) }}">
                                @csrf
                                <input type="hidden" name="action" value="resubscribe">
                                <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-lg bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-5 py-2.5 transition-colors">
                                    Re-subscribe to {{ ucfirst($channel) }} campaigns
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ $channel === 'email' ? route('unsubscribe.email.handle', $token) : route('unsubscribe.sms.handle', $token) }}">
                                @csrf
                                <input type="hidden" name="action" value="optout">
                                <button type="submit"
                                    class="inline-flex items-center gap-2 rounded-lg bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-5 py-2.5 transition-colors">
                                    Unsubscribe from {{ ucfirst($channel) }} campaigns
                                </button>
                            </form>
                        @endif
                    </div>

                    <p class="text-xs text-gray-400 text-center leading-relaxed">
                        You can change this preference at any time by returning to this link, or by updating your preferences in My Profile.
                    </p>

                @endif

            </div>
        </div>
    </div>

</x-layouts.app>
