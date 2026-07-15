<div class="space-y-2">
    {{-- <x-filters.membership-volunteer class="lg:col-span-3" /> --}}
    {{-- Current Status --}}
    <div>
        <p class="text-xs font-semibold text-gray-700 mb-1">
            Current Status
        </p>
        <div class="flex flex-wrap gap-3">
            <label class="inline-flex items-center text-xs text-gray-700">
                <input type="checkbox"
                       name="is_member"
                       value="1"
                       class="rounded border-gray-300 text-red-600 focus:ring-red-500 mr-1"
                    {{ request('is_member') == '1' ? 'checked' : '' }}>
                <span>Member</span>
            </label>

            <label class="inline-flex items-center text-xs text-gray-700">
                <input type="checkbox"
                       name="is_volunteer"
                       value="1"
                       class="rounded border-gray-300 text-red-600 focus:ring-red-500 mr-1"
                    {{ request('is_volunteer') == '1' ? 'checked' : '' }}>
                <span>Volunteer</span>
            </label>
        </div>
    </div>

    {{-- Interest / Willing to Contribute --}}
    <div>
        <p class="text-xs font-semibold text-gray-700 mb-1">
            Willing to Contribute
        </p>
        <div class="flex flex-wrap gap-3">
            <label class="inline-flex items-center text-xs text-gray-700">
                <input type="checkbox"
                       name="wants_membership"
                       value="1"
                       class="rounded border-gray-300 text-red-600 focus:ring-red-500 mr-1"
                    {{ request('wants_membership') == '1' ? 'checked' : '' }}>
                <span>Member</span>
            </label>

            <label class="inline-flex items-center text-xs text-gray-700">
                <input type="checkbox"
                       name="wants_volunteer"
                       value="1"
                       class="rounded border-gray-300 text-red-600 focus:ring-red-500 mr-1"
                    {{ request('wants_volunteer') == '1' ? 'checked' : '' }}>
                <span>Volunteer</span>
            </label>
        </div>


    </div>


</div>
