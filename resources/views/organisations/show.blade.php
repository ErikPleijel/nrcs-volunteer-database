<x-layouts.admin title="Organisation">
    <x-slot name="pageHeader">
        <i class="fas fa-industry mr-3"></i> Organisations
    </x-slot>

    <x-slot name="subHeader">
        Viewing {{ $organisation->name }}
    </x-slot>

    <x-slot name="button1">
        <a href="{{ route('organisations.index') }}" class="btn-cancel">
            <i class="fas fa-arrow-left mr-2"></i>Back to List
        </a>
    </x-slot>

    <x-slot name="button2">
        <a href="{{ route('organisations.edit', $organisation) }}" class="btn-edit">
            <i class="fas fa-edit mr-1"></i>Edit
        </a>
    </x-slot>

    <div class="show-page-container space-y-6">

        @if($organisation->trashed())
            <div class="bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 p-4 flex items-center justify-between">
                <div>
                    <strong>Archived</strong>
                    @if($organisation->deactivated_date)
                        — on {{ $organisation->deactivated_date->format('M d, Y') }}
                    @endif
                    @if($organisation->deactivatedBy)
                        by {{ $organisation->deactivatedBy->first_name }} {{ $organisation->deactivatedBy->last_name }}
                    @endif
                </div>
                <form action="{{ route('organisations.restore', $organisation->id) }}" method="POST" class="ml-4">
                    @csrf
                    <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-1 px-3 rounded text-sm">
                        <i class="fas fa-undo mr-1"></i>Restore
                    </button>
                </form>
            </div>
        @endif

        @if(session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
                {{ session('error') }}
            </div>
        @endif

        <!-- 1. Details -->
        <div class="bg-white rounded-lg shadow p-6">
            <table class="detail-table">
                <tbody>

                    <tr>
                        <td>Name</td>
                        <td>
                            <span class="text-xl font-medium">{{ $organisation->name }}</span>
                        </td>
                    </tr>

                    <tr>
                        <td>Reference</td>
                        <td>
                            <span class="db-code">{{ $organisation->org_reference }}</span>
                        </td>
                    </tr>

                    @if($organisation->short_name)
                        <tr>
                            <td>Short Name</td>
                            <td>{{ $organisation->short_name }}</td>
                        </tr>
                    @endif

                    @if($organisation->registration_number)
                        <tr>
                            <td>Registration Number</td>
                            <td>{{ $organisation->registration_number }}</td>
                        </tr>
                    @endif

                    <tr>
                        <td>Branch</td>
                        <td>{{ $organisation->branch->name ?? '—' }}</td>
                    </tr>

                    @if($organisation->email)
                        <tr>
                            <td>Email</td>
                            <td>
                                <a href="mailto:{{ $organisation->email }}" class="text-blue-600 hover:text-blue-800">
                                    {{ $organisation->email }}
                                </a>
                            </td>
                        </tr>
                    @endif

                    @if($organisation->phone)
                        <tr>
                            <td>Phone</td>
                            <td>{{ $organisation->phone }}</td>
                        </tr>
                    @endif

                    @if($organisation->address)
                        <tr>
                            <td>Address</td>
                            <td class="whitespace-pre-line">{{ $organisation->address }}</td>
                        </tr>
                    @endif

                    @if($organisation->description)
                        <tr>
                            <td>Description</td>
                            <td class="whitespace-pre-line">{{ $organisation->description }}</td>
                        </tr>
                    @endif

                    <tr>
                        <td>Added</td>
                        <td>{{ $organisation->created_at->format('M d, Y') }}</td>
                    </tr>

                </tbody>
            </table>
        </div>

        <!-- 2. Linked Persons -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b">
                <h3 class="text-base font-semibold text-gray-800">
                    Linked Persons
                    <span class="ml-2 text-sm font-normal text-gray-500">({{ $organisation->users->count() }})</span>
                </h3>
            </div>

            @if($organisation->users->count() > 0)
                <div class="divide-y divide-gray-200">
                    @foreach($organisation->users as $user)
                        <div class="px-6 py-4 flex items-center gap-4 hover:bg-gray-50">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-gray-900">
                                        {{ $user->first_name }} {{ $user->last_name }}
                                        @if($user->pivot->is_primary_contact)
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                Primary Contact
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        DB-{{ $user->id }}
                                        @if($user->pivot->linked_at)
                                            &mdash; Linked {{ $user->pivot->linked_at->format('M d, Y') }}
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <a href="{{ route('users.show', $user) }}"
                                   class="btn-primary"
                                   target="_blank"><i class="fas fa-eye mr-1"></i>View</a>

                                @if(!$user->pivot->is_primary_contact)
                                    <form action="{{ route('organisations.set-primary-contact', [$organisation, $user]) }}"
                                          method="POST"
                                          class="inline">
                                        @csrf
                                        @method('PUT')
                                        <button type="submit"
                                                class="text-xs bg-green-100 hover:bg-green-200 text-green-800 font-medium py-1 px-2 rounded">
                                            Set Primary
                                        </button>
                                    </form>
                                @endif

                                <form action="{{ route('organisations.unlink-user', [$organisation, $user]) }}"
                                      method="POST"
                                      class="inline"
                                      onsubmit="return confirm('Unlink {{ addslashes($user->first_name . ' ' . $user->last_name) }} from this organisation?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-xs bg-red-100 hover:bg-red-200 text-red-700 font-medium py-1 px-2 rounded">
                                        Unlink
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="px-6 py-8 text-center text-gray-500 text-sm">
                    No persons linked yet.
                </div>
            @endif

            <!-- Link a person -->
            <div class="px-6 py-4 bg-gray-50 border-t">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">Link a Person</h4>
                <form action="{{ route('organisations.link-user', $organisation) }}" method="POST" id="link-user-form">
                    @csrf
                    <input type="hidden" name="user_id" id="link-user-id">

                    <div class="mb-3">

                        <input type="text"
                               id="org-user-search"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Search by name, ID..."
                               autocomplete="off">
                    </div>

                    <div id="org-search-results"
                         class="hidden mb-3 border border-gray-200 rounded-md divide-y divide-gray-100 max-h-60 overflow-y-auto bg-white">
                    </div>

                    <p id="org-no-results" class="hidden text-sm text-gray-500 mb-3">No persons found.</p>

                    <div id="org-selected-user"
                         class="hidden mb-3 flex items-center justify-between bg-blue-50 border border-blue-200 rounded-md px-4 py-2">
                        <span class="text-sm text-blue-800 font-medium" id="org-selected-name"></span>
                        <button type="button" id="org-clear-selection"
                                class="text-xs text-blue-600 hover:text-blue-800 ml-4">Change</button>
                    </div>

                    @error('user_id')
                        <p class="text-red-500 text-sm mb-3">{{ $message }}</p>
                    @enderror

                    <button type="submit"
                            id="org-link-btn"
                            disabled
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-link mr-1"></i>Link
                    </button>
                </form>
            </div>
        </div>

    </div>

    <div class="max-w-4xl mx-auto px-4 space-y-6">

        <!-- 3. Membership -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="flex justify-between items-center p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-id-card text-blue-600 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">MEMBERSHIP</h2>
                </div>
                @unless($organisation->trashed())
                    <a href="{{ route('organisations.payments.create', $organisation) }}"
                       class="btn-primary">
                        <i class="fas fa-plus mr-2"></i>Add Payment
                    </a>
                @endunless
            </div>
            <div class="px-6 py-4 flex items-center gap-4 flex-wrap">
                @if($organisation->isMember())
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                        Active Member
                    </span>
                    @if($organisation->membership_expiry_date)
                        <p class="text-sm text-gray-600">
                            Expires: <span class="font-medium">{{ $organisation->membership_expiry_date->format('M d, Y') }}</span>
                        </p>
                    @endif
                @else
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-500">
                        Not a Member
                    </span>
                @endif
            </div>

            @php
                $orgPayments = $organisation->membershipPayments()
                    ->with(['user', 'membershipFee', 'submittedByUser', 'branch'])
                    ->where('is_deleted', false)
                    ->orderBy('payment_date', 'desc')
                    ->get();
            @endphp

            @if($orgPayments->isNotEmpty())
                <div class="border-t px-6 pb-4">
                    <div class="overflow-x-auto mt-4">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-2 text-gray-600 bg-white">Date</th>
                                    <th class="text-left py-2 text-gray-600 bg-white">Reference</th>
                                    <th class="text-left py-2 text-gray-600 bg-white">Fee</th>
                                    <th class="text-left py-2 text-gray-600 bg-white">Paid By</th>
                                    <th class="text-left py-2 text-gray-600 bg-white">Expiry</th>
                                    <th class="text-left py-2 text-gray-600 bg-white">Submitted By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orgPayments as $payment)
                                    <tr class="border-b border-gray-100">
                                        <td class="py-2"><x-time-ago :date="$payment->payment_date" :today="true" placeholder="—" /></td>
                                        <td class="py-2">
                                            <div class="font-mono font-semibold">{{ $payment->payment_reference }}</div>
                                            <div class="text-sm text-gray-400 mt-0.5">{{ $payment->reference ?? '—' }}</div>
                                        </td>
                                        <td class="py-2">{{ $payment->membershipFee->name ?? '—' }}</td>
                                        <td class="py-2">
                                            @if($payment->user)
                                                {{ $payment->user->first_name }} {{ $payment->user->last_name }}
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="py-2"><x-time-ago :date="$payment->expiry_date" :today="true" placeholder="—" /></td>
                                        <td class="py-2">
                                            @if($payment->submittedByUser)
                                                {{ $payment->submittedByUser->first_name }} {{ $payment->submittedByUser->last_name }}
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @can('print_certificates')
                    <div class="mt-3">
                        <a href="{{ route('organisations.certificates.index', [
                                'certificate_type' => 'organisation_membership',
                                'search' => $organisation->id,
                                'branch_id' => '',
                            ]) }}"
                           class="btn-certificates">
                            <i class="fas fa-certificate"></i>
                            Print membership certificate
                        </a>
                    </div>
                    @endcan
                </div>
            @endif
        </div>

        <!-- 4. Donations -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="flex justify-between items-center p-6">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-hand-holding-heart text-green-600 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">DONATIONS</h2>
                </div>
                @unless($organisation->trashed())
                    <a href="{{ route('organisations.donations.create', $organisation) }}"
                       class="btn-primary">
                        <i class="fas fa-plus mr-2"></i>Add Donation
                    </a>
                @endunless
            </div>

            @php
                $orgDonations = $organisation->donations()
                    ->with(['user', 'enteredBy', 'branch'])
                    ->orderBy('date_donation', 'desc')
                    ->get();
                $inKindCount  = $orgDonations->where('in_kind_donation', true)->count();
                $cashTotal    = $orgDonations->where('in_kind_donation', false)->sum('amount');
            @endphp

            <div class="px-6 py-4 flex items-center gap-6 flex-wrap">
                <div>
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">Total Cash Donated</div>
                    <div class="text-2xl font-bold text-gray-900">₦{{ number_format($cashTotal) }}</div>
                </div>
                @if($inKindCount > 0)
                    <div>
                        <div class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-1">In-Kind Donations</div>
                        <div class="text-2xl font-bold text-gray-900">{{ $inKindCount }}</div>
                    </div>
                @endif
            </div>

            @if($orgDonations->isNotEmpty())
                <div class="border-t px-6 pb-4">
                    <div class="overflow-x-auto mt-4">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-2 text-gray-600 bg-white">Date</th>
                                    <th class="text-left py-2 text-gray-600 bg-white">Type</th>
                                    <th class="text-left py-2 text-gray-600 bg-white">Amount / Item</th>
                                    <th class="text-left py-2 text-gray-600 bg-white">Purpose</th>
                                    <th class="text-left py-2 text-gray-600 bg-white">Reference</th>
                                    <th class="text-left py-2 text-gray-600 bg-white">Entered By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($orgDonations as $donation)
                                    <tr class="border-b border-gray-100">
                                        <td class="py-2"><x-time-ago :date="$donation->date_donation" :today="true" placeholder="—" /></td>
                                        <td class="py-2">
                                            @if($donation->in_kind_donation)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">In Kind</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Cash</span>
                                            @endif
                                        </td>
                                        <td class="py-2">
                                            @if($donation->in_kind_donation)
                                                {{ $donation->donation_item ?? '—' }}
                                                @if($donation->amount)
                                                    <span class="text-gray-400">({{ $donation->amount }})</span>
                                                @endif
                                            @else
                                                ₦{{ number_format($donation->amount) }}
                                            @endif
                                        </td>
                                        <td class="py-2">{{ $donation->purpose ?? '—' }}</td>
                                        <td class="py-2">
                                            @php
                                                $donBranchCode = $donation->branch
                                                    ? strtoupper($donation->branch->code ?? substr($donation->branch->name, 0, 3))
                                                    : 'UNK';
                                            @endphp
                                            <div class="font-mono font-semibold">DON-{{ $donation->id }}/{{ $donBranchCode }}</div>
                                            <div class="text-sm text-gray-400 mt-0.5">{{ $donation->reference ?? '—' }}</div>
                                        </td>
                                        <td class="py-2">
                                            @if($donation->enteredBy)
                                                {{ $donation->enteredBy->first_name }} {{ $donation->enteredBy->last_name }}
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @can('print_certificates')
                    <div class="mt-3">
                        <a href="{{ route('organisations.certificates.index', [
                                'certificate_type' => 'organisation_donation',
                                'search' => $organisation->id,
                                'branch_id' => '',
                            ]) }}"
                           class="btn-certificates">
                            <i class="fas fa-certificate"></i>
                            Print donation certificate
                        </a>
                    </div>
                    @endcan
                </div>
            @endif
        </div>

        <!-- 5. Printed Certificates -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-print text-indigo-600 text-xl"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-900">PRINTED CERTIFICATES</h2>
            </div>

            @if($certificatePrintsLimitMessage)
                <div class="mb-2 p-2 bg-indigo-50 border border-indigo-200 rounded text-center">
                    <span class="text-indigo-800 text-xs font-medium">
                        <i class="fas fa-info-circle mr-1"></i>Showing recent printed certificates - scroll to view more
                    </span>
                </div>
            @endif

            <div class="overflow-x-auto">
                <div class="@if($certificatePrintsLimitMessage) max-h-64 overflow-y-auto @endif">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-white">
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-2 text-gray-600 bg-white">Printed at</th>
                            <th class="text-left py-2 text-gray-600 bg-white">Certificate type</th>
                            <th class="text-left py-2 text-gray-600 bg-white">Printed by</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($certificatePrints as $print)
                            <tr class="border-b border-gray-100">
                                <td class="py-2">{{ $print['printed_at'] }}</td>
                                <td class="py-2">
                                    <div class="flex items-center">
                                        <i class="fas fa-certificate text-indigo-600 mr-2"></i>
                                        {{ $print['certificate_type'] }}
                                    </div>
                                </td>
                                <td class="py-2">{{ $print['printed_by'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-4 text-center text-gray-500 italic">
                                    No printed certificates found
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 5b. Messages Sent -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-envelope text-indigo-600 text-xl"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-900">MESSAGES SENT</h2>
            </div>

            @if($campaignRecipientsLimitMessage)
                <div class="mb-2 p-2 bg-indigo-50 border border-indigo-200 rounded text-center">
                    <span class="text-indigo-800 text-xs font-medium">
                        <i class="fas fa-info-circle mr-1"></i>Showing recent messages - scroll to view more
                    </span>
                </div>
            @endif

            <div class="overflow-x-auto">
                <div class="@if($campaignRecipientsLimitMessage) max-h-64 overflow-y-auto @endif">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-white">
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-2 text-gray-600 bg-white">Sent at</th>
                            <th class="text-left py-2 text-gray-600 bg-white">Campaign</th>
                            <th class="text-left py-2 text-gray-600 bg-white">Channel</th>
                            <th class="text-left py-2 text-gray-600 bg-white">Status</th>
                            <th class="text-left py-2 text-gray-600 bg-white">Contact used</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($campaignRecipients as $msg)
                            @php
                                $channelClass = match($msg['channel']) {
                                    'email'              => 'bg-blue-100 text-blue-800',
                                    'sms'                => 'bg-green-100 text-green-800',
                                    'both'               => 'bg-purple-100 text-purple-800',
                                    'email_fallback_sms' => 'bg-indigo-100 text-indigo-800',
                                    default              => 'bg-gray-100 text-gray-700',
                                };
                                $channelLabel = match($msg['channel']) {
                                    'email'              => 'Email',
                                    'sms'                => 'SMS',
                                    'both'               => 'Both',
                                    'email_fallback_sms' => 'Email / SMS',
                                    default              => $msg['channel'],
                                };
                                $statusClass = match(strtolower((string) $msg['status'])) {
                                    'sent'    => 'bg-green-100 text-green-800',
                                    'failed'  => 'bg-red-100 text-red-800',
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'bounced' => 'bg-gray-100 text-gray-700',
                                    default   => 'bg-gray-100 text-gray-700',
                                };
                            @endphp
                            <tr class="border-b border-gray-100">
                                <td class="py-2 whitespace-nowrap">
                                    @if($msg['sent_at'])
                                        {{ $msg['sent_at'] }}
                                    @elseif(strtolower((string) $msg['status']) === 'pending')
                                        <span class="text-yellow-600 text-xs font-medium">Pending</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-2">{{ $msg['campaign_title'] }}</td>
                                <td class="py-2">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $channelClass }}">
                                        {{ $channelLabel }}
                                    </span>
                                </td>
                                <td class="py-2">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                                        {{ ucfirst((string) $msg['status']) }}
                                    </span>
                                </td>
                                <td class="py-2 text-xs text-gray-600 break-all">
                                    {{ $msg['email'] ?? $msg['phone'] ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-gray-500 italic">
                                    No messages sent yet
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 6. Danger Zone -->
        @unless($organisation->trashed())
            <div class="bg-white rounded-lg shadow overflow-hidden border border-red-200">

                <div class="px-6 py-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-900">Archive this organisation</p>
                        <p class="text-sm text-gray-500">The organisation will be hidden from active lists. It can be restored later.</p>
                    </div>
                    <form action="{{ route('organisations.archive', $organisation) }}"
                          method="POST"
                          onsubmit="return confirm('Archive {{ addslashes($organisation->name) }}? It can be restored later.')">
                        @csrf
                        <button type="submit"
                                class="ml-6 bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded text-sm whitespace-nowrap">
                            <i class="fas fa-archive mr-1"></i>Archive Organisation
                        </button>
                    </form>
                </div>
            </div>
        @endunless

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput   = document.getElementById('org-user-search');
            const resultsList   = document.getElementById('org-search-results');
            const noResults     = document.getElementById('org-no-results');
            const selectedBox   = document.getElementById('org-selected-user');
            const selectedName  = document.getElementById('org-selected-name');
            const clearBtn      = document.getElementById('org-clear-selection');
            const hiddenUserId  = document.getElementById('link-user-id');
            const linkBtn       = document.getElementById('org-link-btn');

            let debounceTimer = null;

            searchInput.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                const query = this.value.trim();

                if (query.length < 2) {
                    resultsList.classList.add('hidden');
                    noResults.classList.add('hidden');
                    return;
                }

                debounceTimer = setTimeout(() => fetchUsers(query), 300);
            });

            function fetchUsers(query) {
                fetch(`{{ route('users.search') }}?query=${encodeURIComponent(query)}`)
                    .then(r => r.json())
                    .then(users => {
                        resultsList.innerHTML = '';

                        if (users.length === 0) {
                            resultsList.classList.add('hidden');
                            noResults.classList.remove('hidden');
                            return;
                        }

                        noResults.classList.add('hidden');

                        users.forEach(user => {
                            const fullName = [user.first_name, user.middle_name, user.last_name]
                                .filter(Boolean).join(' ');
                            const label = `${fullName} (DB-${user.id})`;

                            const row = document.createElement('div');
                            row.className = 'px-4 py-2 text-sm text-gray-800 hover:bg-gray-50 cursor-pointer';
                            row.textContent = label;
                            row.addEventListener('click', () => selectUser(user.id, label));
                            resultsList.appendChild(row);
                        });

                        resultsList.classList.remove('hidden');
                    })
                    .catch(() => {
                        resultsList.classList.add('hidden');
                        noResults.classList.remove('hidden');
                    });
            }

            function selectUser(id, label) {
                hiddenUserId.value = id;
                selectedName.textContent = label;
                selectedBox.classList.remove('hidden');
                resultsList.classList.add('hidden');
                resultsList.innerHTML = '';
                noResults.classList.add('hidden');
                searchInput.value = '';
                linkBtn.disabled = false;
            }

            clearBtn.addEventListener('click', function () {
                hiddenUserId.value = '';
                selectedBox.classList.add('hidden');
                linkBtn.disabled = true;
                searchInput.value = '';
                searchInput.focus();
            });
        });
    </script>
</x-layouts.admin>
