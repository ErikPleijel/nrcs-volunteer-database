<x-layouts.admin title="Donation Details">

    <x-slot name="pageHeader">
        <i class="fas fa-heart mr-3"></i> Donations
    </x-slot>
    <x-slot name="subHeader">
        Donation details
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
                This donation has been deleted.
            </div>
        @endif

        {{-- DELETED banner --}}
        @if($donation->is_deleted)
            <div class="mb-5 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-red-300 bg-red-50 px-5 py-4">
                <div class="flex items-center gap-3">
                    <i class="fas fa-exclamation-triangle text-red-500"></i>
                    <p class="text-sm font-semibold text-red-700">This donation has been deleted</p>
                </div>
                <p class="text-xs text-red-500">
                    @if($donation->removedBy)
                        Deleted by {{ $donation->removedBy->full_name }}
                        {{ $donation->removedBy->user_id_reference_short }}
                    @endif
                    @if($donation->removed_date)
                        &middot; <x-time-ago :date="$donation->removed_date" :today="true" format="F d, Y" placeholder="" />
                    @endif
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
                                {{ $donation->donor_full_name }}
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <td>DB Number</td>
                        <td>
                            <span class="db-code">
                                {{ $donation->user ? $donation->user->user_id_reference_short : 'N/A' }}
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <td></td>
                        <td>
                            @if($donation->user)
                                <a href="{{ route('users.show', $donation->user) }}" class="btn-show-link">
                                    View donor profile →
                                </a>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td>Type of Donation</td>
                        <td>
                            @if($donation->in_kind_donation)
                                <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800">
                                    <i class="fas fa-box mr-1"></i>In-Kind
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">
                                    <i class="fas fa-money-bill mr-1"></i>Cash
                                </span>
                            @endif
                        </td>
                    </tr>

                    {{-- Donation details --}}
                    <tr>
                        <td>Amount / Item</td>
                        <td class="{{ $donation->in_kind_donation ? 'text-blue-600' : 'text-green-600' }} font-semibold">
                            {{ $donation->formatted_donation }}
                        </td>
                    </tr>

                    <tr>
                        <td>Donation Date</td>
                        <td>
                            <x-time-ago :date="$donation->date_donation" :today="true" format="F d, Y" placeholder="N/A" />
                        </td>
                    </tr>

                    <tr>
                        <td>Purpose</td>
                        <td>{{ $donation->purpose ?? 'N/A' }}</td>
                    </tr>

                    <tr>
                        <td>Anonymous</td>
                        <td>
                            @if($donation->anonymous)
                                <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-semibold text-blue-800">Yes</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-600">No</span>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td>Branch / Division</td>
                        <td>
                            @if($donation->branch)
                                {{ $donation->branch->name }}
                                @if($donation->division)
                                    <div class="text-sm text-gray-400 mt-0.5">{{ $donation->division->name }}</div>
                                @endif
                            @else
                                <span class="text-gray-400">N/A</span>
                            @endif
                        </td>
                    </tr>

                    {{-- Submission details — light blue background rows --}}
                    <tr class="bg-blue-50">
                        <td class="text-blue-700">Reference</td>
                        <td>
                            <div class="font-mono">{{ $donation->donation_reference }}</div>
                            @if($donation->reference)
                                <div class="text-sm text-gray-400 mt-0.5 font-mono">
                                    <i class="fas fa-hashtag mr-1"></i>{{ $donation->reference }}
                                </div>
                            @endif
                        </td>
                    </tr>

                    <tr class="bg-blue-50">
                        <td class="text-blue-700">Submitted By</td>
                        <td>
                            @if($donation->submittedByUser)
                                {{ $donation->submittedByUser->full_name }}
                                <div class="text-sm text-gray-400 mt-0.5 font-mono">
                                    {{ $donation->submittedByUser->user_id_reference_short }}
                                </div>
                            @else
                                <span class="text-gray-400">Not recorded</span>
                            @endif
                        </td>
                    </tr>

                    <tr class="bg-blue-50">
                        <td class="text-blue-700">Submitted At</td>
                        <td>
                            @if($donation->created_at)
                                <x-time-ago :date="$donation->created_at" format="F d, Y" placeholder="Not recorded" />
                                <div class="text-sm text-gray-400 mt-0.5">
                                    {{ $donation->created_at->format('g:i A') }}
                                </div>
                            @else
                                <span class="text-gray-400">Not recorded</span>
                            @endif
                        </td>
                    </tr>

                    <tr class="bg-blue-50">
                        <td class="text-blue-700">Approved</td>
                        <td>
                            Approved{{ $donation->decidedByUser ? ' by ' . $donation->decidedByUser->full_name : '' }} @if($donation->decided_at) on {{ $donation->decided_at->format('M d, Y') }}@endif.
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>

        @can('remove_donations')
            @if(!$donation->is_deleted)
                <div class="flex justify-end mt-4">
                    <form action="{{ route('donations.destroy', $donation) }}"
                          method="POST"
                          onsubmit="return confirm('Are you sure you want to soft delete this donation? It can be restored later.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-delete">
                            <i class="fas fa-trash-alt mr-2"></i>Delete donation
                        </button>
                    </form>
                </div>
            @endif
        @endcan

    </div>

</x-layouts.admin>
