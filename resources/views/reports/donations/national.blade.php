@php
    use Carbon\Carbon;

    $title      = 'Donation Report – National';
    $pageHeader = 'Donations – National Overview';

    $breadcrumbs = [
        ['label' => 'Dashboard', 'route' => 'reports.dashboard'],
        ['label' => 'Donations National'],

    ];

    // Fallbacks from controller; keep blade robust
    $trendOptions             = $trendOptions             ?? [];
    $selectedTrendKey         = $selectedTrendKey         ?? '2_years';
    $years                    = $years                    ?? [];
    $selectedYear             = $selectedYear             ?? Carbon::now()->year;
    $trendDataset             = $trendDataset             ?? ['labels' => [], 'cash_values' => [], 'in_kind_values' => []];
    $branchQuarterlySummaries = $branchQuarterlySummaries ?? collect();

    // Ensure we have a Collection to use ->sum()
    if (! $branchQuarterlySummaries instanceof \Illuminate\Support\Collection) {
        $branchQuarterlySummaries = collect($branchQuarterlySummaries);
    }

    // National totals across all branches (for summary row)
    $totalQ1Cash      = $branchQuarterlySummaries->sum('q1_cash');
    $totalQ1InKind    = $branchQuarterlySummaries->sum('q1_in_kind');
    $totalQ2Cash      = $branchQuarterlySummaries->sum('q2_cash');
    $totalQ2InKind    = $branchQuarterlySummaries->sum('q2_in_kind');
    $totalQ3Cash      = $branchQuarterlySummaries->sum('q3_cash');
    $totalQ3InKind    = $branchQuarterlySummaries->sum('q3_in_kind');
    $totalQ4Cash      = $branchQuarterlySummaries->sum('q4_cash');
    $totalQ4InKind    = $branchQuarterlySummaries->sum('q4_in_kind');
    $totalCashAll     = $branchQuarterlySummaries->sum('total_cash');
    $totalInKindAll   = $branchQuarterlySummaries->sum('total_in_kind');
@endphp

<x-reports.reports-layout
    :title="$title"
    :pageHeader="$pageHeader"
    :breadcrumbs="$breadcrumbs"
>
    <style>
        @media print {
            #donations-actions { display: none !important; }
            #donations-chart-wrapper form { display: none !important; }
            #donations-year-form { display: none !important; }
        }
    </style>

    <div id="donations-actions" class="flex justify-end gap-2 mb-4">
        <a href="{{ route('reports.donations.national', array_merge(request()->query(), ['export' => 'csv'])) }}"
           class="filter-btn-secondary">
            <i class="fas fa-file-csv mr-1"></i>Export CSV
        </a>
        <button type="button" onclick="window.print()" class="filter-btn-secondary">
            <i class="fas fa-print mr-1"></i>Print
        </button>
    </div>

    {{-- 💹 Combined Donations Trend (Cash + In-kind, National) --}}
    <div id="donations-chart-wrapper">
        <x-reports.donations-chart
            chartId="national-donations-trend"
            chartTitle="Donations Trend – National"
            :trendOptions="$trendOptions"
            :selectedTrendKey="$selectedTrendKey"
            :formAction="route('reports.donations.national')"
            :request="request()"
            :labels="$trendDataset['labels'] ?? []"
            :cashValues="$trendDataset['cash_values'] ?? []"
            :inKindValues="$trendDataset['in_kind_values'] ?? []"
        />
    </div>

    {{-- 📅 Year selector for quarterly branch summary --}}
    <div id="donations-year-form" class="mt-8 mb-4">
        <form method="GET"
              action="{{ route('reports.donations.national') }}"
              class="flex items-center gap-3 flex-wrap">
            {{-- Preserve current trend range when changing year --}}
            <input type="hidden" name="trend_years" value="{{ $selectedTrendKey }}">

            <label for="year" class="text-sm text-gray-700 dark:text-gray-300">
                Select year for quarterly summary:
            </label>

            <select
                id="year"
                name="year"
                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-sm rounded-md"
                onchange="this.form.submit()"
            >
                @foreach ($years as $yearOption)
                    <option value="{{ $yearOption }}" @selected($yearOption == $selectedYear)>
                        {{ $yearOption }}
                    </option>
                @endforeach
            </select>

            @if (!empty($trendOptions))
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    Trend range: {{ $trendOptions[$selectedTrendKey] ?? '?' }} year(s)
                </span>
            @endif
        </form>
    </div>

    {{-- 🧮 Quarterly Donations Summary by Branch --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Quarterly Donations Summary by Branch – {{ $selectedYear }}
            </h2>
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                Cash values are in ₦. In-kind figures show the number of in-kind donations (not individual items).
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700 dark:text-gray-200 whitespace-nowrap">
                        Branch
                    </th>

                    {{-- Q1 --}}
                    <th colspan="2" class="px-4 py-2 text-center font-semibold text-gray-700 dark:text-gray-200 border-l border-gray-200 dark:border-gray-700">
                        Q1
                    </th>
                    {{-- Q2 --}}
                    <th colspan="2" class="px-4 py-2 text-center font-semibold text-gray-700 dark:text-gray-200 border-l border-gray-200 dark:border-gray-700">
                        Q2
                    </th>
                    {{-- Q3 --}}
                    <th colspan="2" class="px-4 py-2 text-center font-semibold text-gray-700 dark:text-gray-200 border-l border-gray-200 dark:border-gray-700">
                        Q3
                    </th>
                    {{-- Q4 --}}
                    <th colspan="2" class="px-4 py-2 text-center font-semibold text-gray-700 dark:text-gray-200 border-l border-gray-700">
                        Q4
                    </th>

                    {{-- Totals --}}
                    <th colspan="2" class="px-4 py-2 text-center font-semibold text-gray-700 dark:text-gray-200 border-l border-gray-200 dark:border-gray-700">
                        Total
                    </th>

                </tr>
                <tr class="bg-gray-100 dark:bg-gray-900/70 text-xs text-gray-600 dark:text-gray-300">
                    <th class="px-4 py-2 text-left">Branch</th>

                    <th class="px-2 py-2 text-right border-l border-gray-200 dark:border-gray-700">Cash (₦)</th>
                    <th class="px-2 py-2 text-right">In-kind</th>

                    <th class="px-2 py-2 text-right border-l border-gray-200 dark:border-gray-700">Cash (₦)</th>
                    <th class="px-2 py-2 text-right">In-kind</th>

                    <th class="px-2 py-2 text-right border-l border-gray-200 dark:border-gray-700">Cash (₦)</th>
                    <th class="px-2 py-2 text-right">In-kind</th>

                    <th class="px-2 py-2 text-right border-l border-gray-200 dark:border-gray-700">Cash (₦)</th>
                    <th class="px-2 py-2 text-right">In-kind</th>

                    <th class="px-2 py-2 text-right border-l border-gray-200 dark:border-gray-700">Cash (₦)</th>
                    <th class="px-2 py-2 text-right">In-kind</th>
                </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($branchQuarterlySummaries as $row)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/50">
                        <td class="px-4 py-2 text-gray-900 dark:text-gray-100 whitespace-nowrap">
                            <a href="{{ route('reports.donations.branch', ['branch' => $row->branch_id]) }}?year={{ $selectedYear }}&trend_years={{ $selectedTrendKey }}"
                               class="font-bold underline text-blue-600 hover:text-blue-800">
                                {{ $row->branch_name }}
                            </a>
                        </td>

                        {{-- Q1 --}}
                        <td class="px-2 py-2 text-right text-gray-900 dark:text-gray-100 border-l border-gray-200 dark:border-gray-700">
                            {{ number_format($row->q1_cash, 2) }}
                        </td>
                        <td class="px-2 py-2 text-right text-gray-900 dark:text-gray-100">
                            {{ number_format($row->q1_in_kind) }}
                        </td>

                        {{-- Q2 --}}
                        <td class="px-2 py-2 text-right text-gray-900 dark:text-gray-100 border-l border-gray-200 dark:border-gray-700">
                            {{ number_format($row->q2_cash, 2) }}
                        </td>
                        <td class="px-2 py-2 text-right text-gray-900 dark:text-gray-100">
                            {{ number_format($row->q2_in_kind) }}
                        </td>

                        {{-- Q3 --}}
                        <td class="px-2 py-2 text-right text-gray-900 dark:text-gray-100 border-l border-gray-200 dark:border-gray-700">
                            {{ number_format($row->q3_cash, 2) }}
                        </td>
                        <td class="px-2 py-2 text-right text-gray-900 dark:text-gray-100">
                            {{ number_format($row->q3_in_kind) }}
                        </td>

                        {{-- Q4 --}}
                        <td class="px-2 py-2 text-right text-gray-900 dark:text-gray-100 border-l border-gray-200 dark:border-gray-700">
                            {{ number_format($row->q4_cash, 2) }}
                        </td>
                        <td class="px-2 py-2 text-right text-gray-900 dark:text-gray-100">
                            {{ number_format($row->q4_in_kind) }}
                        </td>

                        {{-- Totals --}}
                        <td class="px-2 py-2 text-right font-semibold text-gray-900 dark:text-gray-100 border-l border-gray-200 dark:border-gray-700">
                            {{ number_format($row->total_cash, 2) }}
                        </td>
                        <td class="px-2 py-2 text-right font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format($row->total_in_kind) }}
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="px-4 py-4 text-center text-sm text-gray-600 dark:text-gray-300">
                            No donation data found for {{ $selectedYear }}.
                        </td>
                    </tr>
                @endforelse

                {{-- National summary row (all branches) --}}
                @if ($branchQuarterlySummaries->isNotEmpty())
                    <tr class="bg-gray-100 dark:bg-gray-900/70 border-t border-gray-300 dark:border-gray-600">
                        <td class="px-4 py-2 font-semibold text-gray-900 dark:text-gray-100">
                            Total (all branches)
                        </td>

                        {{-- Q1 --}}
                        <td class="px-2 py-2 text-right font-semibold text-gray-900 dark:text-gray-100 border-l border-gray-200 dark:border-gray-700">
                            {{ number_format($totalQ1Cash, 2) }}
                        </td>
                        <td class="px-2 py-2 text-right font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format($totalQ1InKind) }}
                        </td>

                        {{-- Q2 --}}
                        <td class="px-2 py-2 text-right font-semibold text-gray-900 dark:text-gray-100 border-l border-gray-200 dark:border-gray-700">
                            {{ number_format($totalQ2Cash, 2) }}
                        </td>
                        <td class="px-2 py-2 text-right font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format($totalQ2InKind) }}
                        </td>

                        {{-- Q3 --}}
                        <td class="px-2 py-2 text-right font-semibold text-gray-900 dark:text-gray-100 border-l border-gray-200 dark:border-gray-700">
                            {{ number_format($totalQ3Cash, 2) }}
                        </td>
                        <td class="px-2 py-2 text-right font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format($totalQ3InKind) }}
                        </td>

                        {{-- Q4 --}}
                        <td class="px-2 py-2 text-right font-semibold text-gray-900 dark:text-gray-100 border-l border-gray-200 dark:border-gray-700">
                            {{ number_format($totalQ4Cash, 2) }}
                        </td>
                        <td class="px-2 py-2 text-right font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format($totalQ4InKind) }}
                        </td>

                        {{-- Totals --}}
                        <td class="px-2 py-2 text-right font-semibold text-gray-900 dark:text-gray-100 border-l border-gray-200 dark:border-gray-700">
                            {{ number_format($totalCashAll, 2) }}
                        </td>
                        <td class="px-2 py-2 text-right font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format($totalInKindAll) }}
                        </td>

                    </tr>
                @endif
                </tbody>
            </table>
        </div>
    </div>
</x-reports.reports-layout>
