@php
    $title      = 'Membership Report – ' . $division->name . ' Division';
    $pageHeader = 'Membership – ' . $division->name . ' Division';

    $breadcrumbs = [
        ['label' => 'Dashboard', 'route' => 'reports.dashboard'],
        ['label' => 'National', 'route' => 'reports.members.national'],
        ['label' => $division->branch->name . ' Branch', 'route' => 'reports.members.branch', 'params' => ['branch' => $division->branch_id]],
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
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Division Membership Report</h1>

        <a href="{{ route('reports.members.branch', $division->branch_id) }}"
           class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <svg class="mr-2 -ml-1 h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
            </svg>
            Back to {{ $division->branch->name }} Branch
        </a>
    </div>

    <x-reports.member-graph
        title="Active members – {{ $division->name }} Division"
        chartId="divisionMembershipTrend"
        :dataset="$trendDataset"
        :trendOptions="$trendOptions"
        :selectedTrendKey="$selectedTrendKey"
        :formAction="route('reports.members.division', $division->id)"
        :request="request()"
    />

    <x-reports.demographics
        :gender="$demographics['gender']"
        :ages="$demographics['ages']"
        :ages-by-gender="$demographics['ages_by_gender'] ?? []"
        chartIdPrefix="divisionDemographics"
    />
</x-reports.reports-layout>
