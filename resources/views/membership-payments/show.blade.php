<x-layouts.admin :title="'Membership Payment: ' . ($membershipPayment->user ? $membershipPayment->user->first_name . ' ' . $membershipPayment->user->last_name : 'Unknown User')">

    <x-slot name="pageHeader">
        <i class="fas fa-hand-holding-dollar mr-3"></i> Payments
    </x-slot>
    <x-slot name="subHeader">
        Payment details
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
                DELETED
            </div>
        @endif

        {{-- DELETED banner --}}
        @if($membershipPayment->is_deleted)
            <div class="mb-5 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-red-300 bg-red-50 px-5 py-4">
                <div class="flex items-center gap-3">
                    <i class="fas fa-exclamation-triangle text-red-500"></i>
                    <p class="text-sm font-semibold text-red-700">This membership payment has been deleted</p>
                </div>
                <p class="text-xs text-red-500">
                    Deleted by
                    @if($membershipPayment->removedByUser)
                        {{ $membershipPayment->removedByUser->first_name }} {{ $membershipPayment->removedByUser->last_name }}
                        {{ $membershipPayment->removedByUser->user_id_reference_short }}
                    @else
                        Unknown
                    @endif
                    &middot;
                    <x-time-ago :date="$membershipPayment->removed_date" format="F d, Y" placeholder="Date not recorded" />
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
                                {{ $membershipPayment->user
                                    ? $membershipPayment->user->first_name . ' ' . $membershipPayment->user->last_name
                                    : 'Unknown User' }}
                            </span>
                        </td>
                    </tr>

                    <tr>
                        <td>DB Number</td>
                        <td>
                            @if($membershipPayment->user)
                                <span class="db-code">
                                    {{ $membershipPayment->user->user_id_reference_short }}
                                </span>
                            @else
                                N/A
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td></td>
                        <td>
                            @if($membershipPayment->user)
                                <a href="{{ route('users.show', $membershipPayment->user) }}" class="btn-show-link">
                                    View full profile →
                                </a>
                            @endif
                        </td>
                    </tr>

                    {{-- Payment details --}}
                    <tr>
                        <td>Membership Fee</td>
                        <td>
                            @if($membershipPayment->membershipFee)
                                {{ $membershipPayment->membershipFee->name }}
                                <div class="text-sm text-gray-400 mt-0.5">
                                    ₦{{ number_format($membershipPayment->membershipFee->amount, 2) }}
                                </div>
                            @else
                                <span class="text-red-500">Fee not found</span>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td>Status</td>
                        <td>
                            @php
                                $isExpired   = method_exists($membershipPayment, 'isExpired') && $membershipPayment->isExpired();
                                $expiresSoon = !$isExpired && method_exists($membershipPayment, 'expiresSoon') && $membershipPayment->expiresSoon();
                                $daysLeft    = method_exists($membershipPayment, 'getDaysUntilExpiryAttribute') ? $membershipPayment->getDaysUntilExpiryAttribute() : null;
                            @endphp
                            @if($isExpired)
                                <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-800">Expired</span>
                                @if($daysLeft !== null)
                                    <div class="text-sm text-gray-400 mt-0.5">{{ number_format(abs($daysLeft), 0) }} days ago</div>
                                @endif
                            @elseif($expiresSoon)
                                <span class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-semibold text-yellow-800">Expires soon</span>
                                @if($daysLeft !== null)
                                    <div class="text-sm text-gray-400 mt-0.5">{{ number_format($daysLeft, 0) }} days left</div>
                                @endif
                            @else
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-semibold text-green-800">Valid</span>
                                @if($daysLeft !== null)
                                    <div class="text-sm text-gray-400 mt-0.5">{{ number_format($daysLeft, 0) }} days left</div>
                                @endif
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td>Payment Date</td>
                        <td>
                            <x-time-ago :date="$membershipPayment->payment_date" :today="true" format="F d, Y" placeholder="Not set" />
                        </td>
                    </tr>

                    <tr>
                        <td>Expiry Date</td>
                        <td>
                            <x-time-ago :date="$membershipPayment->expiry_date" :today="true" format="F d, Y" placeholder="Not set" />
                        </td>
                    </tr>

                    <tr>
                        <td>ID Card Included</td>
                        <td>
                            @if($membershipPayment->id_card_included)
                                <span class="text-green-600 font-medium">Yes</span>
                            @else
                                <span class="text-gray-400">No</span>
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td>Paid in Branch</td>
                        <td>
                            @if($membershipPayment->branch)
                                {{ $membershipPayment->branch->name }}
                                @if($membershipPayment->division)
                                    <div class="text-sm text-gray-400 mt-0.5">{{ $membershipPayment->division->name }}</div>
                                @endif
                            @else
                                <span class="text-gray-400">Not available</span>
                            @endif
                        </td>
                    </tr>

                    {{-- Submission details — light blue background rows --}}
                    <tr class="bg-blue-50">
                        <td class="text-blue-700">Reference</td>
                        <td>
                            @if($membershipPayment->payment_reference)
                                <div class="font-mono">{{ $membershipPayment->payment_reference }}</div>
                            @endif
                            @if($membershipPayment->reference)
                                <div class="text-sm text-gray-400 mt-0.5 font-mono">
                                    <i class="fas fa-hashtag mr-1"></i>{{ $membershipPayment->reference }}
                                </div>
                            @endif
                            @if(!$membershipPayment->reference && !$membershipPayment->payment_reference)
                                <span class="text-gray-400">None</span>
                            @endif
                        </td>
                    </tr>

                    <tr class="bg-blue-50">
                        <td class="text-blue-700">Submitted By</td>
                        <td>
                            @if($membershipPayment->submittedByUser)
                                {{ $membershipPayment->submittedByUser->first_name }}
                                {{ $membershipPayment->submittedByUser->last_name }}
                                <div class="text-sm text-gray-400 mt-0.5 font-mono">
                                    {{ $membershipPayment->submittedByUser->user_id_reference_short }}
                                </div>
                            @else
                                <span class="text-gray-400">Not recorded</span>
                            @endif
                        </td>
                    </tr>

                    <tr class="bg-blue-50">
                        <td class="text-blue-700">Submitted At</td>
                        <td>
                            <x-time-ago :date="$membershipPayment->submitted_at" format="F d, Y" placeholder="Not recorded" />
                            @if($membershipPayment->submitted_at)
                                <div class="text-sm text-gray-400 mt-0.5">
                                    {{ \Carbon\Carbon::parse($membershipPayment->submitted_at)->format('g:i A') }}
                                </div>
                            @endif
                        </td>
                    </tr>

                    <tr class="bg-blue-50">
                        <td class="text-blue-700">Approved</td>
                        <td>
                            Approved{{ $membershipPayment->decidedByUser ? ' by ' . $membershipPayment->decidedByUser->full_name : '' }} @if($membershipPayment->decided_at) on {{ $membershipPayment->decided_at->format('M d, Y') }}@endif.
                        </td>
                    </tr>

                </tbody>
            </table>
        </div>

        @can('remove_payments')
            @if(!$membershipPayment->is_deleted)
                <div class="flex justify-end mt-4">
                    <form action="{{ route('membership-payments.destroy', $membershipPayment) }}"
                          method="POST"
                          onsubmit="return confirm('Are you sure you want to delete this membership payment? This action cannot be undone.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn-delete">
                            <i class="fas fa-trash-alt mr-2"></i>Delete payment
                        </button>
                    </form>
                </div>
            @endif
        @endcan

    </div>

</x-layouts.admin>
