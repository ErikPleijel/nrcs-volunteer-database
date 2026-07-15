@props([
    'active',            // 'records' | 'approvals'
    'recordsRoute',
    'recordsLabel' => 'Records',
    'approvalsRoute',
    'permission',
    'pendingCount' => 0,
])

@php
    $base     = 'whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center';
    $activeCl = 'border-red-600 text-red-600';
    $idleCl   = 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300';
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
