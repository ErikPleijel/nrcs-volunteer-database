<x-layouts.admin title="Review {{ $moduleLabel }}">
    <x-slot name="pageHeader">
        <i class="fas fa-magnifying-glass mr-3"></i>Review {{ $moduleLabel }}
    </x-slot>

    @push('styles')
        <style>[x-cloak]{ display:none !important; }</style>
    @endpush

    <div class="container mx-auto px-4 py-6 max-w-3xl">

        @include('approvals._flash')

        {{-- Status banner --}}
        @php $status = $record->approval_status; @endphp
        <div class="mb-6 rounded-lg border px-4 py-3 flex items-center gap-3
            @if($status === 'pending') border-yellow-200 bg-yellow-50 text-yellow-800
            @elseif($status === 'approved') border-green-200 bg-green-50 text-green-800
            @else border-red-200 bg-red-50 text-red-800 @endif">
            <x-approval-status-badge :status="$status" />
            <span class="text-sm">
                @if($status === 'pending')
                    This {{ strtolower($moduleLabel) }} is awaiting approval.
                @elseif($status === 'approved')
                    Approved{{ $record->decidedByUser ? ' by ' . $record->decidedByUser->full_name : '' }}
                    @if($record->decided_at) on {{ $record->decided_at->format('M d, Y') }}@endif.
                @else
                    Rejected{{ $record->decidedByUser ? ' by ' . $record->decidedByUser->full_name : '' }}
                    @if($record->decided_at) on {{ $record->decided_at->format('M d, Y') }}@endif.
                @endif
            </span>
        </div>

        {{-- Rejection reason --}}
        @if($status === 'rejected' && $record->rejection_reason)
            <div class="mb-6 rounded-lg border border-red-200 bg-white px-4 py-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-red-600 mb-1">Reason for rejection</div>
                <div class="text-sm text-gray-800">{{ $record->rejection_reason }}</div>
            </div>
        @endif

        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            {{-- Member --}}
            <div class="px-6 py-4 border-b border-gray-100">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-1">Member</div>
                <div class="text-base font-medium text-gray-900">{{ $record->user?->full_name ?? 'N/A' }}</div>
                @if($record->user)
                    <div class="text-sm text-gray-500">
                        {{ $record->user->user_id_reference_short }}
                        @if($record->user->redCrossUnit) · {{ $record->user->redCrossUnit->name }}@endif
                    </div>
                    <div class="mt-1">
                        <x-user-lifecycle-status-badge :user="$record->user" />
                    </div>
                @endif
            </div>

            {{-- Detail rows --}}
            <dl class="divide-y divide-gray-100">
                @foreach($record->approvalDetailRows() as $label => $value)
                    <div class="px-6 py-3 flex justify-between text-sm">
                        <dt class="text-gray-500">{{ $label }}</dt>
                        <dd class="text-gray-900 font-medium text-right">{{ $value }}</dd>
                    </div>
                @endforeach
                <div class="px-6 py-3 flex justify-between text-sm">
                    <dt class="text-gray-500">Location</dt>
                    <dd class="text-gray-900 font-medium text-right">
                        {{ $record->branch->name ?? '—' }}@if($record->division) – {{ $record->division->name }}@endif
                    </dd>
                </div>
                <div class="px-6 py-3 flex justify-between text-sm">
                    <dt class="text-gray-500">Submitted by</dt>
                    <dd class="text-gray-900 font-medium text-right">
                        {{ $record->submittedByUser?->full_name ?? 'N/A' }}
                        <span class="block text-xs text-gray-400 font-normal">
                            <x-time-ago :date="$record->created_at" :today="true" />
                        </span>
                    </dd>
                </div>
                @if($record->isSelfDirected)
                    <div class="px-6 py-3 flex justify-end">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                            <i class="fas fa-user-check mr-1"></i>Self-submitted — same person
                        </span>
                    </div>
                @endif
            </dl>

            {{-- Action bar --}}
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                <a href="{{ route($routeName . '.approvals') }}" class="text-sm text-gray-500 hover:text-gray-700">
                    <i class="fas fa-arrow-left mr-1"></i>Back to approvals
                </a>

                <div class="flex items-center gap-3">
                    @if($canApprove)
                        <x-approval-actions :record="$record" :route-name="$routeName" size="lg" />
                    @elseif($isSubmitter && $record->isPendingApproval())
                        <span class="text-sm text-gray-500 mr-1">Awaiting review</span>
                        <x-withdraw-button :url="route($routeName . '.withdraw', $record->id)" />
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
