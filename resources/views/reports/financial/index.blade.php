<x-layouts.admin title="Membership Revenue Report">
    <x-slot name="pageHeader">
        <i class="fas fa-coins mr-3"></i> Membership Revenue Report
    </x-slot>
    <x-slot name="subHeader">
        Membership, volunteer, and organisation revenue
    </x-slot>

    <div class="container mx-auto px-4 py-6">

        {{-- Page-scoped print refinements, layered on top of the global
             @media print rule in resources/css/app.css (checked: that file
             only hides sidebar/header/footer/watermark and fixes chart/
             <main> width — no @page or table rules, so nothing here
             conflicts with it). This report is table-only (no chart), so
             print CSS only needs to hide the actions bar, period selector,
             and tab controls, plus — for the Payments tab's 13-column year
             table only — force landscape orientation and shrink that one
             table specifically via its own .financial-year-table class, so
             the simpler 6-column Fee Breakdown table (unaffected by the
             Payments tab's density problem — see Fee Breakdown tab's own
             conversion) keeps using the normal page orientation/font-size.

             Diagnosed root cause of the print scrollbar/Q3-clipping bug:
             app.css's main{} fix un-clips <main> itself (overflow-x-hidden
             on screen), but this table has its OWN separate
             overflow-x-auto wrapper div one level deeper, which was never
             overridden — an overflow:auto box doesn't get reflowed for
             print, it just clips anything beyond its own box, same class
             of bug main{} already solves one level up. The sticky label
             column's explicit min-w-[180px] floor (Tailwind's raw `sticky`
             utility, confirmed the literal class name) compounded this —
             a fixed 180px regardless of the font shrink below, and
             position:sticky is meaningless on paper anyway. --}}
        <style>
            @media print {
                #financial-actions { display: none !important; }
                #financial-year-form { display: none !important; }
                #financial-breakdown-tabs { display: none !important; }

                @if($activeTab === 'payments')
                    @page { size: landscape; }

                    .overflow-x-auto { overflow: visible !important; }

                    .financial-year-table th.sticky,
                    .financial-year-table td.sticky { min-width: 0 !important; position: static !important; }

                    .financial-year-table { font-size: 7px; }
                    .financial-year-table th,
                    .financial-year-table td { padding: 1px 2px !important; }
                @endif
            }
        </style>

        {{-- ── ACTIONS (top right): Export CSV + Print ─────────────────────────── --}}
        <div id="financial-actions" class="flex justify-end gap-2 mb-4">
            {{-- Preserves the current tab/scope/quarter via request()->query() —
                 exports whichever tab is currently active, not both at once. --}}
            <a href="{{ route('reports.financial.index', array_merge(request()->query(), ['export' => 'csv'])) }}"
               class="filter-btn-secondary">
                <i class="fas fa-file-csv mr-1"></i>Export CSV
            </a>
            <button type="button" onclick="window.print()" class="filter-btn-secondary">
                <i class="fas fa-print mr-1"></i>Print
            </button>
        </div>

        {{-- ── TABS ─────────────────────────────────────────────────────────── --}}
        @php
            $tabParams = array_filter(['scope' => $selectedScope, 'year' => $selectedYear]);
        @endphp
        <div id="financial-breakdown-tabs" class="flex gap-2 border-b border-gray-200 mb-6">
            @foreach([
                'payments'  => ['label' => 'Payments',      'icon' => 'fa-money-bill-wave'],
                'breakdown' => ['label' => 'Fee Breakdown',  'icon' => 'fa-list-ul'],
            ] as $tabKey => $tabDef)
                <a href="{{ route('reports.financial.index', array_merge($tabParams, ['tab' => $tabKey])) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-t-md border border-b-0 transition-colors
                       {{ $activeTab === $tabKey
                           ? 'bg-white border-gray-200 text-indigo-700 font-semibold'
                           : 'bg-gray-50 border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-100' }}">
                    <i class="fas {{ $tabDef['icon'] }} text-xs"></i>
                    {{ $tabDef['label'] }}
                </a>
            @endforeach
        </div>

        {{-- ── PERIOD / SCOPE SELECTOR (centered, just above the scope/period header) ── --}}
        {{-- Branch-scoping is also still driven by clicking a branch name in
             the Payments tab (national view) or "Back to National"
             (branch-scoped view) — those remain as additional, alternative
             ways to change scope; the dropdown below is additive, not a
             replacement. Both tabs are full-year (Q1-Q4) views sharing this
             one year selector — Fee Breakdown's separate quarter selector is
             gone, which also fixed the tab-switch mismatch where switching
             tabs used to silently reset the other tab's period back to its
             own default (confirmed via direct testing before that change:
             Payments year=2020 -> Fee Breakdown carried quarter=<current
             quarter>, not anything derived from 2020, and vice versa) —
             'scope' and 'year' are both fields in this one form, so
             changing either one submits both together, carrying the other
             forward the same way. --}}
        <div id="financial-year-form" class="flex flex-col items-center gap-2 mb-4">
            <form action="{{ route('reports.financial.index') }}" method="GET" class="flex flex-col items-center gap-2">
                <input type="hidden" name="tab" value="{{ $activeTab }}">

                <div class="flex items-center gap-2">
                    <label for="year" class="filter-label-small mb-0">Year</label>
                    <select name="year" id="year"
                            class="filter-select-small {{ $selectedYear !== $defaultYear ? 'filter-active' : '' }}"
                            onchange="this.form.submit()">
                        @foreach($yearOptions as $yearOpt)
                            <option value="{{ $yearOpt }}" @selected($selectedYear === $yearOpt)>
                                {{ $yearOpt }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <label for="scope" class="filter-label-small mb-0">Branch</label>
                    <select name="scope" id="scope"
                            class="filter-select-small {{ $selectedScope !== 'national' ? 'filter-active' : '' }}"
                            onchange="this.form.submit()">
                        <option value="national" @selected($selectedScope === 'national')>National</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) $selectedScope === (string) $branch->id)>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>

        {{-- ── SCOPE / PERIOD HEADER (shared by both tabs) ─────────────────────── --}}
        @php
            $scopeAreaLabel = $isNational ? 'National' : $selectedBranchName;

            // Breakdown-link scoping: mirrors the exact hierarchy-aware rule
            // in FinancialOverviewReportController::breakdown()'s scope check.
            $viewerAccessLevel = auth()->user()->getAccessLevel();
            $viewerScopedId = auth()->user()->getScopedId();
        @endphp
        <div class="text-center mb-6">
            <span class="text-2xl font-bold text-gray-900">
                {{ $selectedYear }} &mdash; {{ $scopeAreaLabel }}
            </span>
        </div>

        {{-- ── TAB 1: Payments ─────────────────────────────────────────────── --}}
        @if($activeTab === 'payments')
            @unless ($isNational)
                <div class="text-center mb-6">
                    <a href="{{ route('reports.financial.index', ['tab' => 'payments', 'scope' => 'national', 'year' => $selectedYear]) }}"
                       class="text-blue-700 hover:text-blue-900 hover:underline">
                        <i class="fas fa-arrow-left mr-1"></i>Back to National
                    </a>
                </div>
            @endunless

            @if(empty($paymentsData))
                <p class="text-center text-gray-400 italic py-12">No payment data available for the selected period.</p>
            @else
                @php
                    // 12 quarter×category columns, in on-screen left-to-right order.
                    $yearColumns = [
                        'q1_member', 'q1_volunteer', 'q1_org',
                        'q2_member', 'q2_volunteer', 'q2_org',
                        'q3_member', 'q3_volunteer', 'q3_org',
                        'q4_member', 'q4_volunteer', 'q4_org',
                    ];
                    $columnTotals = [];
                    foreach ($yearColumns as $col) {
                        $columnTotals[$col] = array_sum(array_column($paymentsData, $col));
                    }
                    $grandTotal = array_sum(array_column($paymentsData, 'year_total'));
                @endphp

                <div class="overflow-x-auto rounded-lg shadow">
                    <table class="financial-year-table mx-auto text-sm bg-white">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                                <th rowspan="2" class="sticky left-0 bg-gray-50 z-10 px-4 py-2 text-left align-middle min-w-[180px]">
                                    {{ $rowType === 'branch' ? 'Branch' : 'Division' }}
                                </th>
                                @foreach ([1, 2, 3, 4] as $qNum)
                                    <th colspan="3" class="px-2 py-2 text-center border-l border-gray-200">Q{{ $qNum }}</th>
                                @endforeach
                                <th rowspan="2" class="px-2 py-2 text-right align-middle border-l border-gray-300">Year Total</th>
                            </tr>
                            <tr class="bg-gray-50 text-gray-400 text-[11px] uppercase tracking-wide border-b border-gray-200">
                                @foreach ([1, 2, 3, 4] as $qNum)
                                    <th class="px-1 py-1 text-right border-l border-gray-200">Mem</th>
                                    <th class="px-1 py-1 text-right">Vol</th>
                                    <th class="px-1 py-1 text-right">Org</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr class="bg-gray-100 font-bold text-sm border-b-2 border-gray-300">
                                <td class="sticky left-0 bg-gray-100 z-10 px-4 py-2 text-gray-700 min-w-[180px]">Total</td>
                                @foreach ($yearColumns as $col)
                                    <td class="px-1 py-2 text-right text-gray-700 {{ str_ends_with($col, '_member') ? 'border-l border-gray-200' : '' }}">
                                        {{ number_format($columnTotals[$col], 0) }}
                                    </td>
                                @endforeach
                                <td class="px-2 py-2 text-right text-gray-900 border-l border-gray-300">{{ number_format($grandTotal, 0) }}</td>
                            </tr>
                            @foreach($paymentsData as $row)
                                @php
                                    // Same hierarchy-aware rule as breakdown()'s scope
                                    // check: a 'division' row's enclosing branch is
                                    // whichever branch is currently selected as scope
                                    // (division rows only ever appear in a
                                    // branch-scoped view, all belonging to that one
                                    // branch) — there's no per-row division->branch_id
                                    // in $paymentsData, so this is equivalent without
                                    // needing one. Access scoping doesn't vary by
                                    // quarter, so this is computed once per row and
                                    // reused across all 4 quarters' cells below.
                                    $canLinkRow = match ($viewerAccessLevel) {
                                        'national' => true,
                                        'branch' => ($row['level'] === 'branch' && $row['id'] === $viewerScopedId)
                                            || ($row['level'] === 'division' && (int) $selectedScope === $viewerScopedId),
                                        'division' => $row['level'] === 'division' && $row['id'] === $viewerScopedId,
                                        default => false,
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="sticky left-0 bg-white z-10 px-4 py-3 text-gray-900 min-w-[180px]">
                                        @if ($row['level'] === 'branch')
                                            <a href="{{ route('reports.financial.index', ['tab' => 'payments', 'scope' => $row['id'], 'year' => $selectedYear]) }}"
                                               class="font-bold text-blue-700 hover:text-blue-900 hover:underline">
                                                {{ $row['label'] }}
                                            </a>
                                        @else
                                            {{ $row['label'] }}
                                        @endif
                                    </td>

                                    @foreach ([1, 2, 3, 4] as $qNum)
                                        @foreach (['member' => 'member', 'volunteer' => 'volunteer', 'org' => 'organisation'] as $catKey => $catParam)
                                            @php
                                                $value = $row["q{$qNum}_{$catKey}"];
                                            @endphp
                                            <td class="px-1 py-2 text-right {{ $catKey === 'member' ? 'border-l border-gray-200' : '' }} {{ $value == 0 ? 'text-gray-300' : 'text-gray-700' }}">
                                                @if ($canLinkRow)
                                                    <a href="{{ route('reports.financial.breakdown', ['branch_id' => $row['id'], 'level' => $row['level'], 'quarter' => "{$selectedYear}-Q{$qNum}", 'category' => $catParam]) }}"
                                                       class="text-blue-700 hover:text-blue-900 hover:underline">
                                                        {{ number_format($value, 0) }}
                                                    </a>
                                                @else
                                                    {{ number_format($value, 0) }}
                                                @endif
                                            </td>
                                        @endforeach
                                    @endforeach

                                    <td class="px-2 py-2 text-right font-bold text-gray-900 border-l border-gray-300">
                                        {{ number_format($row['year_total'], 0) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif

        {{-- ── TAB 2: Fee Breakdown ─────────────────────────────────────────── --}}
        @if($activeTab === 'breakdown')
            @if($feeBreakdownData->isEmpty())
                <p class="text-center text-gray-400 italic py-12">No fee data available for the selected period.</p>
            @else
                @php
                    // Page-level check (not per-row like the Payments tab — Fee
                    // Breakdown has no per-row branch/division concept, only the
                    // page's own national-or-single-branch $selectedScope, so
                    // one check covers every cell). Mirrors
                    // breakdownByFee()'s own server-side check exactly — this is
                    // only a UI convenience to hide links the viewer couldn't
                    // use; breakdownByFee() independently re-verifies access,
                    // same as the Payments tab's breakdown() already does.
                    $viewerScopedBranchId = auth()->user()->getScopedBranchId();
                    $scopeBranchId = !$isNational ? (int) $selectedScope : null;
                    $canLinkFeeCell = match ($viewerAccessLevel) {
                        'national' => true,
                        'branch', 'division' => !$isNational && $scopeBranchId === $viewerScopedBranchId,
                        default => false,
                    };
                @endphp

                <div class="max-w-3xl mx-auto">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm bg-white rounded-lg shadow overflow-hidden">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                                <th class="px-4 py-2 text-left">Fee Type</th>
                                <th class="px-2 py-2 text-right">Q1</th>
                                <th class="px-2 py-2 text-right">Q2</th>
                                <th class="px-2 py-2 text-right">Q3</th>
                                <th class="px-2 py-2 text-right">Q4</th>
                                <th class="px-4 py-2 text-right">Year Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">

                            <tr class="bg-gray-100 font-bold text-sm border-b-2 border-gray-300">
                                <td class="px-4 py-2 text-gray-700">Grand Total</td>
                                <td class="px-2 py-2"></td>
                                <td class="px-2 py-2"></td>
                                <td class="px-2 py-2"></td>
                                <td class="px-2 py-2"></td>
                                <td class="px-4 py-2 text-right text-gray-900">{{ number_format($feeBreakdownGrandTotal, 0) }}</td>
                            </tr>

                            @foreach ([
                                ['label' => 'Member Fee', 'rows' => $memberFeeBreakdown, 'subtotalLabel' => 'Member fees subtotal', 'category' => 'member'],
                                ['label' => 'Volunteer Fee', 'rows' => $volunteerFeeBreakdown, 'subtotalLabel' => 'Volunteer fees subtotal', 'category' => 'volunteer'],
                                ['label' => 'Organisation Fee', 'rows' => $organisationFeeBreakdown, 'subtotalLabel' => 'Organisation fees subtotal', 'category' => 'organisation'],
                            ] as $section)
                                @if($section['rows']->isNotEmpty())
                                    <tr>
                                        <td colspan="6" class="px-4 py-2 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600">
                                            {{ $section['label'] }}
                                        </td>
                                    </tr>
                                    @foreach($section['rows'] as $row)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 text-gray-900">{{ $row['fee_name'] }}</td>
                                            @foreach ([1, 2, 3, 4] as $qNum)
                                                @php $value = $row["q{$qNum}"]; @endphp
                                                <td class="px-2 py-3 text-right {{ $value == 0 ? 'text-gray-300' : 'text-gray-700' }}">
                                                    @if ($canLinkFeeCell)
                                                        <a href="{{ route('reports.financial.breakdown-by-fee', ['fee_id' => $row['fee_id'], 'quarter' => "{$selectedYear}-Q{$qNum}", 'category' => $section['category'], 'scope' => $selectedScope]) }}"
                                                           class="text-blue-700 hover:text-blue-900 hover:underline">
                                                            {{ number_format($value, 0) }}
                                                        </a>
                                                    @else
                                                        {{ number_format($value, 0) }}
                                                    @endif
                                                </td>
                                            @endforeach
                                            <td class="px-4 py-3 text-right font-bold text-gray-900">{{ number_format($row['year_total'], 0) }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="bg-gray-50 font-semibold text-sm">
                                        <td class="px-4 py-2 text-gray-600">{{ $section['subtotalLabel'] }}</td>
                                        <td class="px-2 py-2 text-right">{{ number_format($section['rows']->sum('q1'), 0) }}</td>
                                        <td class="px-2 py-2 text-right">{{ number_format($section['rows']->sum('q2'), 0) }}</td>
                                        <td class="px-2 py-2 text-right">{{ number_format($section['rows']->sum('q3'), 0) }}</td>
                                        <td class="px-2 py-2 text-right">{{ number_format($section['rows']->sum('q4'), 0) }}</td>
                                        <td class="px-4 py-2 text-right">{{ number_format($section['rows']->sum('year_total'), 0) }}</td>
                                    </tr>
                                @endif
                            @endforeach

                        </tbody>
                    </table>
                </div>
                </div>
            @endif
        @endif

    </div>
</x-layouts.admin>
