<x-layouts.app title="Account Deactivated">
    <div class="min-h-screen flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-lg">

            {{-- Icon + heading --}}
            <div class="text-center mb-8">
                <i class="fas fa-user-clock text-6xl text-gray-300 mb-4"></i>
                <h1 class="text-2xl font-bold text-gray-800">Your account has been deactivated</h1>
            </div>

            {{-- DB reference --}}
            @if(!empty($dbReference))
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 text-center">
                    <p class="text-xs uppercase tracking-wide text-red-700 font-semibold mb-1">Your reference number</p>
                    <p class="text-2xl font-extrabold text-gray-900 tracking-wider">{{ $dbReference }}</p>
                    <p class="text-xs text-gray-500 mt-1">Please quote this when you contact your branch.</p>
                </div>
            @endif

            {{-- Body card --}}
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                @if($selfInitiated ?? false)
                    <p class="text-gray-700 leading-relaxed mb-3">
                        You archived your own account, as requested. Contact your branch below if you'd like to be reactivated.
                    </p>
                @else
                    <p class="text-gray-700 leading-relaxed mb-3">
                        It looks like your Red Cross account has been inactive for some time and has been deactivated.
                    </p>
                @endif
                <p class="text-gray-700 leading-relaxed mb-3">
                    If you'd like to continue your involvement with the Nigerian Red Cross Society — whether as a member,
                    volunteer, or donor — we'd love to have you back.
                </p>
                <p class="text-gray-700 leading-relaxed font-medium">
                    Please contact your local branch to reactivate your account.
                </p>
            </div>

            {{-- Rejoin-email card --}}
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-lg font-bold text-gray-900 mb-3 flex items-center">
                    <i class="fas fa-paper-plane mr-2 text-red-600"></i>Email your branch to rejoin
                </h2>
                <p class="text-sm text-gray-600 mb-3">
                    Use this ready-made message — copy it, or open it in your email app — and send it to your branch at
                    <strong>@if($branch->email)  {{ $branch->email }}@endif</strong>.
                </p>
                <pre id="rejoin-message" class="whitespace-pre-wrap text-sm text-gray-800 bg-gray-50 border border-gray-200 rounded-md p-3 mb-4 font-sans">{{ $rejoinBody }}</pre>
                <div class="flex flex-wrap gap-3">
                    <button type="button" id="copy-rejoin-btn"
                            class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium bg-red-600 text-white hover:bg-red-700">
                        <i class="fas fa-copy mr-2"></i><span id="copy-rejoin-label">Copy message</span>
                    </button>
                    @if($branch?->email)
                        <a href="mailto:{{ $branch->email }}?subject={{ rawurlencode($rejoinSubject) }}&body={{ rawurlencode($rejoinBody) }}"
                           class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">
                            <i class="fas fa-envelope mr-2"></i>Open in email app
                        </a>
                    @endif
                </div>
            </div>

            {{-- Branch contact block --}}
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                @if($branch)
                    <h2 class="text-lg font-bold text-gray-900 mb-4">
                        {{ $branch->name }} Branch
                    </h2>

                    <div class="space-y-2 text-sm text-gray-700 mb-4">
                        @if($branch->email)
                            <div class="flex items-center gap-2">
                                <i class="fas fa-envelope text-gray-400 text-xs w-4"></i>
                                <span>{{ $branch->email }}</span>
                            </div>
                        @endif
                        @if($branch->telephone)
                            <div class="flex items-center gap-2">
                                <i class="fas fa-phone text-gray-400 text-xs w-4"></i>
                                <span>{{ $branch->telephone }}</span>
                            </div>
                        @endif
                        @if($branch->physical_address)
                            <div class="flex items-center gap-2">
                                <i class="fas fa-map-marker-alt text-gray-400 text-xs w-4"></i>
                                <span>{{ $branch->physical_address }}</span>
                            </div>
                        @endif
                    </div>

                    @php $branchContacts = $branch->publicContacts(); @endphp

                    @if(!empty($branchContacts))
                        <div class="border-t border-gray-200 pt-4">
                            <h3 class="text-sm font-semibold text-gray-900 mb-3 flex items-center">
                                <i class="fas fa-users mr-2 text-red-600"></i>
                                Contact Persons
                            </h3>

                            <div class="space-y-3 text-sm">
                                @foreach($branchContacts as $contact)
                                    @php $contactUser = $contact['user']; @endphp
                                    <div class="flex items-start gap-3">
                                        {{-- Info --}}
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="font-semibold text-gray-900">
                                                    {{ $contactUser->full_name ?? 'Unnamed contact' }}
                                                </span>
                                                @if(!empty($contact['position']))
                                                    <span class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-800">
                                                        {{ $contact['position'] }}
                                                    </span>
                                                @endif
                                            </div>

                                            <div class="mt-1 space-y-0.5 text-gray-700">
                                                @if($contactUser->email)
                                                    <div class="flex items-center gap-2">
                                                        <i class="fas fa-envelope text-gray-400 text-xs"></i>
                                                        <span class="break-all">{{ $contactUser->email }}</span>
                                                    </div>
                                                @endif
                                                @if($contactUser->telephone1 || $contactUser->telephone2)
                                                    <div class="flex items-center gap-2">
                                                        <i class="fas fa-phone text-gray-400 text-xs"></i>
                                                        <span>{{ $contactUser->telephone1 ?? $contactUser->telephone2 }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @else
                    <p class="text-gray-600 text-sm">
                        Please contact your nearest Nigerian Red Cross branch for assistance.
                    </p>
                @endif
            </div>

            {{-- Button row --}}
            <div class="text-center">
                <a href="{{ route('login') }}"
                   class="inline-flex items-center px-5 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Login
                </a>
            </div>

        </div>
    </div>

    @push('scripts')
        <script>
            document.getElementById('copy-rejoin-btn')?.addEventListener('click', function () {
                const text = document.getElementById('rejoin-message').innerText;
                const done = () => {
                    const label = document.getElementById('copy-rejoin-label');
                    const prev = label.textContent;
                    label.textContent = 'Copied!';
                    setTimeout(() => { label.textContent = prev; }, 2000);
                };
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(done).catch(() => {});
                }
            });
        </script>
    @endpush
</x-layouts.app>
