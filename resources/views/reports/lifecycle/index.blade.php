@php
    $title = 'Lifecycle Report';
    $pageHeader = 'Lifecycle Report';

    $breadcrumbs = [
        ['label' => 'Dashboard', 'route' => 'reports.dashboard'],
        ['label' => 'Lifecycle'],
    ];
@endphp

<x-reports.reports-layout
    :title="$title"
    :pageHeader="$pageHeader"
    :breadcrumbs="$breadcrumbs"
>
    <div class="container mx-auto px-4 py-6">

        {{-- Page-scoped print refinements, layered on top of the global
             @media print rule in resources/css/app.css (which already hides
             the sidebar/header/watermark/footer for every x-layouts.admin
             page). This block only hides content specific to this report;
             it does not touch the global rule. Same pattern as
             policies/code-of-conduct.blade.php's own inline <style>. --}}
        <style>
            @media print {
                .filter-container { display: none !important; }
                /* The chart's own embedded trend-select form (in its title
                   bar) — targeted via the wrapper below so only the form is
                   hidden, not the chart card/canvas itself. */
                #lifecycle-chart-wrapper form { display: none !important; }
                #lifecycle-print-btn { display: none !important; }
                /* No canvas max-width rule needed here anymore — it's now a
                   global `canvas { max-width: 100% !important; }` rule in
                   app.css, covering every report's chart, not just this
                   one's. */
            }
        </style>

        {{-- ── FILTER ───────────────────────────────────────────────────────── --}}
        <div class="filter-container">
            <div class="filter-form-content">
                <form action="{{ route('reports.lifecycle.national') }}" method="GET" class="filter-form">
                    @if($branchId)
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                    @endif

                    <div class="filter-grid lg:grid-cols-2">

                        {{-- Col 1: Trend range — auto-submits, same convention as admin-activities --}}
                        <div>
                            <label for="trend_months" class="filter-label-small">Trend</label>
                            <select name="trend_months" id="trend_months"
                                    class="filter-select-small"
                                    onchange="this.form.submit()">
                                @foreach($trendOptions as $key => $months)
                                    <option value="{{ $key }}" @selected($key == $selectedTrendKey)>
                                        {{ str_replace('_', ' ', ucfirst($key)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Col 2: Status checkboxes — Apply button, not auto-submit --}}
                        <div>
                            <label class="filter-label-small">Statuses shown</label>
                            <div class="flex flex-wrap gap-4 mt-1">
                                @foreach($statusDefs as $key => $label)
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                        <input type="hidden" name="{{ $key }}" value="0">
                                        <input type="checkbox" name="{{ $key }}" value="1"
                                               {{ $checked[$key] ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        {{ $label }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                    </div>

                    <div class="filter-actions">
                        <div class="filter-button-group">
                            <button type="submit" class="filter-btn-primary">
                                <i class="fas fa-check mr-1"></i>Apply
                            </button>
                            {{-- Preserves every current query param (checkboxes, branch_id,
                                 trend_months) via request()->query() — exports whatever is
                                 currently applied/drilled, not a reset state. --}}
                            <a href="{{ route('reports.lifecycle.national', array_merge(request()->query(), ['export' => 'csv'])) }}"
                               class="filter-btn-secondary">
                                <i class="fas fa-file-csv mr-1"></i>Export CSV
                            </a>
                            <button type="button" id="lifecycle-print-btn" onclick="window.print()"
                                    class="filter-btn-secondary">
                                <i class="fas fa-print mr-1"></i>Print
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if (! $hasLifecycleData)
            {{-- Per confirmed decision: detect "no data" by checking whether any
                 relevant snapshot rows have non-null values in the checked
                 columns, not just row existence — stats_snapshots already has
                 10k+ backfilled rows, all with NULL lifecycle columns, so a
                 plain row-existence check would wrongly look "populated". --}}
            <x-reports.drill-breadcrumb :crumbs="$drillCrumbs" />
            <div class="mt-4 bg-white rounded-lg shadow p-8 text-center">
                <i class="fas fa-chart-line text-4xl text-gray-300 mb-4"></i>
                <p class="text-gray-500 text-sm">
                    No lifecycle data yet — this report populates once the daily snapshot job has run for a period of time.
                </p>
            </div>
        @else
            {{-- ── CHART ────────────────────────────────────────────────────── --}}
            <div id="lifecycle-chart-wrapper">
                <x-reports.multi-line-chart
                    chartId="lifecycle-trend"
                    title="Lifecycle Status — Trend"
                    :dataset="$chartDataset"
                    :trendOptions="$trendOptions"
                    :selectedTrendKey="$selectedTrendKey"
                    :formAction="route('reports.lifecycle.national')"
                    :request="request()"
                />
            </div>

            @if($drillLevel === 'division')
                <p class="mt-4 text-xs text-gray-500 italic">
                    <i class="fas fa-circle-info mr-1"></i>
                    No unit-level lifecycle data is tracked — division is the deepest level available.
                </p>
            @endif

            {{-- ── DRILL-DOWN TABLE (shared partial, generalized in Stage 1) ──── --}}
            @include('reports.admin-activities._drill-down-table', [
                'drillRows' => $drillRows,
                'drillAreaHeader' => $drillAreaHeader,
                'drillRowField' => $drillRowField,
                'drillCrumbs' => $drillCrumbs,
                'columns' => $columns,
                'routeName' => 'reports.lifecycle.national',
                'extraLinkParams' => [],
            ])
        @endif

    </div>
</x-reports.reports-layout>
