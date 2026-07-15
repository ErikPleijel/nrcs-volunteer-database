<x-layouts.app title="Unit Details">

    <div class="min-h-screen py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="mb-4">
                <a href="{{ route('red-cross-units.my-unit') }}" class="text-sm text-blue-600 hover:underline">
                    &larr; Back to {{ $redCrossUnit->name }}
                </a>
            </div>

            <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ $redCrossUnit->name }} — Details</h1>

            {{-- Tab navigation --}}
            <div class="flex gap-2 border-b border-gray-200 mb-6">
                @foreach([
                    'membership'        => ['label' => 'Fee & Hours',         'icon' => 'fa-id-card'],
                    'trainings'         => ['label' => 'Trainings',           'icon' => 'fa-graduation-cap'],
                    'activity_summary'  => ['label' => 'Activity Summary',    'icon' => 'fa-chart-bar'],
                    'recent_activities' => ['label' => 'Recent Activities',   'icon' => 'fa-clock'],
                ] as $tabKey => $tabDef)
                    <a href="{{ route('red-cross-units.my-unit-tables', ['tab' => $tabKey]) }}"
                       class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-t-md border border-b-0 transition-colors
                           {{ $activeTab === $tabKey
                               ? 'bg-white border-gray-200 text-indigo-700 font-semibold'
                               : 'bg-gray-50 border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas {{ $tabDef['icon'] }} text-xs"></i>
                        {{ $tabDef['label'] }}
                    </a>
                @endforeach
            </div>

            {{-- Tab: Membership & Volunteering --}}
            @if($activeTab === 'membership')
                <div class="bg-white rounded-lg shadow mb-8">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            Fee & Hours
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-white">
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-2 text-gray-600 bg-white px-4">Member Name</th>
                                <th class="text-left py-2 text-gray-600 bg-white px-4">Fee Type</th>
                                <th class="text-left py-2 text-gray-600 bg-white px-4">Fee Expiry (days)</th>
                                <th class="text-left py-2 text-gray-600 bg-white px-4">Vol. Hours (12m)</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($unitMembersData as $member)
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-2 px-4 text-sm text-gray-900 truncate">{{ $member['full_name'] }}</td>
                                    <td class="py-2 px-4 text-sm text-gray-900">{{ $member['membership_type'] }}</td>
                                    <td class="py-2 px-4 text-sm text-gray-900">{{ $member['days_to_expiry'] }}</td>
                                    <td class="py-2 px-4 text-sm text-gray-900">{{ $member['volunteering_hours_last_12_months'] }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Tab: Trainings --}}
            @if($activeTab === 'trainings')
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
                            @foreach($membersWithTrainingsDetails as $member)
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
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            {{-- Tab: Activity Summary --}}
            @if($activeTab === 'activity_summary')
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
                @else
                    <div class="bg-white rounded-lg shadow p-6 text-gray-500 text-sm">No activity data recorded for the last 12 months.</div>
                @endif
            @endif

            {{-- Tab: Recent Activities --}}
            @if($activeTab === 'recent_activities')
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
                        <div class="px-4 py-2 bg-gray-50">
                            {{ $recentActivities->appends(request()->except('page'))->onEachSide(1)->links() }}
                        </div>
                    </div>
                @else
                    <div class="bg-white rounded-lg shadow p-6 text-gray-500 text-sm">No recent activities recorded.</div>
                @endif
            @endif

        </div>
    </div>

</x-layouts.app>
