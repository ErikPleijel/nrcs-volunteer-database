@php
    $title = 'Membership Revenue Breakdown';
    $pageHeader = 'Membership Revenue Report – Breakdown';

    $categoryLabels = [
        'member'       => 'Member Fees',
        'volunteer'    => 'Volunteer Fees',
        'organisation' => 'Organisation Fees',
    ];
    $categoryLabel = $categoryLabels[$category] ?? ucfirst($category);

    [$quarterYearPart, $quarterNumPart] = explode('-', $quarter);

    $areaLabel = $level === 'division' ? "Division: {$area->name}" : "Branch: {$area->name}";

    // index() has no division-scoped view — going "back to summary" for a
    // division-level breakdown lands on that division's enclosing branch.
    $backScopeId = $level === 'division' ? $area->branch_id : $area->id;

    $breadcrumbs = [
        ['label' => 'Dashboard', 'route' => 'reports.dashboard'],
        ['label' => 'Membership Revenue Report', 'route' => 'reports.financial.index'],
        ['label' => 'Breakdown'],
    ];
@endphp

<x-reports.reports-layout
    :title="$title"
    :pageHeader="$pageHeader"
    :breadcrumbs="$breadcrumbs"
>
    <div class="max-w-3xl mx-auto">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ $areaLabel }} &mdash; {{ $quarterNumPart }} {{ $quarterYearPart }} &mdash; {{ $categoryLabel }}
            </h2>

            <a href="{{ route('reports.financial.index', ['tab' => 'breakdown', 'scope' => $backScopeId, 'quarter' => $quarter]) }}"
               class="filter-btn-secondary">
                <i class="fas fa-arrow-left mr-1"></i>Back to summary
            </a>
        </div>

        <div class="table-container">
            <div class="table-wrapper">
                <table class="data-table">
                    <thead class="table-header">
                        <tr class="table-header-row">
                            <th class="table-header-cell">Payer</th>
                            <th class="table-header-cell">Reference</th>
                            <th class="table-header-cell">Fee Type</th>
                            <th class="table-header-cell">Date</th>
                            <th class="table-header-cell-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="table-body">
                        {{-- Total row: matches exactly what was clicked in the summary --}}
                        <tr class="bg-gray-100 dark:bg-gray-900/70 font-semibold">
                            <td class="table-body-cell" colspan="4">
                                Total ({{ $payments->count() }} {{ Str::plural('payment', $payments->count()) }})
                            </td>
                            <td class="table-body-cell text-right">
                                &#8358;{{ number_format($total, 0, '.', '') }}
                            </td>
                        </tr>

                        @forelse ($payments as $payment)
                            <tr class="table-body-row">
                                <td class="table-body-cell">
                                    {{ $payment->organisation_id ? $payment->organisation->name : ($payment->user->full_name ?? 'N/A (User Not Found)') }}
                                </td>
                                <td class="table-body-cell">
                                    {!! $payment->payment_reference_link !!}
                                </td>
                                <td class="table-body-cell">
                                    {{ $payment->membershipFee->name }}{{ $payment->membershipFee->validity_years ? ' ' . $payment->membershipFee->validity_years . ' Years' : '' }}
                                </td>
                                <td class="table-body-cell">
                                    {{ optional($payment->payment_date)->format('M d, Y') }}
                                </td>
                                <td class="table-body-cell text-right">
                                    &#8358;{{ number_format($payment->membershipFee->amount, 0, '.', '') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="table-body-cell text-center text-gray-500 dark:text-gray-400">
                                    No payments found for this selection.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-reports.reports-layout>
