@props(['n'])

<div class="flex items-start gap-3" data-reveal>
    <span class="flex-shrink-0 inline-flex items-center justify-center w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 text-sm font-bold">
        {{ $n }}
    </span>
    <span class="text-base text-gray-700 leading-snug pt-0.5">{{ $slot }}</span>
</div>
