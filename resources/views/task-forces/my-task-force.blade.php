<x-layouts.app title="{{ $taskForce->name }} - My Task Force Details">

    <div class="min-h-screen bg-gray-50 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- The activity and summary queries are now handled in the controller. --}}
            @php
                $totalMembers = $taskForce->users->count();
            @endphp


            {{-- New Header Section for Task Force Details (similar to my-unit) --}}
            <div class="flex flex-col md:flex-row justify-between items-start mb-4">
                <div class="text-center md:text-left mb-2 md:mb-0">
                    <h1 class="text-3xl font-bold text-gray-900"><i class="fas fa-users-gear mr-3"></i> {{ $taskForce->name }}</h1>
                    <p class="mt-1 text-lg text-gray-600">
                        @if($taskForce->branch)
                            {{ $taskForce->branch->name }}
                        @endif
                    </p>
                    <p class="text-lg text-gray-900">Total Members: {{ $totalMembers }}</p>
                </div>
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
                        @if($taskForce->teamLeader)
                            <div class="flex items-center space-x-4">
                                @php $g1 = ($taskForce->teamLeader->gender ?? 'male') === 'female' ? 'from-pink-400 to-purple-500' : 'from-blue-400 to-blue-600'; @endphp
                                <div data-img-container class="w-20 h-28 rounded-lg overflow-hidden flex items-center justify-center border-4 border-white shadow-lg bg-gradient-to-br {{ $g1 }} hidden">
                                    @if($taskForce->teamLeader->picture) {{-- Simplified condition --}}
                                        <img data-img data-src="{{ route('photos.show', [$taskForce->teamLeader->id, 'profile', 'context' => 'task_force']) }}" src="" alt="Profile Photo" class="w-full h-full object-cover hidden">
                                        <i class="fas fa-user text-2xl text-white hidden" data-img-placeholder></i>
                                    @else
                                        <i class="fas fa-user text-2xl text-white"></i>
                                    @endif
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $taskForce->teamLeader->full_name }}</p>
                                    @if($taskForce->teamLeader->telephone1)
                                        <p class="text-sm text-gray-600 flex items-center gap-1.5">
                                            {{ $taskForce->teamLeader->telephone1 }}
                                            <button type="button"
                                                    class="copy-phone-btn text-gray-400 hover:text-blue-600 transition-colors"
                                                    data-phone="{{ $taskForce->teamLeader->telephone1 }}"
                                                    title="Copy phone number">
                                                <i class="fas fa-copy text-xs"></i>
                                            </button>
                                        </p>
                                    @endif
                                    <p class="text-sm text-gray-600">{{ $taskForce->teamLeader->user_id_reference_short }}</p>
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
                        @if($taskForce->assistantTeamLeader)
                            <div class="flex items-center space-x-4">
                                @php $g2 = ($taskForce->assistantTeamLeader->gender ?? 'male') === 'female' ? 'from-pink-400 to-purple-500' : 'from-blue-400 to-blue-600'; @endphp
                                <div data-img-container class="w-20 h-28 rounded-lg overflow-hidden flex items-center justify-center border-4 border-white shadow-lg bg-gradient-to-br {{ $g2 }} hidden">
                                    @if($taskForce->assistantTeamLeader->picture) {{-- Simplified condition --}}
                                        <img data-img data-src="{{ route('photos.show', [$taskForce->assistantTeamLeader->id, 'profile', 'context' => 'task_force']) }}" src="" alt="Profile Photo" class="w-full h-full object-cover hidden">
                                        <i class="fas fa-user text-2xl text-white hidden" data-img-placeholder></i>
                                    @else
                                        <i class="fas fa-user text-2xl text-white"></i>
                                    @endif
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $taskForce->assistantTeamLeader->full_name }}</p>
                                    @if($taskForce->assistantTeamLeader->telephone1)
                                        <p class="text-sm text-gray-600 flex items-center gap-1.5">
                                            {{ $taskForce->assistantTeamLeader->telephone1 }}
                                            <button type="button"
                                                    class="copy-phone-btn text-gray-400 hover:text-blue-600 transition-colors"
                                                    data-phone="{{ $taskForce->assistantTeamLeader->telephone1 }}"
                                                    title="Copy phone number">
                                                <i class="fas fa-copy text-xs"></i>
                                            </button>
                                        </p>
                                    @endif
                                    <p class="text-sm text-gray-600">{{ $taskForce->assistantTeamLeader->user_id_reference_short }}</p>
                                </div>
                            </div>
                        @else
                            <div class="text-sm text-gray-500">No assistant team leader assigned</div>
                        @endif
                    </div>
                </div>
            </div>
            <!-- ⬆️ END leaders -->

            <!-- Task Force Members -->
            @if($totalMembers > 0)
                <div class="bg-white rounded-lg shadow mb-8">
                    <div class="px-6 py-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @php
                                $teamLeaderId = $taskForce->teamLeader->id ?? null;
                                $assistantTeamLeaderId = $taskForce->assistantTeamLeader->id ?? null;

                                $filteredMembers = $taskForce->users->filter(function ($member) use ($teamLeaderId, $assistantTeamLeaderId) {
                                    return $member->id !== $teamLeaderId && $member->id !== $assistantTeamLeaderId;
                                });
                            @endphp
                            @forelse($filteredMembers as $member)
                                @php $grad = ($member->gender ?? 'male') === 'female' ? 'from-pink-400 to-purple-500' : 'from-blue-400 to-blue-600'; @endphp
                                <div class="flex items-center space-x-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50">
                                    <div data-img-container class="w-20 h-28 rounded-lg overflow-hidden flex items-center justify-center border-4 border-white shadow-lg bg-gradient-to-br {{ $grad }} hidden">
                                        @if($member->picture)
                                            <img data-img data-src="{{ route('photos.show', [$member->id, 'profile', 'context' => 'task_force']) }}" src="" alt="Profile Photo" class="w-full h-full object-cover hidden">
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
                            @empty
                                <div class="col-span-full text-center text-gray-500 py-4">No members in this task force (excluding leaders).</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif


            <!-- Summary of Activities in this Task Force (Last 12 Months) -->
            @if($activitiesSummary->count() > 0)
                <div class="bg-white rounded-lg shadow mb-8">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002 2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            Summary of Activities (Last 12 Months)
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-white">
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-2 text-gray-600 bg-white px-4">Activity Name</th>
                                <th class="text-left py-2 text-gray-600 bg-white px-4">Total Hours</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($activitiesSummary as $activity)
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-2 px-4 text-sm text-gray-900 truncate">{{ $activity['name'] }}</td>
                                    <td class="py-2 px-4 text-sm text-gray-900 font-medium">{{ $activity['total_hours'] }} hours</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- Recent Activities (paginated, tighter spacing) -->
            @if($recentActivities->count() > 0)
                <div class="bg-white rounded-lg shadow mb-8">
                    <div class="px-4 py-2 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002 2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            Recent Activities
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-white">
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-2 text-gray-600 bg-white px-4">Name</th>
                                <th class="text-left py-2 text-gray-600 bg-white px-4">Activity</th>
                                <th class="text-left py-2 text-gray-600 bg-white px-4">Hours</th>
                                <th class="text-left py-2 text-gray-600 bg-white px-4">Date</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($recentActivities as $activity)
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-2 px-4 text-sm text-gray-900 truncate">
                                        {{ $activity->user?->full_name ?? '—' }}
                                    </td>
                                    <td class="py-2 px-4">
                                        <div class="flex items-center space-x-2">
                                            <div class="flex-shrink-0">
                                                <div class="w-7 h-7 bg-green-100 rounded-full flex items-center justify-center">
                                                    <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                                    </svg>
                                                </div>
                                            </div>
                                            <div>
                                                <span class="block text-sm font-medium text-gray-900 truncate">{{ $activity->activityType->name }}</span>
                                                @if($activity->submission_name)<span class="block text-xs text-gray-500 truncate">{{ $activity->submission_name }}</span>@endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-2 px-4 text-sm text-gray-900 font-medium">{{ $activity->hours }} hours</td>
                                    <td class="py-2 px-4 text-xs text-gray-500">
                                        {{ \Illuminate\Support\Carbon::parse($activity->date)->format('M d, Y') }}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- pagination controls -->
                    <div class="px-4 py-2 bg-gray-50">
                        {{ $recentActivities->onEachSide(1)->links() }}
                    </div>
                </div>
            @endif
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
