{{-- National admins only (the count is 0 for anyone else): payments dated
     before the first quarter in the list (2016 Q1) are real history typed
     in from paper records, and belong to no quarter. Information only. --}}
@if($paymentsBeforeFirstQuarter > 0)
    <p class="text-xs text-gray-500 text-center mb-4" data-testid="before-first-quarter-note">
        <i class="fas fa-info-circle mr-1"></i>
        @if($paymentsBeforeFirstQuarter === 1)
            1 older payment (before 2016, from paper records) is not included.
        @else
            {{ number_format($paymentsBeforeFirstQuarter) }} older payments (before 2016, from paper records) are not included.
        @endif
    </p>
@endif
