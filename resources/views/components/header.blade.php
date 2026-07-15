<style>[x-cloak]{ display:none !important; }</style>
<header class="bg-white shadow-sm">
    <div class="mx-auto flex h-16 max-w-screen-xl items-center gap-8 px-4 sm:px-6 lg:px-8">
        <!-- Mobile menu button - moved to left side -->
        <button
            class="block rounded-sm bg-gray-100 p-2.5 text-gray-600 transition hover:text-gray-600/75 md:hidden"
            id="mobile-menu-button"
            onclick="toggleMobileMenu()"
            aria-expanded="false"
            aria-controls="mobile-menu"
        >
            <span class="sr-only">Toggle menu</span>
            <!-- Hamburger icon -->
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="size-5 block"
                id="menu-icon"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            <!-- Close icon (hidden by default) -->
            <svg
                xmlns="http://www.w3.org/2000/svg"
                class="size-5 hidden"
                id="close-icon"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <a class="flex items-center gap-3 text-red-600" href="{{ url('/') }}">
            <span class="sr-only">Home</span>
            <img src="{{ asset('images/NRCS_logo.jpg') }}" alt="Nigeria Red Cross Society" class="h-12">
            <div class="text-sm font-black leading-tight sm:text-lg">
                Volunteer Management System
            </div>
        </a>

        <div class="flex flex-1 items-center justify-end md:justify-between">
            @include('components.navigation')

            <div class="flex items-center gap-4">
                <!-- Compact auth link (below sm only; complements the hidden sm:flex section below) -->
                @auth
                    <div class="sm:hidden flex flex-col items-end gap-2">
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-sm font-medium text-gray-600 hover:text-gray-600/75 whitespace-nowrap">
                                Logout
                            </button>
                        </form>
                        @if(auth()->user()->getRoleNames()->isNotEmpty())
                            <a href="{{ route('admin.dashboard') }}"
                               class="text-sm font-medium text-gray-600 hover:text-gray-600/75 whitespace-nowrap">
                                Admin
                            </a>
                        @endif
                    </div>
                @else
                    <a href="{{ route('login') }}"
                       class="sm:hidden text-sm font-medium text-red-600 hover:text-red-600/75 whitespace-nowrap">
                        Login
                    </a>
                @endauth

                <!-- Authentication Section -->
                @auth
                    @php
                        $notifUser   = auth()->user();
                        $unreadCount = $notifUser->unreadNotifications()->count();
                        $recentNotifs = $notifUser->notifications()->latest()->limit(8)->get();
                    @endphp

                    {{-- Notifications bell --}}
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = ! open"
                                class="relative p-2 text-gray-600 hover:text-red-600 focus:outline-none" aria-label="Notifications">
                            <i class="fas fa-bell text-lg"></i>
                            @if($unreadCount > 0)
                                <span class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold leading-none text-white bg-red-600 rounded-full">
                                    {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                                </span>
                            @endif
                        </button>

                        <div x-show="open" x-cloak @click.outside="open = false" x-transition
                             class="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 z-50">
                            <div class="flex items-center justify-between px-4 py-2 border-b border-gray-100">
                                <span class="text-sm font-semibold text-gray-700">Notifications</span>
                                @if($unreadCount > 0)
                                    <form method="POST" action="{{ route('notifications.read-all') }}">
                                        @csrf
                                        <button type="submit" class="text-xs text-blue-600 hover:text-blue-800">Mark all read</button>
                                    </form>
                                @endif
                            </div>
                            <div class="max-h-96 overflow-y-auto divide-y divide-gray-100">
                                @forelse($recentNotifs as $n)
                                    @php
                                        $d            = $n->data;
                                        $isRej        = ($d['type'] ?? null) === 'record_rejected';
                                        $isCamp       = ($d['type'] ?? null) === 'campaign_decided';
                                        $campApproved = $isCamp && ($d['decision'] ?? null) === 'approved';
                                        $campRejected = $isCamp && ($d['decision'] ?? null) === 'rejected';
                                    @endphp
                                    <a href="{{ route('notifications.read', $n->id) }}"
                                       class="block px-4 py-3 hover:bg-gray-50 {{ $n->read_at ? '' : 'bg-blue-50' }}">
                                        <div class="flex items-start gap-2">
                                            <i class="fas {{ $isRej || $campRejected ? 'fa-circle-xmark text-red-400' : ($campApproved ? 'fa-circle-check text-green-400' : 'fa-circle-info text-gray-400') }} mt-0.5"></i>
                                            <div class="min-w-0 flex-1">
                                                <div class="text-sm text-gray-800">
                                                    @if($isRej)
                                                        Your {{ $d['module'] ?? 'record' }} #{{ $d['record_id'] ?? '' }} was rejected
                                                    @elseif($campApproved)
                                                        <span class="text-green-700">Campaign approved: {{ \Illuminate\Support\Str::limit($d['campaign_title'] ?? ('Campaign #' . ($d['campaign_id'] ?? '')), 40) }}</span>
                                                    @elseif($campRejected)
                                                        <span class="text-red-700">Campaign rejected: {{ \Illuminate\Support\Str::limit($d['campaign_title'] ?? ('Campaign #' . ($d['campaign_id'] ?? '')), 40) }}</span>
                                                    @else
                                                        {{ $d['message'] ?? 'Notification' }}
                                                    @endif
                                                </div>
                                                @if($isRej && ! empty($d['reason']))
                                                    <div class="text-xs text-gray-500 truncate">Reason: {{ \Illuminate\Support\Str::limit($d['reason'], 60) }}</div>
                                                @endif
                                                @if($campRejected && ! empty($d['reason']))
                                                    <div class="text-xs text-gray-500 truncate">Reason: {{ \Illuminate\Support\Str::limit($d['reason'], 60) }}</div>
                                                @endif
                                                <div class="text-[11px] text-gray-400 mt-0.5">{{ $n->created_at->diffForHumans() }}</div>
                                            </div>
                                            @if(! $n->read_at)
                                                <span class="mt-1 h-2 w-2 rounded-full bg-blue-500 flex-shrink-0"></span>
                                            @endif
                                        </div>
                                    </a>
                                @empty
                                    <div class="px-4 py-6 text-center text-sm text-gray-400">No notifications</div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <!-- Logged in user section -->
                    <div class="hidden sm:flex sm:items-center sm:gap-4">
                        <span class="text-sm text-gray-700">
                            Welcome<br>{{ auth()->user()->full_name }}
                        </span>

                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button
                                type="submit"
                                class="block rounded-md bg-red-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-red-700 ml-2"
                            >
                                Logout
                            </button>
                        </form>
                    </div>

                @else
                    <!-- Guest user section -->
                    <div class="hidden sm:flex sm:gap-4">
                        <a
                            class="block rounded-md bg-red-600 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-red-700"
                            href="{{ route('login') }}"
                        >
                            Login
                        </a>

                        <a
                            class="rounded-md bg-gray-100 px-5 py-2.5 text-sm font-medium text-red-600 transition hover:text-red-600/75"
                            href="{{ route('register') }}"
                        >
                            Register
                        </a>
                    </div>
                @endauth
            </div>
        </div>
    </div>

    @include('components.mobile-navigation')

    <!-- Thin horizontal line with shadow beneath -->
    <div class="relative">
        <div class="h-px bg-gray-900"></div>
        <div class="absolute top-px left-0 right-0 h-2 bg-gradient-to-b from-black/20 to-transparent"></div>
    </div>
</header>
