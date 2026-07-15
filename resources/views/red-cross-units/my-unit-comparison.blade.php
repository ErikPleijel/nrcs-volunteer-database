<x-layouts.app title="Unit Comparison">

    <div class="min-h-screen py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <a href="{{ route('red-cross-units.my-unit') }}"
               class="inline-flex items-center gap-2 text-sm text-indigo-600 hover:underline mb-6">
                <i class="fas fa-arrow-left"></i> Back to My Unit
            </a>

            {{-- Drill-up breadcrumb --}}
            <div class="mb-6 flex flex-wrap items-center gap-2 text-xl font-semibold text-gray-700">
                <a href="{{ route('red-cross-units.my-unit-comparison', ['level' => 'branch']) }}"
                   class="underline text-indigo-600 hover:text-indigo-800">National</a>

                @if($currentBranch)
                    <span class="text-gray-400">›</span>
                    <a href="{{ route('red-cross-units.my-unit-comparison', ['level' => 'division', 'branch_id' => $currentBranch->id]) }}"
                       class="underline text-indigo-600 hover:text-indigo-800">{{ $currentBranch->name }}</a>
                @endif

                @if($currentDivision)
                    <span class="text-gray-400">›</span>
                    <a href="{{ route('red-cross-units.my-unit-comparison', ['level' => 'unit', 'division_id' => $currentDivision->id]) }}"
                       class="underline text-indigo-600 hover:text-indigo-800">{{ $currentDivision->name }}</a>
                @endif

                @if($level === 'branch')
                    <span class="text-gray-400">›</span>
                    <span class="text-gray-900">All Branches</span>
                @elseif($level === 'division' && $currentBranch)
                    <span class="text-gray-400">›</span>
                    <span class="text-gray-900">Divisions in {{ $currentBranch->name }}</span>
                @elseif($level === 'unit' && $currentDivision)
                    <span class="text-gray-400">›</span>
                    <span class="text-gray-900">Units in {{ $currentDivision->name }}</span>
                @endif
            </div>

            {{-- Table --}}
            @if(empty($comparisonData))
                <p class="text-center text-gray-400 italic py-12">No data available.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm bg-white rounded-lg shadow overflow-hidden">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                                <th class="px-4 py-2 text-left">{{ $level === 'unit' ? 'Unit' : ($level === 'division' ? 'Division' : 'Branch') }}</th>
                                <th class="px-4 py-2 text-center">Total<br>Volunteers</th>
                                <th class="px-4 py-2 text-center">% Any<br>Training</th>
                                <th class="px-4 py-2 text-center">% First<br>Aid</th>
                                <th class="px-4 py-2 text-center">Total<br>Hours</th>
                                <th class="px-4 py-2 text-center">Hours per<br>Volunteer</th>
                                <th class="px-4 py-2 text-center">% Dormant</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($comparisonData as $row)
                                @php
                                    $colorTraining = $row['pct_any_training'] === null ? 'text-gray-400'
                                        : ($row['pct_any_training'] >= 70 ? 'text-green-600'
                                        : ($row['pct_any_training'] >= 40 ? 'text-amber-500' : 'text-red-600'));
                                    $colorFa = $row['pct_first_aid'] === null ? 'text-gray-400'
                                        : ($row['pct_first_aid'] >= 70 ? 'text-green-600'
                                        : ($row['pct_first_aid'] >= 40 ? 'text-amber-500' : 'text-red-600'));
                                    $colorDormant = $row['pct_dormant'] === null ? 'text-gray-400'
                                        : ($row['pct_dormant'] <= 10 ? 'text-green-600'
                                        : ($row['pct_dormant'] <= 30 ? 'text-amber-500' : 'text-red-600'));
                                @endphp
                                <tr class="hover:bg-gray-50 {{ $row['highlight'] ? 'bg-yellow-50 border-l-4 border-yellow-400' : '' }}">
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        @if($row['link'])
                                            <a href="{{ $row['link'] }}" class="underline text-indigo-600 hover:text-indigo-800">{{ $row['label'] }}</a>
                                        @else
                                            {{ $row['label'] }}
                                        @endif
                                        @if($row['highlight'])
                                            <span class="ml-2 text-xs text-yellow-600 font-normal">(you)</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-block bg-gray-100 text-gray-700 rounded-full px-2 py-0.5 text-xs font-medium">{{ $row['total_volunteers'] }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-center font-medium {{ $colorTraining }}">
                                        {{ $row['pct_any_training'] !== null ? $row['pct_any_training'] . '%' : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center font-medium {{ $colorFa }}">
                                        {{ $row['pct_first_aid'] !== null ? $row['pct_first_aid'] . '%' : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-gray-700">
                                        {{ number_format($row['total_hours']) }}
                                    </td>
                                    <td class="px-4 py-3 text-center text-gray-700">
                                        {{ $row['hours_per_volunteer'] !== null ? number_format($row['hours_per_volunteer'], 1) : '—' }}
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

        </div>
    </div>

</x-layouts.app>
