<x-layouts.admin title="Membership Revenue Report">
    <x-slot name="pageHeader">
        <i class="fas fa-coins mr-3"></i> Membership Revenue Report
    </x-slot>
    <x-slot name="subHeader">
        Membership, volunteer, and organisation revenue
    </x-slot>

    <div class="container mx-auto px-4 py-6">

        {{-- Page-scoped print refinements, layered on top of the global
             @media print rule in resources/css/app.css. This report is
             table-only (no chart), so print CSS only needs to hide the
             actions bar, quarter selector, and tab controls. --}}
        <style>
            @media print {
                #financial-actions { display: none !important; }
                #financial-quarter-form { display: none !important; }
                #financial-breakdown-tabs { display: none !important; }
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
            $tabParams = array_filter(['scope' => $selectedScope, 'quarter' => $selectedQuarter]);
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

        {{-- ── QUARTER SELECTOR (centered, just above the scope/quarter header) ── --}}
        {{-- Area is no longer a dropdown here — branch-scoping is now driven by
             clicking a branch name in the Payments tab (national view) or "Back
             to National" (branch-scoped view), both setting/clearing the same
             'scope' param this form's hidden input never touched. --}}
        <div id="financial-quarter-form" class="flex justify-center mb-4">
            <form action="{{ route('reports.financial.index') }}" method="GET" class="flex items-center gap-2">
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                <input type="hidden" name="scope" value="{{ $selectedScope }}">

                <label for="quarter" class="filter-label-small mb-0">Quarter</label>
                <select name="quarter" id="quarter"
                        class="filter-select-small {{ $selectedQuarter !== $defaultQuarter ? 'filter-active' : '' }}"
                        onchange="this.form.submit()">
                    @foreach($quarterOptions as $opt)
                        <option value="{{ $opt['value'] }}" @selected($selectedQuarter === $opt['value'])>
                            {{ $opt['label'] }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

        {{-- ── SCOPE / QUARTER HEADER (shared by both tabs) ────────────────────── --}}
        @php
            [$scopeYearPart, $scopeQuarterPart] = explode('-', $selectedQuarter);
            $scopeAreaLabel = $isNational ? 'National' : $selectedBranchName;

            // Breakdown-link scoping: mirrors the exact hierarchy-aware rule
            // in FinancialOverviewReportController::breakdown()'s scope check.
            $viewerAccessLevel = auth()->user()->getAccessLevel();
            $viewerScopedId = auth()->user()->getScopedId();
        @endphp
        <div class="text-center mb-6">
            <span class="text-2xl font-bold text-gray-900">
                {{ $scopeQuarterPart }} {{ $scopeYearPart }} &mdash; {{ $scopeAreaLabel }}
            </span>
        </div>

        {{-- ── TAB 1: Payments ─────────────────────────────────────────────── --}}
        @if($activeTab === 'payments')
            @unless ($isNational)
                <div class="text-center mb-6">
                    <a href="{{ route('reports.financial.index', ['tab' => 'payments', 'scope' => 'national', 'quarter' => $selectedQuarter]) }}"
                       class="text-blue-600 hover:text-blue-800 hover:underline">
                        <i class="fas fa-arrow-left mr-1"></i>Back to National
                    </a>
                </div>
            @endunless

            @if(empty($paymentsData))
                <p class="text-center text-gray-400 italic py-12">No payment data available for the selected period.</p>
            @else
                @php
                    $totalMembers   = array_sum(array_column($paymentsData, 'member_amount'));
                    $totalVolunteer = array_sum(array_column($paymentsData, 'volunteer_amount'));
                    $totalOrg       = array_sum(array_column($paymentsData, 'org_amount'));
                    $grandTotal     = array_sum(array_column($paymentsData, 'total'));
                @endphp

                <div class="overflow-x-auto">
                    <table class="mx-auto text-sm bg-white rounded-lg shadow overflow-hidden">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                                <th class="px-4 py-2 text-left">{{ $rowType === 'branch' ? 'Branch' : 'Division' }}</th>
                                <th class="px-2 py-2 text-right">Members</th>
                                <th class="px-2 py-2 text-right">Volunteers</th>
                                <th class="px-2 py-2 text-right">Organisations</th>
                                <th class="px-2 py-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr class="bg-gray-100 font-bold text-sm border-b-2 border-gray-300">
                                <td class="px-4 py-2 text-gray-700">Total</td>
                                <td class="px-2 py-2 text-right text-gray-700">{{ number_format($totalMembers, 0) }}</td>
                                <td class="px-2 py-2 text-right text-gray-700">{{ number_format($totalVolunteer, 0) }}</td>
                                <td class="px-2 py-2 text-right text-gray-700">{{ number_format($totalOrg, 0) }}</td>
                                <td class="px-2 py-2 text-right text-gray-900">{{ number_format($grandTotal, 0) }}</td>
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
                                    // needing one.
                                    $canLinkRow = match ($viewerAccessLevel) {
                                        'national' => true,
                                        'branch' => ($row['level'] === 'branch' && $row['id'] === $viewerScopedId)
                                            || ($row['level'] === 'division' && (int) $selectedScope === $viewerScopedId),
                                        'division' => $row['level'] === 'division' && $row['id'] === $viewerScopedId,
                                        default => false,
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-gray-900">
                                        @if ($row['level'] === 'branch')
                                            <a href="{{ route('reports.financial.index', ['tab' => 'payments', 'scope' => $row['id'], 'quarter' => $selectedQuarter]) }}"
                                               class="font-bold text-blue-600 hover:text-blue-800 hover:underline">
                                                {{ $row['label'] }}
                                            </a>
                                        @else
                                            {{ $row['label'] }}
                                        @endif
                                    </td>
                                    <td class="px-2 py-2 text-right {{ $row['member_amount'] == 0 ? 'text-gray-300' : 'text-gray-700' }}">
                                        @if ($canLinkRow)
                                            <a href="{{ route('reports.financial.breakdown', ['branch_id' => $row['id'], 'level' => $row['level'], 'quarter' => $selectedQuarter, 'category' => 'member']) }}"
                                               class="text-blue-600 hover:text-blue-800 hover:underline">
                                                {{ number_format($row['member_amount'], 0) }}
                                            </a>
                                        @else
                                            {{ number_format($row['member_amount'], 0) }}
                                        @endif
                                    </td>
                                    <td class="px-2 py-2 text-right {{ $row['volunteer_amount'] == 0 ? 'text-gray-300' : 'text-gray-700' }}">
                                        @if ($canLinkRow)
                                            <a href="{{ route('reports.financial.breakdown', ['branch_id' => $row['id'], 'level' => $row['level'], 'quarter' => $selectedQuarter, 'category' => 'volunteer']) }}"
                                               class="text-blue-600 hover:text-blue-800 hover:underline">
                                                {{ number_format($row['volunteer_amount'], 0) }}
                                            </a>
                                        @else
                                            {{ number_format($row['volunteer_amount'], 0) }}
                                        @endif
                                    </td>
                                    <td class="px-2 py-2 text-right {{ $row['org_amount'] == 0 ? 'text-gray-300' : 'text-gray-700' }}">
                                        @if ($canLinkRow)
                                            <a href="{{ route('reports.financial.breakdown', ['branch_id' => $row['id'], 'level' => $row['level'], 'quarter' => $selectedQuarter, 'category' => 'organisation']) }}"
                                               class="text-blue-600 hover:text-blue-800 hover:underline">
                                                {{ number_format($row['org_amount'], 0) }}
                                            </a>
                                        @else
                                            {{ number_format($row['org_amount'], 0) }}
                                        @endif
                                    </td>
                                    <td class="px-2 py-2 text-right font-bold text-gray-900">
                                        {{ number_format($row['total'], 0) }}
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
                <div class="max-w-lg mx-auto">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm bg-white rounded-lg shadow overflow-hidden">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                                <th class="px-4 py-2 text-left">Fee Type</th>
                                <th class="px-4 py-2 text-right">Total Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">

                            <tr class="bg-gray-100 font-bold text-sm border-b-2 border-gray-300">
                                <td class="px-4 py-2 text-gray-700">Grand Total</td>
                                <td class="px-4 py-2 text-right text-gray-900">{{ number_format($feeBreakdownGrandTotal, 0) }}</td>
                            </tr>

                            {{-- Member Fee --}}
                            @if($memberFeeBreakdown->isNotEmpty())
                                <tr>
                                    <td colspan="2" class="px-4 py-2 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Member Fee
                                    </td>
                                </tr>
                                @foreach($memberFeeBreakdown as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-gray-900">{{ $row['fee_name'] }}</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900">{{ number_format($row['total'], 0) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="bg-gray-50 font-semibold text-sm">
                                    <td class="px-4 py-2 text-gray-600">Member fees subtotal</td>
                                    <td class="px-4 py-2 text-right">{{ number_format($memberFeeBreakdown->sum('total'), 0) }}</td>
                                </tr>
                            @endif

                            {{-- Volunteer Fee --}}
                            @if($volunteerFeeBreakdown->isNotEmpty())
                                <tr>
                                    <td colspan="2" class="px-4 py-2 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Volunteer Fee
                                    </td>
                                </tr>
                                @foreach($volunteerFeeBreakdown as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-gray-900">{{ $row['fee_name'] }}</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900">{{ number_format($row['total'], 0) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="bg-gray-50 font-semibold text-sm">
                                    <td class="px-4 py-2 text-gray-600">Volunteer fees subtotal</td>
                                    <td class="px-4 py-2 text-right">{{ number_format($volunteerFeeBreakdown->sum('total'), 0) }}</td>
                                </tr>
                            @endif

                            {{-- Organisation Fee --}}
                            @if($organisationFeeBreakdown->isNotEmpty())
                                <tr>
                                    <td colspan="2" class="px-4 py-2 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-600">
                                        Organisation Fee
                                    </td>
                                </tr>
                                @foreach($organisationFeeBreakdown as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-gray-900">{{ $row['fee_name'] }}</td>
                                        <td class="px-4 py-3 text-right font-bold text-gray-900">{{ number_format($row['total'], 0) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="bg-gray-50 font-semibold text-sm">
                                    <td class="px-4 py-2 text-gray-600">Organisation fees subtotal</td>
                                    <td class="px-4 py-2 text-right">{{ number_format($organisationFeeBreakdown->sum('total'), 0) }}</td>
                                </tr>
                            @endif

                        </tbody>
                    </table>
                </div>
                </div>
            @endif
        @endif

    </div>
</x-layouts.admin>
