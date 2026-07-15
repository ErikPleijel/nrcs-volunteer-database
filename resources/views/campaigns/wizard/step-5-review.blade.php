<x-campaigns.wizard-layout :campaign="$campaign" :step="5"
                           title="Review & submit"
                           subtitle="Check audience, timing, and message before submitting for approval.">

    @php
        // ... (Keep existing @php block exactly as it is) ...
        $filterRaw = $campaign->filter_json ?? [];
        $filter = is_array($filterRaw) ? $filterRaw : (json_decode((string) $filterRaw, true) ?: []);
        $th = $throttling ?? data_get($filter, '_throttling', []);
        $aud = $audience ?? [
            'total' => (int) data_get($filter, '_audience_total', 0),
            'human_summary_html' => data_get($filter, '_audience_human_summary_html', null),
            'samples' => data_get($filter, '_audience_samples', []),
            'willReach' => null,
            'willEmail' => null,
            'willSms' => null,
            'mayReceiveTwo' => null,
            'noReach' => null,
        ];
        $content = $content ?? data_get($filter, '_content', []);
        $warnings = $warnings ?? [];
        $blockers = $blockers ?? [];
        $hasBlockers = count($blockers) > 0;
        $channel = $campaign->channel ?? 'both';
        $hasEmail = in_array($channel, ['email','both','email_fallback_sms'], true);
        $hasSms   = in_array($channel, ['sms','both','email_fallback_sms'], true);
        $emailSubject = (string)($content['email_subject'] ?? $campaign->subject ?? '');
        $emailBody    = (string)($content['email_body'] ?? (string)($campaign->body ?? ''));
        $smsBody      = (string)($content['sms_body'] ?? '');
        $dailyCap = $th['daily_cap'] ?? null;
        $winStart = $th['send_window_start'] ?? null;
        $winEnd   = $th['send_window_end'] ?? null;
        $callsExpected = !empty($th['encourages_phone_calls']);
        $estimatedTotal = (int)($aud['total'] ?? 0);
        $estimatedDays = ($dailyCap && $estimatedTotal) ? (int) ceil($estimatedTotal / max(1, (int)$dailyCap)) : null;
        $activeMsgTab = old('_msg_tab', $hasEmail ? 'email' : 'sms');
        $samplePreviews = $samplePreviews ?? [];
        $firstSampleId = !empty($samplePreviews) ? array_key_first($samplePreviews) : null;
        $firstPreview = $firstSampleId ? ($samplePreviews[$firstSampleId] ?? null) : null;

        // Opt-out footer appended to every SMS at send time (CampaignSendRunner).
        // Placeholder token is a fixed 32 X's, matching the real id_check_token
        // length (Str::random(32)) exactly, so preview counts match reality.
        $smsFooterPlaceholderToken = str_repeat('X', 32);
        $smsFooterSuffix = "\nTo stop: ".config('app.url').'/u/'.$smsFooterPlaceholderToken.'/sms';
        $smsBodyTrimmed = trim((string) $smsBody);
        $smsBodyWithFooter = ($hasSms && $smsBodyTrimmed !== '') ? $smsBodyTrimmed.$smsFooterSuffix : $smsBodyTrimmed;
        $smsCharsRaw = strlen($smsBodyWithFooter);
        $smsPartsRaw = $smsCharsRaw === 0 ? 0 : ($smsCharsRaw <= 160 ? 1 : (int) ceil($smsCharsRaw / 153));
    @endphp

    <div class="space-y-6">

        {{-- Snapshot (Full Width) --}}
        <div class="wizard-card">
            @if ($errors->has('confirm_audience') || $errors->has('confirm_content') || $errors->has('confirm_calls'))
                <p class="mb-3 text-lg text-red-600 font-bold">Please complete the final checklist.</p>
            @endif

            <div class="w-full text-center">
                <div class="text-3xl font-bold text-gray-900">{{ $campaign->title ?: '(no title)' }}</div>
            </div>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-3">
                <div class="rounded-md bg-gray-50 p-3">
                    <div class="wizard-section-label">Audience</div>
                    <div class="mt-1 text-sm font-semibold text-gray-900">{{ number_format($estimatedTotal) }} <span class="text-xs font-normal text-gray-600">estimated</span></div>
                </div>
                <div class="rounded-md bg-gray-50 p-3">
                    <div class="wizard-section-label">Channel</div>
                    <div class="mt-1 text-sm text-gray-900">{{ strtoupper($channel) }}</div>
                </div>
                <div class="rounded-md bg-gray-50 p-3">
                    <div class="wizard-section-label">Send window</div>
                    <div class="mt-1 text-sm text-gray-900">{{ $winStart ?: '—' }} – {{ $winEnd ?: '—' }}</div>
                </div>
                <div class="rounded-md bg-gray-50 p-3">
                    <div class="wizard-section-label">Daily cap</div>
                    <div class="mt-1 text-sm text-gray-900">{{ $dailyCap ?: '—' }}</div>
                </div>
            </div>
            <div class="mt-4">
                <div class="wizard-section-label mb-1">FILTER</div>
                <div class="w-full rounded-md border border-gray-200 bg-white p-4 text-sm text-gray-700 shadow-sm">
                    {!! $aud['human_summary_html'] ?? '<span class="text-gray-500">No audience summary available.</span>' !!}
                </div>
            </div>
        </div>

        {{-- Message Section (SIDE BY SIDE) --}}
        <div class="wizard-card">
            @if(!empty($samplePreviews))
            <div class="flex flex-col items-center gap-2 mb-6">
                <label class="wizard-section-label">Preview as</label>
                <select id="previewRecipient" class="w-full max-w-sm rounded-md border-gray-300 text-sm">
                    @foreach($samplePreviews as $id => $p)
                        <option value="{{ $id }}">{{ $p['label'] ?? ('User #'.$id) }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2 mt-1">
                    <button type="button" id="prev-recipient"
                            class="px-3 py-1 rounded-md border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 text-sm">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <button type="button" id="next-recipient"
                            class="px-3 py-1 rounded-md border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 text-sm">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                {{-- Email Preview Column --}}
                @if($hasEmail)
                    <div class="lg:col-span-7 space-y-4 msg-panel" data-msg-panel="email">
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">Email</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pb-4 border-b border-gray-100">
                            <div>
                                <div class="wizard-section-label">From name</div>
                                <div class="mt-1 text-sm text-gray-900">{{ $campaign->from_name ?: '—' }}</div>
                            </div>
                            <div>
                                <div class="wizard-section-label">Reply-to email</div>
                                <div class="mt-1 text-sm text-gray-900">{{ $campaign->reply_to_email ?: '—' }}</div>
                            </div>
                        </div>
                        <div>
                            <div class="wizard-section-label">Subject</div>
                            <div id="emailSubjectRendered" class="mt-1 text-sm text-gray-900 font-medium">
                                {{ $firstPreview ? ($firstPreview['email_subject'] ?: '—') : ($emailSubject ?: '—') }}
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <div class="wizard-section-label mb-0">Body preview</div>
                                <div class="flex rounded-md border border-gray-300 overflow-hidden text-xs font-medium">
                                    <button type="button" id="s5-tab-code"
                                            class="px-3 py-1 bg-white text-gray-600 hover:bg-gray-50 transition-colors"
                                            onclick="setS5Tab('code')">
                                        <i class="fas fa-code mr-1"></i>Code
                                    </button>
                                    <button type="button" id="s5-tab-preview"
                                            class="px-3 py-1 bg-indigo-600 text-white transition-colors"
                                            onclick="setS5Tab('preview')">
                                        <i class="fas fa-eye mr-1"></i>Preview
                                    </button>
                                </div>
                            </div>

                            {{-- Code view --}}
                            <div id="s5-code-view" class="hidden">
                                <div id="emailBodyRendered"
                                     class="mt-1 rounded-md border border-gray-200 bg-gray-50 p-4 text-xs text-gray-800 whitespace-pre-wrap min-h-[200px] font-mono overflow-auto">
                                    {{ $firstPreview ? (trim((string)($firstPreview['email_body'] ?? '')) ?: '—') : (trim($emailBody) ?: '—') }}
                                </div>
                            </div>

                            {{-- Preview view --}}
                            <div id="s5-preview-view" class="mt-1">
                                <iframe id="s5-preview-frame"
                                        class="w-full rounded-md border border-gray-200 bg-white"
                                        style="height:400px;"
                                        sandbox="allow-same-origin">
                                </iframe>
                                <p class="text-xs text-gray-400 mt-1">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Placeholders have been substituted with the selected recipient's data.
                                </p>
                                @if($hasEmail)
                                    <div class="mt-2 rounded-md border border-gray-200 bg-gray-50 p-3 text-sm text-gray-500">

                                        <p>Stay up to date with the Nigerian Red Cross — <span class="underline">visit our website</span> and log in to your account to see what's on file: your membership status, training history, volunteering record, and any donations you've made.</p>
                                        <p>You are receiving this message because you are a registered member or volunteer of the Nigerian Red Cross Society. <span class="underline">Unsubscribe</span>.</p>

                                        <p class=" text-gray-600 mt-2 text-xs"><i class="fas fa-lock mr-1"></i>Automatically appended to every email.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                {{-- SMS Preview Column --}}
                @if($hasSms)
                    <div class="lg:col-span-5 space-y-4 msg-panel" data-msg-panel="sms">
                        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider">SMS</h3>
                        <div>
                            <div class="wizard-section-label">SMS preview</div>
                            <div id="smsBodyRendered" class="break-all mt-2 rounded-md border border-gray-200 bg-gray-50 p-4 text-sm text-gray-800 whitespace-pre-wrap min-h-[100px]">
                                {{ $firstPreview ? (trim((string)($firstPreview['sms_body'] ?? '')) ?: '—') : ($smsBodyWithFooter ?: '—') }}
                            </div>
                            @if($hasSms && $smsBodyTrimmed !== '')
                                <p class="mt-1 text-xs text-gray-500">
                                    <i class="fas fa-info-circle mr-1"></i>Includes the opt-out link every SMS gets at send time (shown with a placeholder token above).
                                </p>
                            @endif
                            <div id="smsStatsRendered" class="mt-2 text-xs text-gray-500">
                                @if(!$firstPreview && $smsCharsRaw > 0)
                                    {{ $smsCharsRaw }} chars · {{ $smsPartsRaw }} part(s)
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
            @if($callsExpected)
                <div class="w-fit flex items-center gap-2 rounded-full bg-amber-50 text-amber-800 px-3 py-1 text-xs font-semibold">
                    <span>Phone calls expected.</span>
                    <span class="font-normal opacity-90">Did you set your call times?</span>
                </div>
            @endif
        </div>

        {{-- Readiness & Next Steps (Side by Side) --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="wizard-card">
                <h2 class="text-base font-semibold text-gray-900">Readiness</h2>
                @if($hasBlockers)
                    <div class="mt-3 rounded-md border border-red-200 bg-red-50 p-4">
                        <div class="text-sm font-semibold text-red-800">High risk</div>
                        <ul class="mt-2 list-disc pl-5 text-sm text-red-800/90 space-y-1">
                            @foreach($blockers as $b) <li>{{ $b }}</li> @endforeach
                        </ul>
                    </div>
                @endif
                @if(!empty($warnings))
                    <div class="mt-3 rounded-md border border-amber-200 bg-amber-50 p-4">
                        <div class="text-sm font-semibold text-amber-900">Needs attention</div>
                        <ul class="mt-2 list-disc pl-5 text-sm text-amber-900/90 space-y-1">
                            @foreach($warnings as $w) <li>{{ $w }}</li> @endforeach
                        </ul>
                    </div>
                @else
                    <div class="mt-3 rounded-md border border-emerald-200 bg-emerald-50 p-4">
                        <div class="text-sm font-semibold text-emerald-800">Looks good</div>
                        <div class="mt-1 text-sm text-emerald-800/90">No major warnings detected.</div>
                    </div>
                @endif
            </div>

            <div class="wizard-card">
                <h2 class="text-base font-semibold text-gray-900">What happens next</h2>
                <p class="mt-2 text-sm text-gray-600">
                    After submission, HQ will make a review. Once approved, the campaign can be processed and messageas will be sent.

                </p>
            </div>
        </div>

        {{-- Final checklist --}}
        <form method="POST" action="{{ route('campaigns.wizard.submit', $campaign) }}"
              class="wizard-card space-y-4">
            @csrf
            <h2 class="text-base font-semibold text-gray-900">Final checklist</h2>

            @if(session('error'))
                <div class="rounded-md bg-red-50 p-4">
                    <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-4">
                    <div>
                        <label class="flex items-start gap-3 text-sm text-gray-700">
                            <input type="checkbox" name="confirm_audience" value="1" class="mt-1 rounded border-gray-300 @error('confirm_audience') border-red-500 @enderror">
                            <span>I have selected AUDIENCE, CHANNEL, SEND WINDOW and DAILY CAP carefully.</span>
                        </label>
                        @error('confirm_audience')
                            <p class="mt-1 text-xs text-red-600 pl-7">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="flex items-start gap-3 text-sm text-gray-700">
                            <input type="checkbox" name="confirm_content" value="1" class="mt-1 rounded border-gray-300 @error('confirm_content') border-red-500 @enderror">
                            <span>I have reviewed a few sample messages carefully. I think the message tone is good.</span>
                        </label>
                        @error('confirm_content')
                            <p class="mt-1 text-xs text-red-600 pl-7">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
                <div class="space-y-4">
                    @if($callsExpected)
                        <div>
                            <label class="flex items-start gap-3 text-sm text-gray-700">
                                <input type="checkbox" name="confirm_calls" value="1" class="mt-1 rounded border-gray-300 @error('confirm_calls') border-red-500 @enderror">
                                <span>I included a clear call window.</span>
                            </label>
                            @error('confirm_calls')
                                <p class="mt-1 text-xs text-red-600 pl-7">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                </div>
            </div>

            <div class="pt-6 flex flex-col md:flex-row items-center gap-4 border-t">
                <a href="{{ route('campaigns.wizard.step4', $campaign) }}" class="wizard-btn-back w-full md:w-auto text-center"><i class="fas fa-arrow-left mr-2 text-xs"></i> Back</a>
                <button type="submit" class="w-full md:flex-1 rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Submit for approval</button>
            </div>
        </form>
    </div>

    <script>
        (function () {
            const previews      = @json($samplePreviews ?? []);
            const select        = document.getElementById('previewRecipient');
            const emailSubjectEl = document.getElementById('emailSubjectRendered');
            const emailBodyEl   = document.getElementById('emailBodyRendered');
            const smsBodyEl     = document.getElementById('smsBodyRendered');
            const smsStatsEl    = document.getElementById('smsStatsRendered');

            // ── CODE / PREVIEW TOGGLE ──────────────────────────────────
            let currentEmailHtml = '';

            window.setS5Tab = function (tab) {
                const codeView    = document.getElementById('s5-code-view');
                const previewView = document.getElementById('s5-preview-view');
                const tabCode     = document.getElementById('s5-tab-code');
                const tabPreview  = document.getElementById('s5-tab-preview');
                const frame       = document.getElementById('s5-preview-frame');

                if (tab === 'preview') {
                    const doc = frame.contentDocument || frame.contentWindow.document;
                    doc.open();
                    doc.write(currentEmailHtml || '<p style="color:#999;font-family:sans-serif;">No email body.</p>');
                    doc.close();

                    codeView.classList.add('hidden');
                    previewView.classList.remove('hidden');
                    tabCode.classList.remove('bg-indigo-600', 'text-white');
                    tabCode.classList.add('bg-white', 'text-gray-600');
                    tabPreview.classList.remove('bg-white', 'text-gray-600');
                    tabPreview.classList.add('bg-indigo-600', 'text-white');
                } else {
                    codeView.classList.remove('hidden');
                    previewView.classList.add('hidden');
                    tabCode.classList.remove('bg-white', 'text-gray-600');
                    tabCode.classList.add('bg-indigo-600', 'text-white');
                    tabPreview.classList.remove('bg-indigo-600', 'text-white');
                    tabPreview.classList.add('bg-white', 'text-gray-600');
                }
            };

            // ── RECIPIENT SWAP ─────────────────────────────────────────
            function smsParts(text) {
                const t = (text || '').trim();
                const chars = t.length;
                if (chars === 0) return { chars: 0, parts: 0 };
                return { chars, parts: chars <= 160 ? 1 : Math.ceil(chars / 153) };
            }

            function apply(id) {
                const p = previews[id];
                if (!p) return;

                if (emailSubjectEl) emailSubjectEl.textContent = p.email_subject || '—';

                // Update code view text and store html for preview
                currentEmailHtml = p.email_body || '';
                if (emailBodyEl) emailBodyEl.textContent = currentEmailHtml || '—';

                // If preview tab is active, refresh the iframe too
                const previewView = document.getElementById('s5-preview-view');
                if (previewView && !previewView.classList.contains('hidden')) {
                    setS5Tab('preview');
                }

                if (smsBodyEl) smsBodyEl.textContent = p.sms_body || '—';
                if (smsStatsEl) {
                    const s = smsParts(p.sms_body || '');
                    smsStatsEl.textContent = s.chars > 0 ? `${s.chars} chars · ${s.parts} part(s)` : '';
                }
            }

            if (select && Object.keys(previews).length) {
                apply(select.value);
                select.addEventListener('change', () => apply(select.value));
            } else if (emailBodyEl) {
                // No sample previews — seed currentEmailHtml from the rendered div text
                currentEmailHtml = emailBodyEl.textContent || '';
            }

            const prevBtn = document.getElementById('prev-recipient');
            const nextBtn = document.getElementById('next-recipient');

            function navigateRecipient(direction) {
                if (!select) return;
                const options = Array.from(select.options);
                const currentIndex = select.selectedIndex;
                let newIndex;
                if (direction === 'prev') {
                    newIndex = currentIndex <= 0 ? options.length - 1 : currentIndex - 1;
                } else {
                    newIndex = currentIndex >= options.length - 1 ? 0 : currentIndex + 1;
                }
                select.selectedIndex = newIndex;
                apply(select.value);
            }

            if (prevBtn) prevBtn.addEventListener('click', () => navigateRecipient('prev'));
            if (nextBtn) nextBtn.addEventListener('click', () => navigateRecipient('next'));

            // Default to preview tab
            setS5Tab('preview');
        })();
    </script>
</x-campaigns.wizard-layout>
