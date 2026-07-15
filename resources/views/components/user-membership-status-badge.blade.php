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
    </span>
</span>
