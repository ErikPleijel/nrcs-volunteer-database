@php
    $title      = 'Training Report – Branch';
    $pageHeader = 'Trainings – Branch Overview';

    $breadcrumbs = [
        ['label' => 'Reports', 'route' => 'reports.dashboard'],
        ['label' => 'Trainings National', 'route' => 'reports.trainings.national'],
        ['label' => $branch->name ?? 'Branch'],
    ];

    $quarterlySummary            = $quarterlySummary            ?? [];
    $trendDataset                = $trendDataset                ?? ['labels' => [], 'first_aid' => [], 'other' => []];
    $divisionQuarterlySummaries  = $divisionQuarterlySummaries  ?? collect();

    // Safe helpers for quarters (for headline totals)
    $quarters = ['q1', 'q2', 'q3', 'q4'];

    $totalYearFirstAid = 0;
    $totalYearOther    = 0;
    foreach ($quarters as $q) {
        $totalYearFirstAid += $quarterlySummary[$q]['first_aid'] ?? 0;
        $totalYearOther    += $quarterlySummary[$q]['other'] ?? 0;
    }
    $totalYearAll = $totalYearFirstAid + $totalYearOther;
@endphp

<x-reports.reports-layout
    :title="$title"
    :pageHeader="$pageHeader"
    :breadcrumbs="$breadcrumbs"
>
    <style>
        @media print {
            #trainings-branch-actions { display: none !important; }
            #trainings-chart-wrapper form { display: none !important; }
            #trainings-branch-year-form { display: none !important; }
        }
    </style>

    <div id="trainings-branch-actions" class="flex justify-end gap-2 mb-4">
        <a href="{{ route('reports.trainings.branch', array_merge(['branch' => $branch->id], request()->query(), ['export' => 'csv'])) }}"
           class="filter-btn-secondary">
            <i class="fas fa-file-csv mr-1"></i>Export CSV
        </a>
        <button type="button" onclick="window.print()" class="filter-btn-secondary">
            <i class="fas fa-print mr-1"></i>Print
        </button>
    </div>

    {{-- Multi-line training chart (First Aid + Other) --}}
    <div id="trainings-chart-wrapper">
        <x-reports.training-chart
            chartId="branch-training-trend"
            chartTitle="Training Trend ({{ $branch->name }})"
            chartLabel="Trainings"
            :dataset="$trendDataset"
            :trendOptions="$trendOptions"
            :selectedTrendKey="$selectedTrendKey"
            :formAction="route('reports.trainings.branch', $branch)"
            :request="request()"
        />
    </div>

    {{-- Headline stats --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6 mt-6">
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
            <h2 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                First Aid Trainings – Last 12 Months ({{ $branch->name }})
            </h2>
            <p class="text-2xl font-semibold text-emerald-600 dark:text-emerald-400">
                {{ number_format($firstAidLast12Months) }}
            </p>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
            <h2 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                Total Trainings – {{ $selectedYear }}
            </h2>
            <p class="text-2xl font-semibold text-blue-600 dark:text-blue-400">
                {{ number_format($totalYearAll) }}
            </p>
        </div>

        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
            <h2 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
                First Aid vs Other – {{ $selectedYear }}
            </h2>
            <p class="text-sm text-gray-800 dark:text-gray-100">
                First Aid: <span class="font-semibold">{{ number_format($totalYearFirstAid) }}</span><br>
                Other: <span class="font-semibold">{{ number_format($totalYearOther) }}</span>
            </p>
        </div>
    </div>

    {{-- Year selector --}}
    <form id="trainings-branch-year-form" method="GET" class="mt-16 mb-4 inline-flex flex-wrap items-center gap-3">
        <div>
            <label for="year" class="text-sm text-gray-700 dark:text-gray-300 mr-2">
                Select Year:
            </label>
            <select id="year" name="year"
                    onchange="this.form.submit()"
                    class="border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 text-sm rounded-md">
                @foreach($availableYears as $year)
                    <option value="{{ $year }}" @selected($year == $selectedYear)>
                        {{ $year }}
                    </option>
                @endforeach
            </select>
        </div>
        {{-- Preserve trend range when switching year --}}
        <input type="hidden" name="trend_years" value="{{ $selectedTrendKey }}">
    </form>

    {{-- Quarterly summary table by Division --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6">
        <h2 class="text-lg font-semibold mb-3 text-gray-900 dark:text-white">
            Quarterly Trainings by Division – {{ $selectedYear }} ({{ $branch->name }})
        </h2>

        <div class="overflow-x-auto">
            <table class="min-w-full text-xs md:text-sm">
                <thead>

                <tr class="border-b border-gray-300 dark:border-gray-700">
                    <th rowspan="2" class="text-left py-2 pr-4 text-gray-600 dark:text-gray-300 align-middle">
                        Division
                    </th>

                    <th colspan="2" class="text-center py-2 px-2 text-gray-600 dark:text-gray-300">
                        Q1
                    </th>
                    <th colspan="2" class="text-center py-2 px-2 text-gray-600 dark:text-gray-300">
                        Q2
                    </th>
                    <th colspan="2" class="text-center py-2 px-2 text-gray-600 dark:text-gray-300">
                        Q3
                    </th>
                    <th colspan="2" class="text-center py-2 px-2 text-gray-600 dark:text-gray-300">
                        Q4
                    </th>

                    <th rowspan="2" class="text-right py-2 px-2 text-gray-600 dark:text-gray-300 align-middle">
                        Total
                    </th>
                </tr>

                <tr class="border-b border-gray-200 dark:border-gray-700">
                    <th class="text-right py-1 px-2 text-gray-500 dark:text-gray-400">FA</th>
                    <th class="text-right py-1 px-2 text-gray-500 dark:text-gray-400">Other</th>

                    <th class="text-right py-1 px-2 text-gray-500 dark:text-gray-400">FA</th>
                    <th class="text-right py-1 px-2 text-gray-500 dark:text-gray-400">Other</th>

                    <th class="text-right py-1 px-2 text-gray-500 dark:text-gray-400">FA</th>
                    <th class="text-right py-1 px-2 text-gray-500 dark:text-gray-400">Other</th>

                    <th class="text-right py-1 px-2 text-gray-500 dark:text-gray-400">FA</th>
                    <th class="text-right py-1 px-2 text-gray-500 dark:text-gray-400">Other</th>
                </tr>
                </thead>

                <tbody>
                @forelse($divisionQuarterlySummaries as $row)
                    <tr class="border-b border-gray-100 dark:border-gray-700">
                        <td class="py-2 pr-4 text-gray-800 dark:text-gray-100 whitespace-nowrap">
                            {{ $row->division_name }}
                        </td>

                        {{-- Q1 --}}
                        <td class="py-2 px-1 text-right text-gray-800 dark:text-gray-100 border-l border-gray-200 dark:border-gray-700">
                            {{ number_format($row->q1_first_aid) }}
                        </td>
                        <td class="py-2 px-1 text-right text-gray-800 dark:text-gray-100">
                            {{ number_format($row->q1_other) }}
                        </td>

                        {{-- Q2 --}}
                        <td class="py-2 px-1 text-right text-gray-800 dark:text-gray-100 border-l border-gray-200 dark:border-gray-700">
                            {{ number_format($row->q2_first_aid) }}
                        </td>
                        <td class="py-2 px-1 text-right text-gray-800 dark:text-gray-100">
                            {{ number_format($row->q2_other) }}
                        </td>

                        {{-- Q3 --}}
                        <td class="py-2 px-1 text-right text-gray-800 dark:text-gray-100 border-l border-gray-200 dark:border-gray-700">
                            {{ number_format($row->q3_first_aid) }}
                        </td>
                        <td class="py-2 px-1 text-right text-gray-800 dark:text-gray-100">
                            {{ number_format($row->q3_other) }}
                        </td>

                        {{-- Q4 --}}
                        <td class="py-2 px-1 text-right text-gray-800 dark:text-gray-100 border-l border-gray-200 dark:border-gray-700">
                            {{ number_format($row->q4_first_aid) }}
                        </td>
                        <td class="py-2 px-1 text-right text-gray-800 dark:text-gray-100">
                            {{ number_format($row->q4_other) }}
                        </td>

                        {{-- Total --}}
                        <td class="py-2 px-2 text-right font-semibold text-gray-900 dark:text-white border-l border-gray-300 dark:border-gray-600">
                            {{ number_format($row->total_all) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="py-4 text-center text-gray-500 dark:text-gray-400">
                            No training data found for {{ $selectedYear }} in this branch.
                        </td>
                    </tr>
                @endforelse

                {{-- Totals row --}}
                @if($divisionQuarterlySummaries->isNotEmpty())
                    @php
                        $totalQ1Fa = $divisionQuarterlySummaries->sum('q1_first_aid');
                        $totalQ1Ot = $divisionQuarterlySummaries->sum('q1_other');

                        $totalQ2Fa = $divisionQuarterlySummaries->sum('q2_first_aid');
                        $totalQ2Ot = $divisionQuarterlySummaries->sum('q2_other');

                        $totalQ3Fa = $divisionQuarterlySummaries->sum('q3_first_aid');
                        $totalQ3Ot = $divisionQuarterlySummaries->sum('q3_other');

                        $totalQ4Fa = $divisionQuarterlySummaries->sum('q4_first_aid');
                        $totalQ4Ot = $divisionQuarterlySummaries->sum('q4_other');

                        $grandTotal = $divisionQuarterlySummaries->sum('total_all');
                    @endphp

                    <tr class="border-t border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/40">
                        <td class="py-2 pr-4 font-semibold text-gray-900 dark:text-white">
                            Total (all divisions)
                        </td>

                        {{-- Q1 totals --}}
                        <td class="py-2 px-1 text-right font-semibold text-gray-900 dark:text-white border-l border-gray-200 dark:border-gray-700">
                            {{ number_format($totalQ1Fa) }}
                        </td>
                        <td class="py-2 px-1 text-right font-semibold text-gray-900 dark:text-white">
                            {{ number_format($totalQ1Ot) }}
                        </td>

                        {{-- Q2 totals --}}
                        <td class="py-2 px-1 text-right font-semibold text-gray-900 dark:text-white border-l border-gray-200 dark:border-gray-700">
                            {{ number_format($totalQ2Fa) }}
                        </td>
                        <td class="py-2 px-1 text-right font-semibold text-gray-900 dark:text-white">
                            {{ number_format($totalQ2Ot) }}
                        </td>

                        {{-- Q3 totals --}}
                        <td class="py-2 px-1 text-right font-semibold text-gray-900 dark:text-white border-l border-gray-200 dark:border-gray-700">
                            {{ number_format($totalQ3Fa) }}
                        </td>
                        <td class="py-2 px-1 text-right font-semibold text-gray-900 dark:text-white">
                            {{ number_format($totalQ3Ot) }}
                        </td>

                        {{-- Q4 totals --}}
                        <td class="py-2 px-1 text-right font-semibold text-gray-900 dark:text-white border-l border-gray-200 dark:border-gray-700">
                            {{ number_format($totalQ4Fa) }}
                        </td>
                        <td class="py-2 px-1 text-right font-semibold text-gray-900 dark:text-white">
                            {{ number_format($totalQ4Ot) }}
                        </td>

                        {{-- Grand total --}}
                        <td class="py-2 px-2 text-right font-bold text-gray-900 dark:text-white border-l border-gray-300 dark:border-gray-600">
                            {{ number_format($grandTotal) }}
                        </td>
                    </tr>
                @endif
                </tbody>


            </table>
        </div>
    </div>

</x-reports.reports-layout>
