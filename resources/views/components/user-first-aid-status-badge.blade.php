<span class="badge-style {{ $styles }} inline-flex items-center gap-2">
    <i class="fas {{ $icon }}"></i>
    <span class="flex flex-col leading-tight text-left">
        <span>{{ $message }}</span>
        @if ($subtext)
            <span class="text-[10px] opacity-75">{{ $subtext }}</span>
        @endif
    </span>
</span>
