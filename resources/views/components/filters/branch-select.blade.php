@props([
    'branches' => collect(),
    'accessLevel',
    'userBranchId' => null,
    'field' => 'branch_id',
    'label' => 'Branch',
    'value' => null, // current selected branch (e.g. request('branch_id'))
    'filterActive' => false,
])

@php
    $isRestricted = in_array($accessLevel, ['branch', 'division']);
@endphp

<div>
    <label for="{{ $field }}" class="block text-sm font-medium text-gray-700">
        {{ $label }}
    </label>

    <select
        name="{{ $field }}"
        id="{{ $field }}"
        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm
            {{ $isRestricted ? 'bg-gray-100 cursor-not-allowed' : ($filterActive ? 'filter-active' : '') }}"
        {{ $isRestricted ? 'disabled' : '' }}
    >
        @if ($accessLevel === 'national')
            <option value="">All Branches</option>
        @endif

        @foreach($branches as $branch)
            <option value="{{ $branch->id }}"
                    @if((string)$value === (string)$branch->id)
                        selected
                    @elseif($isRestricted && (string)$userBranchId === (string)$branch->id && $value === null)
                        selected
                @endif
            >
                {{ $branch->name }}
            </option>
        @endforeach

        @if ($isRestricted && $branches->isEmpty())
            <option value="" selected>No Branch Accessible</option>
        @endif
    </select>
</div>
