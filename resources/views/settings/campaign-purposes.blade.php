<x-layouts.admin title="Campaign Purposes">
    <x-slot name="pageHeader">
        <i class="fas fa-bullhorn mr-3"></i> Campaign Purposes
    </x-slot>
    <x-audit-notice />

    <x-slot name="subHeader">Settings — Edit default templates</x-slot>

    <div class="container mx-auto px-4 py-8 max-w-5xl">

        @if(session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                <p class="font-bold">Success</p>
                <p>{{ session('success') }}</p>
            </div>
        @endif

        <p class="text-sm text-gray-500 mb-6">
            Edit the default templates for each campaign purpose. These are pre-filled when a user selects a purpose in the campaign wizard.
            Placeholders like <code class="bg-gray-100 px-1 rounded text-xs">@{{user.first_name}}</code> will be substituted at send time.
        </p>

        <form method="POST" action="{{ route('admin.settings.campaign-purposes.update') }}" class="space-y-8">
            @csrf

            @foreach($purposes as $index => $purpose)
                <input type="hidden" name="purposes[{{ $index }}][id]" value="{{ $purpose->id }}">

                <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                    {{-- Header --}}
                    <div class="bg-gray-50 border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">{{ $purpose->name }}</h3>
                            <p class="text-xs text-gray-400 mt-0.5">slug: <code>{{ $purpose->slug }}</code></p>
                        </div>
                        <div class="flex items-center gap-4">
                            {{-- Sort order --}}
                            <div class="flex items-center gap-2">
                                <label class="text-sm text-gray-500 whitespace-nowrap">Sort order</label>
                                <input type="number"
                                       name="purposes[{{ $index }}][sort_order]"
                                       value="{{ $purpose->sort_order }}"
                                       min="0" max="9999"
                                       class="w-20 rounded-md border border-gray-300 px-2 py-1 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                            </div>
                        </div>
                    </div>

                    {{-- Fields --}}
                    <div class="px-6 py-5 space-y-4">

                        {{-- Call window guidance flag --}}
                        <div class="flex items-center gap-2">
                            <input type="hidden" name="purposes[{{ $index }}][default_call_window]" value="0">
                            <input type="checkbox"
                                   id="purpose-call-window-{{ $index }}"
                                   name="purposes[{{ $index }}][default_call_window]"
                                   value="1"
                                   {{ $purpose->default_call_window ? 'checked' : '' }}
                                   class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <label for="purpose-call-window-{{ $index }}" class="text-sm font-medium text-gray-700">
                                Normally has a call window
                            </label>
                            <span class="text-xs text-gray-400">— guides branches to add a call window for this purpose</span>
                        </div>

                        {{-- Channel + Subject side by side --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Default channel</label>
                                <select name="purposes[{{ $index }}][default_channel]"
                                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                    @foreach([
                                        'email_fallback_sms' => 'Email (fallback to SMS)',
                                        'email'              => 'Email only',
                                        'sms'                => 'SMS only',
                                        'both'               => 'Email and SMS (both)',
                                    ] as $k => $v)
                                        <option value="{{ $k }}" {{ $purpose->default_channel === $k ? 'selected' : '' }}>
                                            {{ $v }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Default email subject</label>
                                <input type="text"
                                       name="purposes[{{ $index }}][default_subject]"
                                       value="{{ old("purposes.{$index}.default_subject", $purpose->default_subject) }}"
                                       class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                       placeholder="Email subject line…">
                                @error("purposes.{$index}.default_subject")
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Email body + SMS body side by side --}}
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-sm font-medium text-gray-700">
                                        Default email body
                                        <span class="text-xs text-gray-400 font-normal ml-1">(HTML)</span>
                                    </label>
                                    <div class="flex rounded-md border border-gray-300 overflow-hidden text-xs font-medium">
                                        <button type="button"
                                                class="purposes-tab-code-{{ $index }} px-3 py-1 bg-indigo-600 text-white transition-colors"
                                                onclick="setPurposeTab({{ $index }}, 'code')">
                                            <i class="fas fa-code mr-1"></i>Code
                                        </button>
                                        <button type="button"
                                                class="purposes-tab-preview-{{ $index }} px-3 py-1 bg-white text-gray-600 hover:bg-gray-50 transition-colors"
                                                onclick="setPurposeTab({{ $index }}, 'preview')">
                                            <i class="fas fa-eye mr-1"></i>Preview
                                        </button>
                                    </div>
                                </div>

                                <div id="purpose-code-{{ $index }}">
                                    <textarea id="purpose-email-body-{{ $index }}"
                                              name="purposes[{{ $index }}][default_email_body]"
                                              rows="10"
                                              class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm font-mono shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">{{ old("purposes.{$index}.default_email_body", $purpose->default_email_body) }}</textarea>
                                </div>

                                <div id="purpose-preview-{{ $index }}" class="hidden">
                                    <iframe id="purpose-frame-{{ $index }}"
                                            class="w-full rounded-md border border-gray-300 bg-white"
                                            style="height:360px;"
                                            sandbox="allow-same-origin">
                                    </iframe>
                                    <p class="text-xs text-gray-400 mt-1">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Rendered preview — placeholders will be substituted at send time.
                                    </p>
                                </div>

                                @error("purposes.{$index}.default_email_body")
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Default SMS body
                                    <span class="text-xs text-gray-400 font-normal ml-1">(plain text, max 800 chars)</span>
                                </label>
                                <textarea name="purposes[{{ $index }}][default_sms_body]"
                                          rows="10"
                                          maxlength="800"
                                          class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">{{ old("purposes.{$index}.default_sms_body", $purpose->default_sms_body) }}</textarea>
                                @error("purposes.{$index}.default_sms_body")
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Available placeholders hint --}}
                        <div class="text-xs text-gray-400">
                            Available placeholders:
                            @foreach([
                                'user.first_name', 'user.last_name', 'user.full_name',
                                'user.branch', 'user.division', 'user.current_membership',
                                'user.membership_expiry', 'user.donations_summary',
                                'user.db_code_short', 'user.lifecycle',
                            ] as $ph)
                                <code class="bg-gray-100 px-1 rounded mx-0.5">{{ '{{' . $ph . '}' . '}' }}</code>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- Save button --}}
            <div class="flex justify-end pt-4">
                <button type="submit"
                        class="inline-flex items-center px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-md shadow transition">
                    <i class="fas fa-save mr-2"></i> Save All Purposes
                </button>
            </div>

        </form>

        <div class="mt-6">
            <a href="{{ route('admin.settings.index') }}" class="btn-backlink">← Back to Settings</a>
        </div>

    </div>

    <script>
        function setPurposeTab(index, tab) {
            const codeView    = document.getElementById('purpose-code-' + index);
            const previewView = document.getElementById('purpose-preview-' + index);
            const tabCode     = document.querySelector('.purposes-tab-code-' + index);
            const tabPreview  = document.querySelector('.purposes-tab-preview-' + index);
            const textarea    = document.getElementById('purpose-email-body-' + index);
            const frame       = document.getElementById('purpose-frame-' + index);

            if (tab === 'preview') {
                const doc = frame.contentDocument || frame.contentWindow.document;
                doc.open();
                doc.write(textarea.value || '<p style="color:#999;font-family:sans-serif;padding:20px;">No content yet.</p>');
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
        }
    </script>
</x-layouts.admin>
