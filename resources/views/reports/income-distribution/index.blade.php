<x-layouts.admin title="Income Distribution">
    <x-slot name="pageHeader">
        <i class="fas fa-hand-holding-dollar mr-3"></i> Income Distribution
    </x-slot>
    <x-slot name="subHeader">
        How membership income is shared between HQ, branches and divisions
    </x-slot>

    @php
        $naira = fn (int $kobo) => \App\Support\Naira::format($kobo);
        $ruleLabels = \App\Services\Reports\IncomeDistributionStatsService::RULE_LABELS;
    @endphp

    <div class="container mx-auto px-4 py-6">

        {{-- ── TABS (branch users only have Tab 2) ───────────────────────────── --}}
        @if($isNational)
        <div id="income-distribution-tabs" class="flex gap-2 border-b border-gray-200 mb-6">
            @foreach([
                'online' => ['label' => 'Online payments (Paystack)', 'icon' => 'fa-credit-card'],
                'manual' => ['label' => 'Manual payments (branches)', 'icon' => 'fa-building'],
            ] as $tabKey => $tabDef)
                <a href="{{ route('reports.income-distribution.index', ['tab' => $tabKey, 'quarter' => $selectedQuarter]) }}"
                   class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-t-md border border-b-0 transition-colors
                       {{ $activeTab === $tabKey
                           ? 'bg-white border-gray-200 text-indigo-700 font-semibold'
                           : 'bg-gray-50 border-transparent text-gray-500 hover:text-gray-700 hover:bg-gray-100' }}">
                    <i class="fas {{ $tabDef['icon'] }} text-xs"></i>
                    {{ $tabDef['label'] }}
                </a>
            @endforeach
        </div>
        @endif

        {{-- ── TAB 2: Manual payments (branches) ──────────────────────────────── --}}
        @if($activeTab === 'manual')
            @include('reports.income-distribution.partials.manual-tab')
        @else

        {{-- ── QUARTER SELECTOR (+ in-progress warning) ──────────────────────── --}}
        @include('reports.income-distribution.partials.quarter-selector', [
            'action' => route('reports.income-distribution.index'),
            'hidden' => ['tab' => 'online'],
        ])

        <div class="text-center mb-6">
            <span class="text-2xl font-bold text-gray-900">{{ $quarterLabel }} &mdash; Online payments (Paystack)</span>
        </div>

        {{-- ── INTEGRITY WARNING (should never appear) ──────────────────────── --}}
        @if($integrityProblems)
            <div class="bg-red-100 border-l-4 border-red-600 text-red-800 p-4 mb-6" role="alert" data-testid="integrity-warning">
                <p class="font-bold">The figures on this page do not add up. Do not use them for payouts; report this to the system administrator.</p>
                @foreach($integrityProblems as $problem)
                    <p class="text-sm">{{ $problem }}</p>
                @endforeach
            </div>
        @endif

        {{-- ── HEADLINE ─────────────────────────────────────────────────────── --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-2">
            <div class="bg-white rounded-lg shadow border border-gray-200 px-5 py-4 text-center">
                <p class="text-xs uppercase tracking-wide text-gray-500">Total paid by members</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $naira($headline['paid']) }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ number_format($headline['count']) }} {{ Str::plural('payment', $headline['count']) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow border border-gray-200 px-5 py-4 text-center">
                <p class="text-xs uppercase tracking-wide text-gray-500">Paystack charges</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $naira($headline['fees']) }}</p>
                <p class="text-xs text-gray-400 mt-1">Absorbed by NRCS</p>
            </div>
            <div class="bg-indigo-50 rounded-lg shadow border-2 border-indigo-200 px-5 py-4 text-center">
                <p class="text-xs uppercase tracking-wide text-indigo-700">Net received (distributed)</p>
                <p class="text-3xl font-bold text-indigo-900 mt-1" data-testid="headline-net">{{ $naira($headline['net']) }}</p>
            </div>
        </div>
        <p class="text-xs text-gray-500 text-center mb-6">
            Based on Paystack payment time. A payment near the end of a quarter may reach the bank account in the next quarter.
        </p>

        @if($headline['count'] === 0)
            <p class="text-center text-gray-400 italic py-12">No online payments in {{ $quarterLabel }}.</p>
        @else

            {{-- ── PERCENTAGES USED ──────────────────────────────────────────── --}}
            <div class="bg-white rounded-lg shadow border border-gray-200 px-5 py-4 mb-6 text-sm">
                <p class="font-semibold text-gray-700 mb-2">Percentages used this quarter</p>
                @if($percentagesChanged)
                    <p class="mb-2 text-amber-700 font-semibold" data-testid="percentages-changed">
                        <i class="fas fa-exclamation-triangle mr-1"></i>Percentages changed during this quarter.
                        Each payment keeps the percentages that applied when it was paid.
                    </p>
                @endif
                <ul class="space-y-1 text-gray-700">
                    @foreach($percentageSets as $rule => $sets)
                        @foreach($sets as $set)
                            <li>
                                <span class="text-gray-500">{{ $ruleLabels[$rule] ?? $rule }}:</span>
                                {{ \App\Services\Reports\IncomeDistributionStatsService::describePercentages($rule, $set) }}
                                <span class="text-gray-400">({{ number_format($set['count']) }} {{ Str::plural('payment', $set['count']) }})</span>
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            </div>

            {{-- ── BRANCH TABLE ──────────────────────────────────────────────── --}}
            <div class="flex justify-end gap-2 mb-3">
                <a href="{{ route('reports.income-distribution.index', ['tab' => 'online', 'quarter' => $selectedQuarter, 'export' => 'csv']) }}"
                   class="filter-btn-secondary" data-testid="export-csv">
                    <i class="fas fa-file-csv mr-1"></i>Export CSV
                </a>
                <a href="{{ route('reports.income-distribution.divisions', ['quarter' => $selectedQuarter]) }}"
                   class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-md shadow"
                   data-testid="division-level-button">
                    <i class="fas fa-sitemap mr-2"></i>View division level
                </a>
            </div>
            <div class="overflow-x-auto rounded-lg shadow">
                <table class="min-w-full text-sm bg-white" data-testid="branch-table">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide border-b border-gray-200">
                            <th class="px-4 py-2 text-left">Branch</th>
                            <th class="px-3 py-2 text-right">Payments</th>
                            <th class="px-3 py-2 text-right">Paid by members</th>
                            <th class="px-3 py-2 text-right">Paystack charges</th>
                            <th class="px-3 py-2 text-right border-l border-gray-200">Net received</th>
                            <th class="px-3 py-2 text-right border-l border-gray-200">HQ share</th>
                            <th class="px-3 py-2 text-right">Branch share</th>
                            <th class="px-3 py-2 text-right">Division share</th>
                            <th class="px-3 py-2 text-right border-l border-gray-200" title="Net income from organisation payments, included in Net received">From Organisation</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($rows as $row)
                            <tr class="hover:bg-gray-50 {{ $row['kind'] !== 'branch' ? 'italic text-gray-600' : '' }}">
                                <td class="px-4 py-2 text-gray-900 whitespace-nowrap">
                                    {{-- No link for "No branch (to HQ)": it has no divisions. --}}
                                    @if($row['id'] !== null)
                                        <a href="{{ route('reports.income-distribution.divisions', ['quarter' => $selectedQuarter, 'branch' => $row['id']]) }}"
                                           class="font-semibold text-blue-700 hover:text-blue-900 hover:underline"
                                           title="Divisions paid through {{ $row['label'] }}">
                                            {{ $row['label'] }}
                                        </a>
                                    @else
                                        {{ $row['label'] }}
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right">{{ number_format($row['count']) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">{{ $naira($row['paid']) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">{{ $naira($row['fees']) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap font-semibold border-l border-gray-200">{{ $naira($row['net']) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap border-l border-gray-200">{{ $naira($row['hq']) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">{{ $naira($row['branch']) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap">{{ $naira($row['division']) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap border-l border-gray-200 {{ $row['organisation_net'] === 0 ? 'text-gray-300' : '' }}">{{ $naira($row['organisation_net']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-100 font-bold border-t-2 border-gray-300" data-testid="total-row">
                            <td class="px-4 py-2 text-gray-900">TOTAL</td>
                            <td class="px-3 py-2 text-right">{{ number_format($totals['count']) }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">{{ $naira($totals['paid']) }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">{{ $naira($totals['fees']) }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap border-l border-gray-200">{{ $naira($totals['net']) }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap border-l border-gray-200">{{ $naira($totals['hq']) }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">{{ $naira($totals['branch']) }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">{{ $naira($totals['division']) }}</td>
                            <td class="px-3 py-2 text-right whitespace-nowrap border-l border-gray-200">{{ $naira($totals['organisation_net']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif

        @endif
    </div>
</x-layouts.admin>
