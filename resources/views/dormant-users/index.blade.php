<x-layouts.admin title="Archive Tool">
    <x-slot name="pageHeader">
        <i class="fas fa-archive mr-3 mb-6"></i> Archive Tool
    </x-slot>

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

                    {{-- Understand Dormant vs Pending --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'types' ? null : 'types'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-user-clock mr-2 text-indigo-400"></i>Understand Dormant vs Pending Engagement</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'types' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'types'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li><span class="font-semibold">Dormant</span> — was active at some point, but has had no recorded activity for the selected threshold.</li>
                                <li><span class="font-semibold">Pending Engagement</span> — never engaged at all since registering.</li>
                                <li>Active members and admin users are automatically excluded from both lists.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Set the inactivity threshold --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'threshold' ? null : 'threshold'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-sliders mr-2 text-amber-400"></i>Set the inactivity threshold</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'threshold' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'threshold'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Use <span class="font-semibold">Inactivity Threshold</span> to choose how many years of no activity qualifies someone as dormant.</li>
                                <li>National-level admins can also filter by <span class="font-semibold">Branch</span>; branch-level admins are automatically scoped to their own branch.</li>
                                <li>Click <span class="font-semibold">Clear</span> to reset all filters back to default.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Read the activity & status columns --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'columns' ? null : 'columns'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-table mr-2 text-violet-400"></i>Read the activity &amp; status columns</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'columns' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'columns'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Use the table columns below to quickly evaluate whether a user should be archived.</li>
                                <li><span class="font-semibold">Activity</span> shows last activity, registration date, and last login — a green highlight on last login means they logged in within the past year.</li>
                                <li><span class="font-semibold">Campaigns</span> lists every campaign message this person has been sent, with how long ago it went out.</li>
                                <li><span class="font-semibold">Status</span> shows donation, training, and first-aid badges at a glance.</li>
                                <li><span class="font-semibold">Opt-outs</span> flags anyone who has opted out of email or SMS, and when.</li>
                                <li>Toggle <span class="font-semibold">Show profile photos</span> at the top to display or hide photos in the list — this preference is remembered on your browser.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Select & archive users --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'archive' ? null : 'archive'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-box-archive mr-2 text-red-400"></i>Select &amp; archive users</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'archive' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'archive'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Tick the checkbox in the <span class="font-semibold">Actions</span> column to select a person, or use <span class="font-semibold">Select All</span> / <span class="font-semibold">Deselect All</span>.</li>
                                <li>Click <span class="font-semibold">View</span> on any person to double-check their record before archiving.</li>
                                <li>Once you're ready, click <span class="font-semibold">Archive Selected Users</span> and confirm.</li>
                                <li>Archiving can be reversed individually from a person's profile if needed.</li>
                            </ul>
                        </div>
                    </div>

                </div>{{-- end accordion --}}
            </div>{{-- end max-w-3xl --}}

        </x-help-popup>
    </div>

    <div class="container mx-auto px-4 py-6">

        {{-- Flash messages --}}
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        {{-- Description --}}
        <p class="text-gray-500 text-sm mb-4">
            Users with no recorded activity for an extended period, excluding active members and admin users.
        </p>

        {{-- Filter bar --}}
        <div class="filter-container">
            <div class="filter-form-content">
                <form method="GET" action="{{ route('dormant-users.index') }}" class="filter-form">
                    <div class="filter-grid filter-grid-4">
                        {{-- User type --}}
                        <div>
                            <label class="filter-label">User Type</label>
                            <div class="flex flex-col gap-1 mt-1">
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input type="radio" name="type" value="dormant"
                                           {{ $type === 'dormant' ? 'checked' : '' }}
                                           class="text-red-600 focus:ring-red-500">
                                    <span>Dormant (was active, now inactive)</span>
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm">
                                    <input type="radio" name="type" value="pending"
                                           {{ $type === 'pending' ? 'checked' : '' }}
                                           class="text-red-600 focus:ring-red-500">
                                    <span>Pending engagement (never engaged)</span>
                                </label>
                            </div>
                        </div>

                        {{-- Inactivity threshold --}}
                        <div>
                            <label for="years" class="filter-label">Inactivity Threshold</label>


                            <select name="years" id="years" class="filter-select {{ $years != 2 ? 'filter-active' : '' }}">

                                @foreach([2, 3, 4, 5, 6, 7] as $y)
                                    <option value="{{ $y }}" {{ $years == $y ? 'selected' : '' }}>
                                        {{ $y }} years
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Branch filter (national only) --}}
                        @if($accessLevel === 'national')
                            <div>
                                <label for="branch_id" class="filter-label">Branch</label>
                                <select name="branch_id" id="branch_id" class="filter-select {{ request('branch_id') ? 'filter-active' : '' }}">
                                    <option value="">All Branches</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}"
                                            {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @elseif($accessLevel === 'branch')
                            <input type="hidden" name="branch_id" value="{{ $scopedId }}">
                        @endif
                    </div>

                    <div class="filter-actions mt-4">
                        <div class="filter-button-group">
                            <button type="submit" class="filter-btn-primary">
                                <i class="fas fa-search mr-1"></i>Filter
                            </button>
                            <a href="{{ route('dormant-users.index') }}"
                               class="filter-btn-secondary {{ request()->hasAny(['years', 'branch_id', 'type']) ? 'filter-btn-secondary-active' : 'filter-btn-disabled' }}">
                                <i class="fas fa-times mr-1"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Display preference: show profile photos (per-browser cookie, set via JS) --}}
        <div class="flex justify-end px-1 mt-2 mb-2">
            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" id="toggle-show-photos" {{ $showPhotos ? 'checked' : '' }} class="rounded border-gray-300">
                Show profile photos
            </label>
        </div>

        {{-- Results count --}}
        <div class="text-xl text-gray-600 px-1 mb-4">
            Found {{ $users->total() }} dormant user{{ $users->total() === 1 ? '' : 's' }}
        </div>

        @if($users->count() === 0)
            <div class="text-center py-16">
                <i class="fas fa-user-clock text-5xl text-gray-300 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No dormant users found</h3>
                <p class="text-gray-500">Try adjusting the inactivity threshold or branch filter.</p>
            </div>
        @else
            <form method="POST" action="{{ route('dormant-users.archive') }}" id="archive-form">
                @csrf

                {{-- Bulk controls --}}
                <div class="flex flex-wrap items-center gap-3 mb-4 px-1">
                    <button type="button"
                            id="select-all"
                            class="inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Select All
                    </button>
                    <button type="button"
                            id="deselect-all"
                            class="inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                        Deselect All
                    </button>
                    <span id="selection-counter" class="text-sm font-medium text-gray-700">0 users selected</span>

                    <button type="button"
                            id="archive-btn"
                            disabled
                            class="ml-auto inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-bold rounded disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="fas fa-archive mr-2"></i>Archive Selected Users
                    </button>
                </div>

                <div class="bg-white shadow rounded-lg overflow-hidden mb-4">
                    <div class="overflow-x-auto w-full">
                        <table class="min-w-full table-fixed">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="w-[240px] min-w-[180px] px-3 py-2 table-heading">Person</th>
                                    <th class="w-[180px] min-w-[120px] px-3 py-2 table-heading">Location/Contact</th>
                                    <th class="px-3 py-2 table-heading">Activity</th>
                                    <th class="w-[200px] min-w-[140px] px-3 py-2 table-heading">Campaigns</th>
                                    <th class="w-[200px] min-w-[140px] px-3 py-2 table-heading">Status</th>
                                    <th class="w-[140px] min-w-[110px] px-3 py-2 table-heading">Opt-outs</th>
                                    <th class="w-[160px] min-w-[120px] px-3 py-2 table-heading">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 text-sm">
                                @foreach($users as $user)
                                    @php
                                        $recentLogin = $user->last_login_at && $user->last_login_at->gte(now()->subYear());
                                    @endphp
                                    <tr class="hover:bg-gray-50">

                                        {{-- Person --}}
                                        <td class="px-3 py-2 max-w-[240px]">
                                            <x-user-profile-badge :user="$user" size="md" :show-photo="$showPhotos"/>
                                        </td>

                                        {{-- Location / Contact --}}
                                        <td class="px-3 py-2 truncate max-w-[180px]">
                                            <div class="text-gray-900 truncate">{{ $user->branch->name ?? 'No branch' }}</div>
                                            <div class="text-gray-500 truncate">{{ $user->division->name ?? 'No division' }}</div>
                                            <div class="text-gray-500 truncate">{{ $user->redCrossUnit->name ?? 'No RC Unit' }}</div>
                                            <div class="text-gray-900 truncate">{{ $user->email ?? 'No email' }}</div>
                                            <div class="text-gray-500 truncate">{{ $user->telephone1 ?? 'No phone' }}</div>
                                        </td>

                                        {{-- Activity --}}
                                        <td class="px-3 py-2 align-top">
                                            {{-- Last activity --}}
                                            <div class="mb-1">
                                                <span class="text-gray-500 text-xs">Last activity:</span>
                                                @if($user->last_activity_at)
                                                    <div class="font-medium">
                                                        <x-time-ago :date="$user->last_activity_at" />
                                                    </div>
                                                @else
                                                    <div class="text-gray-400 italic">Never</div>
                                                @endif
                                            </div>

                                            {{-- Registered --}}
                                            <div class="mb-1">
                                                <span class="text-gray-500 text-xs">Registered:</span>
                                                @if($user->created_at)
                                                    <div class="font-medium">
                                                        <x-time-ago :date="$user->created_at" />
                                                    </div>
                                                @else
                                                    <div class="text-gray-400 italic">Unknown</div>
                                                @endif
                                            </div>

                                            {{-- Last login --}}
                                            <div>
                                                <span class="text-gray-500 text-xs">Last login:</span>
                                                @if($user->last_login_at)
                                                    <div class="font-medium {{ $recentLogin ? 'bg-green-100 text-green-800 rounded px-1' : '' }}">
                                                        <x-time-ago :date="$user->last_login_at" />
                                                    </div>
                                                @else
                                                    <div class="text-gray-400 italic">Never</div>
                                                @endif
                                            </div>
                                        </td>

                                        {{-- Campaigns (all sent) --}}
                                        <td class="px-3 py-2 align-top max-w-[200px]">
                                            @if($user->campaignRecipients->isNotEmpty())
                                                <div class="space-y-1">
                                                    @foreach($user->campaignRecipients as $recipient)
                                                        <div class="flex items-center gap-1 rounded bg-purple-100 text-purple-800 px-2 py-0.5 text-xs">
                                                            <i class="fas fa-envelope text-purple-400 text-[10px]"></i>
                                                            <span class="truncate max-w-[110px]"
                                                                  title="{{ $recipient->campaign->title }} ({{ $recipient->campaign->purpose->name ?? 'No purpose' }})">
                                                                {{ $recipient->campaign->title }}
                                                            </span>
                                                            <span class="text-purple-400 whitespace-nowrap ml-auto">
                                                                <x-time-ago :date="$recipient->sent_at" />
                                                            </span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>

                                        {{-- Status pills --}}
                                        <td class="px-3 py-2 align-top max-w-[200px]">
                                            <div class="flex flex-row flex-wrap gap-x-2 gap-y-1">
                                                <x-user-donation-status-badge :user="$user" />
                                                <x-user-training-status-badge :user="$user" />
                                                <x-user-first-aid-status-badge :user="$user" />
                                            </div>
                                        </td>

                                        {{-- Opt-outs --}}
                                        <td class="px-3 py-2 align-top">
                                            @if($user->email_opt_out || $user->sms_opt_out)
                                                <div class="flex flex-wrap gap-1">
                                                    @if($user->email_opt_out)
                                                        <span class="bg-red-100 text-red-800 rounded-full px-2 py-0.5 text-xs">
                                                            <i class="fas fa-envelope mr-1"></i>Email opt-out
                                                            @if($user->email_opt_out_at)
                                                                <br><span class="text-red-400">{{ $user->email_opt_out_at->diffForHumans() }}</span>
                                                            @endif
                                                        </span>
                                                    @endif
                                                    @if($user->sms_opt_out)
                                                        <span class="bg-red-100 text-red-800 rounded-full px-2 py-0.5 text-xs">
                                                            <i class="fas fa-sms mr-1"></i>SMS opt-out
                                                            @if($user->sms_opt_out_at)
                                                                <br><span class="text-red-400">{{ $user->sms_opt_out_at->diffForHumans() }}</span>
                                                            @endif
                                                        </span>
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>

                                        {{-- Actions --}}
                                        <td class="px-3 py-2 max-w-[160px]">
                                            <div class="flex gap-2 mt-1 flex-wrap items-center">
                                                <a href="{{ route('users.show', $user) }}"
                                                   class="btn-primary btn-sm">
                                                    View
                                                </a>
                                                <input type="checkbox"
                                                       name="user_ids[]"
                                                       value="{{ $user->id }}"
                                                       class="bulk-checkbox h-5 w-5 text-red-600 border-gray-300 rounded focus:ring-red-500">
                                            </div>
                                        </td>

                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>


            </form>

            {{-- Pagination --}}
            <div class="px-1">
                {{ $users->links() }}
            </div>
        @endif

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const checkboxes   = document.querySelectorAll('.bulk-checkbox');
            const selectAll    = document.getElementById('select-all');
            const deselectAll  = document.getElementById('deselect-all');
            const counter      = document.getElementById('selection-counter');
            const archiveBtn   = document.getElementById('archive-btn');
            const archiveForm  = document.getElementById('archive-form');

            function checkedCount() {
                return document.querySelectorAll('.bulk-checkbox:checked').length;
            }

            function updateState() {
                const n = checkedCount();
                if (counter) counter.textContent = `${n} user${n !== 1 ? 's' : ''} selected`;
                if (archiveBtn) archiveBtn.disabled = n === 0;
            }

            checkboxes.forEach(cb => cb.addEventListener('change', updateState));

            if (selectAll) selectAll.addEventListener('click', () => {
                checkboxes.forEach(c => c.checked = true);
                updateState();
            });

            if (deselectAll) deselectAll.addEventListener('click', () => {
                checkboxes.forEach(c => c.checked = false);
                updateState();
            });

            if (archiveBtn && archiveForm) {
                archiveBtn.addEventListener('click', function () {
                    const n = checkedCount();
                    if (n === 0) return;
                    const confirmed = confirm(
                        `You are about to archive ${n} user${n !== 1 ? 's' : ''}. ` +
                        `This can be reversed individually from their profile. Continue?`
                    );
                    if (confirmed) archiveForm.submit();
                });
            }

            updateState();

            document.getElementById('toggle-show-photos')?.addEventListener('change', function () {
                const val = this.checked ? '1' : '0';
                document.cookie = 'users_show_photos=' + val + ';path=/;max-age=' + (60 * 60 * 24 * 365) + ';SameSite=Lax';
                window.location.reload();
            });
        });
    </script>
</x-layouts.admin>
