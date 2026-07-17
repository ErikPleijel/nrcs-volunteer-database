<x-layouts.admin>
    <x-slot name="title">Review Campaign</x-slot>

    <x-slot name="pageHeader">
        <i class="fas fa-sliders mr-3 mb-6"></i>  Campaigns Management
    </x-slot>



    <div class="space-y-8">

        {{-- Header --}}
        @php
            $badge = match ($campaign->status) {
                'proposed' => 'bg-yellow-100 text-yellow-800',
                'approved' => 'bg-green-100 text-green-800',
                'rejected' => 'bg-red-100 text-red-800',
                'queued'   => 'bg-blue-100 text-blue-800',
                'sending'  => 'bg-indigo-100 text-indigo-800',
                'sent'     => 'bg-emerald-100 text-emerald-800',
                'cancelled'=> 'bg-gray-100 text-gray-700',
                default    => 'bg-gray-100 text-gray-700',
            };
        @endphp
        {{-- Flash --}}
        @if (session('success'))
            <div class="rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        {{-- Message PHP block (kept outside grid for scope) --}}
        @php
            $channel = $campaign->channel ?? 'email';

            $hasEmail = in_array($channel, ['email', 'both', 'email_fallback_sms'], true);
            $hasSms   = in_array($channel, ['sms', 'both', 'email_fallback_sms'], true);

            $filterRaw = $campaign->filter_json ?? [];
            $filterArr = is_array($filterRaw) ? $filterRaw : (json_decode((string) $filterRaw, true) ?: []);
            $content   = data_get($filterArr, '_content', []);

            $emailBody = (string) ($content['email_body'] ?? $campaign->body ?? '');
            $subject   = (string) ($content['email_subject'] ?? $campaign->subject ?? '');
            $smsText   = (string) ($content['sms_body'] ?? '');

            $callsExpected = !empty($throttling['encourages_phone_calls'] ?? false);

            // Placeholder highlighting (supports {{...}} and @{{...}})
            $highlightPlaceholders = function (string $text): string {
                $text = str_replace("\r\n", "\n", $text);
                $safe = e($text);
                return preg_replace(
                    '/(@?\{\{[^}]+\}\})/',
                    '<span class="px-1 rounded bg-amber-100 text-amber-900 font-mono text-[0.85em]">$1</span>',
                    $safe
                );
            };

            // Opt-out footer appended to every SMS at send time (CampaignSendRunner).
            // Placeholder token is a fixed 32 X's, matching the real id_check_token
            // length (Str::random(32)) exactly, so preview counts match reality.
            $smsFooterPlaceholderToken = str_repeat('X', 32);
            $smsFooterSuffix = "\nTo stop: ".config('app.url').'/u/'.$smsFooterPlaceholderToken.'/sms';

            $smsTrim = trim($smsText);
            $smsTrimWithFooter = ($hasSms && $smsTrim !== '') ? $smsTrim.$smsFooterSuffix : $smsTrim;
            $smsChars = mb_strlen($smsTrimWithFooter);
            $smsParts = $smsChars === 0 ? 0 : ($smsChars <= 160 ? 1 : (int) ceil($smsChars / 153));

            // Build personalised previews from $sample (same pattern as wizard step 5)
            $samplePreviews = [];
            if (isset($sample) && $sample->count()) {
                foreach ($sample as $u) {
                    $label = trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: ('User #' . $u->id);
                    $renderedSmsBody = trim(\App\Support\CampaignPlaceholderRenderer::render($smsText, $u));
                    if ($hasSms && $renderedSmsBody !== '') {
                        $renderedSmsBody .= $smsFooterSuffix;
                    }
                    $samplePreviews[$u->id] = [
                        'label'         => $label,
                        'email_subject' => \App\Support\CampaignPlaceholderRenderer::render($subject, $u),
                        'email_body'    => \App\Support\CampaignPlaceholderRenderer::render($emailBody, $u),
                        'sms_body'      => $renderedSmsBody,
                    ];
                }
            }
            $firstSampleId = !empty($samplePreviews) ? array_key_first($samplePreviews) : null;
            $firstPreview  = $firstSampleId ? $samplePreviews[$firstSampleId] : null;
        @endphp

        <div>
            <a href="{{ route('campaigns.admin.proposed') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700">
                <i class="fas fa-arrow-left mr-1"></i>Back to Campaign Management
            </a>
        </div>

        <div class="leading-tight">
            <div class="text-3xl font-bold text-gray-900">
                {{ $campaign->code }}
                {{ $campaign->title ?? 'Untitled campaign' }}
                <span class="text-gray-400 font-light mx-2">/</span>
                <span class="text-gray-600">{{ $campaign->creator?->branch?->name ?? '—' }}</span>
            </div>
            <div class="mt-1 text-lg text-gray-600">{{ $campaign->purpose?->name ?? '' }}</div>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-lg font-medium {{ $badge }}">{{ ucfirst($campaign->status) }}</span>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">

            {{-- Left column --}}
            <div class="space-y-6 lg:col-span-7">

                {{-- Audience --}}
                <div class="rounded-lg border border-blue-200 bg-blue-100 p-6 shadow-sm">
                    <div>
                        <h2 class="text-2xl font-semibold text-gray-800">Audience</h2>

                        <p class="mt-3 mb-1 flex items-baseline gap-2 text-base text-gray-600">
                            <span class="check-list-number font-semibold">&#10140;</span>
                            <span>Check <strong>Filters</strong>. Does it match the <strong>Purpose</strong>?</span>
                        </p>
                    </div>

                    {{-- Filters and purpose--}}
                    <div class="rounded-md border border-gray-200 bg-white p-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Filters</h3>
                        <div class="text-base max-w-none text-gray-700 mt-1 mb-3">
                            {!! $filterDescriptionHtml !!}
                        </div>
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Purpose</h3>
                        <div class="mt-1 text-lg text-gray-600">{{ $campaign->purpose?->name ?? '' }}</div>
                    </div>

                    <div class="mt-4 ">
                        <p class="flex items-baseline gap-2 text-base text-gray-600">
                            <span class="check-list-number font-semibold">&#10140;</span>
                            <span>Check <strong>Channel</strong> and <strong>size</strong> of campaign.</span>
                        </p>
                    </div>

                    {{-- Delivery summary --}}
                    <div class="rounded-md border border-gray-200 bg-white p-4">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Channel</h3>
                        <div class="mt-2">{{ strtoupper($campaign->channel) }}</div>

                        <h3 class="mt-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Size</h3>
                        <div class="mt-2">
                            @include('campaigns.partials.audience-summary')
                        </div>
                    </div>
                </div>

                {{-- Message --}}
                <div class="rounded-lg border border-blue-200 bg-blue-100 p-6 shadow-sm space-y-5">

                    @if($callsExpected)
                        <div class="w-fit rounded-full bg-amber-50 text-amber-800 px-3 py-1 text-xs font-semibold">
                            <i class="fas fa-phone mr-1"></i>Phone calls expected — check that a call window is included.
                        </div>
                    @endif

                    {{-- Email then Recipient selector then SMS --}}
                    <div class="space-y-4">

                        {{-- Email panel --}}
                        @if($hasEmail)
                            <div class="rounded-md border border-gray-200 bg-white p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <div class="text-sm font-semibold uppercase tracking-wide text-gray-500">Email</div>
                                    <div class="flex rounded-md border border-gray-300 overflow-hidden text-xs font-medium">
                                        <button type="button" id="admin-email-tab-code"
                                                class="px-3 py-1 bg-white text-gray-600 hover:bg-gray-50 transition-colors"
                                                onclick="setAdminEmailTab('code')">
                                            <i class="fas fa-code mr-1"></i>Code
                                        </button>
                                        <button type="button" id="admin-email-tab-preview"
                                                class="px-3 py-1 bg-gray-700 text-white transition-colors"
                                                onclick="setAdminEmailTab('preview')">
                                            <i class="fas fa-eye mr-1"></i>Preview
                                        </button>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    {{-- From name --}}
                                    <div>
                                        <div class="text-xs text-gray-500 mb-1">From name</div>

                                        <div class="p-2 bg-white rounded border text-sm text-gray-800 flex items-center gap-1.5 whitespace-nowrap">
                                            <span class="check-list-number font-semibold">&#10140;</span>
                                            <span>{{ $campaign->from_name ?: '—' }}</span>
                                        </div>
                                    </div>

                                    {{-- Reply-to --}}
                                    <div>
                                        <div class="text-xs text-gray-500 mb-1">Reply-to</div>

                                        <div class="p-2 bg-white rounded border text-sm text-gray-800 flex items-center gap-1.5 whitespace-nowrap">
                                            <span class="check-list-number font-semibold">&#10140;</span>
                                            <span>{{ $campaign->reply_to_email ?: 'Same as sender address' }}</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Subject --}}
                                <div>
                                    <div class="text-xs text-gray-500 mb-1">Subject</div>
                                    <div class="p-2 bg-white rounded border text-sm text-gray-800 flex items-center gap-1.5 whitespace-nowrap">
                                        <span class="check-list-number font-semibold">&#10140;</span>
                                        <span id="admin-email-subject" class="font-bold">{{ ($firstPreview ? $firstPreview['email_subject'] : $subject) ?: '—' }}</span>
                                    </div>

                                </div>

                                {{-- Code view --}}
                                <div id="admin-email-code-view" class="hidden">
                                    <div class="text-xs text-gray-500 mb-1">Body (raw)</div>
                                    <div id="admin-email-body-code"
                                         class="p-3 bg-white rounded border text-xs whitespace-pre-wrap font-mono min-h-[200px] overflow-auto">
                                        {!! $firstPreview ? e(trim($firstPreview['email_body'])) : $highlightPlaceholders($emailBody) !!}
                                    </div>
                                </div>

                                {{-- Preview view --}}
                                <div id="admin-email-preview-view">
                                    <div class="text-xs text-gray-500 mb-1">Body (rendered)</div>
                                    <iframe id="admin-email-preview-frame"
                                            class="w-full rounded border border-gray-200 bg-white"
                                            style="height: 350px;"
                                            sandbox="allow-same-origin">
                                    </iframe>
                                </div>

                                @if($hasEmail)
                                    <div class="mt-2 rounded-md border border-gray-200 bg-gray-50 p-3 text-sm text-gray-500">

                                        <p>Stay up to date with the Nigerian Red Cross — visit our <span class="underline">member database</span> and log in to your account to see what's on file: your membership status, training history, volunteering record, and any donations you've made. Learn more about our work at <span class="underline">redcrossnigeria.org</span>.</p>
                                        <p>You are receiving this message because you are a registered member or volunteer of the Nigerian Red Cross Society. <span class="underline">Unsubscribe</span>.</p>

                                        <p class=" text-gray-600 mt-2 text-xs"><i class="fas fa-lock mr-1"></i>Automatically appended to every email.</p>
                                    </div>
                                @endif
                            </div>
                        @endif

                        {{-- Recipient selector --}}
                        @if(!empty($samplePreviews))
                            <div class="flex items-center justify-center gap-2 flex-wrap">
                                <span class="check-list-number font-semibold">&#10140;</span>
                                <label class="text-sm font-semibold uppercase tracking-wide text-gray-500">Preview as recipient</label>
                                <select id="adminPreviewRecipient" class="w-40 rounded-md border-gray-300 text-sm">
                                    @foreach($samplePreviews as $id => $p)
                                        <option value="{{ $id }}">{{ $p['label'] }}</option>
                                    @endforeach
                                </select>
                                <div class="flex gap-2">
                                    <button type="button" id="admin-prev-recipient"
                                            class="px-3 py-1 rounded-md border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 text-sm">
                                        <i class="fas fa-arrow-left"></i>
                                    </button>
                                    <button type="button" id="admin-next-recipient"
                                            class="px-3 py-1 rounded-md border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 text-sm">
                                        <i class="fas fa-arrow-right"></i>
                                    </button>
                                </div>
                            </div>
                        @endif

                        {{-- SMS panel --}}
                        @if($hasSms)
                            <div class="rounded-md border border-gray-200 bg-white p-4 space-y-3">
                                <div class="text-sm font-semibold uppercase tracking-wide text-gray-500">SMS</div>
                                <div class="text-xs text-gray-500">
                                    {{ $smsChars }} chars · {{ $smsParts }} part(s) · {{ number_format($willSms ?? 0) }} will receive SMS.
                                </div>
                                <div>
                                    <div class="text-xs text-gray-500 mb-1">Message (rendered)</div>
                                    <div id="admin-sms-body"
                                         class="p-3 bg-white rounded border text-sm whitespace-pre-wrap break-all min-h-[100px]">
                                        {{ ($firstPreview ? trim($firstPreview['sms_body']) : $smsTrimWithFooter) ?: '—' }}
                                    </div>
                                </div>
                                @if($hasSms && $smsTrim !== '')
                                    <div class="text-xs text-gray-400">
                                        <i class="fas fa-info-circle mr-1"></i>Includes the opt-out link every SMS gets at send time (shown with a placeholder token above).
                                    </div>
                                @endif
                                @if($smsChars > 160)
                                    <div class="text-xs text-gray-500">Note: multi-part SMS may arrive split on some phones.</div>
                                @endif
                            </div>
                        @endif
                    </div>

                </div>

            </div>{{-- end left column --}}

            {{-- Right column --}}
            <div class="space-y-6 lg:col-span-5">

                {{-- Campaign info card --}}
                <div class="rounded-lg border border-blue-200 bg-blue-100 p-6 shadow-sm space-y-3">
                    <h2 class="text-xl font-semibold text-gray-800">Campaign Info</h2>
                    <dl class="space-y-2 text-base">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Status</dt>
                            <dd><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-sm font-medium {{ $badge }}">{{ ucfirst($campaign->status) }}</span></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Channel</dt>
                            <dd class="font-medium text-gray-900">{{ strtoupper($campaign->channel) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Purpose</dt>
                            <dd class="font-medium text-gray-900">{{ $campaign->purpose?->name ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">From name</dt>
                            <dd class="font-medium text-gray-900">{{ $campaign->from_name ?: '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Created</dt>
                            <dd class="font-medium text-gray-900">{{ $campaign->created_at?->format('Y-m-d H:i') ?? '—' }}<br><span class="text-sm text-gray-500">{{ $campaign->creator?->full_name ?? '—' }}</span></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Submitted</dt>
                            <dd class="font-medium text-gray-900">{{ $campaign->submitted_at?->format('Y-m-d H:i') ?? '—' }}<br><span class="text-sm text-gray-500">{{ $campaign->submitter?->full_name ?? '—' }}</span></dd>
                        </div>
                        @if($campaign->approved_at)
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Approved</dt>
                            <dd class="font-medium text-gray-900">{{ $campaign->approved_at->format('Y-m-d H:i') }}<br><span class="text-sm text-gray-500">{{ $campaign->approver?->full_name ?? '—' }}</span></dd>
                        </div>
                        @endif
                        @if($campaign->reviewed_at)
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Reviewed</dt>
                            <dd class="font-medium text-gray-900">{{ $campaign->reviewed_at?->format('Y-m-d H:i') ?? '—' }}<br><span class="text-sm text-gray-500">{{ $campaign->reviewed_by_user?->full_name ?? '—' }}</span></dd>
                        </div>
                        @endif
                    </dl>
                </div>

                {{-- Rejection note (if rejected) --}}
                @if ($campaign->status === 'rejected' && !empty($campaign->review_note))
                    <div class="rounded-md border border-red-200 bg-red-50 p-4 text-base text-red-800">
                        <div class="font-semibold mb-1">Rejection note</div>
                        <div>{{ $campaign->review_note }}</div>
                        <div class="mt-2 text-sm text-red-700">
                            Rejected at: {{ $campaign->rejected_at?->format('Y-m-d H:i') ?? '—' }}
                            · {{ $campaign->rejector?->full_name ?? '—' }}
                        </div>
                    </div>
                @endif

                {{-- Throttling --}}
                <div class="rounded-lg border border-blue-200 bg-blue-100 p-6 shadow-sm">
                    <h2 class="text-2xl font-semibold text-gray-800 mb-4">Throttling</h2>
                    @php $t = $throttling ?? []; @endphp

                    <div class="rounded-md border border-gray-200 bg-white p-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Send window</h3>
                                <div class="mt-1 text-lg text-gray-600">{{ $t['send_window_start'] ?? '—' }} – {{ $t['send_window_end'] ?? '—' }}</div>
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Daily cap</h3>
                                <div class="mt-1 text-lg text-gray-600">{{ $t['daily_cap'] ?? '—' }}</div>
                            </div>
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Encourages phone calls</h3>
                                <div class="mt-1 text-lg text-gray-600">{{ !empty($t['encourages_phone_calls']) ? 'Yes' : 'No' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Approve or Reject --}}
                @if ($campaign->status === 'proposed')
                <div class="rounded-lg border border-blue-200 bg-blue-100 p-6 shadow-sm">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Approve or Reject</h2>
                    <div class="space-y-4">
                        <div class="rounded-md border border-gray-200 bg-white p-4">
                            <div class="text-sm font-semibold text-gray-800 mb-4">Final checks</div>
                            <div class="space-y-4">

                                @if($campaign->purpose?->slug === 'fundraising_appeal')
                                    <div class="rounded-md border-2 border-red-400 bg-red-50 p-4 text-red-800">
                                        <div class="flex items-start gap-3">
                                            <i class="fas fa-triangle-exclamation text-2xl text-red-500 mt-0.5"></i>
                                            <div class="text-base font-bold leading-snug">
                                                This is a Fundraising Appeal. Review the message extra carefully —
                                                is the purpose clear, and is the call to action clear?
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <div class="flex gap-3">

                                    <div class="text-sm text-gray-700 pt-1">
                                        <div class="font-medium text-gray-800">Does the message content match the purpose?</div>
                                        <ul class="list-disc pl-5 mt-1 space-y-1">
                                            <li>Is the purpose clear in the first sentence?</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="flex gap-3">

                                    <div class="text-sm text-gray-700 pt-1">
                                        <div class="font-medium text-gray-800">Check wording, tone, and links.</div>
                                        <ul class="list-disc pl-5 mt-1 space-y-1">
                                            <li>Is the tone polite and respectful?</li>
                                            <li>Do placeholders look correct (names, units, etc.)?</li>
                                            <li>Are any links appropriate and on the allowed domain list?</li>
                                            <li>Is there any sensitive or ambiguous wording that could be misunderstood?</li>
                                            @if($callsExpected)<li>Is a call window included?</li>@endif
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @if(Auth::id() === $campaign->submitted_by)
                            <div class="rounded-md border border-amber-200 bg-amber-50 p-4 text-base text-amber-800">
                                You submitted this campaign, so you cannot approve or reject it. Another database administrator needs to review it.
                            </div>
                        @else
                        <div class="rounded-lg border border-gray-200 bg-white p-4">
                            <div class="text-base text-gray-600 mb-3">Approve this campaign to queue it for sending.</div>
                            <form method="POST" action="{{ route('campaigns.admin.approve', $campaign) }}">
                                @csrf
                                <button type="submit"
                                        class="inline-flex items-center px-5 py-2 rounded-md text-sm font-medium bg-green-600 text-white hover:bg-green-700">
                                    <i class="fas fa-check mr-2"></i>Approve
                                </button>
                            </form>
                        </div>
                        <div class="rounded-lg border border-gray-200 bg-white p-4">
                            <div class="text-base text-gray-600 mb-3">Reject and provide feedback to the sender.</div>
                            <form method="POST" action="{{ route('campaigns.admin.reject', $campaign) }}">
                                @csrf
                                <label class="block text-sm font-medium text-gray-700 mb-1">Rejection note <span class="text-red-500">*</span></label>
                                <textarea name="review_note" rows="3" required class="form-input w-full"
                                          placeholder="Explain why this campaign is rejected…">{{ old('review_note') }}</textarea>
                                @error('review_note')
                                <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                                @enderror
                                <div class="mt-2 text-right">
                                    <button type="submit"
                                            class="inline-flex items-center px-4 py-1.5 rounded-md text-sm font-medium bg-red-600 text-white hover:bg-red-700">
                                        <i class="fas fa-times mr-2"></i>Reject
                                    </button>
                                </div>
                            </form>
                        </div>
                        @endif
                    </div>
                </div>
                @endif

            </div>{{-- end right column --}}

        </div>{{-- end grid --}}





    </div>

    <script>
    (function () {
        const previews = @json($samplePreviews ?? []);
        const select   = document.getElementById('adminPreviewRecipient');

        let currentEmailHtml = @json($firstPreview['email_body'] ?? $emailBody ?? '');

        // ── EMAIL CODE / PREVIEW TOGGLE ──────────────────────────
        function setAdminEmailTab(tab) {
            const codeView    = document.getElementById('admin-email-code-view');
            const previewView = document.getElementById('admin-email-preview-view');
            const tabCode     = document.getElementById('admin-email-tab-code');
            const tabPreview  = document.getElementById('admin-email-tab-preview');
            const frame       = document.getElementById('admin-email-preview-frame');

            [tabCode, tabPreview].forEach(btn => {
                if (btn) {
                    btn.classList.remove('bg-gray-700', 'text-white');
                    btn.classList.add('bg-white', 'text-gray-600');
                }
            });

            if (tab === 'preview') {
                codeView.classList.add('hidden');
                previewView.classList.remove('hidden');
                tabPreview.classList.add('bg-gray-700', 'text-white');
                tabPreview.classList.remove('bg-white', 'text-gray-600');
                if (frame) {
                    const doc = frame.contentDocument || frame.contentWindow.document;
                    doc.open();
                    doc.write(currentEmailHtml || '<p style="color:#999;font-family:sans-serif;padding:1rem;">No email body.</p>');
                    doc.close();
                }
            } else {
                codeView.classList.remove('hidden');
                previewView.classList.add('hidden');
                tabCode.classList.add('bg-gray-700', 'text-white');
                tabCode.classList.remove('bg-white', 'text-gray-600');
            }
        }
        window.setAdminEmailTab = setAdminEmailTab;

        // ── RECIPIENT SWAP ────────────────────────────────────────
        function applyPreview(id) {
            const p = previews[id];
            if (!p) return;

            currentEmailHtml = p.email_body || '';

            const subjectEl = document.getElementById('admin-email-subject');
            if (subjectEl) subjectEl.textContent = p.email_subject || '—';

            const codeEl = document.getElementById('admin-email-body-code');
            if (codeEl) codeEl.textContent = (p.email_body || '').trim() || '—';

            const smsEl = document.getElementById('admin-sms-body');
            if (smsEl) smsEl.textContent = (p.sms_body || '').trim() || '—';

            // Refresh iframe if preview tab is active
            const previewView = document.getElementById('admin-email-preview-view');
            if (previewView && !previewView.classList.contains('hidden')) {
                setAdminEmailTab('preview');
            }
        }

        if (select && Object.keys(previews).length) {
            applyPreview(select.value);
            select.addEventListener('change', () => applyPreview(select.value));
        }

        // ── PREV / NEXT NAVIGATION ────────────────────────────────
        const prevBtn = document.getElementById('admin-prev-recipient');
        const nextBtn = document.getElementById('admin-next-recipient');

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
            applyPreview(select.value);
        }

        if (prevBtn) prevBtn.addEventListener('click', () => navigateRecipient('prev'));
        if (nextBtn) nextBtn.addEventListener('click', () => navigateRecipient('next'));

        // ── DEFAULT TO PREVIEW TAB ────────────────────────────────
        document.addEventListener('DOMContentLoaded', function () {
            setAdminEmailTab('preview');
        });
    })();
    </script>
</x-layouts.admin>

