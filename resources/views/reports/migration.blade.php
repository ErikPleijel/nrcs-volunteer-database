<x-layouts.admin title="Migration Report">
    <x-slot name="pageHeader">
        <i class="fas fa-route mr-3"></i> Migration Report
    </x-slot>
    <x-slot name="subHeader">
        Persons who moved between branches or divisions
    </x-slot>
    <x-slot name="backLink">
        <a href="{{ route('reports.dashboard') }}" class="btn-backlink">
            ← Back to Dashboard
        </a>
    </x-slot>

    <div class="container mx-auto px-4 py-6">

        {{-- Filter form --}}
        <form method="GET" class="mb-6 bg-white shadow rounded-lg p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">

                {{-- Branch selector — national only --}}
                @if($accessLevel === 'national')
                    <div>
                        <label class="filter-label-small">Branch</label>
                        <select name="branch_id" onchange="this.form.submit()"
                                class="filter-select-small">
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}"
                                    {{ $selectedBranchId == $branch->id ? 'selected' : '' }}>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div>
                        <label class="filter-label-small">Branch</label>
                        <p class="text-sm font-medium text-gray-700 mt-1">
                            {{ $branches->firstWhere('id', $selectedBranchId)?->name ?? '—' }}
                        </p>
                    </div>
                @endif

                {{-- Movement type --}}
                <div>
                    <label class="filter-label-small">Movement Type</label>
                    <select name="movement_type" class="filter-select-small">
                        <option value="branch"
                            {{ $movementType === 'branch' ? 'selected' : '' }}>
                            Between Branches
                        </option>
                        <option value="division"
                            {{ $movementType === 'division' ? 'selected' : '' }}>
                            Between Divisions
                        </option>
                    </select>
                </div>

                {{-- Direction --}}
                <div>
                    <label class="filter-label-small">Direction</label>
                    <select name="direction" class="filter-select-small">
                        <option value="both"
                            {{ $direction === 'both' ? 'selected' : '' }}>
                            Moved in or out
                        </option>
                        <option value="in"
                            {{ $direction === 'in' ? 'selected' : '' }}>
                            Moved in
                        </option>
                        <option value="out"
                            {{ $direction === 'out' ? 'selected' : '' }}>
                            Moved out
                        </option>
                    </select>
                </div>

                {{-- Date from --}}
                <div>
                    <label class="filter-label-small">From</label>
                    <input type="date" name="date_from"
                           value="{{ $dateFrom }}"
                           class="filter-select-small">
                </div>

                {{-- Date to --}}
                <div>
                    <label class="filter-label-small">To</label>
                    <input type="date" name="date_to"
                           value="{{ $dateTo }}"
                           class="filter-select-small">
                </div>

            </div>
            <div class="mt-3 flex gap-2">
                <button type="submit" class="filter-btn-primary">
                    <i class="fas fa-search mr-1"></i>Filter
                </button>
                <a href="{{ route('reports.migration') }}"
                   class="filter-btn-secondary filter-btn-secondary-active">
                    <i class="fas fa-times mr-1"></i>Reset
                </a>
            </div>
        </form>

        {{-- Results --}}
        <div class="bg-white shadow rounded-lg overflow-x-auto">
            @if($logs->count() > 0)
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide
                                  text-gray-500 border-b">
                        <tr>
                            <th class="px-4 py-3 text-left">Person</th>
                            <th class="px-4 py-3 text-left">From</th>
                            <th class="px-4 py-3 text-left">To</th>
                            <th class="px-4 py-3 text-left">Moved by</th>
                            <th class="px-4 py-3 text-left">Type</th>
                            <th class="px-4 py-3 text-left">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($logs as $log)
                            @php
                                $old = $log->old_values ?? [];
                                $new = $log->new_values ?? [];
                                $isSelfMove = $log->user_id === $log->subject_id;

                                $oldBranch = $branchMap[(int)($old['branch_id'] ?? 0)] ?? '—';
                                $newBranch = $branchMap[(int)($new['branch_id'] ?? 0)] ?? '—';

                                $oldDiv = $divisionMap[(int)($old['division_id'] ?? 0)] ?? '—';
                                $newDiv = $divisionMap[(int)($new['division_id'] ?? 0)] ?? '—';

                                $branchChanged   = ($old['branch_id'] ?? null) != ($new['branch_id'] ?? null);
                                $divisionChanged = ($old['division_id'] ?? null) != ($new['division_id'] ?? null);
                            @endphp
                            <tr class="hover:bg-gray-50">
                                {{-- Person moved --}}
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    @if($log->subject)
                                        <a href="{{ route('users.show', $log->subject_id) }}"
                                           class="hover:text-indigo-600 hover:underline"
                                           target="_blank">
                                            {{ $log->subject->full_name ?? '—' }}
                                        </a>
                                    @else
                                        <span class="text-gray-400 italic">Deleted user</span>
                                    @endif
                                </td>

                                {{-- From --}}
                                <td class="px-4 py-3 text-gray-600 text-xs">
                                    @if($branchChanged)
                                        <div>
                                            <span class="font-medium">Branch:</span>
                                            {{ $oldBranch }}
                                        </div>
                                    @endif
                                    @if($divisionChanged)
                                        <div>
                                            <span class="font-medium">Division:</span>
                                            {{ $oldDiv }}
                                        </div>
                                    @endif
                                </td>

                                {{-- To --}}
                                <td class="px-4 py-3 text-gray-600 text-xs">
                                    @if($branchChanged)
                                        <div>
                                            <span class="font-medium">Branch:</span>
                                            {{ $newBranch }}
                                        </div>
                                    @endif
                                    @if($divisionChanged)
                                        <div>
                                            <span class="font-medium">Division:</span>
                                            {{ $newDiv }}
                                        </div>
                                    @endif
                                </td>

                                {{-- Moved by --}}
                                <td class="px-4 py-3 text-sm">
                                    @if($isSelfMove)
                                        <span class="inline-flex items-center gap-1
                                                     px-2 py-0.5 rounded text-xs
                                                     font-medium bg-blue-100 text-blue-700">
                                            <i class="fas fa-user text-[10px]"></i>
                                            Self
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1
                                                     px-2 py-0.5 rounded text-xs
                                                     font-medium bg-gray-100 text-gray-700">
                                            <i class="fas fa-user-shield text-[10px]"></i>
                                            {{ $log->user?->full_name ?? 'Admin' }}
                                        </span>
                                    @endif
                                </td>

                                {{-- Type badge --}}
                                <td class="px-4 py-3">
                                    @if($branchChanged && $divisionChanged)
                                        <span class="inline-flex items-center px-2 py-0.5
                                                     rounded text-xs font-medium
                                                     bg-purple-100 text-purple-700">
                                            Branch + Division
                                        </span>
                                    @elseif($branchChanged)
                                        <span class="inline-flex items-center px-2 py-0.5
                                                     rounded text-xs font-medium
                                                     bg-red-100 text-red-700">
                                            Branch
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5
                                                     rounded text-xs font-medium
                                                     bg-amber-100 text-amber-700">
                                            Division
                                        </span>
                                    @endif
                                </td>

                                {{-- Date --}}
                                <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                                    <x-time-ago :date="$log->created_at" />
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Pagination --}}
                <div class="px-4 py-3 bg-gray-50 border-t">
                    {{ $logs->appends(request()->query())->links() }}
                </div>

            @else
                <div class="px-6 py-12 text-center text-gray-500">
                    <i class="fas fa-route text-4xl text-gray-300 mb-4"></i>
                    <p class="font-medium">No movements found</p>
                    <p class="text-sm mt-1">Try adjusting the filters or date range.</p>
                </div>
            @endif
        </div>

        <p class="mt-3 text-xs text-gray-400">
            Showing moves recorded in the audit log. Only changes made
            after the logging system was introduced are shown.
        </p>
    </div>
</x-layouts.admin>
