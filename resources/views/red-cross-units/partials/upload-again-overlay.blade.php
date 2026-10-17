{{--
    "Upload again" overlay inside a rejected photo/signature area on the ID
    Card Completeness report. Semi-transparent, so the image (once "Show
    photos" loads it) stays faintly visible underneath. Same amber and wording
    as the bulk print page. The reason is shown only when $showReason (admins).
    The parent box must be `relative`.

    @param string      $type        'photo' | 'signature'
    @param int         $userId
    @param string|null $reason      ImageRejection::describe() text
    @param bool        $showReason
--}}
<div data-upload-again-overlay="{{ $type }}-{{ $userId }}"
     class="absolute inset-0 flex flex-col items-center justify-center gap-0.5 px-1 text-center
            bg-amber-100/80 text-amber-900"
     title="{{ $type === 'photo' ? 'Photo' : 'Signature' }}: upload again{{ $showReason && $reason ? ' — '.$reason : '' }}">
    <i class="fas fa-rotate-right text-lg text-amber-600" aria-hidden="true"></i>
    <p class="text-xs font-bold leading-tight">Upload again</p>
    @if($showReason && $reason)
        <p class="text-[11px] leading-tight line-clamp-3 break-words">{{ $reason }}</p>
    @endif
</div>
