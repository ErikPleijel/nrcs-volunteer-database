@props([
    'title' => config('app.name', 'Laravel'),
    'description' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title }}</title>

    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description ?? \App\Models\Setting::get('social.share_description', 'Join the Red Cross today!') }}">
    <meta property="og:image" content="{{ asset('images/NRCS_logo.jpg') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css">

    <script src="//unpkg.com/alpinejs" defer></script>
    <!-- LAYOUT VITE START -->
    @vite(['resources/css/app.css','resources/js/app.js'])
    <!-- LAYOUT VITE END -->


    <style>
        /* Repeating watermark background */
        .watermark-pattern {
            background-image: url('{{ asset('images/NRCS_logo.jpg') }}');
            background-repeat: repeat;
            background-size: 500px 550px;
            background-position: 0 0;
            opacity: 0.03;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 1;
            pointer-events: none;
        }

        /* Mobile responsive watermark */
        @media (max-width: 768px) {
            .watermark-pattern {
                background-size: 100vw auto; /* 100% of viewport width, auto height */
            }
        }

        /* Extra small mobile devices */
        @media (max-width: 480px) {
            .watermark-pattern {
                background-size: 100vw auto;
            }
        }

        /* Make backgrounds semi-transparent so watermark shows through */
        .watermark-transparent {
            background-color: rgba(255, 255, 255, 0.95) !important;
        }

        /* Override any solid white backgrounds */
        .bg-white {
            background-color: rgba(255, 255, 255, 0.95) !important;
        }

        /* Make all backgrounds transparent for watermark visibility */
        .watermark-bg-white {
            background-color: rgba(255, 255, 255, 0.05) !important;
        }

        .watermark-bg-gray-50 {
            background-color: rgba(249, 250, 251, 0.95) !important;
        }
    </style>

    {{ $styles ?? '' }}
    @stack('styles')
</head>
<body class="font-sans antialiased">

    @include('components.header')

    <!-- Repeating watermark pattern -->
    <div class="watermark-pattern"></div>

    <main class="min-h-screen bg-gray-50 relative">
        <!-- Page Header -->
        @if(isset($pageHeader))
            <div class="bg-white shadow relative z-10 watermark-transparent">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                    {{ $pageHeader }}
                </div>
            </div>
        @endif

        <div class="relative z-10">
            {{ $slot }}
        </div>
    </main>

    @include('components.footer')

    {{ $scripts ?? '' }}
    @stack('scripts')

    <script>
        function toggleMobileMenu() {
            const mobileMenu = document.getElementById('mobile-menu');
            const menuButton = document.getElementById('mobile-menu-button');
            const menuIcon = document.getElementById('menu-icon');
            const closeIcon = document.getElementById('close-icon');

            mobileMenu.classList.toggle('hidden');

            // Toggle icons
            menuIcon.classList.toggle('hidden');
            menuIcon.classList.toggle('block');
            closeIcon.classList.toggle('hidden');
            closeIcon.classList.toggle('block');

            // Toggle aria-expanded
            const isExpanded = menuButton.getAttribute('aria-expanded') === 'true';
            menuButton.setAttribute('aria-expanded', !isExpanded);
        }
    </script>
</body>
</html>
