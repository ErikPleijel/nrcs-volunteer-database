@if($compact)
    {{-- Stacked two-line badge for narrow columns (bulk print card top row) --}}
    <span class="inline-flex flex-col items-start rounded-md px-1.5 py-0.5 text-[10px] leading-tight font-semibold {{ $colorClass }}" title="{{ $text }}">
        <span class="whitespace-nowrap"><i class="fas {{ $icon }} mr-0.5"></i>{{ $lines[0] }}</span>
        <span class="whitespace-nowrap">{{ $lines[1] }}@if($isLegacy)<span class="text-gray-400 font-normal cursor-help ml-0.5" title="Date from legacy system migration, not a confirmed recent print">*</span>@endif</span>
    </span>
@else
    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $colorClass }}">
        <i class="fas {{ $icon }}"></i>{{ $text }}
        @if($isLegacy)
            <span class="text-gray-400 font-normal cursor-help" title="Date from legacy system migration, not a confirmed recent print">*</span>
        @endif
    </span>
@endif
