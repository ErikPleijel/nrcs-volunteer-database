<!-- Mobile menu, show/hide based on menu state -->
<div class="md:hidden hidden" id="mobile-menu">
    <div class="border-t border-gray-200 bg-white px-4 py-4 max-h-screen overflow-y-auto">

        <!-- Main Navigation Section -->
        <div class="space-y-1">
            <div class="pb-2 mb-3 border-b border-gray-200">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Navigation</p>
            </div>
            @foreach($navItems as $item)
                @if ($item['allowed'])
                    <a href="{{ url($item['url']) }}"
                       class="flex items-center py-2 px-3 text-base font-medium rounded-md transition hover:text-gray-900 hover:bg-gray-50 {{ request()->is($item['pattern']) ? 'text-gray-900 bg-gray-50 border-l-4 border-red-600' : 'text-gray-500' }}">
                        <i class="{{ $item['icon'] }} mr-3 w-4"></i>
                        {{ $item['label'] }}
                    </a>
                @endif
            @endforeach
        </div>

        <!-- Mobile Login/Register buttons -->
        <div class="mt-6 pt-4 border-t border-gray-200">
            <div class="space-y-2">
                @guest
                    <a href="{{ url('/login') }}"
                       class="block w-full text-center rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700">
                        Login
                    </a>
                    <a href="{{ url('/register') }}"
                       class="block w-full text-center rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-red-600 transition hover:bg-gray-200">
                        Register
                    </a>
                @endguest
            </div>
        </div>
    </div>
</div>
