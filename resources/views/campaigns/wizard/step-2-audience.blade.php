<x-campaigns.wizard-layout
    :campaign="$campaign"
    :step="2"
    title="Campaign wizard"
    subtitle="Review who will receive the campaign (based on your saved filter)."
>


    <div class="grid grid-cols-1 gap-4 items-start">

        {{-- Summary + KPIs --}}
        <div class="wizard-card space-y-4">

            {{-- Filter description --}}
            <div class=" p-5 space-y-4">

                {{-- Delivery summary --}}
                <div>
                    <div class="flex items-center justify-between">
                        <div class="wizard-section-label">
                            Delivery Check
                        </div>
                    </div>

                    <p class="wizard-text margin-top: -10px;">
                        If the matched audience is too big, go back and adjust the filter and create a new campaign from that filter.
                    </p>

                    <div class="mt-6 font-semibold text-base text-gray-700">{{ $channelLabel }}</div>

                    <div class="wizard-text  leading-relaxed">
                        @if($campaign->channel === 'sms')
                            SMS will be sent to
                            <span class="font-semibold">{{ number_format($willSms ?? 0) }}</span> recipients.
                        @elseif($campaign->channel === 'email_fallback_sms')
                            Email will be sent to
                            <span class="font-semibold">{{ number_format($willEmail ?? 0) }}</span>.
                            SMS fallback will be sent to
                            <span class="font-semibold">{{ number_format($willSms ?? 0) }}</span>
                            <span class="text-gray-500">(no email on file)</span>.
                        @elseif($campaign->channel === 'both')
                            Email will be sent to
                            <span class="font-semibold">{{ number_format($willEmail ?? 0) }}</span>.
                            SMS will be sent to
                            <span class="font-semibold">{{ number_format($willSms ?? 0) }}</span>.

                        @else
                            Email will be sent to
                            <span class="font-semibold">{{ number_format($willEmail ?? 0) }}</span> recipients.
                        @endif
                    </div>

                    @if(($campaign->channel === 'both' && ($mayReceiveTwo ?? 0) > 0) || ($noReach ?? 0) > 0)
                        <div class="mt-3 rounded-md bg-amber-50 border border-amber-200 p-3 text-sm text-amber-900 space-y-1">

                            @if($campaign->channel === 'both' && ($mayReceiveTwo ?? 0) > 0)
                                <div>
                                <span class="font-semibold">
                                    Up to {{ number_format($mayReceiveTwo) }} person(s)
                                </span>
                                    may receive both an email and an SMS.
                                </div>
                            @endif

                            @if(($noReach ?? 0) > 0)
                                <div class="text-amber-800">
                                    <span class="font-semibold">{{ number_format($noReach) }}</span>

                                    @if($campaign->channel === 'sms')
                                        person(s) have no phone number and will not receive the message.
                                    @elseif($campaign->channel === 'email')
                                        person(s) have no email address and will not receive the message.
                                    @else
                                        person(s) have neither email nor phone number and will not receive any message.
                                    @endif
                                </div>
                            @endif

                        </div>
                    @endif


                </div>



            </div>


            {{-- Org representatives toggle --}}
            <form method="POST" action="{{ route('campaigns.wizard.step2.post', $campaign) }}">
                @csrf
                <input type="hidden" name="_audience_total" value="{{ $willReach ?? 0 }}">

                @php
                    $isOrgCampaign = ($filters['org_representatives'] ?? null) == '1';
                @endphp

                @if($isOrgCampaign)
                    <div class="rounded-lg border border-gray-200 bg-white p-5">
                        <div class="wizard-section-label mb-3">
                            Additional Targeting
                        </div>

                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox"
                                   id="org_representatives"
                                   name="org_representatives"
                                   value="1"
                                   class="mt-0.5 h-4 w-4 rounded border-gray-300 text-slate-700 focus:ring-slate-500">
                            <span class="text-sm font-medium text-gray-800">Also target org representatives</span>
                        </label>

                        <p id="org-rep-note" class="mt-2 ml-7 wizard-hint hidden">
                            Linked persons will be contacted at their personal email.
                            Each organisation's official email will also receive the campaign.
                        </p>
                    </div>
                @endif


                {{-- Navigation buttons --}}
                <div class="flex justify-between pt-4">
                    <a href="{{ route('campaigns.wizard.step1', $campaign) }}" class="wizard-btn-back">
                        <i class="fas fa-arrow-left mr-2 text-xs"></i> Back
                    </a>

                    <button type="submit" class="wizard-btn-continue">
                        Continue <i class="fas fa-arrow-right ml-2 text-xs"></i>
                    </button>
                </div>
            </form>
        </div>

        {{-- Full-width table --}}
        <h2 class="text-xl font-semibold text-gray-800 mt-8">Sample recipients</h2>
        <div class="rounded-lg border border-gray-200 bg-white overflow-hidden">


            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-white">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Name</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Email</th>
                        <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">Phone</th>
                    </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 bg-white">
                    @forelse($sample as $u)
                        <tr>
                            <td class="px-4 py-2 text-sm text-gray-900 whitespace-nowrap">
                                {{ $u->first_name }} {{ $u->last_name }}
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap">
                                {{ $u->email ?: '—' }}
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap">
                                {{ $u->telephone1 ?: ($u->telephone2 ?: '—') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-sm text-gray-600">
                                No users matched this filter.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Filter snapshot (optional - hidden) --}}
        <details class="hidden rounded-md bg-gray-50 p-4">
            <summary class="cursor-pointer text-sm font-semibold text-gray-800">
                View filter snapshot (for review/audit)
            </summary>
            <pre class="mt-3 text-xs text-gray-700 overflow-auto">{{ json_encode($filters, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </details>

    </div>

    <script>
        document.getElementById('org_representatives')?.addEventListener('change', function () {
            const note = document.getElementById('org-rep-note');
            if (note) note.classList.toggle('hidden', !this.checked);
        });
    </script>
</x-campaigns.wizard-layout>
