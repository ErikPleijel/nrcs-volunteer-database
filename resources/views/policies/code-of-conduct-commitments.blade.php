{{--
    The four Code of Conduct / NDPA consent checkboxes, shared by the public
    registration form (auth/register) and the one-time confirmation page
    (consent/confirm) so the wording is identical on both. The fields are
    validated as 'accepted' in RegisterController and ConsentConfirmationController.

    Expects an Alpine parent providing commitment1..commitment4 and
    hasScrolledToBottom (the boxes stay disabled until the Code of Conduct
    has been scrolled to the end).
--}}
<div class="mt-4 space-y-3">
    <div class="flex items-start">
        <div class="flex items-center h-5">
            <input
                id="coc_commitment_1"
                name="coc_commitment_1"
                type="checkbox"
                value="1"
                class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                x-model="commitment1"
                :disabled="!hasScrolledToBottom"
            >
        </div>
        <div class="ml-3 text-sm">
            <label for="coc_commitment_1" class="font-medium text-gray-700 cursor-pointer">
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
                type="checkbox"
                value="1"
                class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                x-model="commitment2"
                :disabled="!hasScrolledToBottom"
            >
        </div>
        <div class="ml-3 text-sm">
            <label for="coc_commitment_2" class="font-medium text-gray-700 cursor-pointer">
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
                type="checkbox"
                value="1"
                class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                x-model="commitment3"
                :disabled="!hasScrolledToBottom"
            >
        </div>
        <div class="ml-3 text-sm">
            <label for="coc_commitment_3" class="font-medium text-gray-700 cursor-pointer">
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
                type="checkbox"
                value="1"
                class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                x-model="commitment4"
                :disabled="!hasScrolledToBottom"
            >
        </div>
        <div class="ml-3 text-sm">
            <label for="coc_commitment_4" class="font-medium text-gray-700 cursor-pointer">
                I consent to the Nigerian Red Cross Society collecting, storing, and processing my personal data (including identification details and photo) for membership and volunteer management purposes, in accordance with the Nigeria Data Protection Act 2023. I understand I may request access to or deletion of my data by contacting the NRCS <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener" class="text-blue-700 underline hover:text-blue-900">Data Protection Officer</a>.
                <span class="text-red-500">*</span>
            </label>
            @error('coc_commitment_4')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>
