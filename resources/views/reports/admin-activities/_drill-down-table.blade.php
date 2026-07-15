{{--
    Shared drill-down table for reports with a National → Branch → Division
    (→ RC Unit, where applicable) hierarchy. Included per-report/tab with
    $drillRows / $drillAreaHeader / $drillRowField / $columns / $routeName
    specific to that view; $drillCrumbs is shared.

    $columns: ordered array of ['key' => string, 'label' => string, 'org_key' => ?string].
    One <th>/<td> rendered per entry, reading $row[$column['key']]. Optional
    'org_key' renders a muted inline suffix next to that column's value when
    $row[$column['org_key']] > 0 — used by the messages tab's branch-level
    rows to surface the Organisation-recipient portion without fusing it
    into the combined number. Every column carries it defensively (falls
    back to 0 via ??), so tabs/reports that never set that row key are
    unaffected.

    $drillRowField null means "leaf, no link" — covers the RC Unit level
    (nothing below it), the organisation-certificate branch dead-end, and
    the lifecycle report's division dead-end (no unit-level snapshot data)
    with the same mechanism.

    $routeName: route to link drill-down rows to — each report passes its
    own index route name, since this partial is shared across reports now.
    $extraLinkParams (default []): fixed params merged into every drill
    link on top of request()->query() and the drill field itself — e.g.
    admin-activities passes ['tab' => $tab] so drilling preserves the
    active tab even when it isn't present in the current query string.
--}}
@php
    $extraLinkParams = $extraLinkParams ?? [];
@endphp
<x-reports.drill-breadcrumb :crumbs="$drillCrumbs" />

<div class="bg-white rounded-lg shadow overflow-x-auto mt-4">
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50 border-b">
            <tr>
                <th class="text-left px-4 py-3 font-semibold text-gray-700 min-w-[180px]">
                    {{ $drillAreaHeader }}
                </th>
                @foreach ($columns as $column)
                    <th class="text-center px-4 py-3 font-semibold text-gray-700">
                        {{ $column['label'] }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($drillRows as $row)
                <tr class="hover:bg-gray-50 {{ is_null($row['id']) ? 'bg-gray-50 italic' : '' }}">
                    <td class="px-4 py-2 font-medium text-gray-900">
                        @if($drillRowField && ! is_null($row['id']))
                            <a href="{{ route($routeName, array_merge(request()->query(), $extraLinkParams, [$drillRowField => $row['id']])) }}"
                               class="text-indigo-600 hover:text-indigo-800 hover:underline">{{ $row['name'] }}</a>
                        @else
                            {{ $row['name'] }}
                        @endif
                    </td>
                    @foreach ($columns as $column)
                        @php
                            $value = $row[$column['key']] ?? null;
                            $orgValue = $row[$column['org_key'] ?? ''] ?? 0;
                        @endphp
                        <td class="text-center px-4 py-2 font-bold text-gray-700">
                            {{ $value !== null && $value > 0 ? number_format($value) : '—' }}
                            @if($orgValue > 0)
                                <span class="ml-1 text-xs font-normal text-indigo-600">({{ number_format($orgValue) }} org)</span>
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) + 1 }}" class="px-4 py-6 text-center text-gray-400 italic">
                        No data found for this level.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
