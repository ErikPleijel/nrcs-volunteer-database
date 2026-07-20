@props([
    'active',            // 'records' | 'approvals'
    'recordsRoute',
    'recordsLabel' => 'Records',
    'approvalsRoute',
    'permission',
    'pendingCount' => 0,
])

@php
    $base     = 'whitespace-nowrap rounded-md py-2 px-4 font-medium text-sm flex items-center transition';
    $activeCl = 'bg-blue-500 text-white shadow-sm';
    $idleCl   = 'bg-gray-100 text-gray-600 hover:bg-gray-200 hover:text-gray-800';
@endphp

<div class="border-b border-gray-200 mb-6">
    <nav class="-mb-px flex gap-6" aria-label="Tabs">
        <a href="{{ $recordsRoute }}" class="{{ $base }} {{ $active === 'records' ? $activeCl : $idleCl }}">
            <i class="fas fa-list mr-2"></i>{{ $recordsLabel }}
        </a>

        @can($permission)
            <a href="{{ $approvalsRoute }}" class="{{ $base }} {{ $active === 'approvals' ? $activeCl : $idleCl }}">
                <i class="fas fa-clipboard-check mr-2"></i>Approvals
                @if($pendingCount > 0)
                    <span class="ml-2 inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800">
                        {{ $pendingCount }}
                    </span>
                @endif
            </a>
        @endcan
    </nav>
</div>
