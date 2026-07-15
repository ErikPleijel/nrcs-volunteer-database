

<x-layouts.admin title="Persons Management">
    <x-slot name="pageHeader">
        <i class="fas fa-user mr-3"></i> Persons
    </x-slot>
    <x-slot name="subHeader">
        FIND & FILTER
    </x-slot>

    {{-- ── HOW TO BUTTON ───────────────────────────────────────────── --}}
    <div class="flex justify-center mb-4">
        <x-help-popup trigger-class="help-btn">
            <x-slot:trigger><i class="fas fa-question-circle text-base mr-1"></i>Guide</x-slot:trigger>

            {{-- Header --}}
            <div class="-mt-8 mb-4 text-center">
                <i class="fas fa-question-circle text-xl text-sky-500"></i>
                <h3 class="mt-1 text-base font-semibold text-gray-900">How do I...</h3>
            </div>


            {{-- Accordion --}}
            <div class="max-w-3xl mx-auto">
                <div x-data="{ open: null }" class="space-y-1 text-sm mb-4">

                    {{-- Assign person to Red Cross Unit --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'assign_unit' ? null : 'assign_unit'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-people-group mr-2 text-indigo-400"></i>Assign person to Red Cross Unit</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'assign_unit' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'assign_unit'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">

                                <li>Find the person using Search. <span class="font-semibold">Edit → Red Cross Unit → Select in dropdown</span>.</li>
                                <li>If you don't find the correct Red Cross Unit, you might need to change the Division first.</li>
                                <li>Scroll down → click <span class="font-semibold">Update Person</span>.</li>
                                <li>Once assigned, the person automatically moves out of Pending Engagement and becomes Active.</li>
                                <li>Assigning someone to a Red Cross Unit is what makes them a volunteer in the system — it's the defining step.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Why can't I find a person? --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'find_person' ? null : 'find_person'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-magnifying-glass mr-2 text-indigo-400"></i>Why can't I find a person?</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'find_person' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'find_person'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>The default view only shows people who are Active or Dormant.</li>
                                <li>To search everyone: <span class="font-semibold">Show all filters → Lifecycle → All</span>.</li>
                                <li>Then search again using name, email, or DB number.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Move user to another branch/division --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'move_location' ? null : 'move_location'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-right-left mr-2 text-violet-400"></i>Move user to another branch/division</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'move_location' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'move_location'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">

                                <li><span class="font-semibold">Same branch:</span>
                                    <ul class="list-circle pl-4 mt-1 space-y-0.5">
                                        <li>Search → <span class="font-semibold">Edit</span> → select new <span class="font-semibold">Division</span></li>
                                        <li>Click <span class="font-semibold">Update Person</span></li>
                                    </ul>
                                </li>

                                <li><span class="font-semibold">Different branch:</span>
                                    <ul class="list-circle pl-4 mt-1 space-y-0.5">
                                        <li>First <span class="font-semibold">unassign</span> from their Red Cross Unit</li>
                                        <li>A unit belongs to one branch only</li>
                                    </ul>
                                </li>

                                <li><span class="font-semibold">Then, either:</span>
                                    <ul class="list-circle pl-4 mt-1 space-y-0.5">
                                        <li>Person updates branch via <span class="font-semibold">My Profile</span> → new branch assigns a unit</li>
                                        <li>Or <span class="font-semibold">National HQ</span> moves them directly</li>
                                    </ul>
                                </li>

                                <li><span class="font-semibold">Branch admins:</span> can only move people within their <span class="font-semibold">own branch</span></li>

                                <li><span class="font-semibold">Admin role?</span> Must be removed first. Contact your Branch or HQ.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Add photo/signature image --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'photo_signature' ? null : 'photo_signature'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-camera mr-2 text-sky-400"></i>Add photo/signature image</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'photo_signature' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'photo_signature'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Find the person using Search, then click <span class="font-semibold">Edit</span>.</li>
                                <li>Under the photo: <span class="font-semibold">Choose File → Update Profile Photo</span>.</li>
                                <li>Under the signature: <span class="font-semibold">Choose File → Update Signature</span>.</li>
                                <li>(You do not need to click the Update Person button for these).</li>
                                <li>You can also capture both images directly using the built-in camera.</li>
                                <li>A recent, clear photo is required for printed ID cards — use the <span class="font-semibold">Profile Photo &amp; Signature</span> filter to find persons missing one.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Start a campaign from Persons --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'campaign_from_filter' ? null : 'campaign_from_filter'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-bullhorn mr-2 text-amber-400"></i>Start a campaign from Persons</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'campaign_from_filter' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'campaign_from_filter'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Set a filter — e.g. Branch, Division, Volunteers only, Age group — then click <span class="font-semibold">Filter</span> and check the number of records found.</li>
                                <li>Not sure how to filter? Try <span class="font-semibold">Campaign filter wizard</span> for ready-made strategies like Welcome newly registered persons or Re-engage dormant volunteers.</li>
                                <li>If the group isn't too large, click <span class="font-semibold">Make campaign from filter</span> — this hands off to the campaign wizard (Purpose → Audience → Throttling → Message → Review).</li>
                                <li>If the audience is too big, narrow the filter first — a focused message reaches people better than a broad one.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Archive user --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'archive' ? null : 'archive'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-box-archive mr-2 text-red-400"></i>Archive user</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'archive' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'archive'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Find the person using Search, then click <span class="font-semibold">Edit</span>.</li>
                                <li>Scroll down → <span class="font-semibold">Tick 'Archive this user'</span>.</li>
                                <li>Click <span class="font-semibold">Update Person</span>.</li>
                                <li>Use this for someone who has permanently left the organisation, rather than just gone quiet for a while.</li>

                                <li>Archived users are hidden from the list — use the <span class="font-semibold">Show archived</span> button to find them again.</li>
                                <li>You cannot archive your own account.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Activate archived user --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'reactivate' ? null : 'reactivate'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-rotate-left mr-2 text-green-400"></i>Activate archived user</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'reactivate' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'reactivate'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Archived users are hidden from the list — use the <span class="font-semibold">Show archived</span> button to find them again.</li>
                                <li>Find the person using <span class="font-semibold">Search → click Edit</span></li>

                                <li>Scroll down → <span class="font-semibold">Untick 'Archive this user'</span>.</li>

                                <li>Click <span class="font-semibold">Update Person</span>.</li>
                                <li>The person returns to Active status</li>
                            </ul>
                        </div>
                    </div>



                </div>{{-- end accordion --}}
            </div>{{-- end max-w-3xl --}}

        </x-help-popup>
    </div>

    @can('add_user')
        <div class="flex justify-end">
            <a href="{{ route('users.create') }}" class="btn-add-small">
                <i class="fas fa-plus mr-1"></i>Register New Person
            </a>
        </div>
    @endcan

    <div class="container mx-auto px-4 py-6">
        <!-- Search and Filter Section -->
        @php
            $hiddenFilterKeys = [
                'photo_signature_filter',
                'membership_filter',
                'volunteer_filter',
                'org_representatives',
                'team_leader_filter',
                'database_role_filter',
                'registration_filter',
                'dormancy_filter',
                'email_status',
                'training_filter',
                'training_expiry',
                'first_aid_refresher',
                'donation_filter',
                'campaign_msg',
                'donation_since_contact',
                'wizard_purpose', // internal hint, not a real filter
            ];
            $anyHiddenFilterActive = request()->anyFilled($hiddenFilterKeys);
        @endphp
        <div class="filter-container">
            <div class="filter-form-content">
                <form method="GET" action="{{ route('users.index') }}" class="filter-form">
                    {{-- Always-visible row: Search+Sort | Location(span3) | Demography+Members | Button --}}
                    {{-- Note: Age Range in demography component could be moved to expanded section in future --}}
                    <div class="filter-grid filter-grid-6">
                        <div class="flex flex-col gap-4">
                            {{-- Search --}}
                            <div>
                                <label for="search" class="filter-label">Search</label>
                                <input type="text" id="search" name="search"
                                       value="{{ request('search') }}"
                                       placeholder="Name, DB, email, phone..."
                                       class="filter-input {{ request('search') ? 'filter-active' : '' }}">
                            </div>

                            {{-- Sort By --}}
                            <div>
                                <label for="sort_by" class="filter-label">Sort By</label>
                                <select name="sort_by" id="sort_by" class="filter-select">
                                    <option value="created_at_desc" {{ request('sort_by', 'created_at_desc') == 'created_at_desc' ? 'selected' : '' }}>Registration Date (Newest First)</option>
                                    <option value="created_at_asc"  {{ request('sort_by') == 'created_at_asc'  ? 'selected' : '' }}>Registration Date (Oldest First)</option>
                                    <option value="first_name_asc"  {{ request('sort_by') == 'first_name_asc'  ? 'selected' : '' }}>First Name (A-Z)</option>
                                    <option value="first_name_desc" {{ request('sort_by') == 'first_name_desc' ? 'selected' : '' }}>First Name (Z-A)</option>
                                </select>
                            </div>
                        </div>

                        {{-- Location cascade (spans 3 columns) --}}
                        <x-filters.location-cascade
                            :access-level="$accessLevel"
                            :branches="$branches"
                            :divisions="$divisions"
                            :red-cross-units="$redCrossUnits"
                            :branch-id="request('branch_id')"
                            :division-id="request('division_id')"
                            :unit-id="request('red_cross_unit_id')"
                            class="lg:col-span-3"
                        >
                            <x-slot:extraField>
                                <div class="flex flex-col space-y-0.5">
                                    <label for="task_force_id" class="text-xs font-medium text-gray-700">
                                        Task Force
                                    </label>
                                    <select id="task_force_id"
                                            name="task_force_id"
                                            class="w-full
                                               px-2 py-1 text-xs border border-gray-300 rounded-md shadow-sm truncate
                                               focus:outline-none focus:ring-blue-500 focus:border-blue-500
                                               {{ request('task_force_id') ? 'filter-active' : '' }}"
                                            disabled>
                                        <option value="">Select Branch First</option>
                                    </select>
                                </div>
                            </x-slot:extraField>
                        </x-filters.location-cascade>

                        {{-- Always-visible: Gender (+ Age) and Members/Volunteers --}}
                        <div class="flex flex-col gap-2">
                            <x-filters.demography class="lg:col-span-3" />

                            <div class="flex flex-col space-y-0.5">
                                <label for="person_type" class="text-xs font-medium text-gray-700">Members / Volunteers</label>
                                <select name="person_type" id="person_type" class="filter-select-small {{ request('person_type') ? 'filter-active' : '' }}">
                                    <option value="" {{ request('person_type', '') === '' ? 'selected' : '' }}>All</option>
                                    <option value="member"    {{ request('person_type') === 'member'    ? 'selected' : '' }}>Members</option>
                                    <option value="volunteer" {{ request('person_type') === 'volunteer' ? 'selected' : '' }}>Volunteers</option>
                                    <option value="unassigned" {{ request('person_type') === 'unassigned' ? 'selected' : '' }}>Unassigned</option>
                                </select>
                            </div>

                            <div class="flex flex-col space-y-0.5">
                                <label for="archived_filter" class="text-xs font-medium text-gray-700">Lifecycle Status</label>
                                <select name="archived_filter" id="archived_filter" class="filter-select-small {{ request('archived_filter', 'operational') !== 'operational' ? 'filter-active' : '' }}">
                                    <option value="operational"     {{ request('archived_filter', 'operational') == 'operational'     ? 'selected' : '' }}>Operational Users (default)</option>
                                    <option value="pending_engagement" {{ request('archived_filter') == 'pending_engagement' ? 'selected' : '' }}>Pending Engagement</option>
                                    <option value="active"          {{ request('archived_filter') == 'active'          ? 'selected' : '' }}>Active</option>
                                    <option value="dormant"         {{ request('archived_filter') == 'dormant'         ? 'selected' : '' }}>Dormant</option>
                                    <option value="archived"        {{ request('archived_filter') == 'archived'        ? 'selected' : '' }}>Archived</option>
                                    <option value="all"             {{ request('archived_filter') == 'all'             ? 'selected' : '' }}>All (Including Archived)</option>
                                </select>
                            </div>
                        </div>

                        {{-- "Show all filters" toggle button --}}
                        <div id="show-filters-btn-container" class="flex flex-col items-start gap-2 pt-6">
                            <button type="button" id="show-filters-btn"
                                    aria-expanded="{{ $anyHiddenFilterActive ? 'true' : 'false' }}"
                                    class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium bg-white text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 shadow-sm">
                                <i class="fas fa-sliders text-slate-500"></i>
                                <span id="show-filters-btn-label">{{ $anyHiddenFilterActive ? 'Hide filters' : 'Show all filters' }}</span>
                            </button>
                        </div>

                    </div>

                    {{-- Expanded filters — hidden by default unless a hidden filter is active --}}
                    <div id="filters-expanded"
                         class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 {{ $anyHiddenFilterActive ? '' : 'hidden' }}">

                        {{-- Column 1: Photo & Signature + Payments + Volunteering + Org --}}
                        <div class="flex flex-col gap-2">
                            <div>
                                <label for="photo_signature_filter" class="filter-label-small">Profile Photo &amp; Signature</label>
                                <select name="photo_signature_filter" id="photo_signature_filter" class="filter-select-small {{ request('photo_signature_filter') ? 'filter-active' : '' }}">
                                    <option value="" {{ request('photo_signature_filter', '') === '' ? 'selected' : '' }}>All</option>
                                    <option value="photo_yes" {{ request('photo_signature_filter') === 'photo_yes' ? 'selected' : '' }}>Has profile photo</option>
                                    <option value="photo_no"  {{ request('photo_signature_filter') === 'photo_no'  ? 'selected' : '' }}>No profile photo</option>
                                    <option value="sign_yes"  {{ request('photo_signature_filter') === 'sign_yes'  ? 'selected' : '' }}>Has signature</option>
                                    <option value="sign_no"   {{ request('photo_signature_filter') === 'sign_no'   ? 'selected' : '' }}>No signature</option>
                                </select>
                            </div>

                            <div>
                                <label for="membership_filter" class="filter-label-small">Payments</label>
                                <select name="membership_filter" id="membership_filter" class="filter-select-small {{ request('membership_filter') ? 'filter-active' : '' }}">
                                    <option value="" {{ request('membership_filter') === null || request('membership_filter') === '' ? 'selected' : '' }}>All</option>
                                    <option value="members"          {{ request('membership_filter') === 'members'          ? 'selected' : '' }}>Valid payments</option>
                                    <option value="expiring_14"      {{ request('membership_filter') === 'expiring_14'      ? 'selected' : '' }}>Will expire within 14 days</option>
                                    <option value="expiring_28"      {{ request('membership_filter') === 'expiring_28'      ? 'selected' : '' }}>Will expire within 28 days</option>
                                    <option value="expired_members"  {{ request('membership_filter') === 'expired_members'  ? 'selected' : '' }}>Expired payments</option>
                                    <option value="wants_membership" {{ request('membership_filter') === 'wants_membership' ? 'selected' : '' }}>Wants membership but not paid</option>
                                    <option value="high_value_members" {{ request('membership_filter') === 'high_value_members' ? 'selected' : '' }}>High-value members (above median fee tier)</option>
                                    @foreach ($personFeeNames as $name)
                                        <option value="{{ $name }}" {{ request('membership_filter') === $name ? 'selected' : '' }}>{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="volunteer_filter" class="filter-label-small">Wants to contribute as</label>
                                <select name="volunteer_filter" id="volunteer_filter"
                                        class="filter-select-small {{ request('volunteer_filter') ? 'filter-active' : '' }}">
                                    <option value="" {{ request('volunteer_filter', '') === '' ? 'selected' : '' }}>
                                        All
                                    </option>
                                    <option value="wants_volunteer"
                                        {{ request('volunteer_filter') === 'wants_volunteer' ? 'selected' : '' }}>
                                        Wants to volunteer
                                    </option>
                                    <option value="wants_member"
                                        {{ request('volunteer_filter') === 'wants_member' ? 'selected' : '' }}>
                                        Wants to be a member
                                    </option>
                                </select>
                            </div>

                            <div>
                                <label for="org_representatives" class="filter-label-small">Org Representatives</label>
                                <select name="org_representatives" id="org_representatives" class="filter-select-small {{ request('org_representatives') ? 'filter-active' : '' }}">
                                    <option value="" {{ request('org_representatives', '') === '' ? 'selected' : '' }}>All</option>
                                    <option value="1" {{ request('org_representatives') === '1' ? 'selected' : '' }}>Org representatives only</option>
                                </select>
                            </div>
                        </div>

                        {{-- Column 2: Digital Activity + Email + Registration + Database Roles + Team Leaders --}}
                        <div class="flex flex-col gap-2">
                            <div>
                                <label for="dormancy_filter" class="filter-label-small">Digital Activity</label>
                                <select name="dormancy_filter" id="dormancy_filter" class="filter-select-small {{ request('dormancy_filter') ? 'filter-active' : '' }}">
                                    <option value=""               {{ request('dormancy_filter', '') === ''               ? 'selected' : '' }}>All Users</option>
                                    <option value="digital_active" {{ request('dormancy_filter') === 'digital_active'     ? 'selected' : '' }}>Digitally Active (≤6mo since last login)</option>
                                    <option value="digital_dormant" {{ request('dormancy_filter') === 'digital_dormant'   ? 'selected' : '' }}>Digitally Dormant (&gt;6mo no login)</option>
                                    <option value="never_logged_in" {{ request('dormancy_filter') === 'never_logged_in'   ? 'selected' : '' }}>Never logged in</option>
                                </select>
                            </div>

                            <div>
                                <label for="email_status" class="filter-label-small">Email</label>
                                <select name="email_status" id="email_status" class="filter-select-small {{ request('email_status') ? 'filter-active' : '' }}">
                                    <option value=""       {{ request('email_status') == ''        ? 'selected' : '' }}>All</option>
                                    <option value="with"   {{ request('email_status') == 'with'    ? 'selected' : '' }}>With email</option>
                                    <option value="without" {{ request('email_status') == 'without' ? 'selected' : '' }}>Without email</option>
                                </select>
                            </div>

                            <div>
                                <label for="registration_filter" class="filter-label-small">Registration Source</label>
                                <select name="registration_filter" id="registration_filter" class="filter-select-small {{ request('registration_filter') ? 'filter-active' : '' }}">
                                    <option value=""      {{ request('registration_filter') === null  ? 'selected' : '' }}>All</option>
                                    <option value="admin" {{ request('registration_filter') === 'admin' ? 'selected' : '' }}>Registered by Admin</option>
                                    <option value="self"  {{ request('registration_filter') === 'self'  ? 'selected' : '' }}>Self-registered</option>
                                </select>
                            </div>

                            <div>
                                <label for="database_role_filter" class="filter-label-small">Database Roles</label>
                                <select name="database_role_filter" id="database_role_filter" class="filter-select-small {{ request('database_role_filter') ? 'filter-active' : '' }}">
                                    <option value="" {{ request('database_role_filter', '') === '' ? 'selected' : '' }}>All</option>
                                    <option value="any" {{ request('database_role_filter') === 'any' ? 'selected' : '' }}>All database roles</option>
                                    <option value="branch_national" {{ request('database_role_filter') === 'branch_national' ? 'selected' : '' }}>Database roles branch & national</option>
                                </select>
                            </div>

                            <div>
                                <label for="team_leader_filter" class="filter-label-small">Team Leaders</label>
                                <select name="team_leader_filter" id="team_leader_filter" class="filter-select-small {{ request('team_leader_filter') ? 'filter-active' : '' }}">
                                    <option value="" {{ request('team_leader_filter', '') === '' ? 'selected' : '' }}>All</option>
                                    <option value="rc_unit" {{ request('team_leader_filter') === 'rc_unit' ? 'selected' : '' }}>RC Unit Team Leaders</option>
                                    <option value="task_force" {{ request('team_leader_filter') === 'task_force' ? 'selected' : '' }}>Task Force Team Leaders</option>
                                    <option value="all" {{ request('team_leader_filter') === 'all' ? 'selected' : '' }}>All Team Leaders</option>
                                </select>
                            </div>
                        </div>

                        {{-- Column 3: Trainings + First Aid + Donations + Campaign Messages --}}
                        <div class="flex flex-col gap-2">
                            <div>
                                <label for="training_filter" class="filter-label-small">Trainings</label>
                                <select name="training_filter" id="training_filter" class="filter-select-small {{ request('training_filter') ? 'filter-active' : '' }}">
                                    <option value=""        {{ request('training_filter', '') === '' ? 'selected' : '' }}>All</option>
                                    <option value="has_any" {{ request('training_filter') === 'has_any' ? 'selected' : '' }}>Has any training</option>
                                    <option value="none_any"       {{ request('training_filter') === 'none_any'       ? 'selected' : '' }}>No trainings at all</option>
                                    <option value="has_firstaid"   {{ request('training_filter') === 'has_firstaid'   ? 'selected' : '' }}>Has any first aid training</option>
                                    <option value="none_firstaid"  {{ request('training_filter') === 'none_firstaid'  ? 'selected' : '' }}>No first aid training</option>
                                    @if(isset($trainingTypes))
                                        @foreach($trainingTypes as $type)
                                            <optgroup label="{{ $type->name }}">
                                                <option value="has_{{ $type->id }}"  {{ request('training_filter') === "has_{$type->id}"  ? 'selected' : '' }}>Has training in {{ strtolower($type->name) }}</option>
                                                <option value="none_{{ $type->id }}" {{ request('training_filter') === "none_{$type->id}" ? 'selected' : '' }}>No training in {{ strtolower($type->name) }}</option>
                                            </optgroup>
                                        @endforeach
                                    @endif
                                </select>
                            </div>

                            <div>
                                <label for="training_expiry" class="filter-label-small">Training Expiry</label>
                                <select name="training_expiry" id="training_expiry" class="filter-select-small {{ request('training_expiry') ? 'filter-active' : '' }}">
                                    <option value="">All</option>
                                    @foreach($trainingTypesWithExpiry as $type)
                                        <optgroup label="{{ $type->name }}">
                                            <option value="{{ $type->id }}|28"      {{ request('training_expiry') === $type->id.'|28'      ? 'selected' : '' }}>{{ $type->name }} — expires within 28 days</option>
                                            <option value="{{ $type->id }}|21"      {{ request('training_expiry') === $type->id.'|21'      ? 'selected' : '' }}>{{ $type->name }} — expires within 21 days</option>
                                            <option value="{{ $type->id }}|14"      {{ request('training_expiry') === $type->id.'|14'      ? 'selected' : '' }}>{{ $type->name }} — expires within 14 days</option>
                                            <option value="{{ $type->id }}|7"       {{ request('training_expiry') === $type->id.'|7'       ? 'selected' : '' }}>{{ $type->name }} — expires within 7 days</option>
                                            <option value="{{ $type->id }}|expired" {{ request('training_expiry') === $type->id.'|expired' ? 'selected' : '' }}>{{ $type->name }} — has expired (not renewed)</option>
                                        </optgroup>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="first_aid_refresher" class="filter-label-small">First Aid Refresher</label>
                                @php $faRefresherMonths = range(12, 60, 3); @endphp
                                <select name="first_aid_refresher" id="first_aid_refresher"
                                        class="filter-select-small {{ request('first_aid_refresher') ? 'filter-active' : '' }}">
                                    <option value="" {{ request('first_aid_refresher', '') === '' ? 'selected' : '' }}>All</option>
                                    @foreach($faRefresherMonths as $m)
                                        <option value="{{ $m }}" {{ request('first_aid_refresher') === (string) $m ? 'selected' : '' }}>
                                            First aid older than {{ $m }} months
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="donation_filter" class="filter-label-small">Donations</label>
                                <select name="donation_filter" id="donation_filter" class="filter-select-small {{ request('donation_filter') ? 'filter-active' : '' }}">
                                    <option value=""    {{ request('donation_filter', '') === '' ? 'selected' : '' }}>All</option>
                                    <option value="has" {{ request('donation_filter') === 'has' ? 'selected' : '' }}>Has made donations</option>
                                    <option value="none" {{ request('donation_filter') === 'none' ? 'selected' : '' }}>No donations</option>
                                </select>
                            </div>

                            <div>
                                @php
                                    // Wizard shortcuts append a third "|{days}" segment (e.g.
                                    // "training_expiry|0|180") to campaign_msg; match only on the
                                    // slug + expr segments so the dropdown reflects the applied
                                    // filter regardless of any day-window suffix.
                                    $campaignMsgKey = collect(explode('|', (string) request('campaign_msg', '')))->take(2)->implode('|');
                                @endphp
                                <label for="campaign_msg" class="filter-label-small">Campaign Messages</label>
                                <select name="campaign_msg" id="campaign_msg" class="filter-select-small {{ request('campaign_msg') ? 'filter-active' : '' }}">
                                    <option value="">All</option>
                                    @foreach($campaignPurposes as $purpose)
                                        <optgroup label="{{ $purpose->name }}">
                                            <option value="{{ $purpose->slug }}|0"
                                                {{ $campaignMsgKey === $purpose->slug.'|0' ? 'selected' : '' }}>
                                                {{ $purpose->name }} — not yet contacted
                                            </option>
                                            <option value="{{ $purpose->slug }}|<=1"
                                                {{ $campaignMsgKey === $purpose->slug.'|<=1' ? 'selected' : '' }}>
                                                {{ $purpose->name }} — contacted at most once
                                            </option>
                                            <option value="{{ $purpose->slug }}|<=2"
                                                {{ $campaignMsgKey === $purpose->slug.'|<=2' ? 'selected' : '' }}>
                                                {{ $purpose->name }} — contacted at most twice
                                            </option>
                                            <option value="{{ $purpose->slug }}|>=3"
                                                {{ $campaignMsgKey === $purpose->slug.'|>=3' ? 'selected' : '' }}>
                                                {{ $purpose->name }} — contacted 3 or more times
                                            </option>
                                        </optgroup>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                    </div>{{-- end #filters-expanded --}}


                    <input type="hidden" name="view_mode" value="{{ $viewMode }}">
                    <input type="hidden" name="wizard_purpose" value="{{ request('wizard_purpose') }}">

                    <div class="filter-actions">
                        <div class="filter-button-group">
                            <button type="submit" class="filter-btn-primary">
                                <i class="fas fa-search mr-1"></i>Filter
                            </button>
                            <a @if($hasFilters) href="{{ route('users.index') }}" @endif
                            class="filter-btn-secondary {{ $hasFilters ? 'filter-btn-secondary-active' : 'filter-btn-disabled' }}">
                                <i class="fas fa-times mr-1"></i>Clear
                            </a>

                        </div>

                    </div>
                </form>

                @can('campaign_request_create')
                    <div class="border-t border-gray-200 pt-3 mt-3">
                        <div class="mt-3">
                            <button type="button" id="open-wizard-btn"
                                class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-medium bg-slate-700 text-white hover:bg-slate-800 shadow-sm">
                                <i class="fas fa-wand-magic-sparkles"></i>
                                Campaign filter wizard
                            </button>
                        </div>

                        <form method="POST" action="{{ route('campaigns.wizard.start') }}" class="inline-block mt-2">
                            @csrf
                            <input type="hidden" name="filter_json" value='@json(request()->except(["page"]))'>

                            <button
                                type="submit"
                                {{-- Disable if NOT hasFilters --}}
                                @disabled(!$hasFilters)
                                class="inline-flex items-center rounded-md px-3 py-2 text-sm font-semibold text-white transition-colors
                            {{-- If NO filters, show gray. If filters exist, show slate --}}
                            {{ !$hasFilters
                                ? 'bg-gray-300 cursor-not-allowed'
                                : 'bg-slate-700 hover:bg-slate-800'
                            }}"
                            >
                                <i class="fas fa-wand-magic-sparkles mr-1"></i>
                                Make campaign from filter
                            </button>
                        </form>
                    </div>
                @endcan

            </div>


        </div>



        {{-- Results summary --}}
        <div class="flex flex-wrap flex-col px-6 pt-4 pb-2">
            <div class="text-xl text-gray-600">
                Found {{ $users->total() }} result{{ $users->total()==1 ? '' : 's' }}
            </div>
            <div class="text-lg text-gray-700 font-medium">
                {!! $filterDescriptionHtml !!}
            </div>
        </div>

        {{-- Display preference: show profile photos (per-browser cookie, set via JS) --}}
        <div class="flex justify-end px-6 mt-2">
            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" id="toggle-show-photos" {{ $showPhotos ? 'checked' : '' }} class="rounded border-gray-300">
                Show profile photos
            </label>
        </div>

        {{-- View Mode Tabs --}}
        @php
            $toggleBase = array_merge(request()->query(), []);
            $tabs = [
                'standard'     => ['label' => 'Standard View',  'icon' => 'fa-table-list'],
                'volunteering' => ['label' => 'Volunteering',    'icon' => 'fa-hands-helping'],
                'trainings'    => ['label' => 'Trainings',       'icon' => 'fa-graduation-cap'],
                'donations'    => ['label' => 'Donations',       'icon' => 'fa-hand-holding-heart'],
                'certificates' => ['label' => 'Certificates',   'icon' => 'fa-certificate'],
                'campaigns'    => ['label' => 'Campaigns',       'icon' => 'fa-envelope'],
            ];
        @endphp
        <div class="flex gap-0 px-6 border-b border-gray-200 mt-2">
            @foreach($tabs as $mode => $tab)
                <a href="{{ route('users.index', array_merge($toggleBase, ['view_mode' => $mode])) }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium border border-b-0 transition-colors whitespace-nowrap
                       {{ $viewMode === $mode
                           ? 'bg-white border-gray-200 text-indigo-700 font-semibold rounded-t-md -mb-px'
                           : 'bg-gray-50 border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-t-md' }}">
                    <i class="fas {{ $tab['icon'] }} text-xs"></i>
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </div>

        <!-- Users Table -->

        <div class="bg-white shadow rounded-lg w-full">
            @if($users->count() > 0)
                <div class="overflow-x-auto w-full">
                    <table class="min-w-full table-fixed w-full" style="min-width: 700px;">
                        <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="w-[180px] min-w-[120px] px-3 py-2 table-heading">Person</th>
                            <th class="w-[180px] min-w-[120px] px-3 py-2 table-heading">Location/Contact</th>
                            @if($viewMode === 'certificates')
                                <th colspan="3" class="px-3 py-2 table-heading">Certificates Printed</th>
                            @elseif($viewMode === 'trainings')
                                <th colspan="3" class="px-3 py-2 table-heading">Trainings &amp; Certificates</th>
                            @elseif($viewMode === 'campaigns')
                                <th colspan="3" class="px-3 py-2 table-heading">Campaigns</th>
                            @elseif($viewMode === 'donations')
                                <th colspan="3" class="px-3 py-2 table-heading">Donations</th>
                            @elseif($viewMode === 'id_cards')
                                <th colspan="3" class="px-3 py-2 table-heading">ID Card Status</th>
                            @elseif($viewMode === 'volunteering')
                                <th colspan="3" class="px-3 py-2 table-heading">Volunteering</th>
                            @else
                                <th class="w-[120px] min-w-[90px] px-3 py-2 table-heading whitespace-nowrap">Active/Passive</th>
                                <th class="w-[180px] min-w-[90px] px-3 py-2 table-heading whitespace-nowrap">Dates</th>
                                <th class="w-[120px] min-w-[100px] px-3 py-2 table-heading">Status</th>
                            @endif
                            <th class="w-[160px] min-w-[120px] px-3 py-2 table-heading">Actions</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-sm">
                        @foreach($users as $user)
                            <tr class="hover:bg-gray-50">

                                {{-- Person --}}
                                <td class="px-3 py-2  max-w-[180px]">
                                    <x-user-profile-badge :user="$user" size="md" :show-photo="$showPhotos"/>
                                    @if($showPhotos)
                                        <div class="flex items-center gap-2 text-gray-400 text-xs">
                                            @if(is_null($user->image_age_in_years))
                                                <span class="text-gray-500 italic"></span>
                                            @else
                                                <span>{{ $user->photo_age_label }}</span>

                                                @if($user->image_is_too_old)
                                                    <i class="fas fa-exclamation-triangle text-yellow-500"
                                                       title="Photo is too old"></i>
                                                @endif
                                            @endif
                                        </div>
                                    @endif

                                </td>

                                {{-- Location --}}
                                <td class="px-3 py-2 truncate max-w-[180px]">
                                    <div class="text-sm text-gray-900 truncate">{{ $user->branch->code ?? 'No branch' }} {{ $user->division->name ?? 'No division' }}</div>
                                    <x-user-rcunit-taskforce-badge :user="$user" />

                                    <div class="text-gray-900 truncate mt-1">{{ $user->email ?? 'No email' }}</div>
                                    <div class="text-gray-500 truncate">{{ $user->primary_phone ?? 'No phone' }}</div>


                                </td>

                                @if($viewMode === 'certificates')
                                    <td colspan="3" class="px-3 py-2 align-top text-sm">
                                        @forelse($user->certificatePrints as $cert)
                                            <div class="leading-snug py-0.5">
                                                <span class="font-medium">{{ $cert->certificate_type }}</span>
                                                @if($cert->training)
                                                    <span class="text-gray-500"> | {{ $cert->training->name }}@if($cert->training->trainingType) ({{ $cert->training->trainingType->name }})@endif</span>
                                                @endif
                                                <span class="text-gray-400 text-xs ml-1"><x-time-ago :date="$cert->printed_at" placeholder="" /></span>
                                            </div>
                                        @empty
                                            <span class="text-gray-400 italic text-xs">None</span>
                                        @endforelse
                                    </td>
                                @elseif($viewMode === 'volunteering')
                                    <td colspan="3" class="px-3 py-2 align-top text-sm">
                                        @if($user->volunteering_total_count === 0 || $user->volunteering_total_hours == 0)
                                            <span class="text-gray-400 italic text-xs">No volunteering recorded.</span>
                                        @else
                                            <div class="leading-snug py-0.5">
                                                <span class="font-medium">Total: {{ $user->volunteering_total_hours }} hours</span>
                                            </div>
                                            @if($user->volunteering_main_activity)
                                                <div class="leading-snug py-0.5">
                                                    <span class="text-gray-600 text-xs">Mainly: {{ $user->volunteering_main_activity }}</span>
                                                </div>
                                            @endif
                                            @foreach($user->recentActivities as $activity)
                                                <div class="leading-snug py-0.5">
                                                    {{ $activity->hours }} hrs — {{ $activity->activityType?->name ?? '—' }} — <x-time-ago :date="$activity->date" placeholder="—" />
                                                </div>
                                            @endforeach
                                            @if($user->volunteering_total_count > 8)
                                                <span class="text-gray-400 text-xs italic">
                                                    …and {{ $user->volunteering_total_count - 8 }} more. View profile for full list.
                                                </span>
                                            @endif
                                        @endif
                                    </td>
                                @elseif($viewMode === 'id_cards')
                                    @php
                                        $latestIdCardPrint = $user->idCardPrints->sortByDesc('printed_at')->first();
                                        $lastPrintedDate   = $latestIdCardPrint?->printed_at;
                                        $membershipFeeName = $user->currentMembershipPayment?->membershipFee?->name;
                                        $lastIdPaymentDate = $user->last_id_card_payment_date;
                                    @endphp
                                    <td colspan="3" class="px-3 py-2 align-top text-sm">
                                        {{-- Line 1: Last printed --}}
                                        <div class="leading-snug py-0.5">
                                            @if($lastPrintedDate)
                                                Last ID printed: <x-time-ago :date="$lastPrintedDate" />
                                            @else
                                                <span class="text-gray-400 italic">No ID printed</span>
                                            @endif
                                        </div>
                                        {{-- Line 2: National ID --}}
                                        <div class="leading-snug py-0.5">
                                            National ID:
                                            @if($user->national_id_number)
                                                {{ $user->national_id_number }}
                                            @else
                                                <span class="text-red-600">Missing</span>
                                            @endif
                                        </div>
                                        {{-- Line 3: Membership --}}
                                        <div class="leading-snug py-0.5">
                                            Membership:
                                            @if($membershipFeeName)
                                                {{ $membershipFeeName }}
                                            @else
                                                <span class="text-red-600">No membership</span>
                                            @endif
                                        </div>
                                        {{-- Line 4: Last ID payment --}}
                                        <div class="leading-snug py-0.5">
                                            Last ID payment:
                                            @if($lastIdPaymentDate)
                                                <x-time-ago :date="\Carbon\Carbon::parse($lastIdPaymentDate)" />
                                            @else
                                                <span class="text-red-600">N/A</span>
                                            @endif
                                        </div>
                                        {{-- Line 5: Photo --}}
                                        <div class="leading-snug py-0.5">
                                            Photo:
                                            @if($user->picture)
                                                OK
                                            @else
                                                <span class="text-red-600">Not uploaded</span>
                                            @endif
                                        </div>
                                        {{-- Line 6: Signature --}}
                                        <div class="leading-snug py-0.5">
                                            Signature:
                                            @if($user->hasSignature())
                                                OK
                                            @else
                                                <span class="text-red-600">Not uploaded</span>
                                            @endif
                                        </div>
                                    </td>
                                @elseif($viewMode === 'donations')
                                    <td colspan="3" class="px-3 py-2 align-top text-sm">
                                        @if($user->donations_total_count === 0)
                                            <span class="text-gray-400 italic text-xs">None</span>
                                        @else
                                            <div class="leading-snug py-0.5">
                                                <span class="font-medium">
                                                    {{ $user->donations_cash_count }} Cash
                                                    — {{ $user->donations_inkind_count }} In-Kind
                                                </span>
                                            </div>
                                            @if($user->donations_cash_count > 0)
                                                <div class="leading-snug py-0.5">
                                                    <span class="text-gray-600 text-xs">
                                                        Total: ₦{{ number_format($user->donations_cash_total, 0) }}
                                                    </span>
                                                </div>
                                            @endif
                                            @foreach($user->recentDonations as $donation)
                                                <div class="leading-snug py-0.5">
                                                    @if(!$donation->in_kind_donation)
                                                        ₦{{ number_format($donation->amount, 0) }} — <x-time-ago :date="$donation->date_donation" placeholder="—" />
                                                    @else
                                                        {{ $donation->amount }} {{ $donation->donation_item ?? 'In-Kind' }} — <x-time-ago :date="$donation->date_donation" placeholder="—" />
                                                    @endif
                                                </div>
                                            @endforeach
                                            @if($user->donations_total_count > 8)
                                                <span class="text-gray-400 text-xs italic">
                                                    …and {{ $user->donations_total_count - 8 }} more. View profile for full list.
                                                </span>
                                            @endif
                                        @endif
                                    </td>
                                @elseif($viewMode === 'campaigns')
                                    <td colspan="3" class="px-3 py-2 align-top text-sm">
                                        @if($user->campaignRecipients->isEmpty())
                                            <span class="text-gray-400 italic text-xs">None</span>
                                        @else
                                            @foreach($user->campaignRecipients as $recipient)
                                                <div class="leading-snug py-0.5">
                                                    {{ $recipient->campaign?->title ?? '—' }}
                                                    —
                                                    <x-time-ago :date="$recipient->campaign?->send_completed_at ?? $recipient->campaign?->created_at" placeholder="—" />
                                                </div>
                                            @endforeach
                                            @if($user->campaign_recipients_total > 10)
                                                <span class="text-gray-400 text-xs italic">
                                                    …and {{ $user->campaign_recipients_total - 10 }} more. View profile for full list.
                                                </span>
                                            @endif
                                        @endif
                                    </td>
                                @elseif($viewMode === 'trainings')
                                    @php
                                        $trainingItems = $user->trainings->map(fn($t) => [
                                            'date'  => $t->training_date,
                                            'label' => 'Training',
                                            'detail' => $t->trainingType?->name,
                                            'extra' => null,
                                        ]);
                                        $certItems = $user->certificatePrints
                                            ->whereIn('certificate_type', ['training_attendance', 'training_competence'])
                                            ->map(fn($c) => [
                                                'date'  => $c->printed_at,
                                                'label' => 'Certificate',
                                                'detail' => $c->certificate_type,
                                                'extra' => $c->training?->trainingType?->name,
                                            ]);
                                        $merged = $trainingItems->concat($certItems)
                                            ->sortByDesc(fn($item) => optional($item['date'])->timestamp ?? 0)
                                            ->values();
                                    @endphp
                                    <td colspan="3" class="px-3 py-2 align-top text-sm">
                                        @if($merged->isEmpty())
                                            <span class="text-gray-400 italic text-xs">None</span>
                                        @else
                                            @foreach($merged as $item)
                                                <div class="leading-snug py-0.5">
                                                    <span class="font-medium">{{ $item['label'] }}:</span>
                                                    <span class="text-gray-700">{{ $item['detail'] }}@if($item['extra']) ({{ $item['extra'] }})@endif</span>
                                                    <span class="text-gray-400 text-xs ml-1"><x-time-ago :date="$item['date']" placeholder="" /></span>
                                                </div>
                                            @endforeach
                                        @endif
                                    </td>
                                @else
                                    {{-- Status --}}
                                    <td class="px-3  whitespace-nowrap text-gray-900 max-w-[120px]">
                                        <div class="flex flex-col text-xs leading-tight space-y-1">
                                            <x-user-membership-status-badge :user="$user" />
                                            <x-user-lifecycle-status-badge :user="$user" />
                                            <x-user-digital-status-badge :user="$user" />
                                        </div>
                                    </td>

                                    {{-- Date --}}
                                    <td class="px-3 py-2 whitespace-nowrap text-gray-900 max-w-[120px]">
                                        <div class="mb-1">
                                            <span class="text-gray-600">{{ $user->is_form_registration ? 'Reg Admin' : 'Self-reg: ' }} <span class="text-gray-600 font-bold"><x-time-ago :date="$user->created_at" /></span></span>
                                        </div>
                                        <div class="mb-1">
                                            <span class="text-gray-600">Last Act: <span class="text-gray-600 font-bold"><x-time-ago :date="$user->last_activity_at" /></span></span>
                                        </div>
                                        @php $roleName = $user->getRoleNames()->first(); @endphp
                                        @if($roleName)
                                            <div class="mb-1">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700">
                                                    {{ Str::title(str_replace('_', ' ', $roleName)) }}
                                                </span>
                                            </div>
                                        @endif
                                    </td>

                                    {{-- Status --}}
                                    <td class="pl-3 pr-0 py-2 align-top">
                                        <div class="flex flex-col gap-y-1 items-start">
                                            <x-user-donation-status-badge :user="$user" />
                                            <x-user-training-status-badge :user="$user" />
                                            <x-user-first-aid-status-badge :user="$user" />
                                        </div>
                                    </td>
                                @endif

                                {{-- Actions  --}}
                                <td class="table-body-cell">
                                    <div class="flex gap-2">
                                        <a href="{{ route('users.show', $user) }}"
                                           class="btn-primary whitespace-nowrap"
                                           target="_blank">
                                            View<i class="fa-solid fa-up-right-from-square ml-1"></i>
                                        </a>
                                        @can('edit_user')
                                            <a href="{{ route('users.edit', $user) }}"
                                               target="_blank"
                                               class="btn-edit whitespace-nowrap">Edit<i class="fa-solid fa-up-right-from-square ml-1"></i></a>
                                        @endcan
                                    </div>
                                </td>

                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div class="px-3 py-2 bg-gray-50 border-t">
                    {{ $users->links() }}
                </div>
            @else
                <div class="px-6 py-12 text-center">
                    <i class="fas fa-users text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No users found</h3>
                    <p class="text-gray-500">Try adjusting your search or filter criteria.</p>
                </div>
            @endif
        </div>

    </div>

    {{-- Campaign Filter Wizard modal --}}
    <div id="wizard-modal" class="fixed inset-0 z-50 hidden">
        <!-- Backdrop -->
        <div id="wizard-backdrop"
             class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>

        <!-- Panel -->
        <div class="absolute inset-y-0 right-0 w-full max-w-lg bg-white shadow-2xl flex flex-col">

            <!-- Header -->
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-slate-50">
                <div>
                    <h2 class="text-lg font-semibold text-slate-800">
                        <i class="fas fa-wand-magic-sparkles mr-2 text-slate-500"></i>
                        Campaign Filter Wizard
                    </h2>
                    <p class="text-sm text-slate-500 mt-0.5">
                        Choose a goal to pre-fill the right filters for your campaign.
                    </p>
                </div>
                <button type="button" id="close-wizard-btn"
                        class="text-slate-400 hover:text-slate-600 text-xl leading-none">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Scrollable accordion body -->
            <div class="flex-1 overflow-y-auto px-4 py-4 space-y-2" id="wizard-accordion">

                <!-- Section 1 -->
                <div class="wizard-section rounded-lg border border-slate-200 overflow-hidden">
                    <button type="button"
                            class="wizard-section-btn wizard-section-toggle w-full flex items-center justify-between px-4 py-2 text-left transition-colors">
                        <span class="flex items-center gap-3">
                            <i class="fas fa-user-plus text-slate-300 w-4"></i>
                            <span class="font-medium text-white">Welcome newly registered persons</span>
                        </span>
                        <i class="fas fa-chevron-down wizard-chevron text-slate-300 transition-transform duration-200"></i>
                    </button>
                    <div class="wizard-section-body hidden px-4 py-4 bg-white text-sm text-slate-600 border-t border-slate-100">

                        <p class="text-slate-600 mb-4">
                            Find people who have registered but not yet become fully active.
                            Filters applied: Lifecycle Status = Pending Engagement.
                        </p>

                        {{-- Sort order --}}
                        <fieldset class="mb-5">
                            <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                Start with
                            </legend>
                            <div class="flex flex-col gap-2">
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_new_sort" value="created_at_desc"
                                           class="text-slate-700 border-slate-300" checked>
                                    <span>
                                        <span class="font-medium text-slate-800">Newest registrations first</span>
                                        <span class="block text-xs text-slate-400">Those who just joined</span>
                                    </span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_new_sort" value="created_at_asc"
                                           class="text-slate-700 border-slate-300">
                                    <span>
                                        <span class="font-medium text-slate-800">Oldest registrations first</span>
                                        <span class="block text-xs text-slate-400">Those who have been waiting longest</span>
                                    </span>
                                </label>
                            </div>
                        </fieldset>

                        {{-- Contribution preference --}}
                        <fieldset class="mb-4">
                            <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                Wants to contribute as
                            </legend>
                            <div class="flex flex-col gap-2">
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_new_contribution" value="wants_volunteer"
                                           class="text-slate-700 border-slate-300" checked>
                                    <span>
                                        <span class="font-medium text-slate-800">Wants to volunteer</span>
                                        <span class="block text-xs text-slate-400">Persons who expressed interest in volunteering</span>
                                    </span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_new_contribution" value="wants_member"
                                           class="text-slate-700 border-slate-300">
                                    <span>
                                        <span class="font-medium text-slate-800">Wants to be a member</span>
                                        <span class="block text-xs text-slate-400">Persons who expressed interest in membership</span>
                                    </span>
                                </label>
                            </div>
                        </fieldset>

                        {{-- Message history --}}
                        <fieldset class="mb-5">
                            <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                Who to include
                            </legend>
                            <div class="flex flex-col gap-2">
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_new_msg" value="onboarding|0"
                                           class="text-slate-700 border-slate-300" checked>
                                    <span>
                                        <span class="font-medium text-slate-800">Not yet contacted</span>
                                        <span class="block text-xs text-slate-400">Only those who haven't received any onboarding message</span>
                                    </span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_new_msg" value="onboarding|<=1"
                                           class="text-slate-700 border-slate-300">
                                    <span>
                                        <span class="font-medium text-slate-800">Contacted at most once</span>
                                        <span class="block text-xs text-slate-400">Those who received 0 or 1 onboarding messages — give them one more chance</span>
                                    </span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_new_msg" value=""
                                           class="text-slate-700 border-slate-300">
                                    <span>
                                        <span class="font-medium text-slate-800">Everyone in Pending Engagement</span>
                                        <span class="block text-xs text-slate-400">Regardless of prior onboarding messages</span>
                                    </span>
                                </label>
                            </div>
                        </fieldset>

                        {{-- Execute --}}
                        <button type="button" id="wizard-apply-new-members"
                                class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold bg-slate-700 text-white hover:bg-slate-800 shadow-sm w-full justify-center">
                            <i class="fas fa-filter"></i>
                            Apply filter &amp; close
                        </button>

                    </div>
                </div>

                <!-- Section 2 -->
                <div class="wizard-section rounded-lg border border-slate-200 overflow-hidden">
                    <button type="button"
                            class="wizard-section-btn wizard-section-toggle w-full flex items-center justify-between px-4 py-2 text-left transition-colors">
                        <span class="flex items-center gap-3">
                            <i class="fas fa-user-clock text-slate-300 w-4"></i>
                            <span class="font-medium text-white">Re-engage dormant volunteers</span>
                        </span>
                        <i class="fas fa-chevron-down wizard-chevron text-slate-300 transition-transform duration-200"></i>
                    </button>
                    <div class="wizard-section-body hidden px-4 py-4 bg-white text-sm text-slate-600 border-t border-slate-100">

                        <p class="text-slate-600 mb-4">
                            Find volunteers who have become inactive and may need a nudge to re-engage.
                            Filters applied: <strong>Lifecycle Status = Dormant</strong>, <strong>Members / Volunteers = Volunteers</strong>.
                        </p>

                        {{-- Who to include --}}
                        <fieldset class="mb-5">
                            <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                Who to include
                            </legend>
                            <div class="flex flex-col gap-2">
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_dormant_msg" value="dormant_reengagement|0"
                                           class="text-slate-700 border-slate-300" checked>
                                    <span>
                                        <span class="font-medium text-slate-800">Not yet contacted</span>
                                        <span class="block text-xs text-slate-400">Only those who haven't received any re-engagement message</span>
                                    </span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_dormant_msg" value="dormant_reengagement|<=1"
                                           class="text-slate-700 border-slate-300">
                                    <span>
                                        <span class="font-medium text-slate-800">Contacted at most once</span>
                                        <span class="block text-xs text-slate-400">Those who received 0 or 1 re-engagement messages</span>
                                    </span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_dormant_msg" value="dormant_reengagement|<=2"
                                           class="text-slate-700 border-slate-300">
                                    <span>
                                        <span class="font-medium text-slate-800">Contacted at most twice</span>
                                        <span class="block text-xs text-slate-400">Those who received 0, 1 or 2 re-engagement messages</span>
                                    </span>
                                </label>
                            </div>
                        </fieldset>

                        {{-- Execute --}}
                        <button type="button" id="wizard-apply-dormant"
                                class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold bg-slate-700 text-white hover:bg-slate-800 shadow-sm w-full justify-center">
                            <i class="fas fa-filter"></i>
                            Apply filter &amp; close
                        </button>

                    </div>
                </div>

                <!-- Section 3 -->
                <div class="wizard-section rounded-lg border border-slate-200 overflow-hidden">
                    <button type="button"
                            class="wizard-section-btn wizard-section-toggle w-full flex items-center justify-between px-4 py-2 text-left transition-colors">
                        <span class="flex items-center gap-3">
                            <i class="fas fa-id-card text-slate-300 w-4"></i>
                            <span class="font-medium text-white">Remind about expiring membership</span>
                        </span>
                        <i class="fas fa-chevron-down wizard-chevron text-slate-300 transition-transform duration-200"></i>
                    </button>
                    <div class="wizard-section-body hidden px-4 py-4 bg-white text-sm text-slate-600 border-t border-slate-100">

                        <p class="text-slate-600 mb-4">
                            Target members based on their membership expiry status.
                            Choose whether to reach out <strong>before</strong> or <strong>after</strong> expiry.
                        </p>

                        {{-- Before / After toggle --}}
                        <fieldset class="mb-4">
                            <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                Expiry timing
                            </legend>
                            <div class="flex gap-3">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="wizard_membership_timing" value="before"
                                           class="text-slate-700 border-slate-300" checked>
                                    <span class="font-medium text-slate-800">Before expiry</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="wizard_membership_timing" value="after"
                                           class="text-slate-700 border-slate-300">
                                    <span class="font-medium text-slate-800">After expiry</span>
                                </label>
                            </div>
                        </fieldset>

                        {{-- Before expiry options --}}
                        <div id="wizard-membership-before">

                            <fieldset class="mb-3">
                                <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                    Expiry window
                                </legend>
                                <div class="flex gap-3">
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="wizard_membership_window" value="expiring_14"
                                               class="text-slate-700 border-slate-300" checked>
                                        <span class="font-medium text-slate-800">Within 14 days</span>
                                    </label>
                                    <label class="inline-flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="wizard_membership_window" value="expiring_28"
                                               class="text-slate-700 border-slate-300">
                                        <span class="font-medium text-slate-800">Within 28 days</span>
                                    </label>
                                </div>
                            </fieldset>

                            <fieldset class="mb-5">
                                <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                    Who to include
                                </legend>
                                <div class="flex flex-col gap-2">
                                    <label class="inline-flex items-center gap-3 cursor-pointer">
                                        <input type="radio" name="wizard_membership_before_msg" value="membership_pre_expiry|0"
                                               class="text-slate-700 border-slate-300" checked>
                                        <span>
                                            <span class="font-medium text-slate-800">Not yet contacted</span>
                                            <span class="block text-xs text-slate-400">Only those who haven't received any pre-expiry reminder</span>
                                        </span>
                                    </label>
                                    <label class="inline-flex items-center gap-3 cursor-pointer">
                                        <input type="radio" name="wizard_membership_before_msg" value="membership_pre_expiry|<=1"
                                               class="text-slate-700 border-slate-300">
                                        <span>
                                            <span class="font-medium text-slate-800">Contacted at most once</span>
                                            <span class="block text-xs text-slate-400">Those who received 0 or 1 pre-expiry reminders</span>
                                        </span>
                                    </label>
                                    <label class="inline-flex items-center gap-3 cursor-pointer">
                                        <input type="radio" name="wizard_membership_before_msg" value="membership_pre_expiry|<=2"
                                               class="text-slate-700 border-slate-300">
                                        <span>
                                            <span class="font-medium text-slate-800">Contacted at most twice</span>
                                            <span class="block text-xs text-slate-400">Those who received 0, 1 or 2 pre-expiry reminders</span>
                                        </span>
                                    </label>
                                </div>
                            </fieldset>

                            <fieldset class="mb-3">
                                <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                    Audience
                                </legend>
                                <div class="flex flex-col gap-2">
                                    <label class="inline-flex items-center gap-3 cursor-pointer">
                                        <input type="radio" name="wizard_membership_person" value=""
                                               class="text-slate-700 border-slate-300">
                                        <span>
                                            <span class="font-medium text-slate-800">All</span>
                                            <span class="block text-xs text-slate-400">Everyone — no person type filter applied</span>
                                        </span>
                                    </label>
                                    <label class="inline-flex items-center gap-3 cursor-pointer">
                                        <input type="radio" name="wizard_membership_person" value="volunteer"
                                               class="text-slate-700 border-slate-300">
                                        <span>
                                            <span class="font-medium text-slate-800">Volunteers only</span>
                                            <span class="block text-xs text-slate-400">Only those registered as volunteers</span>
                                        </span>
                                    </label>
                                    <label class="inline-flex items-center gap-3 cursor-pointer">
                                        <input type="radio" name="wizard_membership_person" value="member"
                                               class="text-slate-700 border-slate-300" checked>
                                        <span>
                                            <span class="font-medium text-slate-800">Members only</span>
                                            <span class="block text-xs text-slate-400">Only those registered as members</span>
                                        </span>
                                    </label>
                                </div>
                            </fieldset>

                        </div>{{-- end #wizard-membership-before --}}

                        {{-- After expiry options --}}
                        <div id="wizard-membership-after" class="hidden">

                            <fieldset class="mb-5">
                                <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                    Who to include
                                </legend>
                                <div class="flex flex-col gap-2">
                                    <label class="inline-flex items-center gap-3 cursor-pointer">
                                        <input type="radio" name="wizard_membership_after_msg" value="membership_post_expiry|0"
                                               class="text-slate-700 border-slate-300" checked>
                                        <span>
                                            <span class="font-medium text-slate-800">Not yet contacted</span>
                                            <span class="block text-xs text-slate-400">Only those who haven't received any post-expiry reminder</span>
                                        </span>
                                    </label>
                                    <label class="inline-flex items-center gap-3 cursor-pointer">
                                        <input type="radio" name="wizard_membership_after_msg" value="membership_post_expiry|<=1"
                                               class="text-slate-700 border-slate-300">
                                        <span>
                                            <span class="font-medium text-slate-800">Contacted at most once</span>
                                            <span class="block text-xs text-slate-400">Those who received 0 or 1 post-expiry reminders</span>
                                        </span>
                                    </label>
                                    <label class="inline-flex items-center gap-3 cursor-pointer">
                                        <input type="radio" name="wizard_membership_after_msg" value="membership_post_expiry|<=2"
                                               class="text-slate-700 border-slate-300">
                                        <span>
                                            <span class="font-medium text-slate-800">Contacted at most twice</span>
                                            <span class="block text-xs text-slate-400">Those who received 0, 1 or 2 post-expiry reminders</span>
                                        </span>
                                    </label>
                                </div>
                            </fieldset>

                        </div>{{-- end #wizard-membership-after --}}

                        {{-- Execute --}}
                        <button type="button" id="wizard-apply-membership"
                                class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold bg-slate-700 text-white hover:bg-slate-800 shadow-sm w-full justify-center">
                            <i class="fas fa-filter"></i>
                            Apply filter &amp; close
                        </button>

                    </div>
                </div>

                <!-- Section 4 -->
                <div class="wizard-section rounded-lg border border-slate-200 overflow-hidden">
                    <button type="button"
                            class="wizard-section-btn wizard-section-toggle w-full flex items-center justify-between px-4 py-2 text-left transition-colors">
                        <span class="flex items-center gap-3">
                            <i class="fas fa-graduation-cap text-slate-300 w-4"></i>
                            <span class="font-medium text-white">Invite to upcoming training</span>
                        </span>
                        <i class="fas fa-chevron-down wizard-chevron text-slate-300 transition-transform duration-200"></i>
                    </button>
                    <div class="wizard-section-body hidden px-4 py-4 bg-white text-sm text-slate-600 border-t border-slate-100">

                        <p class="text-slate-600 mb-4">
                            Find members or volunteers who have not yet completed a specific training —
                            and invite them to an upcoming course.
                        </p>

                        {{-- Training type dropdown --}}
                        <fieldset class="mb-4">
                            <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                Invite people who have not done this training
                            </legend>
                            <select id="wizard_training_invite_type" name="wizard_training_invite_type"
                                    class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="none_firstaid">First Aid (any)</option>
                                @foreach($trainingTypes->sortBy('name') as $type)
                                    <option value="none_{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-slate-400 mt-1">
                                "First Aid (any)" targets anyone without any first aid training,
                                regardless of type.
                            </p>
                        </fieldset>

                        {{-- Population --}}
                        <fieldset class="mb-4">
                            <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                Population
                            </legend>
                            <div class="flex gap-3">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="wizard_training_invite_population" value="volunteer"
                                           class="text-slate-700 border-slate-300" checked>
                                    <span class="font-medium text-slate-800">Volunteers</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="wizard_training_invite_population" value="all"
                                           class="text-slate-700 border-slate-300">
                                    <span class="font-medium text-slate-800">All</span>
                                </label>
                            </div>
                        </fieldset>

                        {{-- Who to include --}}
                        <fieldset class="mb-5">
                            <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                Who to include
                            </legend>
                            <div class="flex flex-col gap-2">
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_training_invite_msg" value="training_invitation|0"
                                           class="text-slate-700 border-slate-300" checked>
                                    <span>
                                        <span class="font-medium text-slate-800">Not yet contacted</span>
                                        <span class="block text-xs text-slate-400">Only those who haven't received any training invitation in the last 6 months</span>
                                    </span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_training_invite_msg" value="training_invitation|<=1"
                                           class="text-slate-700 border-slate-300">
                                    <span>
                                        <span class="font-medium text-slate-800">Contacted at most once</span>
                                        <span class="block text-xs text-slate-400">Those who received 0 or 1 training invitations in the last 6 months</span>
                                    </span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_training_invite_msg" value="training_invitation|<=2"
                                           class="text-slate-700 border-slate-300">
                                    <span>
                                        <span class="font-medium text-slate-800">Contacted at most twice</span>
                                        <span class="block text-xs text-slate-400">Those who received 0, 1 or 2 training invitations in the last 6 months</span>
                                    </span>
                                </label>
                            </div>
                        </fieldset>

                        {{-- Execute --}}
                        <button type="button" id="wizard-apply-training-invite"
                                class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold bg-slate-700 text-white hover:bg-slate-800 shadow-sm w-full justify-center">
                            <i class="fas fa-filter"></i>
                            Apply filter &amp; close
                        </button>

                    </div>
                </div>

                <!-- Section 5 -->
                <div class="wizard-section rounded-lg border border-slate-200 overflow-hidden">
                    <button type="button"
                            class="wizard-section-btn wizard-section-toggle w-full flex items-center justify-between px-4 py-2 text-left transition-colors">
                        <span class="flex items-center gap-3">
                            <i class="fas fa-kit-medical text-slate-300 w-4"></i>
                            <span class="font-medium text-white">Refresh stale first-aid training</span>
                        </span>
                        <i class="fas fa-chevron-down wizard-chevron text-slate-300 transition-transform duration-200"></i>
                    </button>
                    <div class="wizard-section-body hidden px-4 py-4 bg-white text-sm text-slate-600 border-t border-slate-100">

                        <p class="text-slate-600 mb-4">
                            Find members and volunteers whose most recent first-aid training — of any type — has
                            gone stale, and invite them to arrange a refresher with their branch.
                            Filter applied: <strong>First aid older than the chosen age</strong>.
                        </p>

                        {{-- Staleness threshold --}}
                        <fieldset class="mb-5">
                            <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                Consider first aid stale after
                            </legend>
                            <div class="flex gap-3">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="wizard_refresher_window" value="24" class="text-slate-700 border-slate-300">
                                    <span class="font-medium text-slate-800">24 months</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="wizard_refresher_window" value="36" class="text-slate-700 border-slate-300" checked>
                                    <span class="font-medium text-slate-800">36 months</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="wizard_refresher_window" value="48" class="text-slate-700 border-slate-300">
                                    <span class="font-medium text-slate-800">48 months</span>
                                </label>
                            </div>
                            <p class="text-xs text-slate-400 mt-1">You can fine-tune the exact threshold (3-month steps) on the results page.</p>
                        </fieldset>

                        {{-- Population --}}
                        <fieldset class="mb-4">
                            <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                Population
                            </legend>
                            <div class="flex gap-3">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="wizard_refresher_population" value="volunteer"
                                           class="text-slate-700 border-slate-300" checked>
                                    <span class="font-medium text-slate-800">Volunteers</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="wizard_refresher_population" value="all"
                                           class="text-slate-700 border-slate-300">
                                    <span class="font-medium text-slate-800">All</span>
                                </label>
                            </div>
                        </fieldset>

                        {{-- Who to include --}}
                        <fieldset class="mb-5">
                            <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                Who to include
                            </legend>
                            <div class="flex flex-col gap-2">
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_refresher_msg" value="first_aid_refresher|0" class="text-slate-700 border-slate-300" checked>
                                    <span>
                                        <span class="font-medium text-slate-800">Not yet contacted</span>
                                        <span class="block text-xs text-slate-400">Only those who haven't received any refresher reminder in the last 6 months</span>
                                    </span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_refresher_msg" value="first_aid_refresher|<=1" class="text-slate-700 border-slate-300">
                                    <span>
                                        <span class="font-medium text-slate-800">Contacted at most once</span>
                                        <span class="block text-xs text-slate-400">Those who received 0 or 1 refresher reminders in the last 6 months</span>
                                    </span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_refresher_msg" value="first_aid_refresher|<=2" class="text-slate-700 border-slate-300">
                                    <span>
                                        <span class="font-medium text-slate-800">Contacted at most twice</span>
                                        <span class="block text-xs text-slate-400">Those who received 0, 1 or 2 refresher reminders in the last 6 months</span>
                                    </span>
                                </label>
                            </div>
                        </fieldset>

                        {{-- Execute --}}
                        <button type="button" id="wizard-apply-firstaid-refresher"
                                class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold bg-slate-700 text-white hover:bg-slate-800 shadow-sm w-full justify-center">
                            <i class="fas fa-filter"></i>
                            Apply filter &amp; close
                        </button>

                    </div>
                </div>

                <!-- Section 6 -->
                <div class="wizard-section rounded-lg border border-slate-200 overflow-hidden">
                    <button type="button"
                            class="wizard-section-btn wizard-section-toggle w-full flex items-center justify-between px-4 py-2 text-left transition-colors">
                        <span class="flex items-center gap-3">
                            <i class="fas fa-certificate text-slate-300 w-4"></i>
                            <span class="font-medium text-white">Remind about expiring training certification</span>
                        </span>
                        <i class="fas fa-chevron-down wizard-chevron text-slate-300 transition-transform duration-200"></i>
                    </button>
                    <div class="wizard-section-body hidden px-4 py-4 bg-white text-sm text-slate-600 border-t border-slate-100">

                        <p class="text-slate-600 mb-4">
                            Find members or volunteers whose training certification is approaching expiry or has already lapsed.
                            The message will be a general training expiry reminder — not specific to a particular course.
                        </p>

                        {{-- Training type --}}
                        <fieldset class="mb-4">
                            <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                Training type
                            </legend>
                            <select name="wizard_training_type" id="wizard_training_type"
                                    class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">— Select a training type —</option>
                                @foreach($trainingTypesWithExpiry as $type)
                                    <option value="{{ $type->id }}">{{ $type->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-slate-400 mt-1">Only training types with an expiry limit are listed.</p>
                        </fieldset>

                        {{-- Expiry window --}}
                        <fieldset class="mb-4">
                            <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                Expiry window
                            </legend>
                            <div class="flex flex-col gap-2">
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_training_window" value="14"
                                           class="text-slate-700 border-slate-300" checked>
                                    <span>
                                        <span class="font-medium text-slate-800">Expires within 14 days</span>
                                        <span class="block text-xs text-slate-400">Certification runs out very soon</span>
                                    </span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_training_window" value="28"
                                           class="text-slate-700 border-slate-300">
                                    <span>
                                        <span class="font-medium text-slate-800">Expires within 28 days</span>
                                        <span class="block text-xs text-slate-400">Certification runs out within a month</span>
                                    </span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_training_window" value="expired"
                                           class="text-slate-700 border-slate-300">
                                    <span>
                                        <span class="font-medium text-slate-800">Has expired (not renewed)</span>
                                        <span class="block text-xs text-slate-400">Certification has lapsed and not been renewed</span>
                                    </span>
                                </label>
                            </div>
                        </fieldset>

                        {{-- Population --}}
                        <fieldset class="mb-4">
                            <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                Population
                            </legend>
                            <div class="flex gap-3">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="wizard_training_population" value="volunteer"
                                           class="text-slate-700 border-slate-300" checked>
                                    <span class="font-medium text-slate-800">Volunteers</span>
                                </label>
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="wizard_training_population" value="all"
                                           class="text-slate-700 border-slate-300">
                                    <span class="font-medium text-slate-800">All</span>
                                </label>
                            </div>
                        </fieldset>

                        {{-- Who to include --}}
                        <fieldset class="mb-5">
                            <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                Who to include
                            </legend>
                            <div class="flex flex-col gap-2">
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_training_msg" value="training_expiry|0"
                                           class="text-slate-700 border-slate-300" checked>
                                    <span>
                                        <span class="font-medium text-slate-800">Not yet contacted</span>
                                        <span class="block text-xs text-slate-400">Only those who haven't received any training expiry reminder in the last 6 months</span>
                                    </span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_training_msg" value="training_expiry|<=1"
                                           class="text-slate-700 border-slate-300">
                                    <span>
                                        <span class="font-medium text-slate-800">Contacted at most once</span>
                                        <span class="block text-xs text-slate-400">Those who received 0 or 1 training expiry reminders in the last 6 months</span>
                                    </span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_training_msg" value="training_expiry|<=2"
                                           class="text-slate-700 border-slate-300">
                                    <span>
                                        <span class="font-medium text-slate-800">Contacted at most twice</span>
                                        <span class="block text-xs text-slate-400">Those who received 0, 1 or 2 training expiry reminders in the last 6 months</span>
                                    </span>
                                </label>
                            </div>
                        </fieldset>

                        {{-- Validation warning --}}
                        <div id="wizard-training-warning" class="hidden mb-4 rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-700">
                            <i class="fas fa-exclamation-circle mr-1"></i>
                            Please select a training type before applying the filter.
                        </div>

                        {{-- Execute --}}
                        <button type="button" id="wizard-apply-training"
                                class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold bg-slate-700 text-white hover:bg-slate-800 shadow-sm w-full justify-center">
                            <i class="fas fa-filter"></i>
                            Apply filter &amp; close
                        </button>

                    </div>
                </div>

                <!-- Section 7 -->
                <div class="wizard-section rounded-lg border border-slate-200 overflow-hidden">
                    <button type="button"
                            class="wizard-section-btn wizard-section-toggle w-full flex items-center justify-between px-4 py-2 text-left transition-colors">
                        <span class="flex items-center gap-3">
                            <i class="fas fa-hand-holding-heart text-slate-300 w-4"></i>
                            <span class="font-medium text-white">Appreciate donors</span>
                        </span>
                        <i class="fas fa-chevron-down wizard-chevron text-slate-300 transition-transform duration-200"></i>
                    </button>
                    <div class="wizard-section-body hidden px-4 py-4 bg-white text-sm text-slate-600 border-t border-slate-100">

                        <p class="text-slate-600 mb-4">
                            Find persons who have made cash or in-kind donations —
                            and send them a personal message of appreciation.
                        </p>

                        {{-- Who to include --}}
                        <fieldset class="mb-5">
                            <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                Who to include
                            </legend>
                            <div class="flex flex-col gap-2">
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_donor_msg" value="donation_appreciation|never"
                                           class="text-slate-700 border-slate-300" checked>
                                    <span>
                                        <span class="font-medium text-slate-800">Never thanked</span>
                                        <span class="block text-xs text-slate-400">Have donated but have never received an thank-you message</span>
                                    </span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_donor_msg" value="donation_appreciation|since_last"
                                           class="text-slate-700 border-slate-300">
                                    <span>
                                        <span class="font-medium text-slate-800">Donated again since being thanked</span>
                                        <span class="block text-xs text-slate-400">Were thanked before, and have made a new donation since their last appreciation message</span>
                                    </span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_donor_msg" value=""
                                           class="text-slate-700 border-slate-300">
                                    <span>
                                        <span class="font-medium text-slate-800">All eligible donors</span>
                                        <span class="block text-xs text-slate-400">This includes everyone who has made a donation, whether or not they received a thank-you message.</span>
                                    </span>
                                </label>
                            </div>
                        </fieldset>

                        {{-- Execute --}}
                        <button type="button" id="wizard-apply-donors"
                                class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold bg-slate-700 text-white hover:bg-slate-800 shadow-sm w-full justify-center">
                            <i class="fas fa-filter"></i>
                            Apply filter &amp; close
                        </button>

                    </div>
                </div>

                <!-- Section 8 -->
                <div class="wizard-section rounded-lg border border-slate-200 overflow-hidden">
                    <button type="button"
                            class="wizard-section-btn wizard-section-toggle w-full flex items-center justify-between px-4 py-2 text-left transition-colors">
                        <span class="flex items-center gap-3">
                            <i class="fas fa-newspaper text-slate-300 w-4"></i>
                            <span class="font-medium text-white">Send a newsletter</span>
                        </span>
                        <i class="fas fa-chevron-down wizard-chevron text-slate-300 transition-transform duration-200"></i>
                    </button>
                    <div class="wizard-section-body hidden px-4 py-4 bg-white text-sm text-slate-600 border-t border-slate-100">

                        <p class="text-slate-600 mb-4">
                            Send a broad message — news, updates, or announcements —
                            to a wide audience across your branches.
                        </p>

                        {{-- Who to target --}}
                        <fieldset class="mb-4">
                            <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                Who to target
                            </legend>
                            <div class="flex flex-col gap-2">
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_newsletter_person" value=""
                                           class="text-slate-700 border-slate-300" checked>
                                    <span>
                                        <span class="font-medium text-slate-800">All</span>
                                        <span class="block text-xs text-slate-400">Everyone — no person type filter applied</span>
                                    </span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_newsletter_person" value="volunteer"
                                           class="text-slate-700 border-slate-300">
                                    <span>
                                        <span class="font-medium text-slate-800">Volunteers only</span>
                                        <span class="block text-xs text-slate-400">Only those registered as volunteers</span>
                                    </span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_newsletter_person" value="member"
                                           class="text-slate-700 border-slate-300">
                                    <span>
                                        <span class="font-medium text-slate-800">Members only</span>
                                        <span class="block text-xs text-slate-400">Only those registered as members</span>
                                    </span>
                                </label>
                            </div>
                        </fieldset>

                        {{-- Broad-audience warning (always shown) --}}
                        <div class="mb-5 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                            <i class="fas fa-triangle-exclamation mr-1"></i>
                            This audience may be very broad — consider narrowing by branch, division, or another filter first.
                        </div>

                        {{-- Execute --}}
                        <button type="button" id="wizard-apply-newsletter"
                                class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold bg-slate-700 text-white hover:bg-slate-800 shadow-sm w-full justify-center">
                            <i class="fas fa-filter"></i>
                            Apply filter &amp; close
                        </button>

                    </div>
                </div>

                <!-- Section 9 -->
                <div class="wizard-section rounded-lg border border-slate-200 overflow-hidden">
                    <button type="button"
                            class="wizard-section-btn wizard-section-toggle w-full flex items-center justify-between px-4 py-2 text-left transition-colors">
                        <span class="flex items-center gap-3">
                            <i class="fas fa-bullhorn text-slate-300 w-4"></i>
                            <span class="font-medium text-white">Fundraising appeal</span>
                        </span>
                        <i class="fas fa-chevron-down wizard-chevron text-slate-300 transition-transform duration-200"></i>
                    </button>
                    <div class="wizard-section-body hidden px-4 py-4 bg-white text-sm text-slate-600 border-t border-slate-100">

                        <p class="text-slate-600 mb-4">
                            Ask for cash or in-kind support for a specific need or emergency response.
                        </p>

                        {{-- Who to target --}}
                        <fieldset class="mb-3">
                            <legend class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">
                                Who to target
                            </legend>
                            <div class="flex flex-col gap-2">
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_fundraising_audience" value=""
                                           class="text-slate-700 border-slate-300" checked>
                                    <span>
                                        <span class="font-medium text-slate-800">All</span>
                                        <span class="block text-xs text-slate-400">Everyone — all operational volunteers and members, regardless of unit or payment status</span>
                                    </span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_fundraising_audience" value="member"
                                           class="text-slate-700 border-slate-300">
                                    <span>
                                        <span class="font-medium text-slate-800">Members</span>
                                        <span class="block text-xs text-slate-400">Only those registered as members</span>
                                    </span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_fundraising_audience" value="high_value"
                                           class="text-slate-700 border-slate-300">
                                    <span>
                                        <span class="font-medium text-slate-800">High-end members</span>
                                        <span class="block text-xs text-slate-400">Members paying above the typical membership fee</span>
                                    </span>
                                </label>
                                <label class="inline-flex items-center gap-3 cursor-pointer">
                                    <input type="radio" name="wizard_fundraising_audience" value="donors"
                                           class="text-slate-700 border-slate-300">
                                    <span>
                                        <span class="font-medium text-slate-800">Previous donors</span>
                                        <span class="block text-xs text-slate-400">Anyone who has made a cash or in-kind donation before</span>
                                    </span>
                                </label>
                            </div>
                        </fieldset>

                        {{-- Broad-audience warning (shown for "Volunteers + Members" and "Members") --}}
                        <div id="wizard-fundraising-broad-warning"
                             class="mb-5 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                            <i class="fas fa-triangle-exclamation mr-1"></i>
                            This audience may be very broad — consider narrowing by branch, division, or another filter first.
                        </div>

                        {{-- Execute --}}
                        <button type="button" id="wizard-apply-fundraising"
                                class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold bg-slate-700 text-white hover:bg-slate-800 shadow-sm w-full justify-center">
                            <i class="fas fa-filter"></i>
                            Apply filter &amp; close
                        </button>

                    </div>
                </div>

                {{-- Segmentation tip — always visible --}}
                <div class="mt-8 rounded-lg bg-amber-50 border border-amber-200 px-4 py-4 text-sm text-amber-900">
                    <div class="flex gap-3">
                        <i class="fas fa-lightbulb text-amber-400 mt-0.5 shrink-0"></i>
                        <div>
                            <div class="font-semibold mb-1">Audience too large? Consider segmenting, for example:</div>
                            <ul class="text-amber-800 leading-relaxed list-disc pl-4 space-y-1">
                                <li>Make 1 campaign for every branch (or division)</li>
                                <li>Men 1 campaign, Women 1 campaign</li>
                                <li>Age 20–30 one campaign, age 31–40 another, and so on</li>
                            </ul>
                        </div>
                    </div>
                </div>

            </div>{{-- end accordion --}}

            <!-- Footer -->
            <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 text-xs text-slate-400">
                Selecting an option will pre-fill the filters on the main page. You can adjust them before applying.
            </div>

        </div>{{-- end panel --}}
    </div>{{-- end modal --}}

    <script>
        // Trailing-day window for "has this person been contacted recently"
        // wizard params — mirrors CampaignPurpose::CONTACT_WINDOW_DAYS.
        const CONTACT_WINDOW_DAYS = {{ \App\Models\CampaignPurpose::CONTACT_WINDOW_DAYS }};

        document.addEventListener('DOMContentLoaded', function () {
            // ── Show-profile-photos preference (per-browser cookie + reload) ──
            document.getElementById('toggle-show-photos')?.addEventListener('change', function () {
                const val = this.checked ? '1' : '0';
                // 1-year cookie, site-wide path
                document.cookie = 'users_show_photos=' + val + ';path=/;max-age=' + (60 * 60 * 24 * 365) + ';SameSite=Lax';
                window.location.reload();
            });

            // ── Progressive filter disclosure (toggle) ───────────────
            const showFiltersBtn = document.getElementById('show-filters-btn');
            const filtersExpanded = document.getElementById('filters-expanded');
            const showFiltersBtnLabel = document.getElementById('show-filters-btn-label');
            if (showFiltersBtn && filtersExpanded) {
                showFiltersBtn.addEventListener('click', function () {
                    const willShow = filtersExpanded.classList.toggle('hidden') === false;
                    showFiltersBtn.setAttribute('aria-expanded', willShow ? 'true' : 'false');
                    if (showFiltersBtnLabel) {
                        showFiltersBtnLabel.textContent = willShow ? 'Hide filters' : 'Show all filters';
                    }
                });
            }

            // ── Location cascade ──────────────────────────────────────
            const branchSelect = document.getElementById('branch_id');
            const divisionSelect = document.getElementById('division_id');
            const redCrossUnitSelect = document.getElementById('red_cross_unit_id');
            const taskForceSelect = document.getElementById('task_force_id');

            @php
                $isBranchDisabled = in_array($accessLevel, ['branch', 'division']);
                $isDivisionDisabled = ($accessLevel === 'division');
            @endphp

            const initialSelectedBranch = "{{ request('branch_id', $isBranchDisabled ? ($branches->first()->id ?? null) : null) }}";
            const initialSelectedDivision = "{{ request('division_id', $isDivisionDisabled ? ($divisions->first()->id ?? null) : null) }}";
            const initialSelectedRedCrossUnit = "{{ request('red_cross_unit_id', '') }}";
            const initialSelectedTaskForce = "{{ request('task_force_id', '') }}";

            function resetAndDisableSelect(selectElement, placeholderText) {
                selectElement.innerHTML = `<option value="">${placeholderText}</option>`;
                selectElement.disabled = true;
            }

            async function populateDivisions(branchId, selectedDivisionId = '') {
                // Reset red cross unit select immediately as divisions change
                resetAndDisableSelect(redCrossUnitSelect, 'Select Division First');

                if (!branchId) {
                    resetAndDisableSelect(divisionSelect, 'Select Branch First');
                    return;
                }

                divisionSelect.disabled = false;
                divisionSelect.innerHTML = '<option value="">Loading divisions...</option>';

                try {
                    const response = await fetch(`/divisions/by-branch?branch_id=${branchId}`);
                    const divisions = await response.json();

                    divisionSelect.innerHTML = '<option value="">All Divisions</option>';
                    divisions.forEach(division => {
                        const option = document.createElement('option');
                        option.value = division.id;
                        option.textContent = division.name;
                        if (String(division.id) === String(selectedDivisionId)) {
                            option.selected = true;
                        }
                        divisionSelect.appendChild(option);
                    });

                    // If a specific division was selected and it's present, populate Red Cross Units for it
                    if (selectedDivisionId && Array.from(divisionSelect.options).some(option => String(option.value) === String(selectedDivisionId))) {
                        populateRedCrossUnits(selectedDivisionId, initialSelectedRedCrossUnit);
                    } else {
                        // If no division was pre-selected or the pre-selected one is not valid for this branch
                        resetAndDisableSelect(redCrossUnitSelect, 'Select Division First');
                    }

                } catch (error) {
                    console.error('Error fetching divisions:', error);
                    resetAndDisableSelect(divisionSelect, 'Error loading divisions');
                }
            }

            async function populateRedCrossUnits(divisionId, selectedUnitId = '') {
                if (!divisionId) {
                    resetAndDisableSelect(redCrossUnitSelect, 'Select Division First');
                    return;
                }

                redCrossUnitSelect.disabled = false;
                redCrossUnitSelect.innerHTML = '<option value="">Loading units...</option>';

                try {
                    const response = await fetch(`/red-cross-units/by-division?division_id=${divisionId}`);
                    const units = await response.json();

                    redCrossUnitSelect.innerHTML = '<option value="">All Units</option>';
                    units.forEach(unit => {
                        const option = document.createElement('option');
                        option.value = unit.id;
                        option.textContent = unit.name;
                        if (String(unit.id) === String(selectedUnitId)) {
                            option.selected = true;
                        }
                        redCrossUnitSelect.appendChild(option);
                    });
                } catch (error) {
                    console.error('Error fetching Red Cross Units:', error);
                    resetAndDisableSelect(redCrossUnitSelect, 'Error loading units');
                }
            }

            async function populateTaskForces(branchId, selectedTaskForceId = '') {
                if (!branchId) {
                    resetAndDisableSelect(taskForceSelect, 'Select Branch First');
                    return;
                }

                taskForceSelect.disabled = false;
                taskForceSelect.innerHTML = '<option value="">Loading task forces...</option>';

                try {
                    const response = await fetch(`/task-forces/by-branch?branch_id=${branchId}`);
                    const taskForces = await response.json();

                    taskForceSelect.innerHTML = '<option value="">All Task Forces</option>';
                    taskForces.forEach(taskForce => {
                        const option = document.createElement('option');
                        option.value = taskForce.id;
                        option.textContent = taskForce.name;
                        if (String(taskForce.id) === String(selectedTaskForceId)) {
                            option.selected = true;
                        }
                        taskForceSelect.appendChild(option);
                    });
                } catch (error) {
                    console.error('Error fetching Task Forces:', error);
                    resetAndDisableSelect(taskForceSelect, 'Error loading task forces');
                }
            }

            branchSelect.addEventListener('change', function () {
                populateDivisions(this.value);
                populateTaskForces(this.value);
            });

            divisionSelect.addEventListener('change', function () {
                populateRedCrossUnits(this.value);
            });

            // Initial setup: If a branch was already selected on page load, populate divisions and units.
            // The Blade template will correctly set the disabled state if no initial branch/division is present.
            if (initialSelectedBranch) {
                // For restricted users, the JS should not overwrite the server-rendered dropdowns
                if (!branchSelect.disabled) {
                    populateDivisions(initialSelectedBranch, initialSelectedDivision);
                } else if (!divisionSelect.disabled) {
                    // This is a branch-level user, divisions are loaded, but units might need to be
                    populateRedCrossUnits(divisionSelect.value, initialSelectedRedCrossUnit);
                } else {
                    // This is a division-level user, load their units
                    populateRedCrossUnits(divisionSelect.value, initialSelectedRedCrossUnit);
                }
            } else {
                // If no branch is selected, ensure division and unit dropdowns are disabled and show correct placeholders
                resetAndDisableSelect(divisionSelect, 'Select Branch First');
                resetAndDisableSelect(redCrossUnitSelect, 'Select Division First');
            }

            // Task Force depends only on branch_id (not chained through division), so it's
            // populated independently of the division/unit branching above.
            if (initialSelectedBranch) {
                populateTaskForces(initialSelectedBranch, initialSelectedTaskForce);
            } else {
                resetAndDisableSelect(taskForceSelect, 'Select Branch First');
            }

            // ── Campaign Filter Wizard ────────────────────────────────
            const wizardModal    = document.getElementById('wizard-modal');
            const openWizardBtn  = document.getElementById('open-wizard-btn');
            const closeWizardBtn = document.getElementById('close-wizard-btn');
            const wizardBackdrop = document.getElementById('wizard-backdrop');

            openWizardBtn.addEventListener('click', () => {
                wizardModal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            });

            function closeWizard() {
                wizardModal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }

            closeWizardBtn.addEventListener('click', closeWizard);
            wizardBackdrop.addEventListener('click', closeWizard);

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeWizard();
            });

            // Accordion — one open at a time
            document.querySelectorAll('.wizard-section-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const thisSection = btn.closest('.wizard-section');
                    const thisBody    = thisSection.querySelector('.wizard-section-body');
                    const thisChevron = thisSection.querySelector('.wizard-chevron');
                    const isOpen      = !thisBody.classList.contains('hidden');

                    // Close all
                    document.querySelectorAll('.wizard-section').forEach(section => {
                        section.querySelector('.wizard-section-body').classList.add('hidden');
                        section.querySelector('.wizard-chevron').classList.remove('rotate-180');
                    });

                    // Open this one if it was closed
                    if (!isOpen) {
                        thisBody.classList.remove('hidden');
                        thisChevron.classList.add('rotate-180');
                    }
                });
            });

            // ── Wizard: Welcome newly registered ─────────────────────
            document.getElementById('wizard-apply-new-members').addEventListener('click', () => {
                const sortVal         = document.querySelector('input[name="wizard_new_sort"]:checked')?.value
                                        ?? 'created_at_asc';
                const msgVal          = document.querySelector('input[name="wizard_new_msg"]:checked')?.value
                                        ?? 'onboarding|0';
                const contributionVal = document.querySelector('input[name="wizard_new_contribution"]:checked')?.value
                                        ?? '';

                const paramsObj = {
                    archived_filter: 'pending_engagement',
                    sort_by:         sortVal,
                    wizard_purpose:  'welcome',
                };

                if (contributionVal) {
                    paramsObj.volunteer_filter = contributionVal;
                }

                if (msgVal) {
                    paramsObj.campaign_msg = msgVal + '|' + CONTACT_WINDOW_DAYS;
                }

                const params = new URLSearchParams(paramsObj);

                closeWizard();
                window.location.href = '{{ route('users.index') }}?' + params.toString();
            });

            // ── Wizard: Re-engage dormant ─────────────────────────────
            document.getElementById('wizard-apply-dormant').addEventListener('click', () => {
                const msgVal = document.querySelector('input[name="wizard_dormant_msg"]:checked')?.value
                               ?? 'dormant_reengagement|0';

                const paramsObj = {
                    archived_filter: 'dormant',
                    person_type: 'volunteer',
                    campaign_msg: msgVal,
                    wizard_purpose: 're-engagement',
                };

                const params = new URLSearchParams(paramsObj);

                closeWizard();
                window.location.href = '{{ route('users.index') }}?' + params.toString();
            });

            // ── Wizard: Membership expiry ─────────────────────────────
            const membershipTimingRadios = document.querySelectorAll('input[name="wizard_membership_timing"]');
            const membershipBeforePanel  = document.getElementById('wizard-membership-before');
            const membershipAfterPanel   = document.getElementById('wizard-membership-after');

            membershipTimingRadios.forEach(radio => {
                radio.addEventListener('change', function () {
                    if (this.value === 'before') {
                        membershipBeforePanel.classList.remove('hidden');
                        membershipAfterPanel.classList.add('hidden');
                    } else {
                        membershipBeforePanel.classList.add('hidden');
                        membershipAfterPanel.classList.remove('hidden');
                    }
                });
            });

            document.getElementById('wizard-apply-membership').addEventListener('click', () => {
                const timing = document.querySelector('input[name="wizard_membership_timing"]:checked')?.value
                               ?? 'before';

                const paramsObj = {};

                if (timing === 'before') {
                    const window_    = document.querySelector('input[name="wizard_membership_window"]:checked')?.value
                                      ?? 'expiring_14';
                    const msgVal     = document.querySelector('input[name="wizard_membership_before_msg"]:checked')?.value
                                      ?? 'membership_pre_expiry|0';
                    const personVal  = document.querySelector('input[name="wizard_membership_person"]:checked')?.value
                                      ?? 'member';

                    paramsObj.membership_filter = window_;
                    paramsObj.campaign_msg      = msgVal + '|' + CONTACT_WINDOW_DAYS;
                    paramsObj.wizard_purpose    = 'pre-expiry';

                    if (personVal) {
                        paramsObj.person_type = personVal;
                    }
                } else {
                    const msgVal = document.querySelector('input[name="wizard_membership_after_msg"]:checked')?.value
                                   ?? 'membership_post_expiry|0';

                    paramsObj.membership_filter = 'expired_members';
                    paramsObj.campaign_msg      = msgVal + '|' + CONTACT_WINDOW_DAYS;
                    paramsObj.wizard_purpose    = 'post-expiry';
                }

                const params = new URLSearchParams(paramsObj);

                closeWizard();
                window.location.href = '{{ route('users.index') }}?' + params.toString();
            });

            // ── Wizard: Training expiry ───────────────────────────────
            document.getElementById('wizard-apply-training').addEventListener('click', () => {
                const trainingTypeId  = document.getElementById('wizard_training_type').value;
                const trainingWarning = document.getElementById('wizard-training-warning');

                if (!trainingTypeId) {
                    trainingWarning.classList.remove('hidden');
                    return;
                }
                trainingWarning.classList.add('hidden');

                const window_ = document.querySelector('input[name="wizard_training_window"]:checked')?.value
                                ?? '14';
                const msgVal  = document.querySelector('input[name="wizard_training_msg"]:checked')?.value
                                ?? 'training_expiry|0';
                const populationVal = document.querySelector('input[name="wizard_training_population"]:checked')?.value
                                      ?? 'volunteer';

                const paramsObj = {
                    training_expiry: trainingTypeId + '|' + window_,
                    campaign_msg:    msgVal + '|' + CONTACT_WINDOW_DAYS,
                    wizard_purpose:  'training expiry',
                };

                if (populationVal === 'volunteer') {
                    paramsObj.person_type = 'volunteer';
                }

                const params = new URLSearchParams(paramsObj);

                closeWizard();
                window.location.href = '{{ route('users.index') }}?' + params.toString();
            });

            // ── Wizard: Training invitation ───────────────────────────
            document.getElementById('wizard-apply-training-invite').addEventListener('click', () => {
                const trainingFilter = document.getElementById('wizard_training_invite_type').value
                                       ?? 'none_firstaid';
                const msgVal         = document.querySelector('input[name="wizard_training_invite_msg"]:checked')?.value
                                       ?? 'training_invitation|0';
                const populationVal  = document.querySelector('input[name="wizard_training_invite_population"]:checked')?.value
                                       ?? 'volunteer';

                const paramsObj = {
                    training_filter: trainingFilter,
                    campaign_msg:    msgVal + '|' + CONTACT_WINDOW_DAYS,
                    wizard_purpose:  'training invitation',
                };

                if (populationVal === 'volunteer') {
                    paramsObj.person_type = 'volunteer';
                }

                const params = new URLSearchParams(paramsObj);

                closeWizard();
                window.location.href = '{{ route('users.index') }}?' + params.toString();
            });

            // ── Wizard: First aid refresher ───────────────────────────
            document.getElementById('wizard-apply-firstaid-refresher').addEventListener('click', () => {
                const windowVal = document.querySelector('input[name="wizard_refresher_window"]:checked')?.value ?? '36';
                const msgVal    = document.querySelector('input[name="wizard_refresher_msg"]:checked')?.value ?? 'first_aid_refresher|0';
                const populationVal = document.querySelector('input[name="wizard_refresher_population"]:checked')?.value ?? 'volunteer';

                const paramsObj = {
                    first_aid_refresher: windowVal,
                    campaign_msg:        msgVal + '|' + CONTACT_WINDOW_DAYS,
                    wizard_purpose:      'first aid refresher',
                };

                if (populationVal === 'volunteer') {
                    paramsObj.person_type = 'volunteer';
                }

                const params = new URLSearchParams(paramsObj);

                closeWizard();
                window.location.href = '{{ route('users.index') }}?' + params.toString();
            });

            // ── Wizard: Appreciate donors ─────────────────────────────
            document.getElementById('wizard-apply-donors').addEventListener('click', () => {
                const msgVal = document.querySelector('input[name="wizard_donor_msg"]:checked')?.value
                               ?? '';

                const paramsObj = {
                    donation_filter: 'has',
                    wizard_purpose:  'donation appreciation',
                };

                if (msgVal) {
                    paramsObj.donation_since_contact = msgVal;
                }

                const params = new URLSearchParams(paramsObj);

                closeWizard();
                window.location.href = '{{ route('users.index') }}?' + params.toString();
            });

            // ── Wizard: Newsletter ────────────────────────────────────
            document.getElementById('wizard-apply-newsletter').addEventListener('click', () => {
                const personVal = document.querySelector('input[name="wizard_newsletter_person"]:checked')?.value
                                  ?? '';

                const paramsObj = {
                    wizard_purpose: 'newsletter',
                };

                if (personVal) {
                    paramsObj.person_type = personVal;
                }

                const params = new URLSearchParams(paramsObj);

                closeWizard();
                window.location.href = '{{ route('users.index') }}?' + params.toString();
            });

            // ── Wizard: Fundraising appeal ─────────────────────────────
            const fundraisingAudienceRadios = document.querySelectorAll('input[name="wizard_fundraising_audience"]');
            const fundraisingBroadWarning   = document.getElementById('wizard-fundraising-broad-warning');

            fundraisingAudienceRadios.forEach(radio => {
                radio.addEventListener('change', function () {
                    if (['', 'member'].includes(this.value)) {
                        fundraisingBroadWarning.classList.remove('hidden');
                    } else {
                        fundraisingBroadWarning.classList.add('hidden');
                    }
                });
            });

            document.getElementById('wizard-apply-fundraising').addEventListener('click', () => {
                const audienceVal = document.querySelector('input[name="wizard_fundraising_audience"]:checked')?.value
                                     ?? '';

                const paramsObj = {
                    wizard_purpose: 'fundraising appeal',
                };

                switch (audienceVal) {
                    case 'member':
                        paramsObj.person_type = 'member';
                        break;
                    case 'high_value':
                        paramsObj.membership_filter = 'high_value_members';
                        break;
                    case 'donors':
                        paramsObj.donation_filter = 'has';
                        break;
                    default:
                        // 'Volunteers + Members' — no person_type filter applied
                        break;
                }

                const params = new URLSearchParams(paramsObj);

                closeWizard();
                window.location.href = '{{ route('users.index') }}?' + params.toString();
            });
        });
    </script>
</x-layouts.admin>
