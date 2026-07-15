@php
    $title      = 'Financial Trends – National';
    $pageHeader = 'Financial Trends – National';

    $breadcrumbs = [
        ['label' => 'Dashboard', 'route' => 'reports.dashboard'],
        ['label' => 'Financial Trends'],

    ];

     $trendYears = $trendOptions[$selectedTrendKey] ?? 2;
@endphp

<x-reports.reports-layout
    :title="$title"
    :pageHeader="$pageHeader"
    :breadcrumbs="$breadcrumbs"
>
    {{-- <x-reports.trend-chart> has no embedded form here, so the two
         standalone forms below (trend-range selector, year selector) are
         hidden directly by id rather than via a chart-wrapper selector. --}}
    <style>
        @media print {
            #financial-actions { display: none !important; }
            #financial-trend-form { display: none !important; }
            #financial-year-form { display: none !important; }
        }
    </style>

    <div id="financial-actions" class="flex justify-end gap-2 mb-4">
        <a href="{{ route('reports.financial.national', array_merge(request()->query(), ['export' => 'csv'])) }}"
           class="filter-btn-secondary">
            <i class="fas fa-file-csv mr-1"></i>Export CSV
        </a>
        <button type="button" onclick="window.print()" class="filter-btn-secondary">
            <i class="fas fa-print mr-1"></i>Print
        </button>
    </div>

    <h1 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">
        Financial Trends – National (₦)
    </h1>

    <div class="mb-6 rounded-md bg-blue-50 border border-blue-200 p-4 text-sm text-blue-900">
        <p>
            This report shows <span class="font-semibold">total membership revenue</span>,
            combining all sources — organisations, members, and volunteer fees — without distinction.
        </p>
        <p class="mt-1">
            For a breakdown by contributor type and fee, see the
            <a href="{{ route('reports.financial.index') }}" class="font-semibold underline text-blue-700 hover:text-blue-900">Financial Breakdown</a> report.
        </p>
    </div>

    {{-- Trend range selector for chart --}}
    @if(isset($trendDataset))
        <form id="financial-trend-form" method="GET" class="mb-4 inline-flex items-center gap-2">
            <label class="text-sm text-gray-600 dark:text-gray-300">
                Trend range:
            </label>

            <select name="trend_years"
                    onchange="this.form.submit()"
                    class="border border-gray-300 rounded px-3 py-1 text-sm">
                @foreach($trendOptions as $key => $value)
                    <option value="{{ $key }}" @selected($key === $selectedTrendKey)>
                        Last {{ $value }} years
                    </option>
                @endforeach
            </select>

            {{-- Preserve current year + filters --}}
            <input type="hidden" name="year" value="{{ $selectedYear }}">
            @if(!empty($branchId))
                <input type="hidden" name="branch_id" value="{{ $branchId }}">
            @endif
            @if(!empty($divisionId))
                <input type="hidden" name="division_id" value="{{ $divisionId }}">
            @endif
        </form>
    @endif

    {{-- Trend chart --}}
    @isset($trendDataset)
        <x-reports.trend-chart
            :dataset="$trendDataset"
            chartId="membership-fee-trend-chart"
            :title="'Membership Fee Revenue – last ' . $trendYears . ' years'"
            seriesLabel="Membership fee revenue (₦)"
        />
    @endisset

    {{-- Year selector (for table + total) --}}
    <form id="financial-year-form" method="GET" class="mt-16 mb-6">
        <label class="text-sm text-gray-600 dark:text-gray-300 mr-2">
            Select Year:
        </label>

        <select name="year"
                onchange="this.form.submit()"
                class="border border-gray-300 rounded px-3 py-1 text-sm">
            @foreach($availableYears as $year)
                <option value="{{ $year }}" @selected($year == $selectedYear)>
                    {{ $year }}
                </option>
            @endforeach
        </select>

        {{-- Preserve currently selected trend range + filters --}}
        <input type="hidden" name="trend_years" value="{{ $selectedTrendKey }}">
        @if(!empty($branchId))
            <input type="hidden" name="branch_id" value="{{ $branchId }}">
        @endif
        @if(!empty($divisionId))
            <input type="hidden" name="division_id" value="{{ $divisionId }}">
        @endif
    </form>



    {{-- Quarterly branch table --}}
    <div class="mt-6">
        <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">
            Membership Revenue by Branch – Quarterly {{ $selectedYear }}
        </h2>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 border border-gray-200">
                <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                        Branch
                    </th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Q1</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Q2</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Q3</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Q4</th>
                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Total</th>
                </tr>
                </thead>

                <tbody class="bg-white divide-y divide-gray-200">
                @foreach($branchMembershipSummaries as $row)
                    <tr>
                        <td class="px-4 py-2 text-sm font-medium text-gray-900">
                            <a href="{{ route('reports.financial.branch', [
                                'branch'      => $row->branch_id,
                                'year'        => $selectedYear,
                                'trend_years' => $selectedTrendKey,
                            ]) }}" class="underline text-blue-600 hover:text-blue-800 font-bold">
                                {{ $row->branch_name }}
                            </a>
                        </td>
                        <td class="px-4 py-2 text-sm text-right">
                            {{ number_format($row->q1) }}
                        </td>
                        <td class="px-4 py-2 text-sm text-right">
                            {{ number_format($row->q2) }}
                        </td>
                        <td class="px-4 py-2 text-sm text-right">
                            {{ number_format($row->q3) }}
                        </td>
                        <td class="px-4 py-2 text-sm text-right">
                            {{ number_format($row->q4) }}
                        </td>
                        <td class="px-4 py-2 text-sm text-right font-bold">
                            {{ number_format($row->total) }}
                        </td>
                    </tr>
                @endforeach
                </tbody>

                <tfoot class="bg-gray-50 font-semibold">
                <tr>
                    <td class="px-4 py-2 text-gray-900">Total</td>
                    <td class="px-4 py-2 text-right">
                        {{ number_format($branchMembershipSummaries->sum('q1')) }}
                    </td>
                    <td class="px-4 py-2 text-right">
                        {{ number_format($branchMembershipSummaries->sum('q2')) }}
                    </td>
                    <td class="px-4 py-2 text-right">
                        {{ number_format($branchMembershipSummaries->sum('q3')) }}
                    </td>
                    <td class="px-4 py-2 text-right">
                        {{ number_format($branchMembershipSummaries->sum('q4')) }}
                    </td>
                    <td class="px-4 py-2 text-right">
                        {{ number_format($branchMembershipSummaries->sum('total')) }}
                    </td>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
</x-reports.reports-layout>
