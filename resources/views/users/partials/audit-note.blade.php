{{-- users/edit on a self-managed account: tells staff that a change is audited
     (and, for email or password, emailed to the person). Param: $note (null = nothing). --}}
@if($note)
    <p class="mt-1 text-xs text-amber-700">
        <i class="fas fa-clipboard-list mr-1"></i>{{ $note }}
    </p>
@endif
