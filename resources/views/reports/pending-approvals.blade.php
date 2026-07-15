<x-layouts.admin title="Pending Approvals by Branch">
    <x-slot name="pageHeader">
        <i class="fas fa-hourglass-half mr-3"></i> Pending Approvals
    </x-slot>
    <x-slot name="subHeader">
        Outstanding records awaiting approval — all branches
    </x-slot>


    <div class="container mx-auto px-4 py-6">

        {{-- Grand total banner --}}
        @php $total = $grandTotal['total']; @endphp
        <div class="mb-6 rounded-lg px-4 py-3 flex items-center gap-3
                    {{ $total === 0
                        ? 'bg-green-50 border border-green-200 text-green-800'
                        : 'bg-red-50 border border-red-200 text-red-800' }}">
            <i class="fas {{ $total === 0 ? 'fa-circle-check' : 'fa-hourglass-half' }}
                       text-xl"></i>
            <span class="font-semibold">
                {{ $total === 0
                    ? 'No pending approvals — all clear.'
                    : $total . ' records pending approval across all branches.' }}
            </span>
        </div>

        <div class="bg-white shadow rounded-lg overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide
                              text-gray-500 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left">Branch</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">
                            <i class="fas fa-hand-holding-dollar mr-1 text-indigo-400"></i>
                            Payments
                        </th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">
                            <i class="fas fa-heart mr-1 text-amber-400"></i>
                            Donations
                        </th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">
                            <i class="fas fa-graduation-cap mr-1 text-purple-400"></i>
                            Trainings
                        </th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">
                            <i class="fas fa-hands-helping mr-1 text-blue-400"></i>
                            Volunteering
                        </th>
                        <th class="px-4 py-3 text-center font-semibold">Total</th>
                        <th class="px-4 py-3 text-center whitespace-nowrap">Oldest Pending</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($rows as $row)
                        <tr class="hover:bg-gray-50
                                   {{ $row['total'] > 0 ? '' : 'opacity-40' }}">
                            <td class="px-4 py-3 font-medium text-gray-800">
                                {{ $row['branch']->name }}
                            </td>
                            <td class="px-4 py-3 text-center
                                       {{ $row['payments'] > 0 ? 'text-red-600 font-semibold' : 'text-gray-400' }}">
                                {{ $row['payments'] ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-center
                                       {{ $row['donations'] > 0 ? 'text-red-600 font-semibold' : 'text-gray-400' }}">
                                {{ $row['donations'] ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-center
                                       {{ $row['trainings'] > 0 ? 'text-red-600 font-semibold' : 'text-gray-400' }}">
                                {{ $row['trainings'] ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-center
                                       {{ $row['activities'] > 0 ? 'text-red-600 font-semibold' : 'text-gray-400' }}">
                                {{ $row['activities'] ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-center font-bold
                                       {{ $row['total'] > 0 ? 'text-red-700' : 'text-gray-300' }}">
                                {{ $row['total'] ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-center text-gray-500">
                                @if($row['oldest'])
                                    <x-time-ago :date="$row['oldest']" />
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-gray-100 border-t-2 border-gray-300 text-xs
                              font-semibold text-gray-700">
                    <tr>
                        <td class="px-4 py-3">Total</td>
                        <td class="px-4 py-3 text-center
                                   {{ $grandTotal['payments'] > 0 ? 'text-red-700' : '' }}">
                            {{ $grandTotal['payments'] ?: '—' }}
                        </td>
                        <td class="px-4 py-3 text-center
                                   {{ $grandTotal['donations'] > 0 ? 'text-red-700' : '' }}">
                            {{ $grandTotal['donations'] ?: '—' }}
                        </td>
                        <td class="px-4 py-3 text-center
                                   {{ $grandTotal['trainings'] > 0 ? 'text-red-700' : '' }}">
                            {{ $grandTotal['trainings'] ?: '—' }}
                        </td>
                        <td class="px-4 py-3 text-center
                                   {{ $grandTotal['activities'] > 0 ? 'text-red-700' : '' }}">
                            {{ $grandTotal['activities'] ?: '—' }}
                        </td>
                        <td class="px-4 py-3 text-center text-red-700 font-bold">
                            {{ $grandTotal['total'] ?: '—' }}
                        </td>
                        <td class="px-4 py-3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <p class="mt-3 text-xs text-gray-400 text-center">
            Sorted by total pending (highest first). Branches with no pending
            records are shown dimmed. Counts are nationwide regardless of your
            access level.
        </p>
    </div>
</x-layouts.admin>
