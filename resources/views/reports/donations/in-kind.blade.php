@php
    $title      = 'Donation Report – In Kind';
    $pageHeader = 'Donations – In Kind';

    $breadcrumbs = [
        ['label' => 'Dashboard', 'route' => 'reports.dashboard'],
        ['label' => 'Donations National', 'route' => 'reports.donations.national'],
        ['label' => 'In Kind'],
    ];
@endphp

<x-reports.reports-layout
    :title="$title"
    :pageHeader="$pageHeader"
    :breadcrumbs="$breadcrumbs"
>
    <style>
        @media print {
            #donations-in-kind-actions { display: none !important; }
            #donations-in-kind-branch-form { display: none !important; }

            {{-- .table-wrapper is an overflow-x-auto box (app.css); on paper
                 an overflow:auto box doesn't reflow, it just clips whatever
                 falls past its own edge — same root cause already diagnosed
                 and fixed for the Payments tab table in
                 reports/financial/index.blade.php. --}}
            .table-wrapper { overflow: visible !important; }

            {{-- Reference/Donor/Date/Number of Items/Item/Purpose don't fit
                 a portrait page even with the overflow fix above (Donor and
                 Purpose are free text) — landscape gives enough width
                 without needing to shrink the font, unlike the denser
                 13-column Payments table. --}}
            @page { size: landscape; }
        }
    </style>

    <div id="donations-in-kind-actions" class="flex justify-end gap-2 mb-4">
        <button type="button" onclick="window.print()" class="filter-btn-secondary">
            <i class="fas fa-print mr-1"></i>Print
        </button>
    </div>

    {{-- Branch dropdown — only restricted viewers' single branch appears for
         them; the "National" option is only rendered for national-level
         viewers, since a restricted viewer isn't allowed to select it (see
         DonationReportController::inKind()'s independent server-side check,
         which would reject that request anyway even if it were shown). --}}
    <div id="donations-in-kind-branch-form" class="flex justify-center mb-4">
        <form action="{{ route('reports.donations.in-kind') }}" method="GET" class="flex items-center gap-2">
            <label for="branch" class="filter-label-small mb-0">Branch</label>
            <select name="branch" id="branch"
                    class="filter-select-small {{ $scopeBranchId ? 'filter-active' : '' }}"
                    onchange="this.form.submit()">
                @if ($accessLevel === 'national')
                    <option value="" @selected(! $scopeBranchId)>National</option>
                @endif
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected($scopeBranchId === $branch->id)>
                        {{ $branch->name }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    <p class="text-center text-sm text-gray-600 dark:text-gray-400 mb-4">
        {{ number_format($totalCount) }} {{ Str::plural('in-kind donation', $totalCount) }} found
    </p>

    <div class="table-container">
        <div class="table-wrapper">
            <table class="data-table">
                <thead class="table-header">
                    <tr class="table-header-row">
                        <th class="table-header-cell">Reference</th>
                        <th class="table-header-cell">Donor</th>
                        <th class="table-header-cell">Date</th>
                        <th class="table-header-cell-right">Number of Items</th>
                        <th class="table-header-cell">Item</th>
                        <th class="table-header-cell">Purpose</th>
                    </tr>
                </thead>
                <tbody class="table-body">
                    @forelse ($donations as $donation)
                        <tr class="table-body-row">
                            <td class="table-body-cell">
                                {!! $donation->donation_reference_link !!}
                            </td>
                            <td class="table-body-cell">
                                @if ($donation->anonymous)
                                    Anonymous
                                @else
                                    {{ $donation->donor_display_name }}
                                @endif
                            </td>
                            <td class="table-body-cell whitespace-nowrap">
                                {{ optional($donation->date_donation)->format('M d, Y') }}
                            </td>
                            <td class="table-body-cell text-right">
                                {{ $donation->amount }}
                            </td>
                            <td class="table-body-cell">
                                {{ $donation->donation_item }}
                            </td>
                            <td class="table-body-cell">
                                {{ $donation->purpose }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="table-body-cell text-center text-gray-500 dark:text-gray-400">
                                No in-kind donations found for this selection.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="table-pagination">
            {{ $donations->appends(request()->query())->links() }}
        </div>
    </div>
</x-reports.reports-layout>
