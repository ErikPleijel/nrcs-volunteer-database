@php
    use Carbon\Carbon;

    $title      = 'Registrations Report – National';
    $pageHeader = 'Registrations – National Overview';

    $breadcrumbs = [
        ['label' => 'Dashboard', 'route' => 'reports.dashboard'],
        ['label' => 'Registrations – National'],
    ];

    // Fallbacks from controller; keep blade robust
    $trendOptions                 = $trendOptions                 ?? [];
    $selectedTrendKey             = $selectedTrendKey             ?? '4_years';
    $trendDataset                 = $trendDataset                 ?? ['labels' => [], 'values' => []];
    $selectedYear                 = $selectedYear                 ?? Carbon::now()->year;
    $yearOptions                  = $yearOptions                  ?? collect([$selectedYear]);
    $branchRegistrationSummaries  = $branchRegistrationSummaries  ?? collect();

    if (! $branchRegistrationSummaries instanceof \Illuminate\Support\Collection) {
        $branchRegistrationSummaries = collect($branchRegistrationSummaries);
    }

    // National total registrations across all branches (for summary row)
    $totalRegistrationsAll = $branchRegistrationSummaries->sum('registrations_count');
@endphp

<x-reports.reports-layout
    :title="$title"
    :pageHeader="$pageHeader"
    :breadcrumbs="$breadcrumbs"
>
    <style>
        @media print {
            #registrations-actions { display: none !important; }
            #registrations-chart-wrapper form { display: none !important; }
            #registrations-year-form { display: none !important; }
        }
    </style>

    <div id="registrations-actions" class="flex justify-end gap-2 mb-4">
        <a href="{{ route('reports.registrations.national', array_merge(request()->query(), ['export' => 'csv'])) }}"
           class="filter-btn-secondary">
            <i class="fas fa-file-csv mr-1"></i>Export CSV
        </a>
        <button type="button" onclick="window.print()" class="filter-btn-secondary">
            <i class="fas fa-print mr-1"></i>Print
        </button>
    </div>

    <h1 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">
        National Registrations Report
    </h1>

    <p class="mb-6 text-sm text-gray-700 dark:text-gray-300">
        This report shows the national trend of <strong>new user registrations</strong> over time,
        followed by a summary of registrations by branch for the selected year.
    </p>

    {{-- 📈 Registrations Trend (National) --}}
    <div id="registrations-chart-wrapper">
        <x-reports.registrations-chart
            chartId="registrations-national-trend"
            title="Registrations Trend – National"
            :dataset="$trendDataset"
            seriesLabel="New registrations"
            :trendOptions="$trendOptions"
            :selectedTrendKey="$selectedTrendKey"
            :formAction="route('reports.registrations.national')"
            :request="request()"
        />
    </div>

    {{-- 📅 Year selector for branch summary --}}
    <div id="registrations-year-form" class="mt-16 mb-4">
        <form method="GET"
              action="{{ route('reports.registrations.national') }}"
              class="flex items-center gap-3 flex-wrap">
            {{-- Preserve current trend range when changing year --}}
            <input type="hidden" name="trend_years" value="{{ $selectedTrendKey }}">

            <label for="year" class="text-sm text-gray-700 dark:text-gray-300">
                Select year:
            </label>

            <select
                id="year"
                name="year"
                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-sm rounded-md"
                onchange="this.form.submit()"
            >
                @foreach ($yearOptions as $yearOption)
                    <option value="{{ $yearOption }}" @selected($yearOption == $selectedYear)>
                        {{ $yearOption }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    {{-- 🧮 Registrations Summary by Branch --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Registrations by Branch – {{ $selectedYear }}
            </h2>
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                Shows the number of new user profiles created in each branch during the selected year.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700 dark:text-gray-200 whitespace-nowrap">
                        Branch
                    </th>
                    <th class="px-4 py-2 text-center font-semibold text-gray-700 dark:text-gray-200 whitespace-nowrap">
                        Registrations
                    </th>
                </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($branchRegistrationSummaries as $row)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/50">
                        <td class="px-4 py-2 text-gray-900 dark:text-gray-100 whitespace-nowrap">
                            <a href="{{ route('reports.registrations.branch', ['branchId' => $row->branch_id]) }}?year={{ $selectedYear }}&trend_years={{ $selectedTrendKey }}"
                               class="font-bold underline text-blue-600 hover:text-blue-800">
                                {{ $row->branch_name }}
                            </a>
                        </td>

                        <td class="px-4 py-2 text-center text-gray-900 dark:text-gray-100">
                            {{ number_format($row->registrations_count) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="px-4 py-4 text-center text-sm text-gray-600 dark:text-gray-300">
                            No registration data found for {{ $selectedYear }}.
                        </td>
                    </tr>
                @endforelse

                {{-- National summary row (all branches) --}}
                @if ($branchRegistrationSummaries->isNotEmpty())
                    <tr class="bg-gray-100 dark:bg-gray-900/70 border-t border-gray-300 dark:border-gray-600">
                        <td class="px-4 py-2 font-semibold text-gray-900 dark:text-gray-100">
                            Total (all branches)
                        </td>
                        <td class="px-4 py-2 text-center font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format($totalRegistrationsAll) }}
                        </td>
                    </tr>
                @endif
                </tbody>
            </table>
        </div>
    </div>
</x-reports.reports-layout>
