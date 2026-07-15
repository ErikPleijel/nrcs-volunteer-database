<x-layouts.admin title="Red Cross Units">
    <x-slot name="pageHeader">
        <i class="fas fa-people-group mr-3"></i> Red Cross Units
    </x-slot>
    <x-slot name="subHeader">
        Demographics · Training · Activity
    </x-slot>

    <div class="container mx-auto px-4 py-6">

        {{-- ── BREADCRUMB ───────────────────────────────────────────────────── --}}
        @if($level !== 'branch')
            <div class="mb-4 text-sm text-gray-600 flex items-center gap-1">
                <a href="{{ route('reports.red-cross-units.index', ['tab' => $activeTab]) }}"
                   class="underline text-indigo-600 hover:text-indigo-800">All Branches</a>
                @if($level === 'unit')
                    <span class="text-gray-400">›</span>
                    <a href="{{ route('reports.red-cross-units.index', ['branch_id' => $currentBranch->id, 'tab' => $activeTab]) }}"
                       class="underline text-indigo-600 hover:text-indigo-800">{{ $currentBranch->name }}</a>
                    <span class="text-gray-400">›</span>
                    <span class="text-gray-700">{{ $currentDivision->name }}</span>
                @else
                    <span class="text-gray-400">›</span>
                    <span class="text-gray-700">{{ $currentBranch->name }}</span>
                @endif
            </div>
        @endif

        {{-- ── TABS ─────────────────────────────────────────────────────────── --}}
        <div class="flex gap-2 border-b border-gray-200 mb-6">
            @foreach([
                'demographics' => ['label' => 'Demographics',      'icon' => 'fa-users'],
                'training'     => ['label' => 'Training',           'icon' => 'fa-graduation-cap'],
                'activity'     => ['label' => 'Volunteering Hours', 'icon' => 'fa-chart-bar'],
                'account'      => ['label' => 'Account Status',     'icon' => 'fa-circle-user'],
            ] as $tabKey => $tabDef)
                <a href="{{ route('reports.red-cross-units.index', array_merge(
                        array_filter(['branch_id' => $currentBranch?->id, 'division_id' => $currentDivision?->id]),
                        ['tab' => $tabKey]
                    )) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-t-md border border-b-0 transition-colors
                       {{ $activeTab === $tabKey
                           ? 'bg-white border-gray-200 text-indigo-700 font-semibold'
                           : 'bg-gray-50 border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-100' }}">
                    <i class="fas {{ $tabDef['icon'] }} text-xs"></i>
                    {{ $tabDef['label'] }}
                </a>
            @endforeach
        </div>

        {{-- ── TAB 1: Demographics ──────────────────────────────────────────── --}}
        @if($activeTab === 'demographics')
            @if(empty($demographicsData))
                <p class="text-center text-gray-400 italic py-12">No data available.</p>
            @else
                @php
                    $ageGroupLabels = ['under_15' => '< 15', 'age_15_24' => '15–24', 'age_25_34' => '25–34',
                                       'age_35_44' => '35–44', 'age_45_54' => '45–54', 'age_55_64' => '55–64', 'age_65plus' => '65+'];
                @endphp
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm bg-white rounded-lg shadow overflow-hidden">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                                <th class="px-4 py-2 text-left">{{ $level === 'unit' ? 'Unit' : ($level === 'division' ? 'Division' : 'Branch') }}</th>
                                <th class="px-4 py-2 text-center">Total<br>Volunteers</th>
                                @if($level !== 'unit')
                                    <th class="px-4 py-2 text-center">Total<br>Units</th>
                                @endif
                                <th class="px-4 py-2 text-center">Men</th>
                                <th class="px-4 py-2 text-center">Women</th>
                                <th class="px-4 py-2 text-center">Avg Age</th>
                                @foreach($ageGroupLabels as $label)
                                    <th class="px-4 py-2 text-center">{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($demographicsData as $row)
                                <tr class="hover:bg-gray-50 {{ ($level === 'branch' && isset($highlightBranchId) && $row['id'] == $highlightBranchId) ? 'bg-yellow-50 border-l-4 border-yellow-400' : '' }}">
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        @if($row['link'])
                                            <a href="{{ $row['link'] }}" class="underline text-indigo-600 hover:text-indigo-800">{{ $row['label'] }}</a>
                                        @else
                                            {{ $row['label'] }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-block bg-gray-100 text-gray-700 rounded-full px-2 py-0.5 text-xs font-medium">{{ $row['total_volunteers'] }}</span>
                                    </td>
                                    @if($level !== 'unit')
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-block bg-indigo-100 text-indigo-700 rounded-full px-2 py-0.5 text-xs font-medium">{{ $row['total_units'] }}</span>
                                        </td>
                                    @endif
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-block bg-blue-100 text-blue-700 rounded-full px-2 py-0.5 text-xs font-medium">{{ $row['men'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-block bg-pink-100 text-pink-700 rounded-full px-2 py-0.5 text-xs font-medium">{{ $row['women_display'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-gray-700">
                                        {{ $row['avg_age'] ?? '—' }}
                                    </td>
                                    @foreach(array_keys($ageGroupLabels) as $key)
                                        <td class="px-4 py-3 text-center">
                                            @if(($row['groups'][$key] ?? 0) > 0)
                                                <span class="inline-block bg-gray-100 text-gray-700 rounded-full px-2 py-0.5 text-xs font-medium">{{ $row['groups'][$key] }}</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

        {{-- ── TAB 2: Training ─────────────────────────────────────────────── --}}
        @if($activeTab === 'training')
            @if(empty($trainingData))
                <p class="text-center text-gray-400 italic py-12">No data available.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm bg-white rounded-lg shadow overflow-hidden">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                                <th class="px-4 py-2 text-left">{{ $level === 'unit' ? 'Unit' : ($level === 'division' ? 'Division' : 'Branch') }}</th>
                                <th class="px-4 py-2 text-center">Total<br>Volunteers</th>
                                @if($level !== 'unit')
                                    <th class="px-4 py-2 text-center">Total<br>Units</th>
                                @endif
                                <th class="px-4 py-2 text-center">% Any Training</th>
                                <th class="px-4 py-2 text-center">% First Aid</th>
                                <th class="px-4 py-2 text-center">Trained Last 12m</th>
                                <th class="px-4 py-2 text-center">Trained Last 3m</th>
                                <th class="px-4 py-2 text-center">Trained Last 1m</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($trainingData as $row)
                                @php
                                    $pctAny = $row['pct_any'];
                                    $pctFa  = $row['pct_first_aid'];
                                    $colorAny = $pctAny === null ? 'text-gray-400' : ($pctAny >= 70 ? 'text-green-600' : ($pctAny >= 40 ? 'text-amber-500' : 'text-red-600'));
                                    $colorFa  = $pctFa  === null ? 'text-gray-400' : ($pctFa  >= 70 ? 'text-green-600' : ($pctFa  >= 40 ? 'text-amber-500' : 'text-red-600'));
                                @endphp
                                <tr class="hover:bg-gray-50 {{ ($level === 'branch' && isset($highlightBranchId) && $row['id'] == $highlightBranchId) ? 'bg-yellow-50 border-l-4 border-yellow-400' : '' }}">
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        @if($row['link'])
                                            <a href="{{ $row['link'] }}" class="underline text-indigo-600 hover:text-indigo-800">{{ $row['label'] }}</a>
                                        @else
                                            {{ $row['label'] }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-block bg-gray-100 text-gray-700 rounded-full px-2 py-0.5 text-xs font-medium">{{ $row['total_volunteers'] }}</span>
                                    </td>
                                    @if($level !== 'unit')
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-block bg-indigo-100 text-indigo-700 rounded-full px-2 py-0.5 text-xs font-medium">{{ $row['total_units'] }}</span>
                                        </td>
                                    @endif
                                    <td class="px-4 py-3 text-center {{ $colorAny }} font-medium">
                                        {{ $pctAny !== null ? $pctAny . '%' : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center {{ $colorFa }} font-medium">
                                        {{ $pctFa !== null ? $pctFa . '%' : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-block bg-blue-100 text-blue-700 rounded-full px-2 py-0.5 text-xs font-medium">{{ $row['trained_12m'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-block bg-blue-100 text-blue-700 rounded-full px-2 py-0.5 text-xs font-medium">{{ $row['trained_3m'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-block bg-blue-100 text-blue-700 rounded-full px-2 py-0.5 text-xs font-medium">{{ $row['trained_1m'] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

        {{-- ── TAB 3: Activity ─────────────────────────────────────────────── --}}
        @if($activeTab === 'activity')
            @if(empty($activityData))
                <p class="text-center text-gray-400 italic py-12">No data available.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm bg-white rounded-lg shadow overflow-hidden">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                                <th class="px-4 py-2 text-left">{{ $level === 'unit' ? 'Unit' : ($level === 'division' ? 'Division' : 'Branch') }}</th>
                                <th class="px-4 py-2 text-center">Total<br>Volunteers</th>
                                @if($level !== 'unit')
                                    <th class="px-4 py-2 text-center">Total<br>Units</th>
                                @endif
                                <th class="px-4 py-2 text-center">Total Hours</th>
                                <th class="px-4 py-2 text-center">Hours per<br>Volunteer</th>
                                <th class="px-4 py-2 text-center">Hours Last 12m</th>
                                <th class="px-4 py-2 text-center">Hours Last 3m</th>
                                <th class="px-4 py-2 text-center">Hours Last 1m</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($activityData as $row)
                                <tr class="hover:bg-gray-50 {{ ($level === 'branch' && isset($highlightBranchId) && $row['id'] == $highlightBranchId) ? 'bg-yellow-50 border-l-4 border-yellow-400' : '' }}">
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        @if($row['link'])
                                            <a href="{{ $row['link'] }}" class="underline text-indigo-600 hover:text-indigo-800">{{ $row['label'] }}</a>
                                        @else
                                            {{ $row['label'] }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-block bg-gray-100 text-gray-700 rounded-full px-2 py-0.5 text-xs font-medium">{{ $row['total_volunteers'] }}</span>
                                    </td>
                                    @if($level !== 'unit')
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-block bg-indigo-100 text-indigo-700 rounded-full px-2 py-0.5 text-xs font-medium">{{ $row['total_units'] }}</span>
                                        </td>
                                    @endif
                                    <td class="px-4 py-3 text-center text-gray-700 font-medium">{{ number_format($row['total_hours']) }}</td>
                                    <td class="px-4 py-3 text-center text-gray-700">
                                        {{ $row['hours_per_volunteer'] !== null ? $row['hours_per_volunteer'] : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-gray-700">{{ number_format($row['hours_12m']) }}</td>
                                    <td class="px-4 py-3 text-center text-gray-700">{{ number_format($row['hours_3m']) }}</td>
                                    <td class="px-4 py-3 text-center text-gray-700">{{ number_format($row['hours_1m']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

        {{-- ── TAB 4: Account Status ───────────────────────────────────────── --}}
        @if($activeTab === 'account')
            @if(empty($accountData))
                <p class="text-center text-gray-400 italic py-12">No data available.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm bg-white rounded-lg shadow overflow-hidden">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                                <th class="px-4 py-2 text-left">{{ $level === 'unit' ? 'Unit' : ($level === 'division' ? 'Division' : 'Branch') }}</th>
                                <th class="px-4 py-2 text-center">Total<br>Volunteers</th>
                                @if($level !== 'unit')
                                    <th class="px-4 py-2 text-center">Total<br>Units</th>
                                @endif
                                <th class="px-4 py-2 text-center">% Never<br>Logged In</th>
                                <th class="px-4 py-2 text-center">Avg Days<br>Since Login</th>
                                <th class="px-4 py-2 text-center">% With<br>Email</th>
                                <th class="px-4 py-2 text-center">% Has<br>Photo</th>
                                <th class="px-4 py-2 text-center">Avg Days<br>Since DB Entry</th>
                                <th class="px-4 py-2 text-center">% Dormant</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($accountData as $row)
                                @php
                                    $colorNeverLogin = $row['pct_never_logged_in'] === null ? 'text-gray-400'
                                        : ($row['pct_never_logged_in'] <= 20 ? 'text-green-600'
                                        : ($row['pct_never_logged_in'] <= 50 ? 'text-amber-500' : 'text-red-600'));
                                    $colorPicture    = $row['pct_picture'] === null ? 'text-gray-400'
                                        : ($row['pct_picture'] >= 80 ? 'text-green-600'
                                        : ($row['pct_picture'] >= 50 ? 'text-amber-500' : 'text-red-600'));
                                    $colorDormant    = $row['pct_dormant'] === null ? 'text-gray-400'
                                        : ($row['pct_dormant'] <= 10 ? 'text-green-600'
                                        : ($row['pct_dormant'] <= 30 ? 'text-amber-500' : 'text-red-600'));
                                    $colorHasEmail   = $row['pct_has_email'] === null ? 'text-gray-400'
                                        : ($row['pct_has_email'] >= 80 ? 'text-green-600'
                                        : ($row['pct_has_email'] >= 50 ? 'text-amber-500' : 'text-red-600'));
                                @endphp
                                <tr class="hover:bg-gray-50 {{ ($level === 'branch' && isset($highlightBranchId) && $row['id'] == $highlightBranchId) ? 'bg-yellow-50 border-l-4 border-yellow-400' : '' }}">
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        @if($row['link'])
                                            <a href="{{ $row['link'] }}" class="underline text-indigo-600 hover:text-indigo-800">{{ $row['label'] }}</a>
                                        @else
                                            {{ $row['label'] }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-block bg-gray-100 text-gray-700 rounded-full px-2 py-0.5 text-xs font-medium">{{ $row['total_volunteers'] }}</span>
                                    </td>
                                    @if($level !== 'unit')
                                        <td class="px-4 py-3 text-center">
                                            <span class="inline-block bg-indigo-100 text-indigo-700 rounded-full px-2 py-0.5 text-xs font-medium">{{ $row['total_units'] }}</span>
                                        </td>
                                    @endif
                                    <td class="px-4 py-3 text-center font-medium {{ $colorNeverLogin }}">
                                        {{ $row['pct_never_logged_in'] !== null ? $row['pct_never_logged_in'] . '%' : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-gray-700">
                                        {{ $row['avg_days_since_login'] !== null ? $row['avg_days_since_login'] . ' days' : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center font-medium {{ $colorHasEmail }}">
                                        {{ $row['pct_has_email'] !== null ? $row['pct_has_email'] . '%' : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center font-medium {{ $colorPicture }}">
                                        {{ $row['pct_picture'] !== null ? $row['pct_picture'] . '%' : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-gray-700">
                                        {{ $row['avg_days_since_activity'] !== null ? $row['avg_days_since_activity'] . ' days' : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center font-medium {{ $colorDormant }}">
                                        {{ $row['pct_dormant'] !== null ? $row['pct_dormant'] . '%' : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

    </div>
</x-layouts.admin>
