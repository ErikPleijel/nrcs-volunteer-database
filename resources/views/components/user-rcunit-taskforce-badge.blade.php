@if($hasUnit)
    <div class="flex items-center gap-1">
        <span class="inline-flex items-center gap-1 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 max-w-[140px]">
            <span class="truncate">{{ $unitName }}</span>
            @if($leaderLabel)
                <span class="font-semibold whitespace-nowrap">{{ $leaderLabel }}</span>
            @endif
        </span>
        @if($taskForceLabel)
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 whitespace-nowrap">
                {{ $taskForceLabel }}
            </span>
        @endif
    </div>
@else
    <div class="text-xs text-gray-500 truncate">No RC Unit</div>
@endif
