@props(['record', 'routeName', 'size' => 'sm'])

@php
    $member       = $record->user;
    $archived     = $member && $member->lifecycle_status === 'archived';
    $selfDirected = $record->isSelfDirected;
    $memberName   = $member?->full_name ?? 'this member';
    $approveUrl   = route($routeName . '.approve', $record->id);
    $rejectUrl    = route($routeName . '.reject', $record->id);
    $btn          = $size === 'lg' ? 'px-4 py-2 text-sm' : 'px-3 py-1.5 text-xs';

    // Click behaviour: archived takes priority (its own modal chains into the
    // self-directed modal afterwards if both apply), then self-directed, then submit.
    $approveClick = $archived
        ? 'archivedOpen = true'
        : ($selfDirected ? 'selfDirectedOpen = true' : '$refs.approveForm.submit()');

    // The archived modal's confirm button: chain into the self-directed modal
    // instead of submitting, if that warning also applies — never merge the two.
    $archivedConfirmClick = $selfDirected
        ? 'archivedOpen = false; selfDirectedOpen = true'
        : '$refs.approveForm.submit()';
@endphp

<div x-data="{ rejectOpen: false, archivedOpen: false, selfDirectedOpen: false }" class="inline-flex items-center gap-2">

    {{-- Approve --}}
    <form method="POST" action="{{ $approveUrl }}" x-ref="approveForm" class="inline">
        @csrf
        @if($archived)
            <input type="hidden" name="confirm_archived_reactivation" value="1">
        @endif
        <button type="button"
                @click="{{ $approveClick }}"
                class="inline-flex items-center {{ $btn }} font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500">
            <i class="fas fa-check mr-1"></i>Approve
            @if($archived)<i class="fas fa-triangle-exclamation ml-1 text-yellow-200" title="Member is archived"></i>@endif
            @if($selfDirected)<i class="fas fa-user-check ml-1 text-yellow-200" title="Submitter and beneficiary are the same person"></i>@endif
        </button>
    </form>

    {{-- Reject --}}
    <button type="button" @click="rejectOpen = true"
            class="inline-flex items-center {{ $btn }} font-medium rounded-md text-red-700 bg-white border border-red-300 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500">
        <i class="fas fa-xmark mr-1"></i>Reject
    </button>

    {{-- Reject modal --}}
    <div x-show="rejectOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
         @keydown.escape.window="rejectOpen = false">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6" @click.outside="rejectOpen = false">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Reject this {{ strtolower($record->approvalModuleKey()) }}</h3>
            <p class="text-sm text-gray-600 mb-4">The submitter will be notified with the reason you give.</p>
            <form method="POST" action="{{ $rejectUrl }}">
                @csrf
                <textarea name="reason" rows="3" required maxlength="1000"
                          placeholder="Reason for rejection (required)"
                          class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500"></textarea>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="rejectOpen = false"
                            class="px-4 py-2 text-sm font-medium rounded-md text-gray-700 bg-gray-100 hover:bg-gray-200">Cancel</button>
                    <button type="submit"
                            class="px-4 py-2 text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700">
                        <i class="fas fa-xmark mr-1"></i>Confirm rejection
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Archived-reactivation confirmation modal --}}
    @if($archived)
        <div x-show="archivedOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
             @keydown.escape.window="archivedOpen = false">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6" @click.outside="archivedOpen = false">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 h-10 w-10 rounded-full bg-yellow-100 flex items-center justify-center">
                        <i class="fas fa-triangle-exclamation text-yellow-600"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Reactivate archived member?</h3>
                        <p class="text-sm text-gray-600 mt-1">
                            Approving this will <strong>reactivate archived member {{ $memberName }}</strong>.
                            Confirm to approve the record and bring the member back to active status.
                        </p>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" @click="archivedOpen = false"
                            class="px-4 py-2 text-sm font-medium rounded-md text-gray-700 bg-gray-100 hover:bg-gray-200">Cancel</button>
                    <button type="button" @click="{{ $archivedConfirmClick }}"
                            class="px-4 py-2 text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                        <i class="fas fa-check mr-1"></i>Confirm &amp; reactivate
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Self-directed confirmation modal --}}
    @if($selfDirected)
        <div x-show="selfDirectedOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
             @keydown.escape.window="selfDirectedOpen = false">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6" @click.outside="selfDirectedOpen = false">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 h-10 w-10 rounded-full bg-yellow-100 flex items-center justify-center">
                        <i class="fas fa-user-check text-yellow-600"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Approve a self-submitted record?</h3>
                        <p class="text-sm text-gray-600 mt-1">
                            <strong>{{ $memberName }}</strong> is both the beneficiary and the submitter of this
                            {{ strtolower($record->approvalModuleKey()) }}. Confirm to approve it anyway.
                        </p>
                    </div>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" @click="selfDirectedOpen = false"
                            class="px-4 py-2 text-sm font-medium rounded-md text-gray-700 bg-gray-100 hover:bg-gray-200">Cancel</button>
                    <button type="button" @click="$refs.approveForm.submit()"
                            class="px-4 py-2 text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                        <i class="fas fa-check mr-1"></i>Confirm &amp; approve
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
