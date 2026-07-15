@props(['heading' => null, 'audio' => null])

<div data-slide @if($audio) data-audio="{{ asset($audio) }}" @endif class="px-8 py-10 min-h-[380px]">

    @if($heading)
        <h2 class="text-2xl font-bold text-gray-900 mb-5">{{ $heading }}</h2>
    @endif

    <div class="text-lg leading-relaxed text-gray-700 space-y-4">
        {{ $slot }}
    </div>

</div>
