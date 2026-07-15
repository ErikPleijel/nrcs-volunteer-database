<x-layouts.app title="My Red Cross Unit">



    <div class="min-h-screen py-8">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            @if(!auth()->user()->redCrossUnit)
                <div class="text-center">
                    <div class="bg-white rounded-lg shadow p-6">
                        <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100">
                            <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">No Red Cross Unit Assigned</h3>
                        <p class="mt-1 text-sm text-gray-500">You are not currently assigned to any Red Cross Unit. Please contact your administrator.</p>
                    </div>
                </div>
            @else
                <!-- Header Section -->
                <div class="flex flex-col md:flex-row justify-between items-start mb-8">
                    {{-- Left side content (Unit Name, Members, etc.) --}}
                    <div class="text-center md:text-left mb-4 md:mb-0">
                        <h1 class="text-3xl font-bold text-gray-900">{{ $redCrossUnit->name }}</h1>
                        <p class="mt-2 text-lg text-gray-600">
                            {{ $redCrossUnit->division->name ?? 'N/A' }}
                            @if($redCrossUnit->division && $redCrossUnit->division->branch)
                                • {{ $redCrossUnit->division->branch->name }}
                            @endif
                        </p>
                        <p class="text-lg text-gray-900">Total Members: {{ $totalMembers }}</p>
                    </div>

                    {{-- Right side content (User's Task Forces) --}}
                    @if(auth()->user()->taskForces->isNotEmpty())
                        <div class="w-full md:w-auto text-center md:text-right">
                            @php
                                $taskForces = auth()->user()->taskForces;
                                $label = ($taskForces->count() === 1) ? 'Check out your Task Force:' : 'Check out your Task Forces:';
                            @endphp
                            <p class="text-md text-gray-700 font-semibold mb-2">{{ $label }}</p>
                            <ul class="list-none p-0 m-0 space-y-1">
                                @foreach($taskForces as $taskForce)
                                    <li>
                                        <a href="{{ route('my-task-force.show', $taskForce) }}"
                                           class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 hover:bg-blue-200 transition-colors duration-200">
                                            <i class="fas fa-users-gear mr-3"></i>
                                            {{ $taskForce->name }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <div class="mb-6 flex items-center gap-3">
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox"
                               id="toggle-show-photos"
                               class="h-4 w-4 text-blue-600 border-gray-300 rounded
                                      focus:ring-blue-500"
                               onchange="toggleImages(this.checked)">
                        <span class="text-sm font-medium text-gray-700">
                            Show profile photos
                        </span>
                    </label>
                    <p class="text-xs text-gray-500">
                        <i class="fas fa-triangle-exclamation text-yellow-500 mr-1"></i>
                        Showing images uses mobile data. Connect to Wi-Fi before loading.
                    </p>
                </div>

                <!-- ⬆️  TEAM LEADERS  -->
                <div class="bg-white rounded-lg shadow p-6 mb-8">
                    <div class="flex flex-col lg:flex-row lg:justify-around gap-8">
                        <!-- Team Leader -->
                        <div class="w-full lg:w-5/12">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                Team Leader
                            </h3>
                            @if($redCrossUnit->teamLeader)
                                <div class="flex items-center space-x-4">
                                    @php $g1 = ($redCrossUnit->teamLeader->gender ?? 'male') === 'female' ? 'from-pink-400 to-purple-500' : 'from-blue-400 to-blue-600'; @endphp
                                    <div data-img-container class="hidden w-20 h-28 rounded-lg overflow-hidden flex items-center justify-center border-4 border-white shadow-lg bg-gradient-to-br {{ $g1 }}">
                                        @if($redCrossUnit->teamLeader->picture)
                                            <img data-img data-src="{{ $redCrossUnit->teamLeader->picture ? route('photos.show', [$redCrossUnit->teamLeader->id, 'profile', 'context' => 'red_cross_unit']) : asset('images/placeholders/profile-placeholder.png') }}" src="" alt="Profile Photo" class="w-full h-full object-cover hidden">
                                            <i class="fas fa-user text-2xl text-white hidden" data-img-placeholder></i>
                                        @else
                                            <i class="fas fa-user text-2xl text-white"></i>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ $redCrossUnit->teamLeader->full_name }}</p>
                                        @if($redCrossUnit->teamLeader->telephone1)
                                            <p class="text-sm text-gray-600 flex items-center gap-1.5">
                                                {{ $redCrossUnit->teamLeader->telephone1 }}
                                                <button type="button"
                                                        class="copy-phone-btn text-gray-400 hover:text-blue-600 transition-colors"
                                                        data-phone="{{ $redCrossUnit->teamLeader->telephone1 }}"
                                                        title="Copy phone number">
                                                    <i class="fas fa-copy text-xs"></i>
                                                </button>
                                            </p>
                                        @endif
                                        <p class="text-sm text-gray-600">{{ $redCrossUnit->teamLeader->user_id_reference_short }}</p>
                                    </div>
                                </div>
                            @else
                                <div class="text-sm text-gray-500">No team leader assigned</div>
                            @endif
                        </div>

                        <!-- Assistant Team Leader -->
                        <div class="w-full lg:w-5/12">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                Assistant Team Leader
                            </h3>
                            @if($redCrossUnit->assistantTeamLeader)
                                <div class="flex items-center space-x-4">
                                    @php $g2 = ($redCrossUnit->assistantTeamLeader->gender ?? 'male') === 'female' ? 'from-pink-400 to-purple-500' : 'from-blue-400 to-blue-600'; @endphp
                                    <div data-img-container class="hidden w-20 h-28 rounded-lg overflow-hidden flex items-center justify-center border-4 border-white shadow-lg bg-gradient-to-br {{ $g2 }}">
                                        @if($redCrossUnit->assistantTeamLeader->picture)
                                            <img data-img data-src="{{ $redCrossUnit->assistantTeamLeader->picture ? route('photos.show', [$redCrossUnit->assistantTeamLeader->id, 'profile', 'context' => 'red_cross_unit']) : asset('images/placeholders/profile-placeholder.png') }}" src="" alt="Profile Photo" class="w-full h-full object-cover hidden">
                                            <i class="fas fa-user text-2xl text-white hidden" data-img-placeholder></i>
                                        @else
                                            <i class="fas fa-user text-2xl text-white"></i>
                                        @endif
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900">{{ $redCrossUnit->assistantTeamLeader->full_name }}</p>
                                        @if($redCrossUnit->assistantTeamLeader->telephone1)
                                            <p class="text-sm text-gray-600 flex items-center gap-1.5">
                                                {{ $redCrossUnit->assistantTeamLeader->telephone1 }}
                                                <button type="button"
                                                        class="copy-phone-btn text-gray-400 hover:text-blue-600 transition-colors"
                                                        data-phone="{{ $redCrossUnit->assistantTeamLeader->telephone1 }}"
                                                        title="Copy phone number">
                                                    <i class="fas fa-copy text-xs"></i>
                                                </button>
                                            </p>
                                        @endif
                                        <p class="text-sm text-gray-600">{{ $redCrossUnit->assistantTeamLeader->user_id_reference_short }}</p>
                                    </div>
                                </div>
                            @else
                                <div class="text-sm text-gray-500">No assistant team leader assigned</div>
                            @endif
                        </div>
                    </div>
                </div>
                <!-- ⬆️ END leaders -->



                <!-- Unit Members -->
                @if($totalMembers > 0)
                    <div class="bg-white rounded-lg shadow mb-8">

                        <div class="px-6 py-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                @php
                                    $teamLeaderId = $redCrossUnit->teamLeader->id ?? null;
                                    $assistantTeamLeaderId = $redCrossUnit->assistantTeamLeader->id ?? null;

                                    $filteredMembers = $redCrossUnit->users->filter(function ($member) use ($teamLeaderId, $assistantTeamLeaderId) {
                                        return $member->id !== $teamLeaderId && $member->id !== $assistantTeamLeaderId;
                                    });
                                @endphp
                                @foreach($filteredMembers as $member)
                                    @php $grad = ($member->gender ?? 'male') === 'female' ? 'from-pink-400 to-purple-500' : 'from-blue-400 to-blue-600'; @endphp
                                    <div class="flex items-center space-x-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50">
                                        <div data-img-container class="hidden w-20 h-28 rounded-lg overflow-hidden flex items-center justify-center border-4 border-white shadow-lg bg-gradient-to-br {{ $grad }}">
                                            @if($member->picture)
                                                <img data-img data-src="{{ $member->picture ? route('photos.show', [$member->id, 'profile', 'context' => 'red_cross_unit']) : asset('images/placeholders/profile-placeholder.png') }}" src="" alt="Profile Photo" class="w-full h-full object-cover hidden">
                                                <i class="fas fa-user text-2xl text-white hidden" data-img-placeholder></i>
                                            @else
                                                <i class="fas fa-user text-2xl text-white"></i>
                                            @endif
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="font-semibold text-gray-900 truncate">{{ $member->full_name }}</p>
                                            @if($member->telephone1)
                                                <p class="text-sm text-gray-600 truncate flex items-center gap-1.5">
                                                    <span class="truncate">{{ $member->telephone1 }}</span>
                                                    <button type="button"
                                                            class="copy-phone-btn text-gray-400 hover:text-blue-600 transition-colors flex-shrink-0"
                                                            data-phone="{{ $member->telephone1 }}"
                                                            title="Copy phone number">
                                                        <i class="fas fa-copy text-xs"></i>
                                                    </button>
                                                </p>
                                            @endif
                                            <p class="text-sm text-gray-600 truncate">{{ $member->user_id_reference_short }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

            @endif
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-4 flex flex-wrap gap-3">
            <a href="{{ route('red-cross-units.my-unit-report') }}" class="btn-primary">
                <i class="fas fa-clipboard-list mr-2"></i>ID Cards Completeness Report
            </a>
            <a href="{{ route('red-cross-units.my-unit-tables') }}" class="btn-primary">
                <i class="fas fa-table mr-2"></i>Membership, Training &amp; Activities
            </a>
            <a href="{{ route('red-cross-units.my-unit-comparison') }}" class="btn-primary">
                <i class="fas fa-chart-bar mr-2"></i>Compare with Other Units
            </a>
        </div>

    </div>

<script>
function toggleImages(show) {
    var imgs         = document.querySelectorAll('[data-img]');
    var containers   = document.querySelectorAll('[data-img-container]');
    var placeholders = document.querySelectorAll('[data-img-placeholder]');

    if (show) {
        containers.forEach(function(c) { c.classList.remove('hidden'); });
        imgs.forEach(function(img) {
            if (!img.src || img.src === window.location.href) {
                img.src = img.dataset.src;
            }
            img.classList.remove('hidden');
        });
        placeholders.forEach(function(el) { el.classList.add('hidden'); });
    } else {
        containers.forEach(function(c) { c.classList.add('hidden'); });
        imgs.forEach(function(img) { img.classList.add('hidden'); });
        placeholders.forEach(function(el) { el.classList.remove('hidden'); });
    }
}

document.addEventListener('click', function (e) {
    const btn = e.target.closest('.copy-phone-btn');
    if (!btn) return;

    const phone = btn.dataset.phone;
    if (!phone) return;

    navigator.clipboard.writeText(phone).then(function () {
        const icon = btn.querySelector('i');
        const originalClass = icon.className;
        icon.className = 'fas fa-check text-xs text-green-600';
        setTimeout(function () {
            icon.className = originalClass;
        }, 1200);
    }).catch(function () {
        // Clipboard API unavailable or blocked — fail silently,
        // the phone number is still visible and selectable manually.
    });
});
</script>
</x-layouts.app>
