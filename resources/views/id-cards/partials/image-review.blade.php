{{--
    "Upload again" control under a photo or signature on the bulk print page.
    Rejected: soft amber notice with the reason (full text, who and when in the
    tooltip) and, for admins, an Undo. Not rejected: a Reject button that opens
    the shared reason modal.

    Also returned by IdCardController::rejectImage()/restoreImage() as JSON
    'html', so the page swaps it in without a reload — keep it free of Alpine
    directives; the page's plain JS handles .js-reject-btn and .js-undo-form
    by event delegation. The Undo form still works without JavaScript.

    @param User   $user
    @param string $type  'photo' | 'signature'
--}}
@php
    $column = \App\Support\ImageRejection::column($type);
    $rejectedAt = $user->{$column.'_rejected_at'};
    $reason = \App\Support\ImageRejection::describe($user, $type);
    $rejectedBy = $type === 'photo' ? $user->pictureRejectedBy : $user->signatureRejectedBy;
    $label = $type === 'photo' ? 'photo' : 'signature';
    $canReject = auth()->user()->can('reject_images');
@endphp

<div class="w-full mt-1 text-center" data-image-review="{{ $type }}-{{ $user->id }}">
    @if($rejectedAt)
        <div class="rounded border border-amber-300 bg-amber-50 px-1 py-0.5 text-amber-900"
             title="{{ ucfirst($label) }} — upload again{{ $reason ? ': '.$reason : '' }}. Marked {{ $rejectedAt->format('d M Y') }}{{ $rejectedBy ? ' by '.$rejectedBy->full_name : '' }}.">
            <p class="text-[11px] font-semibold leading-tight">
                <i class="fas fa-rotate-right mr-0.5"></i>Upload again
            </p>
            @if($reason)
                <p class="text-[10px] leading-tight line-clamp-2">{{ $reason }}</p>
            @endif
            <p class="text-[10px] leading-tight text-amber-700">{{ $rejectedAt->format('d M') }}{{ $rejectedBy ? ' · '.$rejectedBy->full_name : '' }}</p>
            @if($canReject)
                <form method="POST" action="{{ route('id-cards.restore-image', [$user, $type]) }}"
                      class="inline js-undo-form" data-user-id="{{ $user->id }}" data-type="{{ $type }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-[10px] text-gray-600 underline hover:text-gray-900 disabled:opacity-50">Undo</button>
                </form>
            @endif
        </div>
    @elseif($canReject && filled($user->{$column}))
        <button type="button"
                data-url="{{ route('id-cards.reject-image', [$user, $type]) }}"
                data-type="{{ $type }}"
                data-user-id="{{ $user->id }}"
                data-name="{{ $user->full_name }}"
                class="js-reject-btn w-full inline-flex items-center justify-center gap-1 px-1.5 py-1 text-[11px] font-semibold rounded border border-red-300 text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500">
            <i class="fas fa-xmark"></i>Reject {{ $label }}
        </button>
    @endif
</div>
