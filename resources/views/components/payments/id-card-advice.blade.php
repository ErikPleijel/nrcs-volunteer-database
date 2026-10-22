{{-- Notes around the "Include ID card" box on a person's own membership
     payment, shared by the online payment page (audience "self") and the
     staff payment form (audience "staff"). Facts and default come from
     App\Services\IdCardOrderAdvice (Decisions.md 2026-10-22). Advice only —
     nothing here blocks a payment.

     "self": $advice is known, so only the notes that apply are rendered:
     "valid-until" and "volunteer-off" (a volunteer's box is unticked) outside
     the panel, and "panel" (shown while the box is ticked) with the rest.
     The page's JS toggles "panel" and "volunteer-off" with the box.

     "staff": staff know the rules, so just three one-line notes, all
     rendered hidden; the form's JS shows them and fills the
     [data-id-card-slot] texts from the current-membership JSON:
     "volunteer-off" while a volunteer's box is unticked, and "valid-until"
     and "missing" (photo, signature, NIN — rejected images count) while it
     is ticked.

     $checked is the box's starting state. --}}
@props(['audience' => 'self', 'advice' => null, 'checked' => false])

@if($audience === 'staff')
    <div {{ $attributes->merge(['class' => 'space-y-1 text-sm']) }}>
        <p data-id-card-advice="volunteer-off" class="hidden text-amber-700">Volunteers should have an ID card.</p>
        <p data-id-card-advice="valid-until" class="hidden text-gray-700">Card valid until <span data-id-card-slot="valid-until"></span>.</p>
        <p data-id-card-advice="missing" class="hidden text-amber-700">Missing for the ID card: <span data-id-card-slot="missing"></span>.</p>
    </div>
@else
    @php
        $a = $advice;
        $hidden = fn (bool $show) => $show ? '' : 'hidden';

        $imageNotes = [
            ['photo-missing', $a && ! $a->hasPhoto, 'You have no photo yet. The card needs a passport-style photo of you.', 'Upload photo', route('profile.edit-photo')],
            ['photo-rejected', $a && $a->hasPhoto && $a->photoRejected, 'Your photo was not accepted for the ID card. Please upload a new one.', 'Upload again', route('profile.edit-photo')],
            ['signature-missing', $a && ! $a->hasSignature, 'You have no signature yet. The card needs your signature.', 'Upload signature', route('profile.edit-signature')],
            ['signature-rejected', $a && $a->hasSignature && $a->signatureRejected, 'Your signature was not accepted for the ID card. Please upload a new one.', 'Upload again', route('profile.edit-signature')],
        ];
    @endphp

    <div {{ $attributes->merge(['class' => 'space-y-2']) }} data-is-volunteer="{{ $a?->isVolunteer ? '1' : '0' }}">

        @if($a?->validUntil)
            <p data-id-card-advice="valid-until" class="text-sm text-gray-700">
                <i class="fas fa-id-card mr-1 text-gray-400"></i>
                Your ID card is valid until {{ $a->validUntil->format('d M Y') }}.
                @if($a->expiresSoon)
                    <span data-id-card-advice="expires-soon">It expires soon.</span>
                @endif
            </p>
        @endif

        @if($a?->isVolunteer)
            <div data-id-card-advice="volunteer-off"
                 class="{{ $hidden(! $checked) }} flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                <i class="fas fa-triangle-exclamation mt-0.5 text-amber-600"></i>
                <span>
                    Volunteers are expected to have a Red Cross ID card. Only turn this off if you already have a valid ID card or have agreed this with your branch.
                </span>
            </div>
        @endif

        <div data-id-card-advice="panel" class="{{ $hidden($checked) }} mt-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
            <div class="font-semibold mb-2">
                <i class="fas fa-id-card mr-1"></i> About your ID card
            </div>
            <ul class="list-disc list-inside space-y-2">
                <li>
                    @if($a?->branchName)
                        Your ID card will not be posted. You will collect it at the {{ $a->branchName }} branch office.
                    @else
                        Your ID card will not be posted. You will collect it at your branch office.
                    @endif
                </li>
                <li>
                    We will use the photo and signature in your profile. If either is not clear, staff may ask you to upload a new one.
                    <a href="{{ route('profile.edit-photo') }}" class="font-medium underline hover:text-blue-700">Check your photo</a>
                    ·
                    <a href="{{ route('profile.edit-signature') }}" class="font-medium underline hover:text-blue-700">Check your signature</a>
                </li>
            </ul>

            @foreach($imageNotes as [$key, $applies, $text, $linkText, $url])
                @if($applies)
                    <div data-id-card-advice="{{ $key }}" class="mt-3 flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900">
                        <i class="fas fa-triangle-exclamation mt-0.5 text-amber-600"></i>
                        <div>
                            {{ $text }}
                            <a href="{{ $url }}" class="font-medium underline hover:text-amber-700">{{ $linkText }}</a>
                        </div>
                    </div>
                @endif
            @endforeach

            {{-- NIN: required for printing, and missing for most people — so a
                 clearly marked box with a direct link. --}}
            @if($a && ! $a->hasNin)
                <div data-id-card-advice="nin-missing" class="mt-3 rounded-lg border-2 border-amber-400 bg-amber-50 px-4 py-3 text-amber-900">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-triangle-exclamation mt-0.5 text-amber-600"></i>
                        <div>
                            <div class="font-semibold">NIN needed to print the card</div>
                            <p class="mt-1">
                                Your card cannot be printed without your National Identification Number (NIN). Please add it to your profile.
                            </p>
                            <a href="{{ route('profile.edit') }}#national_id_number" class="btn-primary mt-2">
                                <i class="fas fa-id-card mr-1"></i>Add NIN
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            @if($a && $a->validUntil && ! $a->expiresSoon)
                <div data-id-card-advice="replacement" class="mt-3 flex items-start gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900">
                    <i class="fas fa-triangle-exclamation mt-0.5 text-amber-600"></i>
                    <div>
                        You already have a valid ID card until {{ $a->validUntil->format('d M Y') }}. Only tick this box if you need a replacement.
                    </div>
                </div>
            @endif

            @if($a?->hasMissingItems())
                <p data-id-card-advice="missing-footnote" class="mt-3 text-xs text-blue-800">
                    You can pay now and add these to your profile afterwards, or add them first and come back to this page to pay.
                </p>
            @endif
        </div>
    </div>
@endif
