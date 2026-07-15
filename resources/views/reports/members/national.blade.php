@php
    use Carbon\Carbon;

    $title      = 'Membership Report – National';
    $pageHeader = 'Membership – National Overview';

    $breadcrumbs = [
        ['label' => 'Dashboard', 'route' => 'reports.dashboard'],
        ['label' => 'National'],
    ];

    $years              = $years              ?? [];
    $selectedYear       = $selectedYear       ?? Carbon::now()->year;
    $trendDataset       = $trendDataset       ?? ['labels' => [], 'series' => null];
    $branchMemberCounts = $branchMemberCounts ?? collect();
    $trendOptions       = $trendOptions       ?? ['2_years' => 24];
    $selectedTrendKey   = $selectedTrendKey   ?? '2_years';
    $demographics       = $demographics       ?? ['gender' => [], 'ages' => [], 'ages_by_gender' => null];
    $latestDate         = $latestDate         ?? null;
@endphp

<x-reports.reports-layout
    :title="$title"
    :pageHeader="$pageHeader"
    :breadcrumbs="$breadcrumbs"
>
    {{-- Page-scoped print refinements, layered on top of the global
         @media print rule in resources/css/app.css (which already hides
         the sidebar/header/watermark/footer, and — as of the confirmed
         root-cause fix — forces <main> to the printable page's own width
         so the chart no longer needs a page-specific resize workaround).
         This block only hides content specific to this report. Same
         pattern as the Lifecycle Report / policies/code-of-conduct.blade.php's
         own inline <style>. --}}
    <style>
        @media print {
            #members-actions { display: none !important; }
            /* The chart's own embedded trend-select form (in its title
               bar) — targeted via the wrapper below so only the form is
               hidden, not the chart card/canvas itself. */
            #members-chart-wrapper form { display: none !important; }
        }
    </style>

    <div id="members-actions" class="flex justify-end gap-2 mb-4">
        {{-- Preserves every current query param (year/trend_months) via
             request()->query() — exports whatever is currently applied,
             not a reset state. --}}
        <a href="{{ route('reports.members.national', array_merge(request()->query(), ['export' => 'csv'])) }}"
           class="filter-btn-secondary">
            <i class="fas fa-file-csv mr-1"></i>Export CSV
        </a>
        <button type="button" onclick="window.print()" class="filter-btn-secondary">
            <i class="fas fa-print mr-1"></i>Print
        </button>
    </div>

    <h1 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">National Membership Report</h1>

    <div id="members-chart-wrapper">
        <x-reports.member-graph
            title="Active members – National"
            chartId="nationalMembershipTrend"
            :dataset="$trendDataset"
            :trendOptions="$trendOptions"
            :selectedTrendKey="$selectedTrendKey"
            :formAction="route('reports.members.national')"
            :request="request()"
        />
    </div>

    <x-reports.demographics
        :gender="$demographics['gender']"
        :ages="$demographics['ages']"
        :ages-by-gender="$demographics['ages_by_gender'] ?? null"
        chartIdPrefix="nationalDemographics"
    />

    <div class="mt-8">
        <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Total Members by Branch</h2>

        @if (! $latestDate)
            <p class="text-center italic text-gray-500 py-8">No snapshot data available yet. Run the stats:snapshot command.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 shadow-sm border border-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Branch</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Men</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Women</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($branchMemberCounts as $row)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <a href="{{ route('reports.members.branch', $row->id) }}"
                                       class="font-bold underline text-blue-600 hover:text-blue-800">
                                        {{ $row->name }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $row->men }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $row->women }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $row->total }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">Total</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">{{ $branchMemberCounts->sum('men') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">{{ $branchMemberCounts->sum('women') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">{{ $branchMemberCounts->sum('total') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>

</x-reports.reports-layout>
