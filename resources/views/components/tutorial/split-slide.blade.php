{{--
  Slide with a screenshot and reveal-points.
  The player treats this identically to <x-tutorial.slide> — same data-slide hook,
  same optional data-audio, same data-reveal stagger on all children.

  Default layout: screenshot left, content right (two columns).
  Pass `stacked` to put the screenshot full-width on top, content below.

  Usage:
  <x-tutorial.split-slide image="tutorials/img/level1-payment-s4.png"
                          imageAlt="The New Payment form"
                          heading="Fill in the payment"
                          audio="tutorials/audio/level1-payment-s4.mp3">
      <div class="space-y-4">
          <x-tutorial.step n="1">Enter the amount paid</x-tutorial.step>
          <x-tutorial.step n="2">Choose the fee type</x-tutorial.step>
      </div>
  </x-tutorial.split-slide>

  Stacked variant: add the `stacked` attribute.
  <x-tutorial.split-slide stacked image="..." heading="..." audio="...">
--}}
@props(['image', 'imageAlt' => '', 'heading' => null, 'audio' => null, 'stacked' => false])

<div data-slide @if($audio) data-audio="{{ asset($audio) }}" @endif class="px-8 py-10 min-h-[380px]">

    <div class="{{ $stacked ? 'flex flex-col gap-8' : 'grid md:grid-cols-2 gap-8 items-start' }}">

        {{-- Screenshot --}}
        <div class="rounded-lg border border-black overflow-hidden bg-gray-50" data-reveal>
            <img src="{{ asset($image) }}" alt="{{ $imageAlt }}" class="w-full h-auto block">
        </div>

        {{-- Heading + slot content --}}
        <div>
            @if($heading)
                <h2 class="text-2xl font-bold text-gray-900 mb-5" data-reveal>{{ $heading }}</h2>
            @endif

            <div class="text-base leading-relaxed text-gray-700">
                {{ $slot }}
            </div>
        </div>

    </div>

</div>
