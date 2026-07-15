@if($type)
    <div class="inline-flex flex-col items-center"
         title="{{ $message }}">

        {{-- SHIELD --}}
        <span class="relative inline-flex flex-col items-center justify-center
                     w-12 h-14
                     {{ $styles }}
                     clip-shield shadow-md text-center">

            {{-- Icon --}}
            <i class="fas {{ $icon ?? 'fa-kit-medical' }} text-base "></i>

            {{-- Inside text --}}
            <span class="text-[11px] font-bold uppercase leading-tight tracking-wide">
                First aid
            </span>

        </span>

        {{-- STATUS TEXT BELOW --}}
        <span class=" text-[11px] font-semibold
                     {{ $type === 'valid' ? 'text-green-700' : 'text-yellow-700' }}
                     uppercase tracking-wide">
            {{ $type }}
        </span>

    </div>
@endif
