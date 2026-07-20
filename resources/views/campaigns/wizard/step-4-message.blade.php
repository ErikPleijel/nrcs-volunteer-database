<x-campaigns.wizard-layout :campaign="$campaign" :step="4"
                           title="Campaign wizard"
                           subtitle="Write the message. Keep it respectful and clear."
>
    @php
        $filter     = $campaign->filter_json ?? [];
        $content    = data_get($filter, '_content', []);
        $throttling = data_get($filter, '_throttling', []);

        $lockedFromEmail = config('campaigns.mail_from_email', $campaign->from_email ?? 'info@nrcs.org');

        $channel   = $campaign->channel ?? 'both';
        $audience  = $campaign->audience_type ?? null;
        $lifecycle = $campaign->lifecycle_status ?? null;

        $encouragesCalls = (bool) data_get($throttling, 'encourages_phone_calls', false);

        $showEmail = in_array($channel, ['email', 'both', 'email_fallback_sms'], true);
        $showSms   = in_array($channel, ['sms', 'both', 'email_fallback_sms'], true);
    @endphp

    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <style>
        .ql-editor p {
            margin-bottom: 0.75em;
        }
        .ql-editor p:last-child {
            margin-bottom: 0;
        }
    </style>

    <div class="space-y-6">
        {{-- Form --}}
        <form method="POST" action="{{ route('campaigns.wizard.step4.post', $campaign) }}" class="space-y-6">
            @csrf

            <div class="wizard-card">
                @if ($errors->has('email_body') || $errors->has('sms_body'))
                <p class="mb-4 text-lg text-red-600 font-bold">Please correct the errors below.</p>
                @endif

                {{-- SIDE BY SIDE VIEW --}}
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">

                    {{-- Email Column --}}
                    @if($showEmail)
                        <div class="lg:col-span-7" data-panel="email">
                            <h3 class="text-lg font-bold text-gray-900 border-b pb-2">Email Content</h3>
                            <div class="mb-6 pb-6 border-b border-gray-100 space-y-3">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="wizard-label">From name</label>
                                        <input name="from_name" value="{{ old('from_name', $campaign->from_name ?? $defaultFromName) }}" class="wizard-input mt-2" placeholder="NRCS – Your Branch" required>
                                        @error('from_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="wizard-label">Reply-to email</label>
                                        <input name="reply_to_email" value="{{ old('reply_to_email', $campaign->reply_to_email ?? $defaultReplyToEmail) }}" class="wizard-input mt-2" placeholder="your.branch@nrcs.org" required>
                                        @error('reply_to_email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>
                                <p class="text-sm text-gray-500" title="This address is managed by the system">
                                    <span class="font-medium text-gray-600">From email:</span> {{ $lockedFromEmail }}
                                </p>
                            </div>
                            <div>
                                <label class="wizard-label">Email subject</label>
                                <input name="subject" value="{{ old('subject', data_get($content, 'email_subject', '')) }}" class="wizard-input mt-2">
                                @error('subject') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                @error('unresolved_email_subject') <p class="mt-1 text-xs text-amber-700 font-semibold"><i class="fas fa-triangle-exclamation mr-1"></i>{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <div class="mt-6 flex items-center justify-between mb-1">
                                    <label class="wizard-label mb-0">Email body</label>
                                    <div class="flex rounded-md border border-gray-300 overflow-hidden text-xs font-medium">
                                        <button type="button" id="email-tab-wysiwyg"
                                                class="px-3 py-1 bg-indigo-600 text-white transition-colors"
                                                onclick="setEmailTab('wysiwyg')">
                                            <i class="fas fa-pen mr-1"></i>Write
                                        </button>
                                        <button type="button" id="email-tab-code"
                                                class="px-3 py-1 bg-white text-gray-600 hover:bg-gray-50 transition-colors"
                                                onclick="setEmailTab('code')">
                                            <i class="fas fa-code mr-1"></i>Code
                                        </button>
                                        <button type="button" id="email-tab-preview"
                                                class="px-3 py-1 bg-white text-gray-600 hover:bg-gray-50 transition-colors"
                                                onclick="setEmailTab('preview')">
                                            <i class="fas fa-eye mr-1"></i>Preview
                                        </button>
                                    </div>
                                </div>

                                {{-- Code view --}}
                                <div id="email-code-view" class="hidden mt-1">
                                    <textarea id="email_body" name="email_body" rows="16"
                                              class="wizard-input mt-1 font-mono text-xs">{{ old('email_body', data_get($content, 'email_body', '')) }}</textarea>
                                </div>

                                {{-- Preview view --}}
                                <div id="email-preview-view" class="hidden mt-1">
                                    <iframe id="email-preview-frame"
                                            class="w-full rounded-md border border-gray-300 bg-white"
                                            style="height:400px;"
                                            sandbox="allow-same-origin">
                                    </iframe>
                                    <p class="wizard-hint">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Placeholders like <code class="bg-gray-100 px-1 rounded">@{{user.first_name}}</code> will be substituted with real data at send time.
                                    </p>
                                </div>



                                {{-- WYSIWYG view (default) --}}
                                <div id="email-wysiwyg-view" class="mt-1">
                                    <div class="mt-1 rounded-md overflow-hidden border border-gray-300">
                                        <div id="email-quill-editor"
                                             style="min-height: 260px; background: white; font-size: 0.9rem;"></div>
                                    </div>

                                </div>

                                @if($showEmail)
                                    <div class="mt-2 rounded-md border border-gray-200 bg-gray-50 p-3 text-sm text-gray-500">

                                        <p>Stay up to date with the Nigerian Red Cross — visit our <span class="underline">member database</span> and log in to your account to see what's on file: your membership status, training history, volunteering record, and any donations you've made. Learn more about our work at <span class="underline">redcrossnigeria.org</span>.</p>
                                        <p>You are receiving this message because you are a registered member or volunteer of the Nigerian Red Cross Society. <span class="underline">Unsubscribe</span>.</p>

                                        <p class=" text-gray-600 mt-2 text-xs"><i class="fas fa-lock mr-1"></i>Automatically appended to every email.</p>
                                    </div>
                                @endif

                                <div id="emailBodyWarning" class="hidden mt-2 rounded-md bg-amber-50 border border-amber-200 p-3 text-xs text-amber-900"></div>

                                <p class="wizard-hint mt-1">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Switch to &lt;/> Code for full HTML newsletters.
                                </p>

                                <div class="mt-2 flex items-center gap-2">
                                    <select class="wizard-input flex-1 py-1 text-sm" data-placeholder-select data-target-textarea="email_body">
                                        <option value="">Insert placeholder…</option>
                                        @foreach(($placeholders ?? []) as $token => $label)
                                            <option value="{{ $token }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                            </div>
                        </div>
                    @endif

                    {{-- SMS Column --}}
                    @if($showSms)
                        <div class="lg:col-span-5 " data-panel="sms">
                            <h3 class="text-lg font-bold text-gray-900 border-b pb-2">SMS Content</h3>
                            <div class="flex justify-between items-end">

                                <div class="text-base text-gray-700"><span id="smsCharCount">0</span> chars • <span class="text-green-700 font-semibold" id="smsPartsHint">~1 SMS</span></div>
                            </div>
                            <textarea id="sms_body" name="sms_body" rows="12" class="wizard-input mt-2">{{ old('sms_body', data_get($content, 'sms_body', '')) }}</textarea>
                            @if($showSms)
                                <div class="rounded-md border border-gray-200 bg-gray-50 p-3 text-xs text-gray-500" id="sms-footer-preview">
                                    <p class="break-all">To stop: {{ config('app.url') }}/u/<span class="not-italic text-gray-600 bg-gray-200 rounded px-0.5" title="Placeholder only — the real link uses a unique 32-character token per recipient">XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX</span>/sms</p>

                                    <p class="mt-3  text-gray-600 mb-1"><i class="fas fa-lock mr-1"></i>Opt out automatically appended.</p>

                                </div>
                            @endif

                            @error('sms_body') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            <div id="smsWarnings" class="hidden rounded-md bg-amber-50 p-3 text-xs text-amber-900"></div>
                            <div id="smsBodyWarning" class="hidden mt-2 rounded-md bg-amber-50 border border-amber-200 p-3 text-xs text-amber-900"></div>



                            <div class="mt-2 flex items-center gap-2">
                                <select class="wizard-input flex-1 py-1 text-sm" data-placeholder-select data-target-textarea="sms_body">
                                    <option value="">Insert placeholder…</option>
                                    @foreach(($placeholders ?? []) as $token => $label)
                                        <option value="{{ $token }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            @if($showSms)
                                <div class="mt-2 flex items-center gap-2">
                                    <!-- Removed mt-0.5 -->
                                    <input type="checkbox" id="insert-login-sms"
                                           class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                    <label for="insert-login-sms" class="wizard-hint cursor-pointer">
                                        Encourage recipient to visit the NRCS website
                                        <span class="">(inserts a line at the end of the SMS body)</span>
                                    </label>
                                </div>
                            @endif


                        </div>
                    @endif
                </div>

                @error('email_body')
                <p class="mt-1 text-lg text-red-600">{{ $message }}</p>
                @enderror
                @error('unresolved_email_body') <p class="mt-1 text-xs text-amber-700 font-semibold"><i class="fas fa-triangle-exclamation mr-1"></i>{{ $message }}</p> @enderror
                @error('unresolved_sms_body') <p class="mt-1 text-xs text-amber-700 font-semibold"><i class="fas fa-triangle-exclamation mr-1"></i>{{ $message }}</p> @enderror

                {{-- Actions --}}
                <div class="flex justify-between mt-10 pt-6 border-t border-gray-100">
                    <button type="submit" name="_direction" value="back" formnovalidate class="wizard-btn-back">
                        <i class="fas fa-arrow-left mr-2 text-xs"></i> Back
                    </button>
                    <button class="wizard-btn-continue">
                        Continue <i class="fas fa-arrow-right ml-2 text-xs"></i>
                    </button>
                </div>
            </div>
        </form>

        {{-- Quick Checks at the bottom --}}
        <div class="rounded-lg border border-gray-200 bg-gray-50 p-6">
            <div class="text-sm font-semibold text-gray-900 mb-4">Quick checks</div>
            <ul class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-700">
                <li class="flex gap-2"><span class="text-green-600 font-bold">✓</span><span>Say who it's from.</span></li>
                <li class="flex gap-2"><span class="text-green-600 font-bold">✓</span><span>One clear action.</span></li>
                <li class="flex gap-2"><span class="text-green-600 font-bold">✓</span><span>Respectful tone.</span></li>
                <li class="flex gap-2"><span class="text-amber-600 font-bold">!</span><span>Only use allowed links.</span></li>
                <li class="flex gap-2"><span class="text-amber-600 font-bold">!</span><span>No passwords or sensitive info.</span></li>
                @if($encouragesCalls)
                    <li class="flex gap-2"><span class="text-amber-600 font-bold">!</span><span>Include call hours.</span></li>
                @endif
            </ul>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {

                // ── EMAIL WYSIWYG / CODE / PREVIEW TOGGLE ────────────────
                let quill = null;
                let quillInitialised = false;

                function syncQuillToTextarea() {
                    if (quill) {
                        const ta = document.getElementById('email_body');
                        if (ta) ta.value = quill.root.innerHTML === '<p><br></p>' ? '' : quill.root.innerHTML;
                    }
                }

                function syncTextareaToQuill() {
                    if (quill) {
                        const ta = document.getElementById('email_body');
                        if (ta) quill.clipboard.dangerouslyPasteHTML(ta.value || '');
                    }
                }

                function formatHtml(html) {
                    if (!html) return '';
                    let formatted = html
                        .replace(/(<\/?(p|div|h[1-6]|ul|ol|li|br|hr|blockquote|table|tr|td|th|thead|tbody|tfoot)[^>]*>)/gi, '\n$1')
                        .replace(/^\n/, '')
                        .replace(/\n{3,}/g, '\n\n');
                    return formatted.trim();
                }

                function setEmailTab(tab) {
                    const wysiwygView = document.getElementById('email-wysiwyg-view');
                    const codeView    = document.getElementById('email-code-view');
                    const previewView = document.getElementById('email-preview-view');
                    const tabWysiwyg  = document.getElementById('email-tab-wysiwyg');
                    const tabCode     = document.getElementById('email-tab-code');
                    const tabPreview  = document.getElementById('email-tab-preview');
                    const textarea    = document.getElementById('email_body');
                    const frame       = document.getElementById('email-preview-frame');

                    // Reset all tab styles
                    [tabWysiwyg, tabCode, tabPreview].forEach(btn => {
                        if (btn) {
                            btn.classList.remove('bg-indigo-600', 'text-white');
                            btn.classList.add('bg-white', 'text-gray-600');
                        }
                    });

                    // Hide all views
                    [wysiwygView, codeView, previewView].forEach(v => {
                        if (v) v.classList.add('hidden');
                    });

                    if (tab === 'wysiwyg') {
                        if (!quillInitialised) {
                            quill = new Quill('#email-quill-editor', {
                                theme: 'snow',
                                modules: {
                                    toolbar: [
                                        ['bold', 'italic', 'underline'],
                                        [{ list: 'ordered' }, { list: 'bullet' }],
                                        ['link', 'clean']
                                    ]
                                }
                            });
                            if (textarea && textarea.value.trim()) {
                                quill.clipboard.dangerouslyPasteHTML(textarea.value);
                            }
                            quill.on('text-change', function () {
                                syncQuillToTextarea();
                                updateWarning(textarea, document.getElementById('emailBodyWarning'));
                            });
                            quillInitialised = true;
                        } else {
                            syncTextareaToQuill();
                        }
                        wysiwygView.classList.remove('hidden');
                        tabWysiwyg.classList.add('bg-indigo-600', 'text-white');
                        tabWysiwyg.classList.remove('bg-white', 'text-gray-600');

                    } else if (tab === 'code') {
                        syncQuillToTextarea();
                        // Format the HTML for readability in code view
                        const ta = document.getElementById('email_body');
                        if (ta && ta.value) {
                            ta.value = formatHtml(ta.value);
                        }
                        codeView.classList.remove('hidden');
                        tabCode.classList.add('bg-indigo-600', 'text-white');
                        tabCode.classList.remove('bg-white', 'text-gray-600');

                    } else if (tab === 'preview') {
                        syncQuillToTextarea();
                        const doc = frame.contentDocument || frame.contentWindow.document;
                        doc.open();
                        doc.write(textarea.value);
                        doc.close();
                        previewView.classList.remove('hidden');
                        tabPreview.classList.add('bg-indigo-600', 'text-white');
                        tabPreview.classList.remove('bg-white', 'text-gray-600');
                    }
                }

                window.setEmailTab = setEmailTab;

                // ── INSERT PLACEHOLDER AT CURSOR ──────────────────────────
                function insertAtCursor(field, value) {
                    if (field.selectionStart || field.selectionStart === 0) {
                        const startPos = field.selectionStart;
                        const endPos   = field.selectionEnd;
                        field.value    = field.value.substring(0, startPos)
                                       + value
                                       + field.value.substring(endPos);
                        const newPos   = startPos + value.length;
                        field.selectionStart = newPos;
                        field.selectionEnd   = newPos;
                        field.focus();
                    } else {
                        field.value += value;
                        field.focus();
                    }
                }

                document.querySelectorAll('[data-placeholder-select]').forEach(select => {
                    select.addEventListener('change', function () {
                        const token      = this.value;
                        const targetName = this.dataset.targetTextarea;
                        if (!token || !targetName) return;

                        if (targetName === 'email_body' && quill) {
                            const range = quill.getSelection(true);
                            quill.insertText(range ? range.index : quill.getLength(), token);
                            syncQuillToTextarea();
                        } else {
                            const ta = document.querySelector(`textarea[name="${targetName}"]`);
                            if (ta) insertAtCursor(ta, token);
                        }
                        this.value = '';
                    });
                });

                // Sync Quill → textarea on form submit so the hidden textarea value is sent
                const wizardForm = document.querySelector('form[action*="step4"]');
                if (wizardForm) {
                    wizardForm.addEventListener('submit', function () {
                        syncQuillToTextarea();
                    });
                }

                // ── UNRESOLVED TAG WARNING ────────────────────────────────
                // Finds any remaining placeholder tags in the text that were not
                // substituted — warns the user before submit.

                // Known valid placeholder keys — these will be substituted at send time
                const knownPlaceholders = new Set([
                    'user.first_name', 'user.last_name', 'user.full_name',
                    'user.email', 'user.phone', 'user.branch', 'user.division',
                    'user.red_cross_unit', 'user.db_code_short', 'user.db_code_long',
                    'user.lifecycle', 'user.donations_summary', 'user.current_membership',
                    'user.membership_expiry', 'user.time_since_last_first_aid',
                    'app.url',
                ]);

                function findUnresolvedTags(text) {
                    const matches = [];

                    // Check for placeholder-style tags — flag only unknown ones
                    const re = /@?\{\{\s*([^}]*?)\s*\}\}/g;
                    let m;
                    while ((m = re.exec(text)) !== null) {
                        const key = m[1].trim();
                        if (!knownPlaceholders.has(key)) {
                            matches.push(m[0]);
                        }
                    }

                    // Also catch lone stray braces that aren't part of any placeholder pair
                    const stripped = text.replace(/@?\{\{[^}]*\}\}/g, '');
                    const loneRe = /\{(?!\{)|(?<!\})\}/g;
                    while ((m = loneRe.exec(stripped)) !== null) {
                        matches.push(m[0]);
                    }

                    return matches;
                }

                function updateWarning(textarea, warningEl) {
                    if (!textarea || !warningEl) return;
                    const unresolved = findUnresolvedTags(textarea.value);
                    if (unresolved.length > 0) {
                        const unique = [...new Set(unresolved)];
                        warningEl.innerHTML = '<i class="fas fa-triangle-exclamation mr-1"></i>'
                            + '<strong>Unresolved placeholders:</strong> '
                            + unique.map(t => `<code class="bg-amber-100 px-1 rounded">${t}</code>`).join(', ')
                            + ' — these will appear as-is in the sent message.';
                        warningEl.classList.remove('hidden');
                    } else {
                        warningEl.classList.add('hidden');
                        warningEl.innerHTML = '';
                    }
                }

                const emailBodyTa  = document.getElementById('email_body');
                const emailWarn    = document.getElementById('emailBodyWarning');
                const smsBodyTa    = document.getElementById('sms_body');
                const smsWarn      = document.getElementById('smsBodyWarning');

                if (emailBodyTa) {
                    emailBodyTa.addEventListener('input', () => updateWarning(emailBodyTa, emailWarn));
                    updateWarning(emailBodyTa, emailWarn); // run on load
                }
                if (smsBodyTa) {
                    smsBodyTa.addEventListener('input', () => updateWarning(smsBodyTa, smsWarn));
                    updateWarning(smsBodyTa, smsWarn); // run on load
                }

                // ── SMS OPT-OUT FOOTER (appended to every SMS at send time) ──
                // Placeholder token is a fixed 32 X's, matching the real
                // id_check_token length (Str::random(32)) exactly, so the
                // character count below matches what actually gets sent.
                const appUrl = "{{ config('app.url') }}";
                const smsFooterSuffix = `\nTo stop: ${appUrl}/u/XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX/sms`;

                // ── SMS CHAR COUNT (existing logic preserved, now includes the footer) ─
                const smsCharCount = document.getElementById('smsCharCount');
                const smsPartsHint = document.getElementById('smsPartsHint');

                if (smsBodyTa && smsCharCount) {
                    function updateSmsCount() {
                        const len   = smsBodyTa.value.length + smsFooterSuffix.length;
                        const parts = len <= 160 ? 1 : Math.ceil(len / 153);
                        smsCharCount.textContent = len;
                        if (smsPartsHint) {
                            smsPartsHint.textContent = `~${parts} SMS`;
                        }
                    }
                    smsBodyTa.addEventListener('input', updateSmsCount);
                    updateSmsCount();
                }

                // ── LOGIN SNIPPET INSERTION ───────────────────────────
                const smsLoginSnippet = `\nVisit the NRCS online: ${appUrl}`;

                const smsLoginCheckbox = document.getElementById('insert-login-sms');

                function applySmsLoginSnippet(checked) {
                    const ta = document.getElementById('sms_body');
                    if (!ta) return;
                    ta.value = ta.value.replace(smsLoginSnippet, '');
                    if (checked) {
                        ta.value = ta.value + smsLoginSnippet;
                    }
                    updateSmsCount();
                    updateWarning(ta, document.getElementById('smsBodyWarning'));
                }

                if (smsLoginCheckbox) {
                    smsLoginCheckbox.addEventListener('change', function () {
                        applySmsLoginSnippet(this.checked);
                    });
                }

                // ── SMS FOOTER CHARACTER COUNT (informational, mirrors the total above) ─
                const smsFooterCharCountEl = document.getElementById('sms-footer-char-count');
                if (smsFooterCharCountEl) {
                    smsFooterCharCountEl.textContent = smsFooterSuffix.length;
                }

                // Default to WYSIWYG tab on load
                setEmailTab('wysiwyg');

            });
        </script>
    @endpush

</x-campaigns.wizard-layout>
