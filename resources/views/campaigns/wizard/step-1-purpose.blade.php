<x-campaigns.wizard-layout :campaign="$campaign" :step="1"
                           title="Campaign wizard"
                           subtitle="Pick purpose and basic settings."
>
    <div class="wizard-card">
        <form method="POST" action="{{ route('campaigns.wizard.step1.post', $campaign) }}" class="space-y-5">
            @csrf

            {{-- Filter description --}}
            <div>
                <p class="wizard-section-label">Recipient filter</p>
                <div class="text-sm text-gray-700">{!! $filterDescriptionHtml !!}</div>
            </div>

            {{-- Purpose + Channel (side by side) --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="wizard-label" for="purpose_id">Purpose</label>
                    <select name="purpose_id" id="purpose_id"
                            class="wizard-input {{ $errors->has('purpose_id') ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : '' }}">
                        <option value="">— Select a purpose —</option>
                        @foreach($purposes as $purpose)
                            <option value="{{ $purpose->id }}"
                                    data-channel="{{ $purpose->default_channel }}"
                                    {{ old('purpose_id', $preselectedPurposeId ?? $campaign->purpose_id) == $purpose->id ? 'selected' : '' }}>
                                {{ $purpose->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('purpose_id')
                        <p class="mt-1 text-xs text-red-600"><i class="fas fa-circle-exclamation mr-1"></i>{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="wizard-label" for="channel">Channel</label>
                    <select name="channel" id="channel" class="wizard-input" required>
                        @foreach([
                            'email_fallback_sms' => 'Email (fallback to SMS)',
                            'sms'                => 'SMS only',
                            'email'              => 'Email only',
                            'both'               => 'Email and SMS (both)',
                        ] as $k => $v)
                            <option value="{{ $k }}" {{ old('channel', $campaign->channel) === $k ? 'selected' : '' }}>
                                {{ $v }}
                            </option>
                        @endforeach
                    </select>
                    <p class="wizard-hint">
                        "Email (fallback to SMS)" sends SMS only if no email is available.
                        "Email and SMS" may send two messages to the same person.
                    </p>
                </div>
            </div>

            {{-- Campaign title --}}
            <div>
                <label class="wizard-label" for="title">Description of Campaign</label>
                <input name="title"
                       id="title"
                       value="{{ old('title', $campaign->title) }}"
                       class="wizard-input {{ $errors->has('title') ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : '' }}"
                       placeholder="e.g. Dormant re-engagement (Jan)">
                @error('title')
                    <p class="mt-1 text-xs text-red-600"><i class="fas fa-circle-exclamation mr-1"></i>{{ $message }}</p>
                @enderror
            </div>

            {{-- Actions --}}
            <div class="pt-2 flex justify-end">
                <button type="submit" class="wizard-btn-continue">
                    Continue <i class="fas fa-arrow-right ml-2 text-xs"></i>
                </button>
            </div>

        </form>
    </div>

    <script>
        // Auto-update channel dropdown when purpose is selected
        const purposeSelect = document.getElementById('purpose_id');
        const channelSelect = document.getElementById('channel');

        purposeSelect.addEventListener('change', function () {
            const selected = this.options[this.selectedIndex];
            const defaultChannel = selected.dataset.channel;
            if (defaultChannel) {
                channelSelect.value = defaultChannel;
            }
        });

        // Auto-select channel on page load if purpose is pre-selected
        document.addEventListener('DOMContentLoaded', function () {
            const selected = purposeSelect.options[purposeSelect.selectedIndex];
            const defaultChannel = selected?.dataset.channel;
            if (defaultChannel && purposeSelect.value) {
                channelSelect.value = defaultChannel;
            }
        });
    </script>
</x-campaigns.wizard-layout>
