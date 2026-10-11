{{-- Tab 2: manual payments, collected by the branches. National admins see
     every branch; branch users ($branchScope) their own branch only.
     Expects the manual-tab data from IncomeDistributionReportController,
     plus $naira and $ruleLabels from index.blade.php. --}}
@php
    $statsClass = \App\Services\Reports\IncomeDistributionStatsService::class;
@endphp

@include('reports.income-distribution.partials.before-first-quarter-note')

{{-- ── QUARTER SELECTOR (+ in-progress warning) ──────────────────────────── --}}
@include('reports.income-distribution.partials.quarter-selector', [
    'action' => route('reports.income-distribution.index'),
    'hidden' => ['tab' => 'manual'],
])

<div class="text-center mb-6">
    <span class="text-2xl font-bold text-gray-900">
        {{ $quarterLabel }} &mdash; Manual payments{{ $branchScopeLabel !== null ? ' — '.$branchScopeLabel : ' (branches)' }}
    </span>
</div>

{{-- ── GO-LIVE NOTES (+ invalid .env value, national admins only) ─────────── --}}
@include('reports.income-distribution.partials.go-live-notes')

{{-- ── MISSING SHARES (national admins only; should be none) ──────────────── --}}
@if($unsplitPayments > 0)
    <div class="bg-red-100 border-l-4 border-red-600 text-red-800 p-4 mb-6" role="alert" data-testid="unsplit-warning">
        <p class="font-bold">
            @if($unsplitPayments === 1)
                1 approved manual payment has no income shares yet, so it is not counted in any quarter.
            @else
                {{ number_format($unsplitPayments) }} approved manual payments have no income shares yet, so they are not counted in any quarter.
            @endif
        </p>
        <p class="text-sm">Report this to the system administrator (php artisan payments:backfill-manual-shares).</p>
    </div>
@endif

{{-- ── INTEGRITY WARNING (should never appear) ────────────────────────────── --}}
@if($integrityProblems)
    <div class="bg-red-100 border-l-4 border-red-600 text-red-800 p-4 mb-6" role="alert" data-testid="integrity-warning">
        <p class="font-bold">The figures on this page do not add up. Do not use them for payouts; report this to the system administrator.</p>
        @foreach($integrityProblems as $problem)
            <p class="text-sm">{{ $problem }}</p>
        @endforeach
    </div>
@endif

{{-- ── HEADLINE ───────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-2">
    <div class="bg-indigo-50 rounded-lg shadow border-2 border-indigo-200 px-5 py-4 text-center">
        <p class="text-xs uppercase tracking-wide text-indigo-700">Collected by {{ $branchScope !== null ? 'the branch' : 'branches' }}</p>
        <p class="text-3xl font-bold text-indigo-900 mt-1" data-testid="headline-collected">{{ $naira($headline['net']) }}</p>
        <p class="text-xs text-indigo-500 mt-1">{{ number_format($headline['count']) }} {{ Str::plural('payment', $headline['count']) }}</p>
    </div>
    <div class="bg-white rounded-lg shadow border border-gray-200 px-5 py-4 text-center">
        <p class="text-xs uppercase tracking-wide text-gray-500">HQ share</p>
        <p class="text-2xl font-bold text-gray-900 mt-1" data-testid="headline-hq">{{ $naira($headline['hq']) }}</p>
        <p class="text-xs text-gray-400 mt-1">Sent to HQ</p>
    </div>
    <div class="bg-white rounded-lg shadow border border-gray-200 px-5 py-4 text-center">
        <p class="text-xs uppercase tracking-wide text-gray-500">Branch share</p>
        <p class="text-2xl font-bold text-gray-900 mt-1" data-testid="headline-branch">{{ $naira($headline['branch']) }}</p>
        <p class="text-xs text-gray-400 mt-1">Kept by the {{ $branchScope !== null ? 'branch' : 'branches' }}</p>
    </div>
    <div class="bg-white rounded-lg shadow border border-gray-200 px-5 py-4 text-center">
        <p class="text-xs uppercase tracking-wide text-gray-500">Division share</p>
        <p class="text-2xl font-bold text-gray-900 mt-1" data-testid="headline-division">{{ $naira($headline['division']) }}</p>
        <p class="text-xs text-gray-400 mt-1">Passed on to divisions</p>
    </div>
</div>

@if($transitionParts !== null)
    @php $goLiveText = $statsClass::formatGoLiveDate($goLiveDate); @endphp
    <div class="flex flex-col sm:flex-row justify-center gap-2 sm:gap-6 text-sm text-gray-700 mb-2" data-testid="transition-parts">
        <p>
            <span class="text-gray-500">Before {{ $goLiveText }} (estimates):</span>
            <span class="font-semibold" data-testid="transition-before">{{ $naira($transitionParts['before']['net']) }}</span>
            <span class="text-gray-400">({{ number_format($transitionParts['before']['count']) }} {{ Str::plural('payment', $transitionParts['before']['count']) }})</span>
        </p>
        <p>
            <span class="text-gray-500">From {{ $goLiveText }} (recorded):</span>
            <span class="font-semibold" data-testid="transition-from">{{ $naira($transitionParts['from']['net']) }}</span>
            <span class="text-gray-400">({{ number_format($transitionParts['from']['count']) }} {{ Str::plural('payment', $transitionParts['from']['count']) }})</span>
        </p>
    </div>
@endif
<p class="text-xs text-gray-500 text-center mb-6">
    A payment counts in the quarter it was approved. Payments imported from the old system count by their payment date.
</p>

@if($headline['count'] === 0)
    <p class="text-center text-gray-400 italic py-12">No manual payments in {{ $quarterLabel }}.</p>
@else

    {{-- ── PERCENTAGES USED ──────────────────────────────────────────────── --}}
    <div class="bg-white rounded-lg shadow border border-gray-200 px-5 py-4 mb-6 text-sm">
        <p class="font-semibold text-gray-700 mb-2">Percentages used this quarter</p>
        @if($percentagesChanged)
            <p class="mb-2 text-amber-700 font-semibold" data-testid="percentages-changed">
                <i class="fas fa-exclamation-triangle mr-1"></i>Percentages changed during this quarter.
                Each payment keeps the percentages that applied when it was approved.
            </p>
        @endif
        <ul class="space-y-1 text-gray-700">
            @foreach($percentageSets as $rule => $sets)
                @foreach($sets as $set)
                    <li>
                        <span class="text-gray-500">{{ $ruleLabels[$rule] ?? $rule }}:</span>
                        {{ $statsClass::describePercentages($rule, $set) }}
                        <span class="text-gray-400">({{ number_format($set['count']) }} {{ Str::plural('payment', $set['count']) }})</span>
                    </li>
                @endforeach
            @endforeach
        </ul>
    </div>

    {{-- ── BRANCH TABLE ──────────────────────────────────────────────────── --}}
    <div class="flex justify-end gap-2 mb-3">
        <a href="{{ route('reports.income-distribution.index', ['tab' => 'manual', 'quarter' => $selectedQuarter, 'export' => 'csv']) }}"
           class="filter-btn-secondary" data-testid="export-csv">
            <i class="fas fa-file-csv mr-1"></i>Export CSV
        </a>
        <a href="{{ route('reports.income-distribution.divisions', array_filter(['channel' => 'manual', 'quarter' => $selectedQuarter, 'branch' => $branchScope])) }}"
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
                    <th class="px-3 py-2 text-right">Collected</th>
                    <th class="px-3 py-2 text-right border-l border-gray-200">HQ share</th>
                    <th class="px-3 py-2 text-right">Branch share</th>
                    <th class="px-3 py-2 text-right">Division share</th>
                    <th class="px-3 py-2 text-right border-l border-gray-200" title="Collected from organisation payments, included in Collected">From Organisation</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($rows as $row)
                    <tr class="hover:bg-gray-50 {{ $row['kind'] !== 'branch' ? 'italic text-gray-600' : '' }}">
                        <td class="px-4 py-2 text-gray-900 whitespace-nowrap">
                            {{-- No link for "No branch (to HQ)": it has no divisions. --}}
                            @if($row['id'] !== null)
                                <a href="{{ route('reports.income-distribution.divisions', ['channel' => 'manual', 'quarter' => $selectedQuarter, 'branch' => $row['id']]) }}"
                                   class="font-semibold text-blue-700 hover:text-blue-900 hover:underline"
                                   title="Divisions paid through {{ $row['label'] }}">
                                    {{ $row['label'] }}
                                </a>
                            @else
                                {{ $row['label'] }}
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right">{{ number_format($row['count']) }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap font-semibold">{{ $naira($row['net']) }}</td>
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
                    <td class="px-3 py-2 text-right whitespace-nowrap">{{ $naira($totals['net']) }}</td>
                    <td class="px-3 py-2 text-right whitespace-nowrap border-l border-gray-200">{{ $naira($totals['hq']) }}</td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">{{ $naira($totals['branch']) }}</td>
                    <td class="px-3 py-2 text-right whitespace-nowrap">{{ $naira($totals['division']) }}</td>
                    <td class="px-3 py-2 text-right whitespace-nowrap border-l border-gray-200">{{ $naira($totals['organisation_net']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif
