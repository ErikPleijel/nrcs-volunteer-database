@props([
    'accessLevel',
    'branches' => collect(),
    'divisions' => collect(),
    'redCrossUnits' => collect(),
    'branchId' => null,
    'divisionId' => null,
    'unitId' => null,
    // Optional: field names (so you can reuse in other forms if needed)
    'branchField' => 'branch_id',
    'divisionField' => 'division_id',
    'unitField' => 'red_cross_unit_id',
])

@php
    $isBranchDisabled      = in_array($accessLevel, ['branch', 'division']);
    $isDivisionDisabled    = ($accessLevel === 'division');
    $isNationalAndNoBranch = ($accessLevel === 'national' && !$branchId);
@endphp

<div
    class="space-y-2" {{-- Changed from grid layout to vertical spacing --}}
data-location-cascade
    data-divisions-url="{{ url('/divisions/by-branch') }}"
    data-units-url="{{ url('/red-cross-units/by-division') }}"
    data-selected-branch="{{ $branchId }}"
    data-selected-division="{{ $divisionId }}"
    data-selected-unit="{{ $unitId }}"
>
    {{-- Branch --}}
    <div class="flex flex-col space-y-0.5">
        <label for="{{ $branchField }}" class="text-xs font-medium text-gray-700">
            Branch
        </label>

        <select id="{{ $branchField }}"
                name="{{ $branchField }}"
                data-branch-select
                class="w-full
                   px-2 py-1 text-xs border border-gray-300 rounded-md shadow-sm truncate
                   focus:outline-none focus:ring-blue-500 focus:border-blue-500
                   {{ $isBranchDisabled ? 'bg-gray-100 cursor-not-allowed' : ($branchId ? 'filter-active' : '') }}"
            {{ $isBranchDisabled ? 'disabled' : '' }}>
            @if ($accessLevel === 'national')
                <option value="">All Branches</option>
            @endif
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}"
                    {{ $isBranchDisabled || (string)$branchId === (string)$branch->id ? 'selected' : '' }}>
                    {{ $branch->name }}
                </option>
            @endforeach
        </select>
    </div>



    {{-- Division --}}
    <div class="flex flex-col space-y-0.5">
        <label for="{{ $divisionField }}" class="text-xs font-medium text-gray-700">
            Division
        </label>

        <select id="{{ $divisionField }}"
                name="{{ $divisionField }}"
                data-division-select
                class="w-full
                   px-2 py-1 text-xs border border-gray-300 rounded-md shadow-sm truncate
                   focus:outline-none focus:ring-blue-500 focus:border-blue-500
                   {{ ($isDivisionDisabled || $isNationalAndNoBranch) ? 'bg-gray-100 cursor-not-allowed' : ($divisionId ? 'filter-active' : '') }}"
            {{ ($isDivisionDisabled || $isNationalAndNoBranch) ? 'disabled' : '' }}>
            @if($isDivisionDisabled)
                @foreach($divisions as $division)
                    <option value="{{ $division->id }}" selected>{{ $division->name }}</option>
                @endforeach
            @else
                <option value="">{{ $isNationalAndNoBranch ? 'Select Branch First' : 'All Divisions' }}</option>
                @foreach($divisions as $division)
                    <option value="{{ $division->id }}"
                        {{ (string)$divisionId === (string)$division->id ? 'selected' : '' }}>
                        {{ $division->name }}
                    </option>
                @endforeach
            @endif
        </select>
    </div>



    {{-- Red Cross Unit --}}
    <div class="flex flex-col space-y-0.5">
        <label for="{{ $unitField }}" class="text-xs font-medium text-gray-700">
            RC Unit
        </label>

        <select id="{{ $unitField }}"
                name="{{ $unitField }}"
                data-unit-select
                class="w-full
                   px-2 py-1 text-xs border border-gray-300 rounded-md shadow-sm truncate
                   focus:outline-none focus:ring-blue-500 focus:border-blue-500
                   {{ $unitId ? 'filter-active' : '' }}"
            {{ !$divisionId && $accessLevel !== 'division' ? 'disabled' : '' }}>
            <option value="">
                {{ $divisionId || $accessLevel === 'division' ? 'All Units' : 'Select Division First' }}
            </option>
            @foreach($redCrossUnits as $unit)
                <option value="{{ $unit->id }}"
                    {{ (string)$unitId === (string)$unit->id ? 'selected' : '' }}>
                    {{ $unit->name }}
                </option>
            @endforeach
        </select>
    </div>

    {{-- Additive extension point — empty for every consumer that doesn't pass it --}}
    {{ $extraField ?? '' }}


</div>
