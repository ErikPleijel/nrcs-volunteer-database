@props([
    'user',
    'size' => 'md', // 'sm', 'md', 'lg' if you want variants
    'showPhoto' => true, // default true so all existing usages are unchanged
])

@php
    // Name (first + last, fallback to full_name or 'No Name')
    $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
    $name = Str::title(strtolower($name));
    if ($name === '') {
        $name = $user->full_name ?? 'No Name';
    }

    // ID reference accessor: getUserIdReferenceAttribute()
    // -> $user->user_id_reference
    $idRef = $user->user_id_reference_short ?? null;

    $profession = $user->profession ?? null;





    // Age
    $currentYear = now()->year;
    $age = $user->birth_year ? $currentYear - $user->birth_year : null;

    // Gender & icon
    $gender = $user->gender ?? null;

    $genderIconClass = null;
    if ($gender === 'female') {
        $genderIconClass = 'fa-venus text-pink-500';
    } elseif ($gender === 'male') {
        $genderIconClass = 'fa-mars text-blue-500';
    }

    // Image size classes
    switch ($size) {
        case 'lg':
            $imageClasses = 'w-36 h-54 rounded-xl';
            $nameClasses  = 'text-base';
            $metaClasses  = 'text-sm';
            break;

        case 'sm':
            $imageClasses = 'w-14 h-18';
            $nameClasses  = 'text-xs';
            $metaClasses  = 'text-[10px]';
            break;

        default: // md
            $imageClasses = 'w-16 h-20 sm:w-20 sm:h-24';
            $nameClasses  = 'text-sm';
            $metaClasses  = 'text-[12px]';
            break;
    }

    $isFemale = ($gender ?? 'male') === 'female';
@endphp

<div class="flex items-center">
    @if($showPhoto)
        {{-- Portrait-style image badge --}}
        <div class="flex-shrink-0">
            <div class="{{ $imageClasses }} rounded-xl overflow-hidden
                        @if($isFemale)
                            bg-gradient-to-br from-pink-400 to-purple-500
                        @else
                            bg-gradient-to-br from-blue-400 to-blue-600
                        @endif
                        flex items-center justify-center shadow-sm">

                @if($user->picture)
                    <img src="{{ $user->profile_photo_url }}"
                         alt="Profile Photo"
                         class="w-full h-full object-cover">
                @else
                    <i class="fas fa-user text-white text-xl"></i>
                @endif
            </div>
        </div>
    @endif

    {{-- Text block --}}
    <div class="{{ $showPhoto ? 'ml-4' : '' }}">
        {{-- Name --}}
        <div class="{{ $nameClasses }} font-semibold text-gray-900 leading-tight">
            {{ $name }}
        </div>

        {{-- ID reference with line breaks at "/" --}}
        @if($idRef)
            <div class="mt-0.5 {{ $metaClasses }} text-gray-500 leading-tight">
                {!! $idRef !!}
            </div>
        @endif

        @if($profession)
            <div class="mt-0.5 {{ $metaClasses }} text-gray-500 leading-tight">
                {!! $profession !!}
            </div>
        @endif
        {{-- Gender + age --}}
        @if($size !== 'sm')
            <div class="mt-1 {{ $metaClasses }} text-gray-600 flex flex-wrap items-center gap-x-2">
                @if($gender)
                    <span class="inline-flex items-center">
                {{ ucfirst($gender) }}

                        @if($genderIconClass)
                            <i class="fas {{ $genderIconClass }} ml-1 text-xs"></i>
                        @endif
                </span>
                    @else
                        <span class="text-gray-400">
                    Gender: N/A
                </span>
                    @endif

                @if($age)
                    <span>
                          {{ $age }} yrs
                     </span>
                @elseif($gender)
                    <span class="text-gray-400">
                         Age: N/A
                     </span>
                @endif
            </div>
        @endif

    </div>
</div>
