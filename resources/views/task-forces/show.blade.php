<x-layouts.admin title="{{ $taskForce->name }} - Task Force Details">

    <div class="min-h-screen bg-gray-50 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- The activity and summary queries are now handled in the controller. --}}
            @php
                $totalMembers = $taskForce->users->count();

                $adminUser     = auth()->user();
                $adminAccess   = $adminUser->getAccessLevel();
                $adminScopedId = $adminUser->getScopedId();

                // Returns true if the given user is within the admin's scope
                $canLinkUser = function ($member) use ($adminAccess, $adminScopedId, $adminUser) {
                    if ($adminAccess === 'national') {
                        return true;
                    }
                    if ($adminAccess === 'branch') {
                        return $member->branch_id === $adminScopedId;
                    }
                    if ($adminAccess === 'division') {
                        return $member->division_id === $adminScopedId;
                    }
                    return false;
                };
            @endphp

            <x-slot name="pageHeader">
                <i class="fas fa-users-gear mr-3 mb-4"></i> Task Force
            </x-slot>
            @can('edit_task_force')
            <x-slot name="button1">
                <a href="{{  route('task-forces.edit', $taskForce)  }}"
                   class="btn-primary">
                    <i class="fas fa-cog mr-2"></i>Edit Task Force
                </a>
            </x-slot>
            @endcan

            <!-- Task force identity -->
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900">{{ $taskForce->name }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    @if($taskForce->branch)
                        {{ $taskForce->branch->name }} &bull;
                    @endif
                    {{ $totalMembers }} {{ $totalMembers === 1 ? 'member' : 'members' }}
                </p>
            </div>

            {{-- Display preference: show profile photos (per-browser cookie, set via JS) --}}
            <div class="flex justify-end mb-2">
                <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                    <input type="checkbox" id="toggle-show-photos" {{ $showPhotos ? 'checked' : '' }} class="rounded border-gray-300">
                    Show profile photos
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
                                @if($showPhotos)
                                    <div class="w-20 h-28 rounded-lg overflow-hidden flex items-center justify-center border-4 border-white shadow-lg bg-gradient-to-br {{ $g1 }}">
                                        @if($taskForce->teamLeader->picture)
                                            <img src="{{ route('photos.show', [$taskForce->teamLeader->id, 'profile', 'context' => 'task_force']) }}" alt="Profile Photo" class="w-full h-full object-cover">
                                        @else
                                            <i class="fas fa-user text-2xl text-white"></i>
                                        @endif
                                    </div>
                                @endif
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $taskForce->teamLeader->full_name }}</p>
                                    <p class="text-sm text-gray-600">{{ $taskForce->teamLeader->email }}</p>
                                    @if($taskForce->teamLeader->telephone1)
                                        <p class="text-sm text-gray-600">{{ $taskForce->teamLeader->telephone1 }}</p>
                                    @endif
                                    <p class="text-xs text-gray-500">
                                        @if($canLinkUser($taskForce->teamLeader))
                                            {!! $taskForce->teamLeader->getUserIdReferenceLinkAttribute() !!}
                                        @else
                                            <span class="db-code">{{ $taskForce->teamLeader->getUserIdReferenceShortAttribute() }}</span>
                                        @endif
                                    </p>
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
                                @if($showPhotos)
                                    <div class="w-20 h-28 rounded-lg overflow-hidden flex items-center justify-center border-4 border-white shadow-lg bg-gradient-to-br {{ $g2 }}">
                                        @if($taskForce->assistantTeamLeader->picture)
                                            <img src="{{ route('photos.show', [$taskForce->assistantTeamLeader->id, 'profile', 'context' => 'task_force']) }}" alt="Profile Photo" class="w-full h-full object-cover">
                                        @else
                                            <i class="fas fa-user text-2xl text-white"></i>
                                        @endif
                                    </div>
                                @endif
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $taskForce->assistantTeamLeader->full_name }}</p>
                                    <p class="text-sm text-gray-600">{{ $taskForce->assistantTeamLeader->email }}</p>
                                    @if($taskForce->assistantTeamLeader->telephone1)
                                        <p class="text-sm text-gray-600">{{ $taskForce->assistantTeamLeader->telephone1 }}</p>
                                    @endif
                                    <p class="text-xs text-gray-500">
                                        @if($canLinkUser($taskForce->assistantTeamLeader))
                                            {!! $taskForce->assistantTeamLeader->getUserIdReferenceLinkAttribute() !!}
                                        @else
                                            <span class="db-code">{{ $taskForce->assistantTeamLeader->getUserIdReferenceShortAttribute() }}</span>
                                        @endif
                                    </p>
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
                                })->sortBy('first_name');
                            @endphp
                            @forelse($filteredMembers as $member)
                                @php $grad = ($member->gender ?? 'male') === 'female' ? 'from-pink-400 to-purple-500' : 'from-blue-400 to-blue-600'; @endphp
                                <div class="flex items-center space-x-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50">
                                    @if($showPhotos)
                                        <div class="w-20 h-28 rounded-lg overflow-hidden flex items-center justify-center border-4 border-white shadow-lg bg-gradient-to-br {{ $grad }}">
                                            @if($member->picture)
                                                <img src="{{ route('photos.show', [$member->id, 'profile', 'context' => 'task_force']) }}" alt="Profile Photo" class="w-full h-full object-cover">
                                            @else
                                                <i class="fas fa-user text-2xl text-white"></i>
                                            @endif
                                        </div>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold text-gray-900 truncate">{{ $member->full_name }}</p>
                                        <p class="text-sm text-gray-600 truncate">{{ $member->email }}</p>
                                        @if($member->telephone1)
                                            <p class="text-sm text-gray-600 truncate">{{ $member->telephone1 }}</p>
                                        @endif
                                        <p class="text-xs text-gray-500">
                                            @if($canLinkUser($member))
                                                {!! $member->getUserIdReferenceLinkAttribute() !!}
                                            @else
                                                <span class="db-code">{{ $member->getUserIdReferenceShortAttribute() }}</span>
                                            @endif
                                        </p>
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

    <x-slot name="scripts">
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // ── Show-profile-photos preference (per-browser cookie + reload) ──
                document.getElementById('toggle-show-photos')?.addEventListener('change', function () {
                    const val = this.checked ? '1' : '0';
                    // 1-year cookie, site-wide path
                    document.cookie = 'users_show_photos=' + val + ';path=/;max-age=' + (60 * 60 * 24 * 365) + ';SameSite=Lax';
                    window.location.reload();
                });
            });
        </script>
    </x-slot>
</x-layouts.admin>
