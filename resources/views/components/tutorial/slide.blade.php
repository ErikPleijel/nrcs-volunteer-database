@props(['heading' => null, 'audio' => null])

@php
    // Version the URL by file mtime so a regenerated MP3 isn't served stale from browser cache.
    $audioUrl = null;
    if ($audio) {
        $mtime = @filemtime(public_path($audio));
        $audioUrl = asset($audio) . ($mtime ? '?v=' . $mtime : '');
    }
@endphp

<div data-slide @if($audioUrl) data-audio="{{ $audioUrl }}" @endif class="px-8 py-10 min-h-[380px]">

    @if($heading)
        <h2 class="text-2xl font-bold text-gray-900 mb-5">{{ $heading }}</h2>
    @endif

    <div class="text-lg leading-relaxed text-gray-700 space-y-4">
        {{ $slot }}
    </div>

</div>
