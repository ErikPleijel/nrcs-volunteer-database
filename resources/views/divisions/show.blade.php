@php
    // Fallback if withCount('redCrossUnits') wasn't used
    $unitsCount = $division->red_cross_units_count ?? ($division->redCrossUnits->count() ?? 0);
@endphp

<x-layouts.admin :title="'Division: ' . $division->name">

    <x-slot name="pageHeader">
        <i class="fas fa-layer-group mr-3 mb-6"></i> Division Details
    </x-slot>


    @can('edit_division_information')
        <x-slot name="button1">
            <a href="{{ route('divisions.edit', $division) }}" class="btn-edit">
                <i class="fas fa-edit mr-1"></i>Edit Division
            </a>
        </x-slot>
    @endcan

    <div class="show-page-container space-y-6">

        <div class="bg-white rounded-lg shadow p-6">
            <table class="detail-table">
                <tbody>

                    <tr>
                        <td>Division Name</td>
                        <td>
                            <span class="text-xl font-medium">{{ $division->name }}</span>
                        </td>
                    </tr>

                    <tr>
                        <td>Parent Branch</td>
                        <td>
                            @if($division->branch)
                                <a href="{{ route('branches.show', $division->branch) }}"
                                   class="text-blue-600 hover:text-blue-800 underline">
                                    {{ $division->branch->name }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td>Red Cross Units</td>
                        <td>{{ $unitsCount }}</td>
                    </tr>

                    <tr>
                        <td>Physical Address</td>
                        <td>{{ $division->physical_address ?? '—' }}</td>
                    </tr>

                    <tr>
                        <td>Postal Address</td>
                        <td>{{ $division->postal_address ?? '—' }}</td>
                    </tr>

                    <tr>
                        <td>Telephone</td>
                        <td>
                            @if($division->telephone)
                                <a href="tel:{{ $division->telephone }}" class="text-blue-600 hover:text-blue-800">
                                    {{ $division->telephone }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>

                    <tr>
                        <td>Email</td>
                        <td>
                            @if($division->email)
                                <a href="mailto:{{ $division->email }}" class="text-blue-600 hover:text-blue-800">
                                    {{ $division->email }}
                                </a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>

                    @if($division->latitude && $division->longitude)
                        <tr>
                            <td>Location</td>
                            <td>Lat: {{ $division->latitude }}, Long: {{ $division->longitude }}</td>
                        </tr>
                    @endif

                </tbody>
            </table>
        </div>

        {{-- Related Units (optional) --}}
        @if(isset($division->redCrossUnits) && $division->redCrossUnits->count())
            @php
                $unitsWithMembers = $division->redCrossUnits->filter(fn($u) => $u->users_count > 0);
                $emptyUnits = $division->redCrossUnits->filter(fn($u) => $u->users_count === 0);
            @endphp

            <div class="bg-white rounded-lg shadow-lg p-6">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-map-marker-alt text-red-600 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">RED CROSS UNITS</h2>
                </div>

                {{-- Units with members --}}
                @if($unitsWithMembers->count())
                    @php $chunks = $unitsWithMembers->chunk(ceil($unitsWithMembers->count() / 2)); @endphp
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 mb-4">
                        @foreach($chunks as $chunk)
                            <div>
                                @foreach($chunk as $unit)
                                    <div class="py-1 border-b border-gray-100">
                                        <a href="{{ route('red-cross-units.show', $unit) }}"
                                           class="text-base underline text-gray-700 hover:text-blue-600">{{ $unit->name }}</a>
                                        <span class="text-base text-gray-500">({{ $unit->users_count }})</span>
                                    </div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Empty units --}}
                @if($emptyUnits->count())
                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-2">Empty Units</p>
                        @php $emptyChunks = $emptyUnits->chunk(ceil($emptyUnits->count() / 2)); @endphp
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6">
                            @foreach($emptyChunks as $chunk)
                                <div>
                                    @foreach($chunk as $unit)
                                        <div class="py-1 border-b border-gray-100">
                                            <a href="{{ route('red-cross-units.show', $unit) }}"
                                               class="text-base underline text-gray-400 hover:text-blue-600">{{ $unit->name }}</a>
                                            <span class="text-base text-gray-300">(0)</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

            </div>
        @endif

    </div>
</x-layouts.admin>
