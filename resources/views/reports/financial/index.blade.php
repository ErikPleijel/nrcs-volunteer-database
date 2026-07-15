<x-layouts.admin title="Financial Breakdown">
    <x-slot name="pageHeader">
        <i class="fas fa-coins mr-3"></i> Financial Breakdown
    </x-slot>
    <x-slot name="subHeader">
        By contributor type (organisations · members · volunteers) and fee
    </x-slot>

    <div class="container mx-auto px-4 py-6">

        {{-- Page-scoped print refinements, layered on top of the global
             @media print rule in resources/css/app.css. This report is
             table-only (no chart), so print CSS only needs to hide the
             filter-container (which now also contains the Export/Print
             buttons themselves, same as the Lifecycle Report's convention)
             and the tab controls. --}}
        <style>
            @media print {
                .filter-container { display: none !important; }
                #financial-breakdown-tabs { display: none !important; }
            }
        </style>

        {{-- ── FILTER ───────────────────────────────────────────────────────── --}}
        <div class="filter-container">
            <div class="filter-form-content">
                <form action="{{ route('reports.financial.index') }}" method="GET" class="filter-form" id="filter-form">
                    <input type="hidden" name="tab" value="{{ $activeTab }}">

                    <div class="filter-grid lg:grid-cols-3">

                        {{-- Col 1: Scope --}}
                        <div>
                            <label for="scope" class="filter-label-small">Area</label>
                            <select name="scope" id="scope"
                                    class="filter-select-small disabled:bg-gray-200 disabled:opacity-75 {{ $selectedScope !== 'national' ? 'filter-active' : '' }}"
                                    onchange="this.form.submit()">
                                <option value="national" @selected($selectedScope === 'national')>National</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected($selectedScope == $branch->id)>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Col 2: Quarter --}}
                        <div>
                            <label for="quarter" class="filter-label-small">Quarter</label>
                            <select name="quarter" id="quarter"
                                    class="filter-select-small {{ $selectedQuarter !== $defaultQuarter ? 'filter-active' : '' }}"
                                    onchange="this.form.submit()">
                                @foreach($quarterOptions as $opt)
                                    <option value="{{ $opt['value'] }}" @selected($selectedQuarter === $opt['value'])>
                                        {{ $opt['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Col 3: spacer --}}
                        <div></div>

                    </div>

                    <div class="filter-actions">
                        <div class="filter-button-group">
                            <a href="{{ route('reports.financial.index', ['tab' => $activeTab]) }}"
                               class="filter-btn-secondary filter-btn-secondary-active">
                                <i class="fas fa-times mr-1"></i>Clear
                            </a>
                            {{-- Preserves the current tab/scope/quarter via
                                 request()->query() — exports whichever tab is
                                 currently active, not both at once. --}}
                            <a href="{{ route('reports.financial.index', array_merge(request()->query(), ['export' => 'csv'])) }}"
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

        {{-- ── CLARIFYING NOTE ─────────────────────────────────────────────── --}}
        <div class="mb-6 rounded-md bg-blue-50 border border-blue-200 p-4 text-sm text-blue-900">
            <p>
                This report <span class="font-semibold">disaggregates</span> membership revenue by
                contributor type (organisations, members, volunteers) and by fee type.
            </p>
            <p class="mt-1">
                For totals over time and by location, see the
                <a href="{{ route('reports.financial.national') }}" class="font-semibold underline text-blue-700 hover:text-blue-900">Financial Trends</a> report.
            </p>
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

        {{-- ── TAB 1: Payments ─────────────────────────────────────────────── --}}
        @if($activeTab === 'payments')
            @if(empty($paymentsData))
                <p class="text-center text-gray-400 italic py-12">No payment data available for the selected period.</p>
            @else
                <p class="text-xs italic text-gray-400 mb-3">Amounts in NGN. Deleted payments excluded.</p>
                @php
                    $totalMembers   = array_sum(array_column($paymentsData, 'member_amount'));
                    $totalVolunteer = array_sum(array_column($paymentsData, 'volunteer_amount'));
                    $totalOrg       = array_sum(array_column($paymentsData, 'org_amount'));
                    $grandTotal     = array_sum(array_column($paymentsData, 'total'));
                @endphp
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm bg-white rounded-lg shadow overflow-hidden">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                                <th class="px-4 py-2 text-left">{{ $rowType === 'branch' ? 'Branch' : 'Division' }}</th>
                                <th class="px-4 py-2 text-right">Members</th>
                                <th class="px-4 py-2 text-right">Volunteers</th>
                                <th class="px-4 py-2 text-right">Organisations</th>
                                <th class="px-4 py-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($paymentsData as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-gray-900">{{ $row['label'] }}</td>
                                    <td class="px-4 py-3 text-right {{ $row['member_amount'] == 0 ? 'text-gray-300' : 'text-gray-700' }}">
                                        {{ number_format($row['member_amount'], 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-right {{ $row['volunteer_amount'] == 0 ? 'text-gray-300' : 'text-gray-700' }}">
                                        {{ number_format($row['volunteer_amount'], 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-right {{ $row['org_amount'] == 0 ? 'text-gray-300' : 'text-gray-700' }}">
                                        {{ number_format($row['org_amount'], 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold text-gray-900">
                                        {{ number_format($row['total'], 0) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50 font-bold text-sm border-t border-gray-200">
                                <td class="px-4 py-2 text-gray-700">Total</td>
                                <td class="px-4 py-2 text-right text-gray-700">{{ number_format($totalMembers, 0) }}</td>
                                <td class="px-4 py-2 text-right text-gray-700">{{ number_format($totalVolunteer, 0) }}</td>
                                <td class="px-4 py-2 text-right text-gray-700">{{ number_format($totalOrg, 0) }}</td>
                                <td class="px-4 py-2 text-right text-gray-900">{{ number_format($grandTotal, 0) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        @endif

        {{-- ── TAB 2: Fee Breakdown ─────────────────────────────────────────── --}}
        @if($activeTab === 'breakdown')
            <div class="mb-4 text-sm font-semibold text-gray-700">
                <i class="fas fa-filter mr-1 text-gray-400"></i>
                {{ $isNational ? 'National' : $selectedBranchName }} — {{ $selectedQuarter }}
            </div>

            @if($feeBreakdownData->isEmpty())
                <p class="text-center text-gray-400 italic py-12">No fee data available for the selected period.</p>
            @else
                @php
                    $memberFees    = $feeBreakdownData->where('is_volunteer_fee', false)->values();
                    $volunteerFees = $feeBreakdownData->where('is_volunteer_fee', true)->values();
                    $memberSubtotal    = $memberFees->sum('total');
                    $volunteerSubtotal = $volunteerFees->sum('total');
                    $grandTotal        = $memberSubtotal + $volunteerSubtotal;
                @endphp
                <div class="max-w-lg">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm bg-white rounded-lg shadow overflow-hidden">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide">
                                <th class="px-4 py-2 text-left">Fee Type</th>
                                <th class="px-4 py-2 text-right">Total Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">

                            {{-- Member fees --}}
                            @foreach($memberFees as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-gray-900">
                                        {{ $row['fee_name'] }}
                                        <span class="ml-2 inline-block bg-blue-100 text-blue-700 rounded-full px-2 py-0.5 text-xs font-medium">Member</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold text-gray-900">{{ number_format($row['total'], 0) }}</td>
                                </tr>
                            @endforeach
                            @if($memberFees->isNotEmpty())
                                <tr class="bg-gray-50 font-semibold text-sm">
                                    <td class="px-4 py-2 text-gray-600">Member fees subtotal</td>
                                    <td class="px-4 py-2 text-right">{{ number_format($memberSubtotal, 0) }}</td>
                                </tr>
                            @endif

                            {{-- Volunteer fees --}}
                            @foreach($volunteerFees as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-gray-900">
                                        {{ $row['fee_name'] }}
                                        <span class="ml-2 inline-block bg-green-100 text-green-700 rounded-full px-2 py-0.5 text-xs font-medium">Volunteer</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold text-gray-900">{{ number_format($row['total'], 0) }}</td>
                                </tr>
                            @endforeach
                            @if($volunteerFees->isNotEmpty())
                                <tr class="bg-gray-50 font-semibold text-sm">
                                    <td class="px-4 py-2 text-gray-600">Volunteer fees subtotal</td>
                                    <td class="px-4 py-2 text-right">{{ number_format($volunteerSubtotal, 0) }}</td>
                                </tr>
                            @endif

                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-100 font-bold text-sm border-t-2 border-gray-300">
                                <td class="px-4 py-2 text-gray-700">Grand Total</td>
                                <td class="px-4 py-2 text-right text-gray-900">{{ number_format($grandTotal, 0) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                </div>
            @endif
        @endif

    </div>
</x-layouts.admin>
