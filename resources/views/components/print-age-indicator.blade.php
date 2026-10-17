@if($compact)
    {{-- Stacked badge for narrow columns (bulk print card top row). text-lg: the
         second line may wrap ("this / month") on a narrow card, which is fine. --}}
    <span class="inline-flex flex-col items-start rounded-md px-1 py-0.5 text-lg leading-tight font-semibold break-words {{ $colorClass }}" title="{{ $text }}">
        <span class="whitespace-nowrap"><i class="fas {{ $icon }} mr-0.5 text-sm"></i>{{ $lines[0] }}</span>
        <span>{{ $lines[1] }}@if($isLegacy)<span class="text-gray-400 font-normal cursor-help ml-0.5" title="Date from legacy system migration, not a confirmed recent print">*</span>@endif</span>
    </span>
@else
    <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $colorClass }}">
        <i class="fas {{ $icon }}"></i>{{ $text }}
        @if($isLegacy)
            <span class="text-gray-400 font-normal cursor-help" title="Date from legacy system migration, not a confirmed recent print">*</span>
        @endif
    </span>
@endif
