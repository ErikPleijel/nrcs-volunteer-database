@php
    $title      = 'Donation Breakdown';
    $pageHeader = 'Donations – Breakdown';
    $typeLabel  = $type === 'in-kind' ? 'In-kind' : 'Cash';

    $areaLabel = $level === 'division' ? "Division: {$area->name}" : "Branch: {$area->name}";

    $breadcrumbs = [
        ['label' => 'Dashboard', 'route' => 'reports.dashboard'],
        ['label' => 'Donations National', 'route' => 'reports.donations.national'],
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
                {{ $areaLabel }} &mdash; {{ $year }} Q{{ $quarter }} &mdash; {{ $typeLabel }} donations
            </h2>

            {{-- Division-level breakdowns land on the branch view (the actual
                 page with division-level donation data); branch-level ones
                 land on the national summary — donations.branch has no
                 division-scoped mode the way financial.index lacks one. --}}
            @if ($level === 'division')
                <a href="{{ route('reports.donations.branch', ['branch' => $area->branch_id, 'year' => $year]) }}"
                   class="filter-btn-secondary">
                    <i class="fas fa-arrow-left mr-1"></i>Back to summary
                </a>
            @else
                <a href="{{ route('reports.donations.national', ['year' => $year]) }}"
                   class="filter-btn-secondary">
                    <i class="fas fa-arrow-left mr-1"></i>Back to summary
                </a>
            @endif
        </div>

        <div class="table-container">
            <div class="table-wrapper">
                <table class="data-table">
                    <thead class="table-header">
                        <tr class="table-header-row">
                            <th class="table-header-cell">Donor</th>
                            <th class="table-header-cell">Reference</th>
                            <th class="table-header-cell">Date</th>
                            <th class="table-header-cell-right">
                                {{ $type === 'in-kind' ? 'Item' : 'Amount' }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="table-body">
                        {{-- Total row: matches exactly what was clicked in the summary --}}
                        <tr class="bg-gray-100 dark:bg-gray-900/70 font-semibold">
                            <td class="table-body-cell" colspan="3">
                                Total ({{ $donations->count() }} {{ Str::plural('donation', $donations->count()) }})
                            </td>
                            <td class="table-body-cell text-right">
                                @if ($type === 'in-kind')
                                    {{ number_format($total) }}
                                @else
                                    &#8358;{{ number_format($total, 2) }}
                                @endif
                            </td>
                        </tr>

                        @forelse ($donations as $donation)
                            <tr class="table-body-row">
                                <td class="table-body-cell">
                                    {{ $donation->donor_display_name }}
                                </td>
                                <td class="table-body-cell">
                                    {!! $donation->donation_reference_link !!}
                                </td>
                                <td class="table-body-cell">
                                    {{ optional($donation->date_donation)->format('M d, Y') }}
                                </td>
                                <td class="table-body-cell text-right">
                                    @if ($type === 'in-kind')
                                        {{ $donation->amount }} x {{ $donation->donation_item ?? 'items' }}
                                    @else
                                        &#8358;{{ number_format($donation->amount, 2) }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="table-body-cell text-center text-gray-500 dark:text-gray-400">
                                    No donations found for this selection.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-reports.reports-layout>
