@if($state)
    @if($state === 'no_date')
        <p class="{{ $compact ? 'inline-flex items-center gap-1' : 'text-center mt-0.5' }} text-[10px] text-gray-400">
            @if($label)
                <span>{{ $label }}:</span>
            @endif
            no date
        </p>
    @else
        <p class="{{ $compact ? 'inline-flex items-center gap-1' : 'text-center mt-0.5' }} {{ $sizeClass }} {{ $colorClass }} font-semibold">
            @if($label)
                <span class="text-gray-500 font-normal">{{ $label }}:</span>
            @endif
            <i class="fas {{ $icon }} mr-0.5"></i>{{ $text }}
        </p>
    @endif
@endif
