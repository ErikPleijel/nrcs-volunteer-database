<x-layouts.admin title="Audit Log">


    <x-slot name="pageHeader">
        <i class="fa-solid fa-clipboard-list mr-3 mb-6"></i> Audit Log
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

                    {{-- What the log tracks --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'intro' ? null : 'intro'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-shield-halved mr-2 text-indigo-400"></i>Understand what the log tracks</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'intro' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'intro'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>This log focuses on <span class="font-semibold">deletions</span> and <span class="font-semibold">administrative changes</span> — not on ordinary day-to-day activity.</li>
                                <li>Every deletion is kept — nothing disappears silently.</li>
                                <li>It records <span class="font-semibold">admin-initiated</span> branch/division moves — when an administrator moves someone. Self-service moves a person makes to their own profile are not logged here.</li>
                                <li>It records <span class="font-semibold">role and special permission changes</span> — who was assigned or removed from a role, and which special permissions were granted or revoked.</li>
                                <li>It records <span class="font-semibold">National Settings changes</span> — including settings values, signatures, membership fees, training types, campaign purposes, and task force types.</li>
                                <li>Approved records are <span class="font-semibold">not</span> logged separately — the approval itself (who, when) is already stored on the record, e.g. the payment, donation, activity, or training.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Search & filter --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'filter' ? null : 'filter'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-filter mr-2 text-sky-400"></i>Search &amp; filter the log</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'filter' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'filter'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li><span class="font-semibold">Search</span> matches description, action, or subject type/ID — and if you type a number, it also searches for that <span class="font-semibold">User ID</span> as the actor, submitter, or entered-by person.</li>
                                <li>Use <span class="font-semibold">Action</span> to isolate a specific type of event, e.g. <span class="font-mono text-xs">payment_deleted</span>, <span class="font-mono text-xs">member_branch_division_changed</span>, <span class="font-mono text-xs">user_roles_updated</span>, or <span class="font-mono text-xs">setting_changed</span>.</li>
                                <li>Narrow down by <span class="font-semibold">Branch</span> and/or <span class="font-semibold">Division</span> — national admins see both, branch admins see Division only (scoped to their own branch).</li>
                                <li>Use <span class="font-semibold">From date</span> / <span class="font-semibold">To date</span> to bound the results to a specific period.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Read the table --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'table' ? null : 'table'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-table mr-2 text-violet-400"></i>Read the log table</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'table' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'table'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li><span class="font-semibold">Actor</span> is who performed the action — shown as "System / N/A" if no user is attached.</li>
                                <li><span class="font-semibold">User</span> shows the person the record relates to, e.g. the member on a payment, donation, activity, or training.</li>
                                <li><span class="font-semibold">Submitted by</span> shows who originally entered that record, where applicable.</li>
                                <li><span class="font-semibold">Subject</span> shows the record type and ID affected, e.g. <span class="font-mono text-xs">MembershipPayment #123</span>.</li>
                                <li>The <span class="font-semibold">description</span> spells out what changed where possible — e.g. old and new role, old and new fee amount, or which fields were edited.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Track migrations --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'migrations' ? null : 'migrations'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-right-left mr-2 text-amber-400"></i>Track branch/division migrations</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'migrations' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'migrations'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Search or filter by the action <span class="font-mono text-xs">member_branch_division_changed</span> to see who an administrator has moved between branches or divisions.</li>
                                <li>This only covers moves made by an admin — a person changing their own branch/division via self-service is not recorded here, by design.</li>

                            </ul>
                        </div>
                    </div>

                    {{-- Roles, permissions & settings --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'admin_changes' ? null : 'admin_changes'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-user-shield mr-2 text-red-400"></i>Roles, permissions &amp; settings</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'admin_changes' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'admin_changes'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li><span class="font-mono text-xs">user_roles_updated</span> — shows the new role, the previous role, and any special permissions granted or revoked.</li>
                                <li><span class="font-mono text-xs">setting_changed</span> — any National Database Setting, e.g. dormancy period, site motto, or campaign sending caps.</li>
                                <li>Signature, Membership Fee, Training Type, Campaign Purpose, and Task Force Type changes are logged under their own matching action names — use the <span class="font-semibold">Action</span> filter to find them.</li>
                                <li>These are the most sensitive entries in the log — they show changes to who can do what, and how the system behaves for everyone.</li>
                            </ul>
                        </div>
                    </div>

                </div>{{-- end accordion --}}
            </div>{{-- end max-w-3xl --}}

        </x-help-popup>
    </div>

    {{-- Prominent Search Bar --}}
    <form method="GET" action="{{ route('logs.index') }}" class="mb-4">
        <div class="bg-white p-4 rounded shadow-sm space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                {{-- Combined search (text + optional user ID) --}}
                <div class="md:col-span-3">
                    <label for="search" class="block text-sm font-semibold text-gray-800">
                        Search
                        <span class="text-xs font-normal text-gray-500">
                            (description, action, subject type/id, or user ID)
                        </span>
                    </label>
                    <input
                        type="text"
                        name="search"
                        id="search"
                        value="{{ request('search') }}"

                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm px-3 py-2 text-sm
                               focus:ring-red-500 focus:border-red-500 {{ request('search') ? 'filter-active' : '' }}"
                    >
                </div>
            </div>

            {{-- Secondary filters --}}
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                {{-- Branch / Division filters:
                     national → both selects with JS cascade
                     branch   → division only (server-side options, scoped to their branch)
                     division → no filters rendered --}}
                @if($accessLevel === 'national')
                <div class="md:col-span-2 lg:col-span-2">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Branch --}}
                        <div>
                            <label for="branch_id" class="block text-sm font-medium text-gray-700">
                                Branch
                            </label>
                            <select
                                name="branch_id"
                                id="branch_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm {{ request('branch_id') ? 'filter-active' : '' }}"
                                data-branch-select
                                data-selected-branch="{{ $selectedBranch ?? '' }}"
                            >
                                <option value="">All Branches</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}"
                                        @selected((string)($selectedBranch ?? '') === (string)$branch->id)
                                    >
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Division --}}
                        <div>
                            <label for="division_id" class="block text-sm font-medium text-gray-700">
                                Division
                            </label>
                            <select
                                name="division_id"
                                id="division_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm {{ request('division_id') ? 'filter-active' : '' }}"
                                data-division-select
                                data-selected-division="{{ $selectedDivision ?? '' }}"
                            >
                                <option value="">All Divisions</option>
                                {{-- Options will be populated/filtered by JS --}}
                            </select>
                        </div>
                    </div>

                    {{-- Embed divisions data for JS (all divisions, no scope limits) --}}
                    <script type="application/json" id="all-divisions-json">
                        {!! $divisions->map(fn($d) => [
                            'id'        => $d->id,
                            'name'      => $d->name,
                            'branch_id' => $d->branch_id,
                        ])->values()->toJson() !!}
                    </script>
                </div>
                @elseif($accessLevel === 'branch')
                <div class="md:col-span-2 lg:col-span-2">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {{-- Division (pre-filtered to this user's branch; no branch select) --}}
                        <div>
                            <label for="division_id" class="block text-sm font-medium text-gray-700">
                                Division
                            </label>
                            <select
                                name="division_id"
                                id="division_id"
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm {{ request('division_id') ? 'filter-active' : '' }}"
                            >
                                <option value="">All Divisions</option>
                                @foreach($divisions as $division)
                                    <option value="{{ $division->id }}"
                                        @selected((string)($selectedDivision ?? '') === (string)$division->id)
                                    >
                                        {{ $division->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Action --}}
                <div>
                    <label for="action" class="block text-sm font-medium text-gray-700">Action</label>
                    <select
                        name="action"
                        id="action"
                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm {{ request('action') ? 'filter-active' : '' }}"
                    >
                        <option value="">All</option>
                        @foreach($actions as $action)
                            <option value="{{ $action }}" @selected(request('action') === $action)>
                                {{ $action }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Date range --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="from_date" class="block text-sm font-medium text-gray-700">From date</label>
                        <input
                            type="date"
                            name="from_date"
                            id="from_date"
                            value="{{ request('from_date') }}"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm {{ request('from_date') ? 'filter-active' : '' }}"
                        >
                    </div>

                    <div>
                        <label for="to_date" class="block text-sm font-medium text-gray-700">To date</label>
                        <input
                            type="date"
                            name="to_date"
                            id="to_date"
                            value="{{ request('to_date') }}"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm {{ request('to_date') ? 'filter-active' : '' }}"
                        >
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 pt-1">
                <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm font-medium rounded shadow-sm">
                    Apply filters
                </button>
                <a href="{{ route('logs.index') }}" class="px-4 py-2 bg-gray-100 text-gray-800 text-sm rounded">
                    Reset
                </a>
            </div>
        </div>
    </form>

    {{-- Log table --}}
    <div class="bg-white shadow-sm rounded overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100">
            <tr>
                <th class="px-3 py-2 text-left">Time</th>
                <th class="px-3 py-2 text-left">Actor</th>
                <th class="px-3 py-2 text-left">User</th>
                <th class="px-3 py-2 text-left">Submitted by</th>
                <th class="px-3 py-2 text-left">Action</th>
                <th class="px-3 py-2 text-left">Subject</th>
                <th class="px-3 py-2 text-left">Branch / Division</th>
                <th class="px-3 py-2 text-left">Description</th>
            </tr>
            </thead>
            <tbody>
            @forelse($logs as $log)
                @php
                    $subject = $log->subject;
                @endphp

                <tr class="border-t align-top">
                    {{-- Time --}}
                    <td class="px-3 py-2 whitespace-nowrap">
                        {{ $log->created_at?->format('Y-m-d H:i') }}
                    </td>

                    {{-- Actor --}}
                    <td class="px-3 py-2 text-xs text-gray-800">
                        @if($log->user)
                            <div>
                                {{ $log->user->first_name }} {{ $log->user->last_name }}
                                <span class="text-gray-500">(#{{ $log->user->id }})</span>
                            </div>
                        @else
                            <div class="text-gray-400 italic">
                                System / N/A
                            </div>
                        @endif
                        @if($log->action === 'user_roles_updated'
                            && $log->subject_type === \App\Models\User::class
                            && $log->user_id === $log->subject_id)
                            <span class="inline-flex items-center gap-1 mt-1 px-1.5 py-0.5 rounded text-xs font-semibold bg-amber-100 text-amber-700 border border-amber-300">
                                <i class="fas fa-triangle-exclamation text-[10px]"></i>
                                Self-action
                            </span>
                        @endif
                    </td>

                    {{-- User (subject-related) --}}
                    <td class="px-3 py-2 text-xs text-gray-800">
                        {{-- Subject IS a User (e.g. member_branch_division_changed) --}}
                        @if($subject instanceof \App\Models\User)
                            <div>
                                <span class="font-semibold">User:</span>
                                {{ $subject->full_name ?? trim($subject->first_name . ' ' . $subject->last_name) }}
                                <span class="text-gray-500">(#{{ $subject->id }})</span>
                            </div>
                        @endif

                        {{-- MembershipPayment: the member/user the payment is for --}}
                        @if($subject instanceof \App\Models\MembershipPayment)
                            @if($subject->user)
                                <div>
                                    <span class="font-semibold">User:</span>
                                    {{ $subject->user->first_name }} {{ $subject->user->last_name }}
                                    <span class="text-gray-500">(#{{ $subject->user->id }})</span>
                                </div>
                            @endif
                        @endif

                        {{-- Donation: the donating user --}}
                        @if($subject instanceof \App\Models\Donation)
                            @if($subject->user)
                                <div>
                                    <span class="font-semibold">User:</span>
                                    {{ $subject->user->first_name }} {{ $subject->user->last_name }}
                                    <span class="text-gray-500">(#{{ $subject->user->id }})</span>
                                </div>
                            @else
                                <span class="text-gray-400">No user linked</span>
                            @endif
                        @endif

                        {{-- Activity: the user the activity is for --}}
                        @if($subject instanceof \App\Models\Activity)
                            @if($subject->user)
                                <div>
                                    <span class="font-semibold">User:</span>
                                    {{ $subject->user->first_name }} {{ $subject->user->last_name }}
                                    <span class="text-gray-500">(#{{ $subject->user->id }})</span>
                                </div>
                            @else
                                <span class="text-gray-400">No user linked</span>
                            @endif
                        @endif

                        {{-- Training: the user the training is for --}}
                        @if($subject instanceof \App\Models\Training)
                            @if($subject->user)
                                <div>
                                    <span class="font-semibold">User:</span>
                                    {{ $subject->user->first_name }} {{ $subject->user->last_name }}
                                    <span class="text-gray-500">(#{{ $subject->user->id }})</span>
                                </div>
                            @else
                                <span class="text-gray-400">No user linked</span>
                            @endif
                        @endif
                    </td>

                    {{-- Submitted by --}}
                    {{-- Submitted by --}}
                    <td class="px-3 py-2 text-xs text-gray-800">
                        {{-- MembershipPayment submittedByUser --}}
                        @if($subject instanceof \App\Models\MembershipPayment && $subject->submittedByUser)
                            <div>
                                {{ $subject->submittedByUser->first_name }} {{ $subject->submittedByUser->last_name }}
                                <span class="text-gray-500">(#{{ $subject->submittedByUser->id }})</span>
                            </div>

                            {{-- Donation: enteredBy (who submitted/entered the donation) --}}
                        @elseif($subject instanceof \App\Models\Donation && $subject->enteredBy)
                            <div>
                                {{ $subject->enteredBy->first_name }} {{ $subject->enteredBy->last_name }}
                                <span class="text-gray-500">(#{{ $subject->enteredBy->id }})</span>
                            </div>

                            {{-- Activity: submittedByUser (who submitted/entered the activity) --}}
                        @elseif($subject instanceof \App\Models\Activity && $subject->submittedByUser)
                            <div>
                                {{ $subject->submittedByUser->first_name }} {{ $subject->submittedByUser->last_name }}
                                <span class="text-gray-500">(#{{ $subject->submittedByUser->id }})</span>
                            </div>

                            {{-- Training: submittedByUser (who submitted/entered the training) --}}
                        @elseif($subject instanceof \App\Models\Training && $subject->submittedByUser)
                            <div>
                                {{ $subject->submittedByUser->first_name }} {{ $subject->submittedByUser->last_name }}
                                <span class="text-gray-500">(#{{ $subject->submittedByUser->id }})</span>
                            </div>

                        @else
                            <span class="text-gray-400">N/A</span>
                        @endif
                    </td>

                    {{-- Action (limited line break at first underscore) --}}
                    <td class="px-3 py-2">
                        @php
                            $action = $log->action ?? '';
                            $parts = explode('_', $action);
                            if (count($parts) <= 2) {
                                $firstLine  = $action;
                                $secondLine = null;
                            } else {
                                $mid = (int) ceil(count($parts) / 2);
                                $firstLine  = implode('_', array_slice($parts, 0, $mid));
                                $secondLine = implode('_', array_slice($parts, $mid));
                            }
                        @endphp

                        <span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded break-words">
                            @if($secondLine)
                                {{ $firstLine }}<br>{{ $secondLine }}
                            @else
                                {{ $firstLine }}
                            @endif
                        </span>
                    </td>

                    {{-- Subject (generic model info) --}}
                    <td class="px-3 py-2">
                        @if($log->subject_type && $log->subject_id)
                            <div class="text-xs text-gray-700">
                                {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}
                            </div>
                        @else
                            <span class="text-gray-400 text-xs">N/A</span>
                        @endif
                    </td>

                    {{-- Branch / Division --}}
                    <td class="px-3 py-2 text-xs text-gray-700">
                        @if($log->branch)
                            <div>{{ $log->branch->name }}</div>
                        @endif
                        @if($log->division)
                            <div>{{ $log->division->name }}</div>
                        @endif
                        @if(!$log->branch && !$log->division)
                            <span class="text-gray-400">N/A</span>
                        @endif
                    </td>

                    {{-- Description --}}
                    <td class="px-3 py-2">
                        <div class="text-xs text-gray-800">
                            {{ $log->description ?? '—' }}
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-3 py-4 text-center text-gray-500">
                        No log entries found for the selected filters.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $logs->links() }}
    </div>

    {{-- Simple JS for cascading Branch -> Division (all client-side) --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const branchSelect   = document.querySelector('[data-branch-select]');
            const divisionSelect = document.querySelector('[data-division-select]');
            const divisionsJsonEl = document.getElementById('all-divisions-json');

            if (!branchSelect || !divisionSelect || !divisionsJsonEl) {
                return;
            }

            const allDivisions = JSON.parse(divisionsJsonEl.textContent || '[]');
            const initialBranchId   = branchSelect.dataset.selectedBranch || '';
            const initialDivisionId = divisionSelect.dataset.selectedDivision || '';

            function populateDivisions(branchId, selectedDivisionId) {
                // Clear current options
                divisionSelect.innerHTML = '';

                // Base "All" option
                const allOpt = document.createElement('option');
                allOpt.value = '';
                allOpt.textContent = 'All Divisions';
                divisionSelect.appendChild(allOpt);

                // Filter divisions by branch if branchId given; else show all
                const filtered = branchId
                    ? allDivisions.filter(d => String(d.branch_id) === String(branchId))
                    : allDivisions;

                filtered.forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = d.id;
                    opt.textContent = d.name;

                    if (selectedDivisionId && String(selectedDivisionId) === String(d.id)) {
                        opt.selected = true;
                    }

                    divisionSelect.appendChild(opt);
                });
            }

            // Initial population (on page load)
            populateDivisions(initialBranchId, initialDivisionId);

            // Update divisions when branch changes
            branchSelect.addEventListener('change', function () {
                populateDivisions(this.value, '');
            });
        });
    </script>
</x-layouts.admin>
