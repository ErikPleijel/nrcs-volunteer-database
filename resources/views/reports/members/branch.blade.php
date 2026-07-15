@php
    use Carbon\Carbon;

    $title      = 'Membership Report – ' . $branch->name . ' Branch';
    $pageHeader = 'Membership – ' . $branch->name . ' Branch';

    $breadcrumbs = [
        ['label' => 'Dashboard', 'route' => 'reports.dashboard'],
        ['label' => 'National', 'route' => 'reports.members.national'],
        ['label' => $branch->name . ' Branch'],
    ];

    $trendOptions         = $trendOptions         ?? ['2_years' => 24];
    $selectedTrendKey     = $selectedTrendKey     ?? '2_years';
    $trendDataset         = $trendDataset         ?? ['labels' => [], 'series' => null];
    $divisionMemberCounts = $divisionMemberCounts ?? collect();
    $latestDate           = $latestDate           ?? null;
    $demographics         = $demographics         ?? ['gender' => [], 'ages' => [], 'ages_by_gender' => []];
    $years                = $years                ?? [];
    $selectedYear         = $selectedYear         ?? \Carbon\Carbon::now()->year;
@endphp

<x-reports.reports-layout
    :title="$title"
    :pageHeader="$pageHeader"
    :breadcrumbs="$breadcrumbs"
>
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Branch Membership Report</h1>

        <a href="{{ route('reports.members.national') }}"
           class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <svg class="mr-2 -ml-1 h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
            </svg>
            Back to National View
        </a>
    </div>

    <x-reports.member-graph
        title="Active members – {{ $branch->name }} Branch"
        chartId="branchMembershipTrend"
        :dataset="$trendDataset"
        :trendOptions="$trendOptions"
        :selectedTrendKey="$selectedTrendKey"
        :formAction="route('reports.members.branch', $branch->id)"
        :request="request()"
    />

    <x-reports.demographics
        :gender="$demographics['gender']"
        :ages="$demographics['ages']"
        :ages-by-gender="$demographics['ages_by_gender'] ?? []"
        chartIdPrefix="branchDemographics"
    />

    <div class="mt-8">
        <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Total Members by Division</h2>

        @if (! $latestDate)
            <p class="text-center italic text-gray-500 py-8">No snapshot data available yet. Run the stats:snapshot command.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 shadow-sm border border-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Division</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Men</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Women</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($divisionMemberCounts as $row)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <a href="{{ route('reports.members.division', $row->id) }}"
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
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">{{ $divisionMemberCounts->sum('men') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">{{ $divisionMemberCounts->sum('women') }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">{{ $divisionMemberCounts->sum('total') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</x-reports.reports-layout>
