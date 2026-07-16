<x-layouts.admin
    :title="'Activity: ' . ($activity->user ? $activity->user->first_name . ' ' . $activity->user->last_name : 'Unknown Volunteer') . ' - ' . ($activity->activityType->name ?? 'Unknown Activity')">

    <x-slot name="pageHeader">
        <i class="fas fa-hands-helping mr-3"></i> Volunteer Activity Log
    </x-slot>
    <x-slot name="subHeader">
        Activity details
    </x-slot>

    <div class="show-page-container">

        {{-- Flash messages --}}
        @if (session('success'))
            <div class="mb-5 flex items-center gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                <i class="fas fa-check-circle text-green-500"></i>
                {{ session('success') }}
            </div>
        @endif

        @if (session('deleted'))
            <div class="mb-5 flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <i class="fas fa-trash-alt text-red-400"></i>
                This activity has been deleted.
            </div>
        @endif

        {{-- DELETED banner --}}
        @if($activity->is_deleted)
            <div class="mb-5 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-red-300 bg-red-50 px-5 py-4">
                <div class="flex items-center gap-3">
                    <i class="fas fa-exclamation-triangle text-red-500"></i>
                    <p class="text-sm font-semibold text-red-700">This activity has been deleted</p>
                </div>
                <p class="text-xs text-red-500">
                    Deleted by
                    @if($activity->removedByUser)
                        {{ $activity->removedByUser->first_name }}
                        {{ $activity->removedByUser->last_name }}
                        {{ $activity->removedByUser->user_id_reference_short }}
                    @else
                        Unknown
                    @endif
                    &middot;
                    <x-time-ago :date="$activity->removed_date" :today="true" format="F d, Y" placeholder="Date not recorded" />
                </p>
            </div>
        @endif

        <div class="bg-white rounded-lg shadow p-6 mt-6">
            <table class="detail-table">
                <tbody>

                    {{-- Person --}}
                    <tr>
                        <td>Name</td>
                        <td>
                            <span class="text-xl font-medium">
                                {{ $activity->user ? $activity->user->full_name : 'Unknown Volunteer' }}
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <td>DB Number</td>
                        <td>
                            <span class="db-code">
                                {{ $activity->user ? $activity->user->user_id_reference_short : 'N/A' }}
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <td></td>
                        <td>
                            @if($activity->user)
                                <a href="{{ route('users.show', $activity->user) }}" class="btn-show-link">
                                    View full profile →
                                </a>
                            @endif
                        </td>
                    </tr>

                    {{-- Activity details --}}
                    <tr>
                        <td>Activity Type</td>
                        <td>{{ $activity->activityType->name ?? 'N/A' }}</td>
                    </tr>

                    <tr>
                        <td>Date</td>
                        <td>
                            <x-time-ago :date="$activity->date" :today="true" format="F d, Y" placeholder="N/A" />
                        </td>
                    </tr>

                    <tr>
                        <td>Hours</td>
                        <td>{{ $activity->hours }} {{ Str::plural('hour', $activity->hours) }}</td>
                    </tr>

                    @if($activity->assignable)
                        <tr>
                            <td>Assigned To</td>
                            <td>
                                {{ $activity->assignable->name }}
                                @if($activity->unit_type)
                                    <div class="text-sm text-gray-400 mt-0.5">
                                        {{ ucwords(str_replace('_', ' ', $activity->unit_type)) }}
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endif

                    @if($activity->branch_id || $activity->division_id)
                        <tr>
                            <td>Branch / Division</td>
                            <td>
                                {{ implode(' / ', array_filter([$activity->branch?->name, $activity->division?->name])) }}
                            </td>
                        </tr>
                    @endif

                    {{-- Submission details — light blue background rows --}}
                    <tr class="bg-blue-50">
                        <td class="text-blue-700">Reference</td>
                        <td>
                            <div class="font-mono">{{ $activity->getActivityReferenceAttribute() }}</div>
                            @if($activity->reference)
                                <div class="text-sm text-gray-400 mt-0.5 font-mono">
                                    <i class="fas fa-hashtag mr-1"></i>{{ $activity->reference }}
                                </div>
                            @else
                                <div class="text-sm text-gray-400 mt-0.5">No external ref.</div>
                            @endif
                        </td>
                    </tr>

                    <tr class="bg-blue-50">
                        <td class="text-blue-700">Submitted By</td>
                        <td>
                            @if($activity->submittedByUser)
                                {{ $activity->submittedByUser->full_name ?? ($activity->submittedByUser->first_name . ' ' . $activity->submittedByUser->last_name) }}
                                <div class="text-sm text-gray-400 mt-0.5 font-mono">
                                    {{ $activity->submittedByUser->user_id_reference_short }}
                                </div>
                            @else
                                <span class="text-gray-400">Not recorded</span>
                            @endif
                        </td>
                    </tr>

                    <tr class="bg-blue-50">
                        <td class="text-blue-700">Submitted At</td>
                        <td>
                            @if($activity->submitted_at)
                                <x-time-ago :date="$activity->submitted_at" format="F d, Y" placeholder="Not recorded" />
                                <div class="text-sm text-gray-400 mt-0.5">
                                    {{ \Carbon\Carbon::parse($activity->submitted_at)->format('g:i A') }}
                                </div>
                            @else
                                <span class="text-gray-400">Not recorded</span>
                            @endif
                        </td>
                    </tr>

                    <tr class="bg-blue-50">
                        <td class="text-blue-700">Approved</td>
                        <td>
                            Approved{{ $activity->decidedByUser ? ' by ' . $activity->decidedByUser->full_name : '' }} @if($activity->decided_at) on {{ $activity->decided_at->format('M d, Y') }}@endif.
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>

        @can('remove_volunteering')
            @if(!$activity->is_deleted)
                <div class="flex justify-end mt-4">
                    <form action="{{ route('activities.destroy', $activity) }}"
                          method="POST"
                          onsubmit="return confirm('Are you sure you want to delete this activity? This will move it to the trash.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-delete">
                            <i class="fas fa-trash-alt mr-2"></i>Delete activity
                        </button>
                    </form>
                </div>
            @endif
        @endcan

    </div>

</x-layouts.admin>
