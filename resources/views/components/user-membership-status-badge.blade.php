<span class="badge-style {{ $styles }} whitespace-nowrap">
    <i class="fas {{ $icon }} mr-2"></i>

    <span class="flex flex-col leading-tight text-left">
        <span class="font-semibold">
            {{ $line1 }}
        </span>

        @if(!empty($line2))
            <span class="text-[10px] opacity-80 {{ $line2Danger ? 'text-red-600 font-semibold' : '' }}">
        {{ $line2 }}
    </span>
        @endif

        @if(!empty($line3))
            <span class="text-[10px] opacity-80 text-red-600 font-semibold">
        {{ $line3 }} days to expiry
    </span>
        @endif
    </span>
</span>
