@php
    use Carbon\Carbon;

    /** @var \App\Models\Branch $branch */

    $title      = 'Registrations Report – ' . ($branch->name ?? 'Branch');
    $pageHeader = 'Registrations – ' . ($branch->name ?? 'Branch') . ' Overview';

    $breadcrumbs = [
        ['label' => 'Dashboard', 'route' => 'reports.dashboard'],
        ['label' => 'Registrations – National', 'route' => 'reports.registrations.national'],
        ['label' => $branch->name ?? 'Branch'],
    ];

    // Fallbacks from controller; keep blade robust
    $trendOptions                    = $trendOptions                    ?? [];
    $selectedTrendKey                = $selectedTrendKey                ?? '4_years';
    $trendDataset                    = $trendDataset                    ?? ['labels' => [], 'values' => []];
    $selectedYear                    = $selectedYear                    ?? Carbon::now()->year;
    $yearOptions                     = $yearOptions                     ?? collect([$selectedYear]);
    $divisionRegistrationSummaries   = $divisionRegistrationSummaries   ?? collect();

    if (! $divisionRegistrationSummaries instanceof \Illuminate\Support\Collection) {
        $divisionRegistrationSummaries = collect($divisionRegistrationSummaries);
    }

    // Branch total registrations across all divisions (for summary row)
    $totalRegistrationsBranch = $divisionRegistrationSummaries->sum('registrations_count');
@endphp

<x-reports.reports-layout
    :title="$title"
    :pageHeader="$pageHeader"
    :breadcrumbs="$breadcrumbs"
>
    {{-- No embedded chart form at this drill level (trendOptions/formAction/
         request aren't passed to <x-reports.registrations-chart> below), so
         the two standalone forms on this page are hidden directly by id
         rather than via a chart-wrapper selector. --}}
    <style>
        @media print {
            #registrations-branch-actions { display: none !important; }
            #registrations-branch-trend-form { display: none !important; }
            #registrations-branch-year-form { display: none !important; }
        }
    </style>

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
            Registrations Report – {{ $branch->name }}
        </h1>

        <div id="registrations-branch-actions" class="flex items-center gap-2">
            <a href="{{ route('reports.registrations.branch', array_merge(['branchId' => $branch->id], request()->query(), ['export' => 'csv'])) }}"
               class="filter-btn-secondary">
                <i class="fas fa-file-csv mr-1"></i>Export CSV
            </a>
            <button type="button" onclick="window.print()" class="filter-btn-secondary">
                <i class="fas fa-print mr-1"></i>Print
            </button>
        </div>
    </div>

    <p class="mb-6 text-sm text-gray-700 dark:text-gray-300">
        This report shows the trend of <strong>new user registrations</strong> in
        the <strong>{{ $branch->name }}</strong> branch over time, followed by a
        summary of registrations by division for the selected year.
    </p>

    {{-- 🔢 Trend range selector (2 / 4 / 6 / 8 years) --}}
    <div id="registrations-branch-trend-form" class="mb-4">
        <form method="GET"
              action="{{ route('reports.registrations.branch', ['branchId' => $branch->id]) }}"
              class="flex items-center gap-3 flex-wrap">
            {{-- Preserve current year when changing trend range --}}
            <input type="hidden" name="year" value="{{ $selectedYear }}">

            <label for="trend_years" class="text-sm text-gray-700 dark:text-gray-300">
                Trend range:
            </label>

            <select
                id="trend_years"
                name="trend_years"
                class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-sm rounded-md"
                onchange="this.form.submit()"
            >
                @foreach ($trendOptions as $key => $years)
                    <option value="{{ $key }}" @selected($key === $selectedTrendKey)>
                        Last {{ $years }} year(s)
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    {{-- 📈 Registrations Trend (Branch) --}}
    <div id="registrations-chart-wrapper">
        <x-reports.registrations-chart
            :chartId="'registrations-branch-' . $branch->id . '-trend'"
            :title="'Registrations Trend – ' . $branch->name . ' Branch'"
            :dataset="$trendDataset"
            seriesLabel="New registrations"
        />
    </div>

    {{-- 📅 Year selector for division summary --}}
    <div id="registrations-branch-year-form" class="mt-8 mb-4">
        <form method="GET"
              action="{{ route('reports.registrations.branch', ['branchId' => $branch->id]) }}"
              class="flex items-center gap-3 flex-wrap">
            {{-- Preserve current trend range when changing year --}}
            <input type="hidden" name="trend_years" value="{{ $selectedTrendKey }}">

            <label for="year" class="text-sm text-gray-700 dark:text-gray-300">
                Select year for division summary:
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

            @if (!empty($trendOptions))
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    Trend range: {{ $trendOptions[$selectedTrendKey] ?? '?' }} years
                </span>
            @endif
        </form>
    </div>

    {{-- 🧮 Registrations Summary by Division --}}
    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                Registrations by Division – {{ $selectedYear }} ({{ $branch->name }} Branch)
            </h2>
            <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">
                Shows the number of new user profiles created in each division under this branch
                during the selected year.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-900">
                <tr>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700 dark:text-gray-200 whitespace-nowrap">
                        Division
                    </th>
                    <th class="px-4 py-2 text-center font-semibold text-gray-700 dark:text-gray-200 whitespace-nowrap">
                        Registrations
                    </th>
                </tr>
                </thead>

                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($divisionRegistrationSummaries as $row)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-900/50">
                        <td class="px-4 py-2 text-gray-900 dark:text-gray-100 whitespace-nowrap">
                            {{ $row->division_name }}
                        </td>

                        <td class="px-4 py-2 text-center text-gray-900 dark:text-gray-100">
                            {{ number_format($row->registrations_count) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="px-4 py-4 text-center text-sm text-gray-600 dark:text-gray-300">
                            No registration data found for {{ $selectedYear }} in this branch.
                        </td>
                    </tr>
                @endforelse

                {{-- Branch summary row (all divisions) --}}
                @if ($divisionRegistrationSummaries->isNotEmpty())
                    <tr class="bg-gray-100 dark:bg-gray-900/70 border-t border-gray-300 dark:border-gray-600">
                        <td class="px-4 py-2 font-semibold text-gray-900 dark:text-gray-100">
                            Total (all divisions)
                        </td>
                        <td class="px-4 py-2 text-center font-semibold text-gray-900 dark:text-gray-100">
                            {{ number_format($totalRegistrationsBranch) }}
                        </td>
                    </tr>
                @endif
                </tbody>
            </table>
        </div>
    </div>
</x-reports.reports-layout>
