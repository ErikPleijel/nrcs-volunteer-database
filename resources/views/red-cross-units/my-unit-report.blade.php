<x-layouts.app title="Unit Data Report — {{ $redCrossUnit->name }}">

    <x-slot name="pageHeader">
        <h1 class="text-2xl font-bold text-gray-900">
            <i class="fas fa-clipboard-list mr-2"></i>ID Cards Completeness Report
        </h1>
        <p class="text-gray-500 mt-1 text-sm">{{ $redCrossUnit->name }} &bull; {{ $redCrossUnit->division->name ?? '' }} &bull; {{ $redCrossUnit->division->branch->name ?? '' }}</p>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 py-6">

        <div class="mb-6 flex items-center gap-3">
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox"
                       id="toggle-show-photos"
                       class="h-4 w-4 text-blue-600 border-gray-300 rounded
                              focus:ring-blue-500"
                       onchange="toggleImages(this.checked)">
                <span class="text-sm font-medium text-gray-700">
                    Show photos
                </span>
            </label>
            <p class="text-xs text-gray-500">
                <i class="fas fa-triangle-exclamation text-yellow-500 mr-1"></i>
                Showing images uses mobile data. Connect to Wi-Fi before loading.
            </p>
        </div>

        {{-- Legend --}}
        <div class="mb-6 p-4 bg-white rounded-lg shadow-sm border border-gray-200 text-sm flex flex-wrap gap-6">
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 rounded border-2 border-red-500 bg-red-50"></div>
                <span class="text-gray-700">Missing required data — card cannot be printed</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 rounded border-2 border-blue-500 bg-blue-50"></div>
                <span class="text-gray-700">All required data present</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-red-500 font-bold text-base">MISSING</span>
                <span class="text-gray-700">Field needs to be provided</span>
            </div>
        </div>

        {{-- Summary --}}
        @php
            $total = $redCrossUnit->users->count();
            $complete = $redCrossUnit->users->filter(function ($u) {
                $membershipType = $u->currentMembershipPayment?->membershipFee?->name;
                return $u->picture && $u->hasSignature() && $u->national_id_number
                    && $membershipType && $u->last_name && $u->first_name;
            })->count();
            $incomplete = $total - $complete;
            $oldPhoto = $redCrossUnit->users->filter(function ($u) {
                return $u->picture && !is_null($u->image_age_in_years) && $u->image_age_in_years >= 5;
            })->count();
        @endphp

        <div class="mb-6 flex flex-wrap gap-4 text-sm">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 px-4 py-3 flex items-center gap-3">
                <i class="fas fa-users text-gray-400 text-lg"></i>
                <div>
                    <p class="text-gray-500">Total members</p>
                    <p class="font-bold text-gray-900 text-lg">{{ $total }}</p>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-green-200 px-4 py-3 flex items-center gap-3">
                <i class="fas fa-circle-check text-green-500 text-lg"></i>
                <div>
                    <p class="text-gray-500">Complete</p>
                    <p class="font-bold text-green-700 text-lg">{{ $complete }}</p>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-red-200 px-4 py-3 flex items-center gap-3">
                <i class="fas fa-circle-exclamation text-red-500 text-lg"></i>
                <div>
                    <p class="text-gray-500">Incomplete</p>
                    <p class="font-bold text-red-700 text-lg">{{ $incomplete }}</p>
                </div>
            </div>
            <div class="bg-white rounded-lg shadow-sm border border-orange-200 px-4 py-3 flex items-center gap-3">
                <i class="fas fa-triangle-exclamation text-orange-500 text-lg"></i>
                <div>
                    <p class="text-gray-500">Old photo (5+ yrs)</p>
                    <p class="font-bold text-orange-700 text-lg">{{ $oldPhoto }}</p>
                </div>
            </div>
        </div>

        {{-- Cards grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($redCrossUnit->users as $user)
                @php
                    $membershipType = $user->currentMembershipPayment?->membershipFee?->name;
                    $hasMissingData = !$user->picture
                        || !$user->hasSignature()
                        || !$user->national_id_number
                        || !$membershipType
                        || !$user->last_name
                        || !$user->first_name;
                @endphp

                <div class="shadow border-2 rounded-lg p-3
                    {{ $hasMissingData ? 'border-red-500 bg-white' : 'border-blue-500 bg-blue-50' }}">

                    {{-- TOP ROW: photo + signature --}}
                    <div class="flex items-start gap-2 mb-2">

                        {{-- Profile photo --}}
                        <div class="flex-shrink-0">
                            <div class="w-24 h-28 rounded-md overflow-hidden flex items-center justify-center
                                border-2 {{ !$user->picture ? 'border-red-500' : 'border-gray-200' }}
                                @if(($user->gender ?? 'male') === 'female') bg-gradient-to-br from-pink-400 to-purple-500
                                @else bg-gradient-to-br from-blue-400 to-blue-600 @endif">
                                @if($user->picture)
                                    <img data-img data-src="{{ $user->profile_photo_url }}"
                                         src="" alt="Profile Photo"
                                         class="w-full h-full object-cover hidden">
                                    <div class="text-center" data-img-placeholder>
                                        <i class="fas fa-circle-check text-green-400 text-2xl"></i>
                                    </div>
                                @else
                                    <div class="text-center">
                                        <i class="fas fa-user text-2xl text-white mb-1"></i>
                                        <i class="fas fa-circle-xmark text-red-300 text-sm"></i>
                                    </div>
                                @endif
                            </div>
                            {{-- Photo age indicator --}}
                            @if($user->picture && !is_null($user->image_age_in_years))
                                @php
                                    $age = $user->image_age_in_years;
                                    $ageClass = $age < 3
                                        ? 'text-green-600'
                                        : ($age < 5 ? 'text-yellow-600' : 'text-red-600');
                                    $ageIcon = $age < 3
                                        ? 'fa-circle-check'
                                        : ($age < 5 ? 'fa-circle-exclamation' : 'fa-triangle-exclamation');
                                    $ageLabel = $age < 1
                                        ? '< 1 yr'
                                        : (int) $age . ' yrs';
                                    $ageSizeClass = $age >= 5 ? 'text-sm' : 'text-[10px]';
                                @endphp
                                <p class="text-center {{ $ageSizeClass }} mt-0.5 {{ $ageClass }} font-semibold">
                                    <i class="fas {{ $ageIcon }} mr-0.5"></i>{{ $ageLabel }}
                                </p>
                            @elseif($user->picture)
                                <p class="text-center text-[10px] mt-0.5 text-gray-400">no date</p>
                            @endif
                        </div>

                        {{-- Signature --}}
                        @if($user->hasSignature())
                            <div class="w-32 h-28 flex-shrink-0 flex flex-col items-center
                                        justify-center rounded border border-green-300 bg-green-50">
                                <i class="fas fa-circle-check text-green-500 text-3xl"></i>
                                <p class="text-green-700 text-xs font-semibold mt-1">Signature</p>
                            </div>
                        @else
                            <div class="w-32 h-28 flex-shrink-0 flex flex-col items-center
                                        justify-center rounded border border-red-400 bg-red-50">
                                <i class="fas fa-circle-xmark text-red-500 text-3xl"></i>
                                <p class="text-red-600 text-xs font-semibold mt-1">No signature</p>
                            </div>
                        @endif

                    </div>

                    {{-- DB reference --}}
                    <p class="text-xs text-gray-500 break-words mb-1">
                        {!! str_replace('/', '/<wbr>', $user->user_id_reference) !!}
                    </p>

                    {{-- Two-column identity grid --}}
                    <div class="grid grid-cols-2 gap-x-2 text-xs">
                        <div class="space-y-0.5">
                            <p>
                                <span class="text-gray-500">Surname:</span>
                                <span class="font-semibold {{ !$user->last_name ? 'text-red-500' : '' }}">
                                    {{ $user->last_name ? strtoupper($user->last_name) : 'MISSING' }}
                                </span>
                            </p>
                            <p>
                                <span class="text-gray-500">National ID:</span>
                                @if($user->national_id_number)
                                    <i class="fas fa-circle-check text-green-500"></i>
                                @else
                                    <span class="font-bold text-red-500">MISSING</span>
                                @endif
                            </p>
                            <p>
                                <span class="text-gray-500">Branch:</span>
                                <span class="font-semibold">
                                    {{ strtoupper($user->branch->name ?? '—') }}
                                </span>
                            </p>
                        </div>
                        <div class="space-y-0.5">
                            <p>
                                <span class="text-gray-500">First name:</span>
                                <span class="font-semibold {{ !$user->first_name ? 'text-red-500' : '' }}">
                                    {{ $user->first_name ? strtoupper($user->first_name) : 'MISSING' }}
                                </span>
                            </p>
                            <p>
                                <span class="text-gray-500">Membership:</span>
                                <span class="font-bold {{ !$membershipType ? 'text-red-500' : '' }}">
                                    {{ $membershipType ? strtoupper($membershipType) : 'MISSING' }}
                                </span>
                            </p>
                            <p>
                                <span class="text-gray-500">Division:</span>
                                <span class="font-semibold">
                                    {{ strtoupper($user->division->name ?? '—') }}
                                </span>
                            </p>
                        </div>
                    </div>

                    {{-- Missing fields summary --}}
                    @if($hasMissingData)
                        <div class="mt-2 pt-2 border-t border-red-200">
                            <p class="text-xs text-red-600 font-semibold">
                                <i class="fas fa-triangle-exclamation mr-1"></i>Missing:
                                {{ collect([
                                    !$user->picture ? 'Photo' : null,
                                    !$user->hasSignature() ? 'Signature' : null,
                                    !$user->national_id_number ? 'National ID' : null,
                                    !$membershipType ? 'Membership' : null,
                                    !$user->last_name ? 'Surname' : null,
                                    !$user->first_name ? 'First name' : null,
                                ])->filter()->implode(', ') }}
                            </p>
                        </div>
                    @endif

                </div>
            @endforeach
        </div>

        {{-- Back button --}}
        <div class="mt-8">
            <a href="{{ route('red-cross-units.my-unit') }}" class="btn-backlink">
                ← Back to My Unit
            </a>
        </div>
    </div>

<script>
    function toggleImages(show) {
        var imgs = document.querySelectorAll('[data-img]');
        var placeholders = document.querySelectorAll('[data-img-placeholder]');

        if (show) {
            imgs.forEach(function(img) {
                if (!img.src || img.src === window.location.href) {
                    img.src = img.dataset.src;
                }
                img.classList.remove('hidden');
            });
            placeholders.forEach(function(el) { el.classList.add('hidden'); });
        } else {
            imgs.forEach(function(img) { img.classList.add('hidden'); });
            placeholders.forEach(function(el) { el.classList.remove('hidden'); });
        }
    }
</script>
</x-layouts.app>
