<x-layouts.admin title="Training Details">

    <x-slot name="pageHeader">
        <i class="fas fa-graduation-cap mr-3"></i>Trainings
    </x-slot>
    <x-slot name="subHeader">
        Training details
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
                This training record has been deleted.
            </div>
        @endif

        {{-- DELETED banner --}}
        @if($training->is_deleted)
            <div class="mb-5 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-red-300 bg-red-50 px-5 py-4">
                <div class="flex items-center gap-3">
                    <i class="fas fa-exclamation-triangle text-red-500"></i>
                    <p class="text-sm font-semibold text-red-700">This training record has been deleted</p>
                </div>
                <p class="text-xs text-red-500">
                    Deleted by
                    @if($training->removedByUser)
                        {{ $training->removedByUser->first_name }}
                        {{ $training->removedByUser->last_name }}
                        {{ $training->removedByUser->user_id_reference_short }}
                    @else
                        Unknown
                    @endif
                    &middot;
                    <x-time-ago :date="$training->removed_date" :today="true" format="F d, Y" placeholder="Date not recorded" />
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
                                {{ $training->user->full_name ?? 'Unknown Volunteer' }}
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <td>DB Number</td>
                        <td>
                            <span class="db-code">
                                {{ $training->user ? $training->user->user_id_reference_short : 'N/A' }}
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <td></td>
                        <td>
                            @if($training->user)
                                <a href="{{ route('users.show', $training->user) }}" class="btn-show-link">
                                    View full profile →
                                </a>
                            @endif
                        </td>
                    </tr>

                    {{-- Training details --}}
                    <tr>
                        <td>Training</td>
                        <td>{{ $training->trainingType->name ?? 'N/A' }}</td>
                    </tr>

                    <tr>
                        <td>Training Date</td>
                        <td><x-time-ago :date="$training->training_date" :today="true" format="F d, Y" placeholder="N/A" /></td>
                    </tr>

                    <tr>
                        <td>Duration</td>
                        <td>{{ $training->formatted_duration }}</td>
                    </tr>

                    <tr>
                        <td>Status</td>
                        <td>
                            @php
                                $status = $training->status;
                                $statusClasses = [
                                    'valid'         => 'bg-green-100 text-green-800',
                                    'expired'       => 'bg-red-100 text-red-800',
                                    'expiring_soon' => 'bg-yellow-100 text-yellow-800',
                                    'deleted'       => 'bg-gray-100 text-gray-800',
                                    'permanent'     => 'bg-blue-100 text-blue-800',
                                ];
                            @endphp
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusClasses[$status] ?? 'bg-gray-100 text-gray-800' }}">
                                {{ ucwords(str_replace('_', ' ', $status)) }}
                            </span>
                        </td>
                    </tr>



                    @if($training->expiry_date)
                        <tr>
                            <td>Expiry Date</td>
                            <td>
                                <x-time-ago :date="$training->expiry_date" format="F d, Y" placeholder="" />
                                @if($training->days_until_expiry !== null)
                                    <div class="text-sm text-gray-400 mt-0.5">
                                        @if($training->days_until_expiry < 0)
                                            Expired {{ floor(abs($training->days_until_expiry)) }} days ago
                                        @elseif($training->days_until_expiry == 0)
                                            Expires today
                                        @else
                                            Expires in {{ floor($training->days_until_expiry) }} days
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endif

                    @if($training->branch && $training->division)
                        <tr>
                            <td>Branch / Division</td>
                            <td>
                                {{ $training->branch->name }}
                                <div class="text-sm text-gray-400 mt-0.5">{{ $training->division->name }}</div>
                            </td>
                        </tr>
                    @endif

                    {{-- Submission details — light blue background rows --}}
                    <tr class="bg-blue-50">
                        <td class="text-blue-700">Reference</td>
                        <td>
                            @if($training->training_reference)
                                <div class="font-mono">{{ $training->training_reference }}</div>
                            @endif
                            @if($training->reference)
                                <div class="text-sm text-gray-400 mt-0.5 font-mono">
                                    <i class="fas fa-hashtag mr-1"></i>{{ $training->reference }}
                                </div>
                            @endif
                            @if(!$training->training_reference && !$training->reference)
                                <span class="text-gray-400">None</span>
                            @endif
                        </td>
                    </tr>

                    <tr class="bg-blue-50">
                        <td class="text-blue-700">Submitted By</td>
                        <td>
                            @if($training->submittedByUser)
                                {{ $training->submittedByUser->full_name }}
                                <div class="text-sm text-gray-400 mt-0.5 font-mono">
                                    {{ $training->submittedByUser->user_id_reference_short }}
                                </div>
                            @else
                                <span class="text-gray-400">Not recorded</span>
                            @endif
                        </td>
                    </tr>

                    <tr class="bg-blue-50">
                        <td class="text-blue-700">Submitted At</td>
                        <td>
                            @if($training->submitted_at)
                                <x-time-ago :date="$training->submitted_at" format="F d, Y" placeholder="Not recorded" />
                                <div class="text-sm text-gray-400 mt-0.5">
                                    {{ $training->submitted_at->format('g:i A') }}
                                </div>
                            @else
                                <span class="text-gray-400">Not recorded</span>
                            @endif
                        </td>
                    </tr>

                    <tr class="bg-blue-50">
                        <td class="text-blue-700">Approved</td>
                        <td>
                            Approved{{ $training->decidedByUser ? ' by ' . $training->decidedByUser->full_name : '' }} @if($training->decided_at) on {{ $training->decided_at->format('M d, Y') }}@endif.
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>

        @if(!$training->is_deleted)
            <div class="flex justify-end mt-4">
                <form action="{{ route('trainings.destroy', $training) }}"
                      method="POST"
                      onsubmit="return confirm('Are you sure you want to delete this training record? This action cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-delete">
                        <i class="fas fa-trash-alt mr-2"></i>Delete training
                    </button>
                </form>
            </div>
        @endif

    </div>

</x-layouts.admin>
