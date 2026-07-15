<nav aria-label="Global" class="hidden md:block">
    <ul class="flex items-center gap-6 text-sm">
        @foreach($navItems as $item)
            @if($item['allowed'])
                <li>
                    <a class="text-gray-500 transition hover:text-gray-500/75 {{ request()->is($item['pattern']) ? 'text-gray-900 font-medium' : '' }}"
                       href="{{ url($item['url']) }}">
                        {{ $item['label'] }}
                    </a>
                </li>
            @endif
        @endforeach
    </ul>
</nav>
