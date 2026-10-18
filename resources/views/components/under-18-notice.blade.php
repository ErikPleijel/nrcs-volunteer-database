@props([
    'for' => 'birth_year', // id of the birth year <select>
    'audience' => 'self',  // 'self' (public registration) or 'staff' (users/create)
])

{{-- Friendly notice below the birth year dropdown for people who may be under 18.
     Only the year is known, so someone born exactly 18 years ago may still be 17:
     that year gets the softer "If you have not had your 18th birthday yet" wording.
     Information only — nothing is blocked or recorded (COMPLIANCE.md §4).
     Both texts are always in the HTML; JS only shows/hides them. Attributes are read with
     getAttribute(): a dataset key would break on digits ("data-under-18-text" → "under-18Text"). --}}
@php
    $boundaryYear = (int) date('Y') - 18;
    $selected = (int) old($for);
    $state = $selected > $boundaryYear ? 'under' : ($selected === $boundaryYear ? 'maybe' : null);

    $messages = $audience === 'staff'
        ? [
            'under' => 'This person is under 18. Check that their parent or guardian agrees to them joining.',
            'maybe' => 'This person may be under 18. Check that their parent or guardian agrees to them joining.',
        ]
        : [
            'under' => 'You are under 18. Please ask your parent or guardian if it is OK for you to join. They may need to give their permission at your branch.',
            'maybe' => 'If you have not had your 18th birthday yet, please ask your parent or guardian if it is OK for you to join. They may need to give their permission at your branch.',
        ];
@endphp

<div id="{{ $for }}-under-18" data-age-notice data-boundary-year="{{ $boundaryYear }}"
     class="mt-2 p-2 bg-blue-50 border border-blue-200 rounded-md text-sm text-blue-900 {{ $state ? '' : 'hidden' }}"
     role="status" aria-live="polite">
    <i class="fas fa-circle-info mr-1 text-blue-500"></i>
    <span data-age-notice-text="under" class="{{ $state === 'under' ? '' : 'hidden' }}">{{ $messages['under'] }}</span>
    <span data-age-notice-text="maybe" class="{{ $state === 'maybe' ? '' : 'hidden' }}">{{ $messages['maybe'] }}</span>
</div>

<script>
    (function () {
        var select = document.getElementById(@json($for));
        var notice = document.getElementById(@json($for . '-under-18'));
        if (!select || !notice) return;

        var boundaryYear = parseInt(notice.getAttribute('data-boundary-year'), 10);

        select.addEventListener('change', function () {
            var year = parseInt(select.value, 10);
            var state = year > boundaryYear ? 'under' : (year === boundaryYear ? 'maybe' : null);

            notice.classList.toggle('hidden', !state);
            notice.querySelectorAll('[data-age-notice-text]').forEach(function (el) {
                el.classList.toggle('hidden', el.getAttribute('data-age-notice-text') !== state);
            });
        });
    })();
</script>
