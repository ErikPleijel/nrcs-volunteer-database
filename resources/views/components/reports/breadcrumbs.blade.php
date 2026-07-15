@props(['breadcrumbs' => []])

<nav class="text-2xl text-gray-600 dark:text-gray-300 mb-3">
    @foreach($breadcrumbs as $crumb)
        @if($loop->first) @continue @endif

        @if($loop->index > 1)
            <span class="mx-1">/</span>
        @endif

        @if(isset($crumb['route']))
            <a href="{{ route($crumb['route'], $crumb['params'] ?? []) }}"
               class="text-sky-600 hover:underline">
                {{ $crumb['label'] }}
            </a>
        @else
            <span class="font-semibold">{{ $crumb['label'] }}</span>
        @endif
    @endforeach
</nav>
