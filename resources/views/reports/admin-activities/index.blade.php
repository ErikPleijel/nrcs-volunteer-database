@php
    $title = 'Admin Activities Report';
    $pageHeader = 'Admin Activities';

    $breadcrumbs = [
        ['label' => 'Dashboard', 'route' => 'reports.dashboard'],
        ['label' => 'Admin Activities'],
    ];

    // Preserve the other current filters when switching tabs (branch_id,
    // trend_years, certificate_type) — only 'tab' changes per link.
    $tabParams = array_filter([
        'branch_id' => $branchId,
        'trend_years' => $trendYears,
        'certificate_type' => $certificateType,
    ]);

    // Single "Total" column, shared by all three tabs — the generalized
    // drill-down-table partial takes a $columns list instead of a
    // hardcoded label, so the "Total (Last N Years)" text moves here.
    // org_key is carried unconditionally; only the messages tab's rows
    // ever set 'org_total', so it's a no-op suffix for the other two.
    $drillColumns = [[
        'key'     => 'total',
        'label'   => 'Total (Last '.$trendYears.' '.\Illuminate\Support\Str::plural('Year', $trendYears).')',
        'org_key' => 'org_total',
    ]];
@endphp

<x-reports.reports-layout
    :title="$title"
    :pageHeader="$pageHeader"
    :breadcrumbs="$breadcrumbs"
>
    <div class="container mx-auto px-4 py-6">

        {{-- Page-scoped print refinements, layered on top of the global
             @media print rule in resources/css/app.css. Hides the
             tab-switcher, filter-container (which now also holds the
             Export/Print buttons, same filter-actions convention as the
             Lifecycle Report and Financial Breakdown), and the chart's
             embedded trend-select form. Keeps the chart, drill-breadcrumb,
             drill-down table, and explanatory banners visible — they're
             informative context, not interactive controls. --}}
        <style>
            @media print {
                #admin-activities-tabs { display: none !important; }
                .filter-container { display: none !important; }
                #admin-activities-chart-wrapper form { display: none !important; }
            }
        </style>

        {{-- ── TABS ─────────────────────────────────────────────────────────── --}}
        <div id="admin-activities-tabs" class="flex gap-2 border-b border-gray-200 mb-6">
            @foreach([
                'idcards'      => ['label' => 'ID Cards',     'icon' => 'fa-id-card'],
                'certificates' => ['label' => 'Certificates', 'icon' => 'fa-certificate'],
                'messages'     => ['label' => 'Messages',     'icon' => 'fa-envelope'],
            ] as $tabKey => $tabDef)
                <a href="{{ route('reports.admin-activities.index', array_merge($tabParams, ['tab' => $tabKey])) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-t-md border border-b-0 transition-colors
                       {{ $tab === $tabKey
                           ? 'bg-white border-gray-200 text-indigo-700 font-semibold'
                           : 'bg-gray-50 border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-100' }}">
                    <i class="fas {{ $tabDef['icon'] }} text-xs"></i>
                    {{ $tabDef['label'] }}
                </a>
            @endforeach
        </div>

        {{-- ── FILTER ───────────────────────────────────────────────────────── --}}
        <div class="filter-container">
            <div class="filter-form-content">
                <form action="{{ route('reports.admin-activities.index') }}" method="GET" class="filter-form" id="filter-form">
                    <input type="hidden" name="tab" value="{{ $tab }}">

                    <div class="filter-grid lg:grid-cols-3">

                        {{-- Col 1: Branch --}}
                        <div>
                            <label for="branch_id" class="filter-label-small">Branch</label>
                            <select name="branch_id" id="branch_id"
                                    class="filter-select-small {{ $branchId ? 'filter-active' : '' }}"
                                    onchange="this.form.submit()">
                                <option value="">All Branches</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected($branchId == $branch->id)>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Col 2: Trend years --}}
                        <div>
                            <label for="trend_years" class="filter-label-small">Trend</label>
                            <select name="trend_years" id="trend_years"
                                    class="filter-select-small"
                                    onchange="this.form.submit()">
                                @foreach($trendYearOptions as $key => $label)
                                    <option value="{{ $key }}" @selected($key == $trendYears)>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Col 3: Certificate type (certificates tab only) --}}
                        <div>
                            @if($tab === 'certificates')
                                <label for="certificate_type" class="filter-label-small">Certificate Type</label>
                                <select name="certificate_type" id="certificate_type"
                                        class="filter-select-small"
                                        onchange="this.form.submit()">
                                    @foreach($certificateTypeOptions as $key => $label)
                                        <option value="{{ $key }}" @selected($key == $certificateType)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                    </div>

                    <div class="filter-actions">
                        <div class="filter-button-group">
                            {{-- Preserves the current tab/branch/division/
                                 trend/certificate-type via request()->query()
                                 — exports whichever tab and drill level is
                                 currently active, not a reset state. --}}
                            <a href="{{ route('reports.admin-activities.index', array_merge(request()->query(), ['export' => 'csv'])) }}"
                               class="filter-btn-secondary">
                                <i class="fas fa-file-csv mr-1"></i>Export CSV
                            </a>
                            <button type="button" onclick="window.print()" class="filter-btn-secondary">
                                <i class="fas fa-print mr-1"></i>Print
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── CHART ────────────────────────────────────────────────────────── --}}
        @if($tab === 'idcards')
            @php
                $chartDataset = ['labels' => $idCardTrend['labels'], 'series' => [
                    ['label' => 'ID Cards Printed', 'data' => $idCardTrend['values']],
                ]];
            @endphp
            <div id="admin-activities-chart-wrapper">
                <x-reports.multi-line-chart
                    chartId="idcards-trend"
                    title="ID Cards Printed — Trend"
                    :dataset="$chartDataset"
                    :trendOptions="$trendYearOptions"
                    :selectedTrendKey="$trendYears"
                    :formAction="route('reports.admin-activities.index')"
                    :request="request()"
                />
            </div>

            @include('reports.admin-activities._drill-down-table', [
                'drillRows' => $drillRows,
                'drillAreaHeader' => $drillAreaHeader,
                'drillRowField' => $drillRowField,
                'drillCrumbs' => $drillCrumbs,
                'columns' => $drillColumns,
                'routeName' => 'reports.admin-activities.index',
                'extraLinkParams' => ['tab' => $tab],
            ])
        @endif

        @if($tab === 'certificates')
            @php
                $chartDataset = ['labels' => $certificateTrend['labels'], 'series' => [
                    ['label' => $certificateTypeOptions[$certificateType] ?? 'Certificates', 'data' => $certificateTrend['values']],
                ]];
            @endphp
            <div id="admin-activities-chart-wrapper">
                <x-reports.multi-line-chart
                    chartId="certificates-trend"
                    title="Certificates Printed — Trend"
                    :dataset="$chartDataset"
                    :trendOptions="$trendYearOptions"
                    :selectedTrendKey="$trendYears"
                    :formAction="route('reports.admin-activities.index')"
                    :request="request()"
                />
            </div>

            @if($isOrganisationScoped)
                <p class="mt-4 text-xs text-gray-500 italic">
                    <i class="fas fa-circle-info mr-1"></i>
                    Organisation-based certificates are only tracked at branch level — branches below cannot be drilled into divisions or RC units.
                </p>
            @endif

            @include('reports.admin-activities._drill-down-table', [
                'drillRows' => $drillRows,
                'drillAreaHeader' => $drillAreaHeader,
                'drillRowField' => $drillRowField,
                'drillCrumbs' => $drillCrumbs,
                'columns' => $drillColumns,
                'routeName' => 'reports.admin-activities.index',
                'extraLinkParams' => ['tab' => $tab],
            ])
        @endif

        @if($tab === 'messages')
            @php
                $chartDataset = ['labels' => $messageTrend['labels'], 'series' => [
                    ['label' => 'Email', 'data' => $messageTrend['email']],
                    ['label' => 'SMS', 'data' => $messageTrend['sms']],
                ]];
            @endphp
            <div id="admin-activities-chart-wrapper">
                <x-reports.multi-line-chart
                    chartId="messages-trend"
                    title="Messages Sent — Trend"
                    :dataset="$chartDataset"
                    :trendOptions="$trendYearOptions"
                    :selectedTrendKey="$trendYears"
                    :formAction="route('reports.admin-activities.index')"
                    :request="request()"
                />
            </div>

            @if($showMessagesOrgNote)
                <p class="mt-4 text-xs text-gray-500 italic">
                    <i class="fas fa-circle-info mr-1"></i>
                    Organisation-recipient messages are only tracked at branch level — totals below branch reflect individual recipients only.
                </p>
            @endif

            @include('reports.admin-activities._drill-down-table', [
                'drillRows' => $drillRows,
                'drillAreaHeader' => $drillAreaHeader,
                'drillRowField' => $drillRowField,
                'drillCrumbs' => $drillCrumbs,
                'columns' => $drillColumns,
                'routeName' => 'reports.admin-activities.index',
                'extraLinkParams' => ['tab' => $tab],
            ])
        @endif

    </div>
</x-reports.reports-layout>
