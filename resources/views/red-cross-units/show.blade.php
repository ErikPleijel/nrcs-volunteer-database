<x-layouts.admin title="{{ $redCrossUnit->name }} - Unit Details">

    <div class="min-h-screen bg-gray-50 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- All variables are now passed from the controller --}}

            <x-slot name="pageHeader">
                <i class="fas fa-shield-alt mr-3 mb-4"></i> Red Cross Unit
            </x-slot>

            <x-slot name="button1">
                <a href="{{ route('red-cross-units.index') }}" class="btn-cancel">
                    <i class="fas fa-arrow-left mr-2"></i>Back to List
                </a>
            </x-slot>

            @can('edit_red_cross_unit')
                <x-slot name="button2">
                    <a href="{{ route('red-cross-units.edit', $redCrossUnit) }}"
                       class="btn-edit">
                        <i class="fas fa-cog mr-2"></i>Edit Unit
                    </a>
                </x-slot>
            @endcan



            <!-- Unit identity -->
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900">{{ $redCrossUnit->name }}</h2>
                <p class="mt-1 text-sm text-gray-500">
                    @if($redCrossUnit->division && $redCrossUnit->division->branch)
                        {{ $redCrossUnit->division->branch->name }} &bull;
                    @endif
                    {{ $redCrossUnit->division->name ?? 'N/A' }}
                    &bull; {{ $totalMembers }} {{ $totalMembers === 1 ? 'member' : 'members' }}
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
                        @if($redCrossUnit->teamLeader)
                            <div class="flex items-center space-x-4">
                                <!-- SAME AVATAR LOOK AS MEMBERS -->
                                @php $g1 = ($redCrossUnit->teamLeader->gender ?? 'male') === 'female' ? 'from-pink-400 to-purple-500' : 'from-blue-400 to-blue-600'; @endphp
                                @if($showPhotos)
                                    <div class="w-20 h-28 rounded-lg overflow-hidden flex items-center justify-center border-4 border-white shadow-lg bg-gradient-to-br {{ $g1 }}">
                                        @if($redCrossUnit->teamLeader->picture)
                                            <img src="{{ $redCrossUnit->teamLeader->profile_photo_url }}" alt="Profile Photo" class="w-full h-full object-cover">
                                        @else
                                            <i class="fas fa-user text-2xl text-white"></i>
                                        @endif
                                    </div>
                                @endif
                                <div>
                                    <p class="font-semibold text-gray-900">
                                        {{ $redCrossUnit->teamLeader->full_name }}
                                        @if($redCrossUnit->teamLeader->lifecycle_status === 'archived')
                                            <span class="text-lg font-semibold text-red-700 tracking-wide ml-1 uppercase">(Archived)</span>
                                        @endif
                                    </p>
                                    <p class="text-sm text-gray-600">{{ $redCrossUnit->teamLeader->email }}</p>
                                    @if($redCrossUnit->teamLeader->telephone1)
                                        <p class="text-sm text-gray-600">{{ $redCrossUnit->teamLeader->telephone1 }}</p>
                                    @endif
                                    <p class="text-xs text-gray-500 db-code">{!! $redCrossUnit->teamLeader->getUserIdReferenceLinkAttribute() !!}</p>
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
                                @if($showPhotos)
                                    <div class="w-20 h-28 rounded-lg overflow-hidden flex items-center justify-center border-4 border-white shadow-lg bg-gradient-to-br {{ $g2 }}">
                                        @if($redCrossUnit->assistantTeamLeader->picture)
                                            <img src="{{ $redCrossUnit->assistantTeamLeader->profile_photo_url }}" alt="Profile Photo" class="w-full h-full object-cover">
                                        @else
                                            <i class="fas fa-user text-2xl text-white"></i>
                                        @endif
                                    </div>
                                @endif
                                <div>
                                    <p class="font-semibold text-gray-900">
                                        {{ $redCrossUnit->assistantTeamLeader->full_name }}
                                        @if($redCrossUnit->assistantTeamLeader->lifecycle_status === 'archived')
                                            <span class="text-lg font-semibold text-red-700 tracking-wide ml-1 uppercase">(Archived)</span>
                                        @endif
                                    </p>
                                    <p class="text-sm text-gray-600">{{ $redCrossUnit->assistantTeamLeader->email }}</p>
                                    @if($redCrossUnit->assistantTeamLeader->telephone1)
                                        <p class="text-sm text-gray-600">{{ $redCrossUnit->assistantTeamLeader->telephone1 }}</p>
                                    @endif
                                    <p class="text-xs text-gray-500 db-code">{!! $redCrossUnit->assistantTeamLeader->getUserIdReferenceLinkAttribute() !!}</p>
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

                                $filteredMembers = $redCrossUnit->activeUsers->filter(function ($member) use ($teamLeaderId, $assistantTeamLeaderId) {
                                    return $member->id !== $teamLeaderId && $member->id !== $assistantTeamLeaderId;
                                });
                            @endphp
                            @forelse($filteredMembers as $member)
                                @php $grad = ($member->gender ?? 'male') === 'female' ? 'from-pink-400 to-purple-500' : 'from-blue-400 to-blue-600'; @endphp
                                <div class="flex items-center space-x-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50">
                                    @if($showPhotos)
                                        <div class="w-20 h-28 rounded-lg overflow-hidden flex items-center justify-center border-4 border-white shadow-lg bg-gradient-to-br {{ $grad }}">
                                            @if($member->picture)
                                                <img src="{{ $member->profile_photo_url }}" alt="Profile Photo" class="w-full h-full object-cover">
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
                                        <p class="text-xs text-gray-500 db-code">{!! $member->getUserIdReferenceLinkAttribute() !!}</p>
                                    </div>
                                </div>
                            @empty
                                <div class="col-span-full text-center text-gray-500 py-4">No members in this unit (excluding leaders).</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif

            <!-- Red Cross Unit Members Details Table -->
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        Membership and Volunteering
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-white">
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-2 text-gray-600 bg-white px-4">Member Name</th>
                            <th class="text-left py-2 text-gray-600 bg-white px-4">Membership Type</th>
                            <th class="text-left py-2 text-gray-600 bg-white px-4">Days to Expiry</th>
                            <th class="text-left py-2 text-gray-600 bg-white px-4">Volunteering Hours (Last 12 Months)</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($unitMembersData as $member)
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-2 px-4 text-sm text-gray-900 truncate">{{ $member['full_name'] }}</td>
                                <td class="py-2 px-4 text-sm text-gray-900">{{ $member['membership_type'] }}</td>
                                <td class="py-2 px-4 text-sm text-gray-900">{{ $member['days_to_expiry'] }}</td>
                                <td class="py-2 px-4 text-sm text-gray-900">{{ $member['volunteering_hours_last_12_months'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-center text-gray-500">No members with membership data found.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- New table for all members and their trainings --}}
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        Trainings
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-white">
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-2 text-gray-600 bg-white px-4">Member Name</th>
                            <th class="text-left py-2 text-gray-600 bg-white px-4">Trainings</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($membersWithTrainingsDetails as $member)
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-2 px-4 text-sm text-gray-900 truncate">{{ $member['full_name'] }}</td>
                                <td class="py-2 px-4">
                                    @if($member['trainings']->count() > 0)
                                        <table class="w-full text-xs bg-gray-50 rounded-lg">
                                            <thead>
                                            <tr class="border-b border-gray-200">
                                                <th class="text-left py-1 px-2 text-gray-600">Training Type</th>
                                                <th class="text-left py-1 px-2 text-gray-600">Training Date</th>
                                                <th class="text-left py-1 px-2 text-gray-600">Expiry Status</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            @foreach($member['trainings'] as $training)
                                                <tr class="border-b border-gray-100 last:border-b-0">
                                                    <td class="py-1 px-2 text-gray-800">{{ $training['training_name'] }}</td>
                                                    <td class="py-1 px-2 text-gray-800">{{ \Illuminate\Support\Carbon::parse($training['training_date'])->format('M d, Y') }}</td>
                                                    <td class="py-1 px-2 text-gray-800">
                                                        @if($training['expiry_status'] === 'Expired')
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Expired</span>
                                                        @elseif(str_contains($training['expiry_status'], 'days left'))
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">{{ $training['expiry_status'] }}</span>
                                                        @else
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">{{ $training['expiry_status'] }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                            </tbody>
                                        </table>
                                    @else
                                        <span class="text-gray-500">No trainings recorded.</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="py-4 text-center text-gray-500">No members with training data found.</td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Summary of Activities in this Red Cross Unit (Last 12 Months) -->
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
                                    <td class="py-2 px-4 text-sm text-gray-900 truncate">{{ $activity->user?->full_name ?? 'N/A' }}</td>
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
                                                <span class="block text-sm font-medium text-gray-900 truncate">{{ $activity->activityType->name ?? 'N/A' }}</span>
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

                    <!-- pagination controls; removed the "View all activities" link -->
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
