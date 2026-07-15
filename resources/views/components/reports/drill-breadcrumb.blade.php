@props(['crumbs' => []])

<div class="mt-4 text-sm text-gray-600 flex items-center gap-1 flex-wrap">
    <i class="fas fa-arrow-left text-xs text-gray-400"></i>
    @foreach($crumbs as $crumb)
        @if(!$loop->first)
            <span class="text-gray-400 mx-1">/</span>
        @endif
        @if($crumb['href'] ?? null)
            <a href="{{ $crumb['href'] }}" class="text-indigo-600 hover:text-indigo-800 underline">{{ $crumb['label'] }}</a>
        @else
            <span class="font-medium text-gray-800">{{ $crumb['label'] }}</span>
        @endif
        @if($crumb['badge'] ?? null)
            <span class="ml-1 text-xs text-gray-400">({{ $crumb['badge'] }})</span>
        @endif
    @endforeach
</div>
