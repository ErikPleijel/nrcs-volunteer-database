<x-layouts.admin title="Income Distribution — Divisions">
    <x-slot name="pageHeader">
        <i class="fas fa-hand-holding-dollar mr-3"></i> Income Distribution &mdash; Division level
    </x-slot>
    @php
        $naira = fn (int $kobo) => \App\Support\Naira::format($kobo);
        $isManual = $channel === 'manual';
        $channelLabel = $isManual ? 'Manual payments' : 'Online payments (Paystack)';
        // The channel and quarter every link on this page keeps.
        $keep = fn (array $extra = []) => array_filter(['channel' => $isManual ? 'manual' : null, 'quarter' => $selectedQuarter] + $extra);
    @endphp

    <x-slot name="subHeader">
        {{ $isManual
            ? 'What each division receives from manual membership payments collected by the branches'
            : 'What each division receives from online (Paystack) membership income' }}
    </x-slot>

    <style>[x-cloak]{ display:none !important; }</style>

    <div class="container mx-auto px-4 py-6">

        <div class="mb-4">
            <a href="{{ route('reports.income-distribution.index', ['tab' => $channel, 'quarter' => $selectedQuarter]) }}"
               class="text-blue-700 hover:text-blue-900 hover:underline">
                <i class="fas fa-arrow-left mr-1"></i>Back to branch level
            </a>
        </div>

        @include('reports.income-distribution.partials.before-first-quarter-note')

        @include('reports.income-distribution.partials.quarter-selector', [
            'action' => route('reports.income-distribution.divisions'),
            'hidden' => array_filter(['channel' => $isManual ? 'manual' : null, 'branch' => $branchFilter]),
        ])

        <div class="text-center mb-6">
            <span class="text-2xl font-bold text-gray-900">
                {{ $quarterLabel }} &mdash; {{ $channelLabel }} &mdash; {{ $branchFilterLabel ?? 'All branches' }}
            </span>
            {{-- Branch users only ever see their own branch. --}}
            @if($branchFilter !== null && $isNational)
                <div class="mt-1">
                    <a href="{{ route('reports.income-distribution.divisions', $keep()) }}"
                       class="text-sm text-blue-700 hover:text-blue-900 hover:underline">Show all branches</a>
                </div>
            @endif
        </div>

        @if($isManual)
            @include('reports.income-distribution.partials.go-live-notes')
        @endif

        @if($integrityProblems)
            <div class="bg-red-100 border-l-4 border-red-600 text-red-800 p-4 mb-6" role="alert" data-testid="integrity-warning">
                <p class="font-bold">The figures on this page do not add up. Do not use them for payouts; report this to the system administrator.</p>
                @foreach($integrityProblems as $problem)
                    <p class="text-sm">{{ $problem }}</p>
                @endforeach
            </div>
        @endif

        @if($rows->isEmpty())
            <p class="text-center text-gray-400 italic py-12">No division received a share in {{ $quarterLabel }}.</p>
        @else
            <script>
                // Client-side search and sort over every division row (up to
                // ~776, no paging). Money stays in whole kobo; totals are
                // summed as integers and only formatted for display.
                window.incomeDivisionTable = function (rows) {
                    return {
                        rows,
                        search: '',
                        sortKey: 'branch',
                        sortAsc: true,
                        get filtered() {
                            const q = this.search.trim().toLowerCase();
                            return q === ''
                                ? this.rows
                                : this.rows.filter(r => r.division.toLowerCase().includes(q) || r.branch.toLowerCase().includes(q));
                        },
                        get sorted() {
                            const key = this.sortKey;
                            const dir = this.sortAsc ? 1 : -1;
                            const byName = (a, b) => a.branch.localeCompare(b.branch) || a.division.localeCompare(b.division);
                            return [...this.filtered].sort((a, b) => {
                                const cmp = typeof a[key] === 'number' ? a[key] - b[key] : a[key].localeCompare(b[key]);
                                return (cmp * dir) || byName(a, b);
                            });
                        },
                        total(key) {
                            return this.filtered.reduce((sum, r) => sum + r[key], 0);
                        },
                        sortBy(key) {
                            if (this.sortKey === key) {
                                this.sortAsc = !this.sortAsc;
                            } else {
                                this.sortKey = key;
                                // Names A–Z first; amounts largest first.
                                this.sortAsc = key === 'division' || key === 'branch';
                            }
                        },
                        arrow(key) {
                            return this.sortKey === key ? (this.sortAsc ? '▲' : '▼') : '';
                        },
                        naira(kobo) {
                            const sign = kobo < 0 ? '-' : '';
                            kobo = Math.abs(kobo);
                            return sign + '₦' + Math.floor(kobo / 100).toLocaleString('en-US') + '.' + String(kobo % 100).padStart(2, '0');
                        },
                        number(n) {
                            return n.toLocaleString('en-US');
                        },
                    };
                };
            </script>

            <div x-data="incomeDivisionTable(@js($rows))">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <div class="flex items-center gap-2">
                        <label for="division-search" class="sr-only">Search divisions</label>
                        <input type="search" id="division-search" x-model="search"
                               placeholder="Search division or branch…"
                               class="w-72 rounded-md border-2 border-gray-300 px-3 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                    </div>
                    <div class="flex items-center gap-4">
                        <p class="text-sm text-gray-600" data-testid="showing-count">
                            Showing <span x-text="filtered.length">{{ $rows->count() }}</span>
                            of {{ number_format($rows->count()) }} {{ Str::plural('division', $rows->count()) }}
                        </p>
                        {{-- The search box is client-side only: the file always
                             holds every division for this quarter and branch.
                             Manual payments: CSV comes in a later step. --}}
                        @unless($isManual)
                        <a href="{{ route('reports.income-distribution.divisions', $keep(['branch' => $branchFilter, 'export' => 'csv'])) }}"
                           class="filter-btn-secondary" data-testid="export-csv"
                           title="Exports every division for this quarter{{ $branchFilter !== null ? ' and branch' : '' }}, not only the search results">
                            <i class="fas fa-file-csv mr-1"></i>Export CSV
                        </a>
                        @endunless
                    </div>
                </div>

                <div class="overflow-x-auto rounded-lg shadow">
                    <table class="min-w-full text-sm bg-white" data-testid="division-table">
                        <thead>
                            <tr class="bg-gray-50 text-gray-500 text-xs uppercase tracking-wide border-b border-gray-200 select-none">
                                <th class="px-4 py-2 text-left cursor-pointer hover:text-gray-800" @click="sortBy('division')">
                                    Division <span x-text="arrow('division')"></span>
                                </th>
                                <th class="px-4 py-2 text-left cursor-pointer hover:text-gray-800" @click="sortBy('branch')">
                                    Branch <span x-text="arrow('branch')">▲</span>
                                </th>
                                <th class="px-3 py-2 text-right cursor-pointer hover:text-gray-800" @click="sortBy('count')">
                                    Payments <span x-text="arrow('count')"></span>
                                </th>
                                <th class="px-3 py-2 text-right cursor-pointer hover:text-gray-800" @click="sortBy('net')">
                                    {{ $isManual ? 'Collected' : 'Net received' }} <span x-text="arrow('net')"></span>
                                </th>
                                <th class="px-3 py-2 text-right cursor-pointer hover:text-gray-800 border-l border-gray-200" @click="sortBy('division_share')">
                                    Division share <span x-text="arrow('division_share')"></span>
                                </th>
                                {{-- Bank details (bank name, account number, account
                                     name) are planned as columns here, once divisions
                                     have them. --}}
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template x-for="row in sorted" :key="row.division_id + '-' + row.branch_id">
                                <tr class="hover:bg-gray-50" :class="row.division_deleted ? 'italic text-gray-600' : ''">
                                    <td class="px-4 py-2 text-gray-900" x-text="row.division"></td>
                                    <td class="px-4 py-2 text-gray-700" x-text="row.branch"></td>
                                    <td class="px-3 py-2 text-right" x-text="number(row.count)"></td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap" x-text="naira(row.net)"></td>
                                    <td class="px-3 py-2 text-right whitespace-nowrap font-semibold border-l border-gray-200" x-text="naira(row.division_share)"></td>
                                </tr>
                            </template>
                            <tr x-show="filtered.length === 0" x-cloak>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-400 italic">No division matches your search.</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-100 font-bold border-t-2 border-gray-300" data-testid="total-row">
                                <td class="px-4 py-2 text-gray-900">
                                    <span x-text="search.trim() === '' ? 'TOTAL' : 'TOTAL (shown divisions)'">TOTAL</span>
                                </td>
                                <td class="px-4 py-2"></td>
                                <td class="px-3 py-2 text-right" x-text="number(total('count'))">{{ number_format($totals['count']) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap" x-text="naira(total('net'))">{{ $naira($totals['net']) }}</td>
                                <td class="px-3 py-2 text-right whitespace-nowrap border-l border-gray-200" x-text="naira(total('division_share'))" data-testid="division-share-total">{{ $naira($totals['division']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <p class="text-xs text-gray-500 mt-3">
                    @if($isManual)
                        Collected is the amount of the manual payments made in each division; the division share is the part the branch passes on to the division.
                        Payments are counted by the branch and division stored on them, in the quarter they were approved (old-system payments by payment date).
                    @else
                        Net received is the income from payments made in each division; the division share is the part that goes to the division.
                        Payments are counted by the branch and division stored when they were paid.
                    @endif
                </p>
            </div>
        @endif
    </div>
</x-layouts.admin>
