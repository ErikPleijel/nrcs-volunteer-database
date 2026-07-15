@php
    $title      = $title      ?? 'Database Access Team';
    $pageHeader = $pageHeader ?? 'Database Access Team';

    $breadcrumbs = [
        ['label' => 'Dashboard', 'route' => 'reports.dashboard'],
        ['label' => 'Database Access Team'],
    ];

    $team                  = $team                  ?? collect();
    $summary               = $summary               ?? collect();
    $directPermissionUsers = $directPermissionUsers ?? collect();
    $branches              = $branches              ?? collect();
    $selectedBranchId      = $selectedBranchId      ?? null;

    // Current branch (for headings)
    $currentBranch = $selectedBranchId
        ? $branches->firstWhere('id', (int) $selectedBranchId)
        : null;

    $currentBranchName = $currentBranch->name ?? '—';

    // -----------------------
    // Split / filter by level
    // -----------------------

    // 1) National
    $nationalTeam = $team->filter(function ($user) {
        return $user->getAccessLevel() === 'national';
    })->sortBy(function ($user) {
        $roleId = $user->role_id ?? 0;
        return sprintf('%05d-%s-%s', $roleId, $user->last_name, $user->first_name);
    });

    // 2) Branch – filtered by selected branch
    $branchTeam = $team->filter(function ($user) use ($selectedBranchId) {
        if ($user->getAccessLevel() !== 'branch') {
            return false;
        }
        if (!$selectedBranchId) {
            return true;
        }
        return $user->getScopedBranchId() === (int) $selectedBranchId;
    })->sortBy(function ($user) {
        $roleId = $user->role_id ?? 0;
        return sprintf('%05d-%s-%s', $roleId, $user->last_name, $user->first_name);
    });

    // 3) Division – filtered by same branch
    $divisionTeam = $team->filter(function ($user) use ($selectedBranchId) {
        if ($user->getAccessLevel() !== 'division') {
            return false;
        }
        if (!$selectedBranchId) {
            return true;
        }
        return $user->getScopedBranchId() === (int) $selectedBranchId;
    })->sortBy(function ($user) {
        $roleId     = $user->role_id ?? 0;
        $divisionId = $user->division_id ?? 0;
        return sprintf('%05d-%05d-%s-%s', $roleId, $divisionId, $user->last_name, $user->first_name);
    });
@endphp

<x-reports.reports-layout
    :title="$title"
    :pageHeader="$pageHeader"
    :breadcrumbs="$breadcrumbs"
>
    <h1 class="text-xl font-semibold mb-6 text-gray-900 dark:text-white">
        Database Access Team
    </h1>

    {{-- =======================
         STATS CARDS (TOP)
       ======================= --}}
    @if($summary->count())
        <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($summary as $row)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4">
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $row->display_name }}
                    </div>
                    <div class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $row->user_count }}
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- =======================
         1) NATIONAL ROLES
       ======================= --}}
    <div class="mt-8 mb-6">
        <h2 class="text-lg font-semibold mb-3 text-gray-900 dark:text-white">
            National Level Roles
        </h2>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-x-auto">
            <table class="min-w-full border-collapse">
                <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                        Name
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                        Role
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                        DB Reference
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                        Telephone
                    </th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($nationalTeam as $user)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        {{-- Name --}}
                        <td class="px-4 py-3 text-gray-900 dark:text-white font-medium whitespace-nowrap">
                            {{ $user->full_name }}
                        </td>

                        {{-- Role --}}
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                            {{ $user->role_display_name_for_list ?? $user->role_display_name }}
                        </td>

                        {{-- DB Reference --}}
                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400 font-mono whitespace-nowrap">
                            {{ $user->user_id_reference }}
                        </td>

                        {{-- Telephone --}}
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-200 whitespace-nowrap">
                            @if($user->primary_phone)
                                <div>{{ $user->primary_phone }}</div>
                            @endif
                            @if($user->secondary_phone)
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $user->secondary_phone }}
                                </div>
                            @endif
                            @if(!$user->primary_phone && !$user->secondary_phone)
                                <span class="text-sm text-gray-400 italic">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                            No national-level database roles found.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- =======================
         2) USERS WITH DIRECT PERMISSIONS
       ======================= --}}
    <div class="mt-8 mb-6">
        <h2 class="text-lg font-semibold mb-1 text-gray-900 dark:text-white">
            Users with Extra Database Permissions
        </h2>

        <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
            These users have one or more specific permissions (for example to authorize roles or manage access)
            assigned directly to their account, in addition to what they inherit from their roles.
        </p>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-x-auto">
            <table class="min-w-full border-collapse">
                <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                        Name
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                        Role
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                        DB Reference
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                        Telephone
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                        Direct Permissions
                    </th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($directPermissionUsers as $user)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        {{-- Name --}}
                        <td class="px-4 py-3 text-gray-900 dark:text-white font-medium whitespace-nowrap">
                            {{ $user->full_name }}
                        </td>

                        {{-- Role --}}
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                            {{ $user->role_display_name_for_list ?? $user->role_display_name }}
                        </td>

                        {{-- DB Reference --}}
                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400 font-mono whitespace-nowrap">
                            {{ $user->user_id_reference }}
                        </td>

                        {{-- Telephone --}}
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-200 whitespace-nowrap">
                            @if($user->primary_phone)
                                <div>{{ $user->primary_phone }}</div>
                            @endif
                            @if($user->secondary_phone)
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $user->secondary_phone }}
                                </div>
                            @endif
                            @if(!$user->primary_phone && !$user->secondary_phone)
                                <span class="text-sm text-gray-400 italic">—</span>
                            @endif
                        </td>

                        {{-- Direct permissions --}}
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-200">
                            {{ $user->direct_permission_names ?? '' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                            No users with extra database permissions found.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- =======================
         3) BRANCH LEVEL ROLES (FILTERED BY BRANCH)
       ======================= --}}
    <div class="mt-10 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Branch Level Roles – {{ $currentBranchName }}
            </h2>

            {{-- Branch dropdown (big and prominent, left in its flex group) --}}
            <form method="GET" class="flex items-center gap-2">
                <label for="branch_id" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                    Branch
                </label>
                <select
                    id="branch_id"
                    name="branch_id"
                    class="text-sm md:text-base px-3 py-2 border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 rounded-md shadow-sm"
                    onchange="this.form.submit()"
                >
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}"
                            @selected((int)$branch->id === (int)$selectedBranchId)
                        >
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-x-auto">
            <table class="min-w-full border-collapse">
                <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                        Name
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                        Role / Branch
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                        DB Reference
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                        Telephone
                    </th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($branchTeam as $user)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        {{-- Name --}}
                        <td class="px-4 py-3 text-gray-900 dark:text-white font-medium whitespace-nowrap">
                            {{ $user->full_name }}
                        </td>

                        {{-- Role + branch name --}}
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                            <div>
                                {{ $user->role_display_name_for_list ?? $user->role_display_name }}
                            </div>
                            @if($user->branch)
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $user->branch->name }}
                                </div>
                            @endif
                        </td>

                        {{-- DB Reference --}}
                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400 font-mono whitespace-nowrap">
                            {{ $user->user_id_reference }}
                        </td>

                        {{-- Telephone --}}
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-200 whitespace-nowrap">
                            @if($user->primary_phone)
                                <div>{{ $user->primary_phone }}</div>
                            @endif
                            @if($user->secondary_phone)
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $user->secondary_phone }}
                                </div>
                            @endif
                            @if(!$user->primary_phone && !$user->secondary_phone)
                                <span class="text-sm text-gray-400 italic">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                            No branch-level database roles found for this branch.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- =======================
         4) DIVISION LEVEL ROLES (FILTERED BY SAME BRANCH)
       ======================= --}}
    <div class="mt-10 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Division Level Roles – {{ $currentBranchName }}
            </h2>
            {{-- No extra dropdown here; uses same branch filter as above --}}
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-x-auto">
            <table class="min-w-full border-collapse">
                <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                        Name
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                        Role / Branch &amp; Division
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                        DB Reference
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-300 uppercase tracking-wider">
                        Telephone
                    </th>
                </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($divisionTeam as $user)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                        {{-- Name --}}
                        <td class="px-4 py-3 text-gray-900 dark:text-white font-medium whitespace-nowrap">
                            {{ $user->full_name }}
                        </td>

                        {{-- Role + branch code / division name --}}
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                            <div>
                                {{ $user->role_display_name_for_list ?? $user->role_display_name }}
                            </div>
                            @if($user->branch || $user->division)
                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                    @if($user->branch)
                                        {{ $user->branch->getBranchCodeForReference() }}
                                    @endif
                                    @if($user->division)
                                        / {{ $user->division->name }}
                                    @endif
                                </div>
                            @endif
                        </td>

                        {{-- DB Reference --}}
                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400 font-mono whitespace-nowrap">
                            {{ $user->user_id_reference }}
                        </td>

                        {{-- Telephone --}}
                        <td class="px-4 py-3 text-gray-800 dark:text-gray-200 whitespace-nowrap">
                            @if($user->primary_phone)
                                <div>{{ $user->primary_phone }}</div>
                            @endif
                            @if($user->secondary_phone)
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ $user->secondary_phone }}
                                </div>
                            @endif
                            @if(!$user->primary_phone && !$user->secondary_phone)
                                <span class="text-sm text-gray-400 italic">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                            No division-level database roles found for this branch.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-reports.reports-layout>
