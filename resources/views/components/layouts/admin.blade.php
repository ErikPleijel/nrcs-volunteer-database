@props(['title' => config('app.name', 'Laravel')])

    <!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }} - Admin</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        .watermark-pattern {
            position: fixed;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background-repeat: repeat;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='220' height='220'%3E%3Ctext x='0' y='110' transform='rotate(-30 160 100)' font-family='Figtree, Arial, sans-serif' font-size='36' font-weight='600' fill='%239ca3af' fill-opacity='0.1'%3ENRCS ADMIN%3C/text%3E%3C/svg%3E");
        }
    </style>

    {{ $styles ?? '' }}
</head>
<body class="font-sans antialiased bg-gray-200">
<div class="watermark-pattern" aria-hidden="true"></div>
<div class="flex flex-col min-h-screen relative z-10">
    @include('components.header')

    <div class="flex flex-1 gap-4">
        <!-- Admin Sidebar (includes both main navigation and admin navigation on mobile) -->
        <div id="sidebar" class="bg-white w-64 shadow-lg sidebar-transition transform -translate-x-full lg:translate-x-0 fixed lg:static z-30 h-full lg:h-auto overflow-y-auto">
            <!-- Main Navigation (mobile only) -->
            <div class="lg:hidden border-b border-gray-200 px-6 py-4">
                <div class="space-y-2">
                    @foreach($navItems as $item)
                        @continue($item['label'] === 'Admin')
                        @if($item['allowed'])
                            <a href="{{ url($item['url']) }}" class="block py-2 text-sm font-medium text-gray-700 hover:text-red-600 {{ request()->is($item['pattern']) ? 'text-red-600' : '' }}">
                                {{ $item['label'] }}
                            </a>
                        @endif
                    @endforeach
                </div>

                <!-- Mobile Login/Register buttons -->
                @guest
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <div class="space-y-2">

                            <a href="{{ url('/login') }}"
                               class="block w-full text-center rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700">
                                Login
                            </a>
                            <a href="{{ url('/register') }}"
                               class="block w-full text-center rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-red-600 transition hover:bg-gray-200">
                                Register.
                            </a>

                    </div>
                </div>
                @endguest
            </div>

            <!-- Admin Navigation -->
            <nav class="mt-6 pb-6">
                <div class="px-6 py-2">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Main Menu</p>
                </div>
                <ul class="mt-2">
                    {{-- Dashboard --}}
                    <li>
                        <a href="{{ route('admin.dashboard') }}"
                           class="flex items-center px-6 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors {{ request()->routeIs('admin.dashboard') ? 'bg-red-50 text-red-600 border-r-4 border-red-600' : '' }}">
                            <i class="fas fa-tachometer-alt mr-3"></i>Dashboard
                        </a>
                    </li>

                    {{-- Persons --}}
                    @can('view_user')
                        <li>
                            <a href="{{ route('users.index') }}"
                               class="flex items-center px-6 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors {{ request()->routeIs('users.*') ? 'bg-red-50 text-red-600 border-r-4 border-red-600' : '' }}">
                                <i class="fas fa-user mr-3"></i>Persons
                            </a>
                        </li>
                    @endcan



                    {{-- Red Cross Units (Admin list) --}}
                    @can('view_user')
                        <li>
                            <a href="{{ route('red-cross-units.index') }}"
                               class="flex items-center px-6 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors {{ request()->routeIs('red-cross-units.*') ? 'bg-red-50 text-red-600 border-r-4 border-red-600' : '' }}">
                                <i class="fas fa-shield-alt mr-3"></i>Red Cross Units
                            </a>
                        </li>
                    @endcan

                    {{-- Task Forces (Admin list) --}}
                    @can('view_task_force')
                        <li>
                            <a href="{{ route('task-forces.index') }}"
                               class="flex items-center px-6 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors {{ request()->routeIs('task-forces.*') ? 'bg-red-50 text-red-600 border-r-4 border-red-600' : '' }}">
                                <i class="fas fa-users-gear mr-3"></i>Task Forces
                            </a>
                        </li>
                    @endcan

                    {{-- Organisations --}}
                    @can('view_organisation')
                        <li>
                            <a href="{{ route('organisations.index') }}"
                               class="flex items-center px-6 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors {{ request()->routeIs('organisations.*') ? 'bg-red-50 text-red-600 border-r-4 border-red-600' : '' }}">
                                <i class="fas fa-industry mr-3"></i>Organisations
                            </a>
                        </li>
                    @endcan

                    {{-- Branches --}}
                    @can('view_branch_information')
                        <li>
                            <a href="{{ route('branches.index') }}"
                               class="flex items-center px-6 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors {{ request()->routeIs('branches.*') ? 'bg-red-50 text-red-600 border-r-4 border-red-600' : '' }}">
                                <i class="fas fa-sitemap mr-3"></i>Branches
                            </a>
                        </li>
                    @endcan

                    {{-- Divisions --}}
                    @can('view_division_information')
                        <li>
                            <a href="{{ route('divisions.index') }}"
                               class="flex items-center px-6 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors {{ request()->routeIs('divisions.*') ? 'bg-red-50 text-red-600 border-r-4 border-red-600' : '' }}">
                                <i class="fas fa-layer-group mr-3"></i>Divisions
                            </a>
                        </li>
                    @endcan
                </ul>

                <div class="px-6 py-2 mt-6">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Management</p>
                </div>
                <ul class="mt-2">
                    {{-- Membership / Members (payments) --}}
                    @can('view_payments')
                        <li>
                            <a href="{{ route('membership-payments.index') }}"
                               class="flex items-center px-6 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors {{ request()->routeIs('membership-payments.*') ? 'bg-red-50 text-red-600 border-r-4 border-red-600' : '' }}">
                                <i class="fas fa-hand-holding-dollar mr-3"></i>Payment Records
                            </a>
                        </li>
                    @endcan

                    {{-- Activities / Volunteering --}}
                    @can('view_volunteering')
                        <li>
                            <a href="{{ route('activities.index') }}"
                               class="flex items-center px-6 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors {{ request()->routeIs('activities.*') ? 'bg-red-50 text-red-600 border-r-4 border-red-600' : '' }}">
                                <i class="fas fa-hands-helping mr-3"></i>Volunteering Log
                            </a>
                        </li>
                    @endcan

                    {{-- Trainings --}}
                    @can('view_trainings')
                        <li>
                            <a href="{{ route('trainings.index') }}"
                               class="flex items-center px-6 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors {{ request()->routeIs('trainings.*') ? 'bg-red-50 text-red-600 border-r-4 border-red-600' : '' }}">
                                <i class="fas fa-graduation-cap mr-3"></i>Training Records
                            </a>
                        </li>
                    @endcan

                    {{-- Donations --}}
                    @can('view_donations')
                        <li>
                            <a href="{{ route('donations.index') }}"
                               class="flex items-center px-6 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors {{ request()->routeIs('donations.*') ? 'bg-red-50 text-red-600 border-r-4 border-red-600' : '' }}">
                                <i class="fas fa-heart mr-3"></i>Donation Records
                            </a>
                        </li>
                    @endcan

                    {{-- Bulk Email / SMS --}}
                    @can('campaign_request_approve')
                        <li>
                            <a href="{{ route('campaigns.admin.proposed') }}"
                               class="flex items-center px-6 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors
                                  {{ request()->routeIs('campaigns.admin.*') ? 'bg-red-50 text-red-600 border-r-4 border-red-600' : '' }}">
                                <i class="fas fa-sliders mr-3"></i>
                                Campaign Management
                            </a>
                        </li>

                    @endcan

                    @can('campaign_request_create')
                    @php
                        $campaignsLabel = auth()->user()->getAccessLevel() === 'national'
                            ? 'NAT Campaigns'
                            : (\App\Models\Branch::find(auth()->user()->getScopedBranchId())?->code ?? 'Branch') . ' Campaigns';
                    @endphp
                    <li>
                        <a href="{{ route('campaigns.mine') }}"
                           class="flex items-center px-6 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors
                          {{ request()->routeIs('campaigns.mine') ? 'bg-red-50 text-red-600 border-r-4 border-red-600' : '' }}">
                            <i class="fas fa-bullhorn mr-3"></i>{{ $campaignsLabel }}
                        </a>
                    </li>
                    @endcan

                    {{-- ID Cards --}}
                    @can('view_idcards')
                        <li>
                            <a href="{{ route('id-cards.prepare-bulk-print') }}"
                               class="flex items-center px-6 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors {{ request()->routeIs('id-cards.prepare-bulk-print') ? 'bg-red-50 text-red-600 border-r-4 border-red-600' : '' }}">
                                <i class="fas fa-id-card mr-3"></i>ID Cards
                            </a>
                        </li>
                    @endcan

                    {{-- Certificates --}}
                    @can('view_certificates')
                    <li>
                        <a href="{{ route('certificates.index') }}"
                           class="flex items-center px-6 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors {{ request()->routeIs('certificates.*') ? 'bg-red-50 text-red-600 border-r-4 border-red-600' : '' }}">
                            <i class="fas fa-certificate mr-3"></i>Certificates
                        </a>
                    </li>
                    @endcan

                    {{-- Dormant Users / Archive Tool --}}
                    @can('use_archive_tool')
                        <li>
                            <a href="{{ route('dormant-users.index') }}"
                               class="flex items-center px-6 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors {{ request()->routeIs('dormant-users.*') ? 'bg-red-50 text-red-600 border-r-4 border-red-600' : '' }}">
                                <i class="fas fa-archive mr-3"></i>Archive Tool
                            </a>
                        </li>
                    @endcan

                    {{-- Authorizations / Roles & Permissions --}}
                    @can('manage_roles_and_permissions')
                        <li>
                            <a href="{{ route('users.roles.edit') }}"
                               class="flex items-center px-6 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors {{ request()->routeIs('users.roles.edit') ? 'bg-red-50 text-red-600 border-r-4 border-red-600' : '' }}">
                                <i class="fas fa-key mr-3"></i>Authorizations
                            </a>
                        </li>
                    @endcan



                    {{-- Settings --}}
                    @can('change_settings')
                        <li>
                            <a href="{{ route('admin.settings.index') }}"
                               class="flex items-center px-6 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors {{ request()->routeIs('admin.settings.*') ? 'bg-red-50 text-red-600 border-r-4 border-red-600' : '' }}">
                                <i class="fa-solid fa-cog mr-3"></i>Settings
                            </a>
                        </li>
                    @endcan



                    {{-- Log --}}
                    @can('view_log')
                        <li>
                            <a href="{{ route('logs.index') }}"
                               class="flex items-center px-6 py-3 text-gray-700 hover:bg-red-50 hover:text-red-600 transition-colors {{ request()->routeIs('logs.*') ? 'bg-red-50 text-red-600 border-r-4 border-red-600' : '' }}">
                                <i class="fa-solid fa-clipboard-list mr-3"></i>Log
                            </a>
                        </li>
                    @endcan

                    <br><br>


                </ul>
            </nav>
        </div>

        <!-- Main Content Area -->
        <main class="flex-1 lg:ml-0 min-w-0 overflow-x-hidden">
            <div class="flex flex-col mb-6">
                @if(isset($pageHeader))
                    <div class="bg-blue-50 border border-gray-400 mx-auto mt-2 rounded-md">
                        <div class="container mx-auto px-4 pt-6 text-center">
                            <h1 class="text-2xl font-bold text-gray-800">{{ $pageHeader }}</h1>
                        </div>
                        @isset($subHeader)
                            <p class="my-2 mx-3 px-6 text-base text-gray-600 text-center">
                                {{ $subHeader }}
                            </p>
                        @endisset
                        @isset($backLink)
                            <p class="my-3 text-center mx-3">
                                {{ $backLink }}
                            </p>
                        @endisset
                    </div>
                @endif

                <div class="flex justify-center items-center mt-4 space-x-4">
                    @isset($button1)
                        {{ $button1 }}
                    @endisset
                    @isset($button2)
                        {{ $button2 }}
                    @endisset
                </div>
            </div>

            {{ $slot }}
        </main>
    </div>

    @include('components.footer')
</div>

{{ $scripts ?? '' }}

<script>
    // Updated toggleMobileMenu to handle admin sidebar
    function toggleMobileMenu() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('-translate-x-full');
    }

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('-translate-x-full');
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const mobileMenuButton = document.getElementById('mobile-menu-button');

        if (window.innerWidth < 1024 && !sidebar.contains(event.target) && !mobileMenuButton.contains(event.target)) {
            sidebar.classList.add('-translate-x-full');
        }
    });
</script>
@stack('scripts')

</body>
</html>
