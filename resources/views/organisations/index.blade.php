<x-layouts.admin title="Organisations">
    <x-slot name="pageHeader">
        <i class="fas fa-industry mr-3 mb-6"></i> Organisations
    </x-slot>

    @if($status === 'active')
        <x-slot name="button1">
            <a href="{{ route('organisations.create') }}" class="btn-add">
                <i class="fas fa-plus mr-2"></i>Add Organisation
            </a>
        </x-slot>
    @endif

    {{-- ── Guide BUTTON ───────────────────────────────────────────── --}}
    <div class="flex justify-center mb-4">
        <x-help-popup trigger-class="help-btn">
            <x-slot:trigger><i class="fas fa-question-circle text-base mr-1"></i>Guide</x-slot:trigger>

            {{-- Header --}}
            <div class="-mt-8 mb-4 text-center">
                <i class="fas fa-circle-question text-xl text-blue-500"></i>
                <h3 class="mt-1 text-base font-semibold text-gray-900">Organisation Guidelines</h3>
            </div>

            {{-- Accordion --}}
            <div class="max-w-3xl mx-auto">
                <div x-data="{ open: null }" class="space-y-1 text-sm mb-4">

                    {{-- General information --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'general' ? null : 'general'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-circle-info mr-2 text-blue-400"></i>General information</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'general' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'general'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>An <span class="font-semibold">organisation</span> is a company or institution that belongs to a branch.</li>
                                <li>It can pay membership fees, make donations, receive certificates, and receive campaign messages.</li>
                                <li>Unlike persons, an organisation <span class="font-semibold">cannot register itself</span> — it can only be added by a branch.</li>
                                <li>An organisation must have at least one linked person before it can make payments or donations.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Add organisation --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'add_org' ? null : 'add_org'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-industry mr-2 text-indigo-400"></i>Add organisation</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'add_org' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'add_org'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>The <span class="font-semibold">contact person(s)</span> must first register an account on the homepage.</li>
                                <li>Then <span class="font-semibold">register the organisation</span> here.</li>
                                <li>Finally, <span class="font-semibold">link the contact person(s)</span> to the organisation.</li>
                                <li>If more than one person is linked, mark <span class="font-semibold">one as the primary contact</span>.</li>
                                <li>Linked persons do <span class="font-semibold">not need to be members or volunteers themselves</span> — but it is encouraged, since it keeps them active in the system.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Add payments / donations --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'payments' ? null : 'payments'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-hand-holding-heart mr-2 text-green-400"></i>Add payments / donations</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'payments' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'payments'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>To add a <span class="font-semibold">membership payment</span>: Search for organisation → View → Add Payment.</li>
                                <li>To add a <span class="font-semibold">donation</span>: Search for organisation → View → Add Donation.</li>
                                <li>If no persons are linked, the organisation <span class="font-semibold">cannot make donations or membership payments</span>.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Campaigns --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'campaigns' ? null : 'campaigns'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-bullhorn mr-2 text-amber-400"></i>Campaigns</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'campaigns' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'campaigns'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Organisations can be included in campaigns.</li>
                                <li>Set a filter above — e.g. Branch or Membership status — then click <span class="font-semibold">Make campaign from this filter</span> to launch the campaign wizard with that audience pre-loaded.</li>
                                <li>When a campaign reaches an organisation, it is sent to <span class="font-semibold">both</span> the organisation's own email/SMS contact details <span class="font-semibold">and</span> its linked contact person(s).</li>
                                <li>If no contact persons are linked, only the organisation's own email/SMS (if provided) will receive the message.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Archive --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'archive' ? null : 'archive'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-box-archive mr-2 text-red-400"></i>Archive</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'archive' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'archive'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Find the organisation using Search, then click <span class="font-semibold">View</span>.</li>
                                <li>Scroll down to <span class="font-semibold">Danger Zone → Archive Organisation</span>.</li>
                                <li>Use this for an organisation that has permanently ended its relationship with the branch.</li>
                                <li>Archived organisations are hidden from active lists but can be restored later.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Reactivate --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'reactivate' ? null : 'reactivate'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-rotate-left mr-2 text-green-400"></i>Reactivate</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'reactivate' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'reactivate'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Find the organisation using Search, with <span class="font-semibold">Show archived</span> enabled.</li>
                                <li>Click <span class="font-semibold">View</span>, then click <span class="font-semibold">Restore</span>.</li>
                                <li>The organisation returns to the active list immediately.</li>
                            </ul>
                        </div>
                    </div>



                </div>{{-- end accordion --}}
            </div>{{-- end max-w-3xl --}}

        </x-help-popup>
    </div>

    <div class="container mx-auto px-4 py-6">

        @if(session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                {{ session('error') }}
            </div>
        @endif

        <!-- Filters -->
        <div class="filter-container">
            <div class="filter-form-content">
                <form method="GET" action="{{ route('organisations.index') }}" class="filter-form">
                    <div class="filter-grid filter-grid-4">
                        <div class="flex flex-col gap-4">
                            <div>
                                <label for="search" class="filter-label">Search</label>
                                <input type="text"
                                       id="search"
                                       name="search"
                                       value="{{ request('search') }}"
                                       placeholder="Name, email, phone, reg. number..."
                                       class="filter-input {{ request('search') ? 'filter-active' : '' }}">
                            </div>
                            <div>
                                <label for="sort_by" class="filter-label">Sort By</label>
                                <select name="sort_by" id="sort_by" class="filter-select">
                                    <option value="name_asc" {{ $sortBy === 'name_asc' ? 'selected' : '' }}>Name A–Z</option>
                                    <option value="name_desc" {{ $sortBy === 'name_desc' ? 'selected' : '' }}>Name Z–A</option>
                                    <option value="created_at_desc" {{ $sortBy === 'created_at_desc' ? 'selected' : '' }}>Newest first</option>
                                    <option value="created_at_asc" {{ $sortBy === 'created_at_asc' ? 'selected' : '' }}>Oldest first</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="branch_id" class="filter-label">Branch</label>
                            @if($accessLevel === 'branch')
                                <select name="branch_id" id="branch_id" class="filter-select" disabled>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" selected>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="branch_id" value="{{ $branches->first()->id ?? '' }}">
                            @else
                                <select name="branch_id" id="branch_id" class="filter-select {{ request('branch_id') ? 'filter-active' : '' }}">
                                    <option value="">All Branches</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        <div>
                            <label for="status" class="filter-label">Status</label>
                            <select name="status" id="status" class="filter-select {{ $status !== 'active' ? 'filter-active' : '' }}">
                                <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="archived" {{ $status === 'archived' ? 'selected' : '' }}>Archived</option>
                            </select>
                        </div>

                        <div>
                            <label for="membership" class="filter-label">Membership</label>
                            <select name="membership" id="membership" class="filter-select {{ $membership !== 'all' ? 'filter-active' : '' }}">
                                <option value="all" {{ $membership === 'all' ? 'selected' : '' }}>All</option>
                                <option value="members" {{ $membership === 'members' ? 'selected' : '' }}>Members</option>
                                <option value="expiring_14" {{ $membership === 'expiring_14' ? 'selected' : '' }}>Expiring in 14 days</option>
                                <option value="expiring_28" {{ $membership === 'expiring_28' ? 'selected' : '' }}>Expiring in 28 days</option>
                                <option value="non_members" {{ $membership === 'non_members' ? 'selected' : '' }}>Non-members</option>
                            </select>
                        </div>
                    </div>

                    <div class="filter-actions">
                        <div class="filter-button-group">
                            <button type="submit" class="filter-btn-primary">
                                <i class="fas fa-search mr-1"></i>Filter
                            </button>
                            <a @if($hasFilters) href="{{ route('organisations.index', ['status' => $status]) }}" @endif
                               class="filter-btn-secondary {{ $hasFilters ? 'filter-btn-secondary-active' : 'filter-btn-disabled' }}">
                                <i class="fas fa-times mr-1"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>

                @can('campaign_request_create')
                    @if($status === 'active')
                        <form method="POST" action="{{ route('campaigns.wizard.start') }}" class="inline-block mt-2">
                            @csrf
                            <input type="hidden" name="filter_json[org_representatives]" value="1">
                            @if(request('branch_id'))
                                <input type="hidden" name="filter_json[branch_id]" value="{{ request('branch_id') }}">
                            @endif
                            @if(request('membership') && request('membership') !== 'all')
                                <input type="hidden" name="filter_json[membership_filter]" value="{{ request('membership') }}">
                            @endif
                            @if(request('search'))
                                <input type="hidden" name="filter_json[search]" value="{{ request('search') }}">
                            @endif
                            <button
                                type="submit"
                                class="inline-flex items-center rounded-md px-3 py-2 text-sm font-semibold text-white transition-colors bg-slate-700 hover:bg-slate-800">
                                <i class="fas fa-envelope mr-2"></i>Make campaign from this filter
                            </button>
                        </form>
                    @endif
                @endcan
            </div>
        </div>

        <!-- Results count -->
        <div class="text-gray-600 px-1 pb-2">
            Found {{ $organisations->total() }} {{ $status === 'archived' ? 'archived' : '' }} organisation{{ $organisations->total() === 1 ? '' : 's' }}
        </div>

        <!-- Table -->
        <div class="table-container">
            @if($organisations->count() > 0)

                <div class="table-wrapper">
                    <table class="data-table">
                        <thead class="table-header">
                            <tr class="table-header-row">
                                <th class="table-header-cell">Name</th>
                                <th class="table-header-cell">Branch</th>
                                <th class="table-header-cell">Contact</th>
                                <th class="table-header-cell">Reg. Number</th>
                                <th class="table-header-cell">Persons</th>
                                @if($status === 'active')
                                    <th class="table-header-cell">Contributions</th>
                                @endif
                                @if($status === 'archived')
                                    <th class="table-header-cell">Archived On</th>
                                @endif
                                <th class="table-header-cell">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="table-body">
                            @foreach($organisations as $organisation)
                                <tr class="table-body-row {{ $status === 'archived' ? 'opacity-75' : '' }}">

                                    <td class="table-body-cell">
                                        <div class="table-field-main">{{ $organisation->name }}</div>
                                        @if($organisation->short_name)
                                            <div class="table-field-sub">{{ $organisation->short_name }}</div>
                                        @endif
                                        <div class="table-field-sub">{{ $organisation->org_reference }}</div>
                                    </td>

                                    <td class="table-body-cell">
                                        <div class="table-field-main">{{ $organisation->branch->name ?? '—' }}</div>
                                    </td>

                                    <td class="table-body-cell">
                                        @if($organisation->email)
                                            <div class="table-field-main">{{ $organisation->email }}</div>
                                        @endif
                                        @if($organisation->phone)
                                            <div class="table-field-sub">{{ $organisation->phone }}</div>
                                        @endif
                                        @if(!$organisation->email && !$organisation->phone)
                                            <span class="table-field-sub italic">—</span>
                                        @endif
                                    </td>

                                    <td class="table-body-cell">
                                        <div class="table-field-main">{{ $organisation->registration_number ?? '—' }}</div>
                                    </td>

                                    <td class="table-body-cell">
                                        @if($organisation->users_count > 0)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                                                {{ $organisation->users_count }}
                                            </span>
                                        @else
                                            <span class="text-xs font-medium text-orange-600">⚠️ No persons</span>
                                        @endif
                                    </td>

                                    @if($status === 'active')
                                        <td class="table-body-cell">
                                            <div class="flex flex-col gap-1">
                                                @if($organisation->activeMembership)
                                                    <span class="badge-style bg-green-100 text-green-800 whitespace-nowrap">
                                                        <i class="fas fa-id-card mr-2"></i>
                                                        <span class="flex flex-col leading-tight text-left">
                                                            <span class="font-semibold">Member</span>
                                                            @if($organisation->activeMembership->membershipFee)
                                                                <span class="text-[10px] opacity-80">{{ $organisation->activeMembership->membershipFee->name }}</span>
                                                            @endif
                                                        </span>
                                                    </span>
                                                @elseif($organisation->latestMembership)
                                                    <span class="badge-style bg-red-100 text-red-800 whitespace-nowrap">
                                                        <i class="fas fa-id-card mr-2"></i>
                                                        <span class="flex flex-col leading-tight text-left">
                                                            <span class="font-semibold">Membership</span>
                                                            <span class="text-[10px] opacity-80">Expired</span>
                                                        </span>
                                                    </span>
                                                @else
                                                    <span class="text-gray-400 text-xs">—</span>
                                                @endif
                                                @if($organisation->donations_count > 0)
                                                    <span class="badge-style bg-green-100 text-green-800 whitespace-nowrap">
                                                        <i class="fas fa-hand-holding-heart mr-2"></i>
                                                        <span class="font-semibold">{{ $organisation->donations_count }} {{ $organisation->donations_count === 1 ? 'Donation' : 'Donations' }}</span>
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                    @endif

                                    @if($status === 'archived')
                                        <td class="table-body-cell">
                                            <div class="table-field-main">{{ $organisation->deactivated_date?->format('M d, Y') ?? '—' }}</div>
                                        </td>
                                    @endif

                                    <td class="table-body-cell">
                                        @if($status === 'archived')
                                            <form action="{{ route('organisations.restore', $organisation->id) }}"
                                                  method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="btn-primary">Restore</button>
                                            </form>
                                        @else
                                            <div class="flex gap-2">
                                                <a href="{{ route('organisations.show', $organisation) }}"
                                                   class="btn-primary inline-flex flex-col items-center justify-center py-1 px-3 leading-tight text-center">
                                                    <span class="font-semibold">
                                                        View
                                                    </span>
                                                    <span class="text-xs -mt-1 font-normal opacity-90">
                                                        Link persons
                                                    </span>
                                                </a>
                                                <a href="{{ route('organisations.edit', $organisation) }}"
                                                   class="btn-edit">Edit
                                                </a>
                                            </div>
                                        @endif
                                    </td>

                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="table-pagination">
                    {{ $organisations->appends(request()->query())->links() }}
                </div>

            @else
                <div class="table-empty-state">
                    <i class="fas fa-industry text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">
                        {{ $status === 'archived' ? 'No archived organisations.' : 'No organisations found.' }}
                    </h3>
                    @if($status === 'active')
                        <p class="text-gray-500 mb-4">Try adjusting your search or filter criteria.</p>
                        <a href="{{ route('organisations.create') }}" class="btn-add">
                            Add First Organisation
                        </a>
                    @endif
                </div>
            @endif
        </div>

    </div>
</x-layouts.admin>
