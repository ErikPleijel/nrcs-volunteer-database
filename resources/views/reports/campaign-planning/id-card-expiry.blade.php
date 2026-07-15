@php
    if (isset($division)) {
        $pageTitle = 'ID Card Expiry — ' . $division->name;
    } elseif (isset($branch)) {
        $pageTitle = 'ID Card Expiry — ' . $branch->name;
    } else {
        $pageTitle = 'ID Card Expiry Report — National';
    }
@endphp
<x-layouts.admin :title="$pageTitle">
    <x-slot name="pageHeader">
        <i class="fas fa-id-card mr-3"></i>
        @if(isset($division))
            ID Card Expiry — {{ $division->name }}
        @elseif(isset($branch))
            ID Card Expiry — {{ $branch->name }} Branch
        @else
            ID Card Expiry Report
        @endif
    </x-slot>
    <x-slot name="subHeader">
        @if(isset($division))
            Division level — {{ $division->name }}, {{ $branch->name }} Branch
        @elseif(isset($branch))
            Branch level — {{ $branch->name }}
        @else
            National level
        @endif
    </x-slot>

    <div class="container mx-auto px-4 py-6">

        @if(isset($division))
            <div class="mb-4 flex gap-3">
                <a href="{{ route('reports.id-card-expiry.branch', $branch->id) }}" class="btn-backlink">
                    ← {{ $branch->name }} Branch
                </a>
            </div>

            <p class="text-gray-500 text-sm mb-6">
                Red Cross Units in <strong>{{ $division->name }}</strong> division.
            </p>
        @elseif(isset($branch))


            <p class="text-gray-500 text-sm mb-6">
                Divisions in <strong>{{ $branch->name }}</strong> branch. Click a division to see RC units.
            </p>
        @else
            <p class="text-gray-500 text-sm mb-6">
                Showing count of currently valid ID cards expiring within each month window, by branch.
                Click a branch to drill down to division level.
            </p>
        @endif

        @php
            $crumbs = [];

            $crumbs[] = [
                'label' => 'National',
                'href'  => ($accessLevel === 'national' && isset($branch)) ? route('reports.id-card-expiry.national') : null,
                'badge' => null,
            ];

            if (isset($branch)) {
                $crumbs[] = [
                    'label' => $branch->name,
                    'href'  => (isset($division) && in_array($accessLevel, ['national', 'branch']))
                        ? route('reports.id-card-expiry.branch', $branch->id)
                        : null,
                    'badge' => ($accessLevel === 'branch' && (int) $userBranchId === (int) $branch->id) ? 'your branch' : null,
                ];
            }

            if (isset($division)) {
                $crumbs[] = [
                    'label' => $division->name,
                    'href'  => null,
                    'badge' => ($accessLevel === 'division' && (int) $userDivisionId === (int) $division->id) ? 'your division' : null,
                ];
            }

            $columnColorClasses = [
                'gray'   => 'bg-gray-100 text-gray-500',
                'red'    => 'bg-red-100 text-red-700',
                'orange' => 'bg-orange-100 text-orange-700',
                'blue'   => 'bg-blue-50 text-blue-700',
            ];
        @endphp
        <x-reports.drill-breadcrumb :crumbs="$crumbs" />

        <div class="bg-white rounded-lg shadow overflow-x-auto mt-4">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-gray-700 w-48">
                            @if(isset($division))
                                RC Unit
                            @elseif(isset($branch))
                                Division
                            @else
                                Branch
                            @endif
                        </th>
                        @foreach($columns as $i => $col)
                            <th class="text-center px-2 py-3 font-semibold text-gray-700 whitespace-nowrap">
                                {{ $col['label'] }}
                            </th>
                        @endforeach
                        <th class="text-center px-3 py-3 font-semibold text-gray-700">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($rows as $row)
                        <tr class="hover:bg-gray-50 {{ (isset($division) && is_null($row['id'])) ? 'bg-gray-50 italic' : '' }}">
                            <td class="px-4 py-2 font-medium text-gray-900">
                                @if(isset($division) || is_null($row['id']))
                                    {{ $row['name'] }}
                                @elseif(isset($branch))
                                    <a href="{{ route('reports.id-card-expiry.division', [$branch->id, $row['id']]) }}"
                                       class="text-indigo-600 hover:text-indigo-800 hover:underline">{{ $row['name'] }}</a>
                                @else
                                    <a href="{{ route('reports.id-card-expiry.branch', $row['id']) }}"
                                       class="text-indigo-600 hover:text-indigo-800 hover:underline">{{ $row['name'] }}</a>
                                @endif
                            </td>
                            @foreach($columns as $i => $col)
                                <td class="text-center px-2 py-2">
                                    @if($row['counts'][$i] > 0)
                                        <span class="inline-block px-2 py-0.5 rounded-full text-xs font-semibold
                                            {{ $columnColorClasses[$col['color']] ?? '' }}">
                                            {{ $row['counts'][$i] }}
                                        </span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-center px-3 py-2 font-bold text-gray-700">
                                {{ $row['total'] > 0 ? $row['total'] : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.admin>
