<x-campaigns.wizard-layout :campaign="$campaign" :step="3"
                           title="Campaign wizard"
                           subtitle="Control timing and volume so branches don't get overwhelmed."
>

    @php
        $th            = $campaign->filter_json['_throttling'] ?? [];
        $callsChecked  = old('encourages_phone_calls', $encouragesPhoneCallsDefault);
    @endphp

    <div class="wizard-card">
        <form method="POST" action="{{ route('campaigns.wizard.step3.post', $campaign) }}" class="space-y-4">
            @csrf


            <div class="wizard-section-label">
                Call window & Daily cap
            </div>



            {{-- Context hint (always visible) --}}
            <div class=" space-y-2">
                <ul class="list-disc list-inside space-y-1">
                    <li>Set a <strong>call window</strong> so people know <em>when</em> to call.</li>
                    <li>Use a <strong>daily cap</strong> to limit how many messages are sent at once.</li>
                    <li>Smaller batches help avoid being overwhelmed by incoming calls.</li>
                </ul>
            </div>

            {{-- Checkbox first --}}
            <label class="inline-flex items-center gap-2 text-base font-medium text-gray-800 cursor-pointer">
                <input type="checkbox"
                       id="encourages_phone_calls"
                       name="encourages_phone_calls"
                       value="1"
                       class="h-5 w-5 rounded border-gray-300"
                    @checked($callsChecked)>
                This campaign encourages people to call the branch
            </label>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="wizard-label" for="send_window_start">Send window start</label>
                    <input
                        id="send_window_start"
                        name="send_window_start"
                        value="{{ old('send_window_start', $th['send_window_start'] ?? '') }}"
                        class="wizard-input disabled:opacity-50 disabled:bg-gray-100 disabled:cursor-not-allowed @error('send_window_start') border-red-300 focus:border-red-500 focus:ring-red-500 @enderror"
                        placeholder="For example: 08:00"
                        {{ $callsChecked ? '' : 'disabled' }}
                    >
                    @error('send_window_start')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="wizard-label" for="send_window_end">Send window end</label>
                    <input
                        id="send_window_end"
                        name="send_window_end"
                        value="{{ old('send_window_end', $th['send_window_end'] ?? '') }}"
                        class="wizard-input disabled:opacity-50 disabled:bg-gray-100 disabled:cursor-not-allowed @error('send_window_end') border-red-300 focus:border-red-500 focus:ring-red-500 @enderror"
                        placeholder="For example: 20:00"
                        {{ $callsChecked ? '' : 'disabled' }}
                    >
                    @error('send_window_end')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label class="wizard-label">Daily cap (optional)</label>
                    <input type="number" name="daily_cap"
                           value="{{ old('daily_cap', $th['daily_cap'] ?? '') }}"
                           class="wizard-input" placeholder="For example: 200">
                </div>
            </div>

            <div class="flex justify-between pt-2">
                <a href="{{ route('campaigns.wizard.step2', $campaign) }}" class="wizard-btn-back">
                    <i class="fas fa-arrow-left mr-2 text-xs"></i> Back
                </a>
                <button class="wizard-btn-continue">
                    Continue <i class="fas fa-arrow-right ml-2 text-xs"></i>
                </button>
            </div>
        </form>
    </div>

    <script>
        (function () {
            const cb    = document.getElementById('encourages_phone_calls');
            const start = document.getElementById('send_window_start');
            const end   = document.getElementById('send_window_end');

            function toggle() {
                start.disabled = !cb.checked;
                end.disabled   = !cb.checked;
                if (!cb.checked) {
                    start.value = '';
                    end.value   = '';
                }
            }

            cb.addEventListener('change', toggle);
        })();
    </script>
</x-campaigns.wizard-layout>
