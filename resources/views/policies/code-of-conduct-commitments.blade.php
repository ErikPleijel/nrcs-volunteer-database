{{--
    The four Code of Conduct / NDPA consent checkboxes, included by
    policies/code-of-conduct-reader, which the public registration form
    (auth/register) and the one-time confirmation page (consent/confirm) share,
    so the wording is identical on both. The fields are validated as 'accepted'
    in RegisterController and ConsentConfirmationController.

    Expects the cocReader() Alpine parent (commitment1..commitment4 and
    hasScrolledToBottom; the boxes stay disabled until the Code of Conduct
    has been scrolled to the end). Disabled boxes ignore pointer events so a
    click falls through to the reader's wrapper, which explains why they are
    locked. data-old-checked restores ticks after a validation error.
--}}
<div class="mt-4 space-y-3">
    <div class="flex items-start">
        <div class="flex items-center h-5">
            <input
                id="coc_commitment_1"
                name="coc_commitment_1"
                @if(old('coc_commitment_1')) data-old-checked @endif
                type="checkbox"
                value="1"
                class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none"
                x-model="commitment1"
                :disabled="!hasScrolledToBottom"
            >
        </div>
        <div class="ml-3 text-sm">
            <label for="coc_commitment_1" class="font-medium text-gray-700" :class="hasScrolledToBottom ? 'cursor-pointer' : 'cursor-not-allowed'">
                I have read the entire Code of Conduct.
                <span class="text-red-500">*</span>
            </label>
            @error('coc_commitment_1')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="flex items-start">
        <div class="flex items-center h-5">
            <input
                id="coc_commitment_2"
                name="coc_commitment_2"
                @if(old('coc_commitment_2')) data-old-checked @endif
                type="checkbox"
                value="1"
                class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none"
                x-model="commitment2"
                :disabled="!hasScrolledToBottom"
            >
        </div>
        <div class="ml-3 text-sm">
            <label for="coc_commitment_2" class="font-medium text-gray-700" :class="hasScrolledToBottom ? 'cursor-pointer' : 'cursor-not-allowed'">
                I agree to follow the Code of Conduct at all times.
                <span class="text-red-500">*</span>
            </label>
            @error('coc_commitment_2')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="flex items-start">
        <div class="flex items-center h-5">
            <input
                id="coc_commitment_3"
                name="coc_commitment_3"
                @if(old('coc_commitment_3')) data-old-checked @endif
                type="checkbox"
                value="1"
                class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none"
                x-model="commitment3"
                :disabled="!hasScrolledToBottom"
            >
        </div>
        <div class="ml-3 text-sm">
            <label for="coc_commitment_3" class="font-medium text-gray-700" :class="hasScrolledToBottom ? 'cursor-pointer' : 'cursor-not-allowed'">
                I understand that violations may result in disciplinary action.
                <span class="text-red-500">*</span>
            </label>
            @error('coc_commitment_3')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>

    <div class="flex items-start">
        <div class="flex items-center h-5">
            <input
                id="coc_commitment_4"
                name="coc_commitment_4"
                @if(old('coc_commitment_4')) data-old-checked @endif
                type="checkbox"
                value="1"
                class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 disabled:opacity-50 disabled:pointer-events-none"
                x-model="commitment4"
                :disabled="!hasScrolledToBottom"
            >
        </div>
        <div class="ml-3 text-sm">
            <label for="coc_commitment_4" class="font-medium text-gray-700" :class="hasScrolledToBottom ? 'cursor-pointer' : 'cursor-not-allowed'">
                I consent to the Nigerian Red Cross Society collecting, storing, and processing my personal data (including identification details and photo) for membership and volunteer management purposes, in accordance with the Nigeria Data Protection Act 2023. I understand I may request access to or deletion of my data by contacting the NRCS <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener" class="text-blue-700 underline hover:text-blue-900">Data Protection Officer</a>.
                <span class="text-red-500">*</span>
            </label>
            @error('coc_commitment_4')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>
