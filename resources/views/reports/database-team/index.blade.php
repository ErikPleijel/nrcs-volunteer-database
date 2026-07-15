<x-layouts.admin title="Database Team">
    <x-slot name="pageHeader">
        <i class="fas fa-users-gear mr-3"></i> Database Team
    </x-slot>
    <x-slot name="subHeader">
        Roles · Coverage · Activity
    </x-slot>

    <div class="bg-gray-300 container mx-auto px-4 py-6">

        {{-- Tab bar --}}
        <div class="flex gap-2 border-b border-gray-200 mb-6">
            @foreach([
                'national'   => ['label' => 'National',   'icon' => 'fa-globe'],
                'branch'     => ['label' => 'Branch',     'icon' => 'fa-code-branch'],
                'activity'   => ['label' => 'Activity',   'icon' => 'fa-chart-bar'],
                'statistics' => ['label' => 'Statistics', 'icon' => 'fa-chart-pie'],
            ] as $tabKey => $tabDef)
                <a href="{{ route('reports.database-team.index', array_merge(request()->except('tab'), ['tab' => $tabKey])) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-t-md border border-b-0 transition-colors
                       {{ $activeTab === $tabKey
                           ? 'bg-white border-gray-200 text-indigo-700 font-semibold'
                           : 'bg-gray-50 border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-100' }}">
                    <i class="fas {{ $tabDef['icon'] }} text-xs"></i>
                    {{ $tabDef['label'] }}
                </a>
            @endforeach
        </div>

        {{-- ── TAB 1: National ──────────────────────────────────────────────── --}}
        @if($activeTab === 'national')
            @php
                $nationalLabels = [
                    'national_db_administrator' => 'National DB Administrator',
                    'national_db_assistant'      => 'National DB Assistant',
                    'observer_national_level'    => 'Observer',
                ];
            @endphp

            <form method="GET" class="mb-4" id="photos-toggle-national">
                <input type="hidden" name="tab" value="national">
                <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                    <input type="checkbox" name="show_photos" value="1"
                           {{ $showPhotos ? 'checked' : '' }}
                           onchange="this.form.submit()"
                           class="rounded border-gray-300">
                    Show profile photos
                </label>
            </form>

            @foreach($nationalLabels as $roleSlug => $roleLabel)
                @php
                    $headerColor = match($roleSlug) {
                        'national_db_administrator' => 'bg-indigo-600',
                        'national_db_assistant'     => 'bg-blue-600',
                        'observer_national_level'   => 'bg-gray-500',
                        default                     => 'bg-gray-500',
                    };
                @endphp
                <div class="mb-8">
                    <h2 class="text-base font-semibold text-gray-800 mb-3">{{ $roleLabel }}</h2>

                    @if(isset($nationalData[$roleSlug]) && $nationalData[$roleSlug]->isNotEmpty())
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($nationalData[$roleSlug] as $person)
                                <div class="bg-white rounded-lg shadow overflow-hidden flex flex-col">
                                    <div class="px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-white {{ $headerColor }}">
                                        {{ $roleLabel }}
                                    </div>
                                    <div class="p-4 flex gap-4">
                                        @if($showPhotos)
                                            <img src="{{ $person->profile_photo_url }}"
                                                 alt="{{ $person->first_name }}"
                                                 class="w-20 h-28 object-cover object-top rounded border border-gray-200 flex-shrink-0">
                                        @endif
                                        <div class="flex flex-col justify-center gap-1 text-sm">
                                            <div class="font-semibold text-gray-900">{{ $person->first_name }} {{ $person->last_name }}</div>
                                            <div class="text-xs text-gray-400">{!! $person->user_id_reference_link !!}</div>
                                            <div class="text-gray-500">{{ ucfirst($person->gender ?? '—') }}{{ $person->birth_year ? ', age ' . (now()->year - $person->birth_year) : '' }}</div>
                                            <div class="text-gray-700">{{ $person->email ?? '—' }}</div>
                                            <div class="text-gray-700">{{ $person->telephone1 ?? '—' }}</div>
                                            @php $directPerms = $person->getDirectPermissions(); @endphp
                                            @if($directPerms->isNotEmpty())
                                                <div class="mt-1 flex flex-wrap gap-1">
                                                    @foreach($directPerms as $perm)
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5
                                                                     rounded text-xs font-semibold
                                                                     bg-red-100 text-red-700 border border-red-300">
                                                            <i class="fas fa-key text-[10px]"></i>
                                                            {{ str_replace('_', ' ', $perm->name) }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-400 italic text-sm">No persons in this role.</p>
                    @endif
                </div>
            @endforeach
        @endif

        {{-- ── TAB 2: Branch ───────────────────────────────────────────────── --}}
        @if($activeTab === 'branch')
            @php
                $branchRoleLabels = [
                    'branch_secretary'        => 'Branch Secretary',
                    'branch_db_administrator' => 'Branch DB Administrator',
                    'branch_db_assistant'     => 'Branch DB Assistant',
                ];
            @endphp

            {{-- Branch dropdown --}}
            <form method="GET" class="mb-3" id="branch-select-form">
                <input type="hidden" name="tab" value="branch">
                @if($showPhotos) <input type="hidden" name="show_photos" value="1"> @endif
                <div class="flex items-center gap-3">
                    <label for="branch_id" class="filter-label-small">Branch</label>
                    <select name="branch_id" id="branch_id"
                            onchange="this.form.submit()"
                            class="px-2 py-1 text-xs border border-gray-300 rounded shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">— Select a branch —</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" {{ request('branch_id', $defaultBranchId) == $branch->id ? 'selected' : '' }}>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>

            <form method="GET" class="mb-6" id="photos-toggle-branch">
                <input type="hidden" name="tab" value="branch">
                <input type="hidden" name="branch_id" value="{{ request('branch_id', $defaultBranchId) }}">
                <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                    <input type="checkbox" name="show_photos" value="1"
                           {{ $showPhotos ? 'checked' : '' }}
                           onchange="this.form.submit()"
                           class="rounded border-gray-300">
                    Show profile photos
                </label>
            </form>

            @if(!$selectedBranch)
                <p class="text-gray-500 italic text-sm">Select a branch above to view its team.</p>
            @else
                {{-- Branch staff --}}
                <div class="mb-8">
                    <h2 class="text-base font-semibold text-gray-800 mb-3">Branch Staff</h2>

                    @foreach($branchRoleLabels as $roleSlug => $roleLabel)
                        @php
                            $persons = $branchData['branch'][$roleSlug] ?? collect();
                            $headerColor = match($roleSlug) {
                                'branch_secretary'        => 'bg-indigo-600',
                                'branch_db_administrator' => 'bg-blue-600',
                                'branch_db_assistant'     => 'bg-slate-600',
                                default                   => 'bg-gray-500',
                            };
                        @endphp

                        @if($roleSlug === 'branch_secretary')
                            @php $count = $persons->count(); @endphp
                            @if($count === 0 || $count > 1)
                                <div class="mb-3 px-4 py-2 bg-red-50 border border-red-300 rounded text-sm text-red-700 flex items-center gap-2">
                                    <i class="fas fa-triangle-exclamation"></i>
                                    {{ $count === 0 ? 'No Branch Secretary assigned.' : 'More than one Branch Secretary assigned — please review.' }}
                                </div>
                            @endif
                        @endif

                        @if($persons->isNotEmpty())
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                                @foreach($persons as $person)
                                    <div class="bg-white rounded-lg shadow overflow-hidden flex flex-col">
                                        <div class="px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-white {{ $headerColor }}">
                                            {{ $roleLabel }}
                                        </div>
                                        <div class="p-4 flex gap-4">
                                            @if($showPhotos)
                                                <img src="{{ $person->profile_photo_url }}"
                                                     alt="{{ $person->first_name }}"
                                                     class="w-20 h-28 object-cover object-top rounded border border-gray-200 flex-shrink-0">
                                            @endif
                                            <div class="flex flex-col justify-center gap-1 text-sm">
                                                <div class="font-semibold text-gray-900">{{ $person->first_name }} {{ $person->last_name }}</div>
                                                <div class="text-xs text-gray-400">{!! $person->user_id_reference_link !!}</div>
                                                <div class="text-gray-500">{{ ucfirst($person->gender ?? '—') }}{{ $person->birth_year ? ', age ' . (now()->year - $person->birth_year) : '' }}</div>
                                                <div class="text-gray-700">{{ $person->email ?? '—' }}</div>
                                                <div class="text-gray-700">{{ $person->telephone1 ?? '—' }}</div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-400 italic text-sm mt-4">No persons in this role.</p>
                        @endif
                    @endforeach
                </div>

                {{-- Division staff --}}
                <div class="mb-8">
                    <h2 class="text-base font-semibold text-gray-800 mb-4">Division Staff</h2>

                    @if(!empty($branchData['divisions']))
                        @foreach($branchData['divisions'] as $divGroup)
                            <div class="mb-6">
                                <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ $divGroup['division']->name }}</h3>
                                @if($divGroup['persons']->isNotEmpty())
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                        @foreach($divGroup['persons'] as $person)
                                            @php
                                                $divHeaderColor = str_contains($person->team_role_label ?? '', 'Finance')
                                                    ? 'bg-emerald-600'
                                                    : 'bg-amber-600';
                                            @endphp
                                            <div class="bg-white rounded-lg shadow overflow-hidden flex flex-col">
                                                <div class="px-4 py-1.5 text-xs font-semibold uppercase tracking-wide text-white {{ $divHeaderColor }}">
                                                    {{ $person->team_role_label ?? 'Division Assistant' }}
                                                </div>
                                                <div class="p-4 flex gap-4">
                                                    @if($showPhotos)
                                                        <img src="{{ $person->profile_photo_url }}"
                                                             alt="{{ $person->first_name }}"
                                                             class="w-20 h-28 object-cover object-top rounded border border-gray-200 flex-shrink-0">
                                                    @endif
                                                    <div class="flex flex-col justify-center gap-1 text-sm">
                                                        <div class="font-semibold text-gray-900">{{ $person->first_name }} {{ $person->last_name }}</div>
                                                        <div class="text-xs text-gray-400">{!! $person->user_id_reference_link !!}</div>
                                                        <div class="text-gray-500">{{ ucfirst($person->gender ?? '—') }}{{ $person->birth_year ? ', age ' . (now()->year - $person->birth_year) : '' }}</div>
                                                        <div class="text-gray-700">{{ $person->email ?? '—' }}</div>
                                                        <div class="text-gray-700">{{ $person->telephone1 ?? '—' }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="text-gray-400 italic text-sm">No division staff assigned.</p>
                                @endif
                            </div>
                        @endforeach
                    @else
                        <p class="text-gray-400 italic text-sm">No divisions found for this branch.</p>
                    @endif
                </div>
            @endif
        @endif

        {{-- ── TAB 3: Activity ─────────────────────────────────────────────── --}}
        @if($activeTab === 'activity')

            {{-- Scope dropdown --}}
            <form method="GET" class="mb-6" id="activity-scope-form">
                <input type="hidden" name="tab" value="activity">
                @if($showPhotos) <input type="hidden" name="show_photos" value="1"> @endif
                <div class="flex items-center gap-3">
                    <label for="activity_scope" class="filter-label-small">Scope</label>
                    <select name="activity_scope" id="activity_scope"
                            onchange="this.form.submit()"
                            class="px-2 py-1 text-xs border border-gray-300 rounded shadow-sm focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                        <option value="national" {{ $activitySelection === 'national' ? 'selected' : '' }}>National</option>
                        @foreach($branches as $branch)
                            <option value="branch_{{ $branch->id }}" {{ $activitySelection === 'branch_' . $branch->id ? 'selected' : '' }}>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>

            @foreach($activityData as $group)
                @if($group['persons']->isEmpty())
                    @continue
                @endif

                <div class="mb-8">
                    <table class="min-w-full text-sm bg-white rounded-lg shadow overflow-hidden">
                        <thead>
                            <tr class="bg-gray-100">
                                <th colspan="6" class="px-4 py-2 text-left font-semibold text-gray-700">
                                    {{ $group['group'] }}
                                </th>
                            </tr>
                            <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                                <th class="px-4 py-2 text-left">Name / DB#</th>
                                <th class="px-4 py-2 text-center">Membership</th>
                                <th class="px-4 py-2 text-center">Volunteering</th>
                                <th class="px-4 py-2 text-center">Trainings</th>
                                <th class="px-4 py-2 text-center">Donations</th>
                                <th class="px-4 py-2 text-center">Last Activity</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($group['persons'] as $person)
                                @php
                                    $lastActivity = $person->last_admin_activity_at;
                                    if ($lastActivity) {
                                        $days = now()->diffInDays($lastActivity);
                                        $actColor = $days <= 30 ? 'text-green-600' : ($days <= 90 ? 'text-amber-500' : 'text-red-600');
                                    } else {
                                        $actColor = 'text-gray-400';
                                    }
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-gray-900">{{ $person->first_name }} {{ $person->last_name }}</div>
                                        <div class="text-xs text-gray-400">{!! $person->user_id_reference_link !!}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="text-green-700 font-medium">{{ $person->cnt_membership_entered }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="text-green-700 font-medium">{{ $person->cnt_volunteering_entered }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="text-green-700 font-medium">{{ $person->cnt_trainings_entered }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="text-green-700 font-medium">{{ $person->cnt_donations_entered }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center {{ $actColor }}">
                                        {{ $lastActivity ? $lastActivity->format('d M Y') : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        @endif

        {{-- ── TAB 4: Statistics ───────────────────────────────────────────── --}}
        @if($activeTab === 'statistics')

            {{-- ── PART 1: National level ── --}}
            <div class="mb-8">
                <h2 class="text-base font-semibold text-gray-800 mb-3">National Level</h2>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-left">Role</th>
                                <th class="px-4 py-2 text-center">Count</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-gray-700">National DB Administrator</td>
                                <td class="px-4 py-2 text-center font-semibold
                                    {{ $statsNational['national_db_administrator'] === 0 ? 'text-red-500' : 'text-gray-900' }}">
                                    {{ $statsNational['national_db_administrator'] }}
                                </td>
                            </tr>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-gray-700">National DB Assistant</td>
                                <td class="px-4 py-2 text-center font-semibold text-gray-900">
                                    {{ $statsNational['national_db_assistant'] }}
                                </td>
                            </tr>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 text-gray-700">Observer</td>
                                <td class="px-4 py-2 text-center font-semibold text-gray-900">
                                    {{ $statsNational['observer_national_level'] }}
                                </td>
                            </tr>

                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ── PART 2: Branch coverage ── --}}
            <div class="mb-8">
                <h2 class="text-base font-semibold text-gray-800 mb-3">Branch Coverage</h2>
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-left">Branch</th>
                                <th class="px-4 py-2 text-center whitespace-nowrap">Secretary</th>
                                <th class="px-4 py-2 text-center whitespace-nowrap">DB Admin</th>
                                <th class="px-4 py-2 text-center whitespace-nowrap">DB Asst</th>
                                <th class="px-4 py-2 text-center whitespace-nowrap">Div Finance</th>
                                <th class="px-4 py-2 text-center whitespace-nowrap">Div Ops</th>
                                <th class="px-4 py-2 text-center whitespace-nowrap">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($statsBranches as $row)
                                @php
                                    $noSecretary = $row['secretary'] === 0;
                                    $noAdmin     = $row['db_admin'] === 0;
                                    $noStaff     = $row['total'] === 0;
                                @endphp
                                <tr class="hover:bg-gray-50 {{ $noStaff ? 'bg-red-50' : '' }}">
                                    <td class="px-4 py-2 text-gray-800 font-medium">
                                        {{ $row['branch']->name }}
                                    </td>
                                    <td class="px-4 py-2 text-center
                                        {{ $noSecretary ? 'text-red-500 font-semibold' : 'text-gray-700' }}">
                                        {{ $row['secretary'] }}
                                        @if($row['secretary'] > 1)
                                            <i class="fas fa-circle-exclamation text-orange-500 ml-1"
                                               title="More than one Branch Secretary assigned"></i>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-center
                                        {{ $noAdmin ? 'text-red-500 font-semibold' : 'text-gray-700' }}">
                                        {{ $row['db_admin'] }}
                                    </td>
                                    <td class="px-4 py-2 text-center text-gray-700">
                                        {{ $row['db_assistant'] }}
                                    </td>
                                    <td class="px-4 py-2 text-center text-gray-700">
                                        {{ $row['fin'] }}
                                    </td>
                                    <td class="px-4 py-2 text-center text-gray-700">
                                        {{ $row['ops'] }}
                                    </td>
                                    <td class="px-4 py-2 text-center font-semibold
                                        {{ $noStaff ? 'text-red-600' : 'text-gray-900' }}">
                                        {{ $row['total'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        {{-- Totals footer --}}
                        <tfoot class="bg-gray-100 text-xs font-semibold text-gray-700 border-t-2 border-gray-300">
                            <tr>
                                <td class="px-4 py-2">Total</td>
                                <td class="px-4 py-2 text-center">{{ $statsBranches->sum('secretary') }}</td>
                                <td class="px-4 py-2 text-center">{{ $statsBranches->sum('db_admin') }}</td>
                                <td class="px-4 py-2 text-center">{{ $statsBranches->sum('db_assistant') }}</td>
                                <td class="px-4 py-2 text-center">{{ $statsBranches->sum('fin') }}</td>
                                <td class="px-4 py-2 text-center">{{ $statsBranches->sum('ops') }}</td>
                                <td class="px-4 py-2 text-center">{{ $statsBranches->sum('total') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="mt-2 text-xs text-gray-400">
                    Rows highlighted in red have no assigned staff. Zero in Secretary or DB Admin column indicates a coverage gap.
                </p>
            </div>

        @endif

    </div>
</x-layouts.admin>
