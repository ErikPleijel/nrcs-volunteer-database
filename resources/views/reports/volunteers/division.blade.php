@php
    $title      = 'Volunteer Report – ' . $division->name . ' Division';
    $pageHeader = 'Volunteers – ' . $division->name . ' Division';

    $breadcrumbs = [
        ['label' => 'Dashboard', 'route' => 'reports.dashboard'],
        ['label' => 'National', 'route' => 'reports.volunteers.national'],
        ['label' => $division->branch->name . ' Branch', 'route' => 'reports.volunteers.branch', 'params' => ['branch' => $division->branch->id]],
        ['label' => $division->name . ' Division'],
    ];

    $trendOptions     = $trendOptions     ?? ['2_years' => 24];
    $selectedTrendKey = $selectedTrendKey ?? '2_years';
    $trendDataset     = $trendDataset     ?? ['labels' => [], 'series' => null];
    $demographics     = $demographics     ?? ['gender' => [], 'ages' => [], 'ages_by_gender' => []];
    $years            = $years            ?? [];
    $selectedYear     = $selectedYear     ?? \Carbon\Carbon::now()->year;
@endphp

<x-reports.reports-layout
    :title="$title"
    :pageHeader="$pageHeader"
    :breadcrumbs="$breadcrumbs"
>
    {{-- No table exists at this drill level (chart + demographics only),
         so there's nothing to export — Print-only, no Export CSV button. --}}
    <style>
        @media print {
            #volunteers-division-actions { display: none !important; }
            #volunteers-chart-wrapper form { display: none !important; }
        }
    </style>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Division Volunteer Report</h1>

        <div id="volunteers-division-actions" class="flex items-center gap-2">
            <a href="{{ route('reports.volunteers.branch', $division->branch_id) }}"
               class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <svg class="mr-2 -ml-1 h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                </svg>
                Back to {{ $division->branch->name }} Branch
            </a>
            <button type="button" onclick="window.print()" class="filter-btn-secondary">
                <i class="fas fa-print mr-1"></i>Print
            </button>
        </div>
    </div>

    <div id="volunteers-chart-wrapper">
        <x-reports.time-series-chart
            chartId="division-volunteer-trend"
            :chartTitle="'Active Volunteers – ' . $division->name . ' Division'"
            chartLabel="Active Volunteers"
            :dataset="$trendDataset"
            :trendOptions="$trendOptions"
            :selectedTrendKey="$selectedTrendKey"
            :formAction="route('reports.volunteers.division', $division->id)"
            :request="request()"
        />
    </div>

    <x-reports.demographics
        :gender="$demographics['gender']"
        :ages="$demographics['ages']"
        :ages-by-gender="$demographics['ages_by_gender'] ?? []"
        chartIdPrefix="divisionVolDemographics"
    />
</x-reports.reports-layout>
