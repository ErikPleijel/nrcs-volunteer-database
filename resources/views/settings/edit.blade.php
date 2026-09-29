<x-layouts.admin :title="'National Database Settings'">

    <x-slot name="pageHeader">
        <i class="fas fa-database mr-2 mb-6"></i>National Database Settings
    </x-slot>

    <div class="container mx-auto py-8 px-4">

        <div class="mb-12 bg-white shadow-lg rounded-lg overflow-hidden border-2 border-indigo-200">
            <div class="px-6 py-4 bg-indigo-50 border-b border-indigo-100">

                <p class="text-sm text-indigo-700 mt-1">
                    Raw key-value system settings that affect the whole organisation. Change with care.
                </p>
            </div>

        </div>



        @if (session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p class="font-bold">Success</p>
                <p>{{ session('success') }}</p>
            </div>
        @endif

        <form action="{{ route('admin.settings.update') }}" method="POST">
            @csrf
            <div class="space-y-12">
                @foreach($settings as $group => $groupSettings)
                    <div class="bg-white shadow-md rounded-lg overflow-hidden">
                        <div class="px-4 py-5 sm:px-6 bg-gray-50">
                            <h3 class="text-lg leading-6 font-medium text-gray-900">{{ ucfirst($group) }}</h3>
                        </div>
                        <div class="border-t border-gray-200">
                            <dl>
                                @foreach($groupSettings as $setting)
                                    <div class="px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 @if(!$loop->last) border-b border-gray-200 @endif">
                                        <dt class="text-sm font-medium text-gray-500">
                                            <label for="setting-{{ $setting->key }}">{{ $setting->label }}</label>
                                            @if($setting->description)
                                                <p class="text-xs text-gray-400 mt-1">{{ $setting->description }}</p>
                                            @endif
                                        </dt>
                                        <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                            @if($setting->type === 'html')
                                                {{-- Raw HTML with a Code / Preview toggle. The textarea is what's
                                                     submitted; the preview shows what the footer will render. --}}
                                                @php
                                                    $previewClass = match ($setting->key) {
                                                        'site.footer_contact_html'     => 'footer-contact text-base text-gray-700',
                                                        'site.footer_quick_links_html' => 'footer-links text-sm',
                                                        default                        => 'text-sm text-gray-700',
                                                    };
                                                @endphp
                                                <div class="max-w-lg" data-html-setting>
                                                    <div class="flex justify-end mb-1">
                                                        <div class="flex rounded-md border border-gray-300 overflow-hidden text-xs font-medium">
                                                            <button type="button" data-html-tab="code"
                                                                    class="px-3 py-1 bg-indigo-600 text-white transition-colors">
                                                                <i class="fas fa-code mr-1"></i>Code
                                                            </button>
                                                            <button type="button" data-html-tab="preview"
                                                                    class="px-3 py-1 bg-white text-gray-600 hover:bg-gray-50 transition-colors">
                                                                <i class="fas fa-eye mr-1"></i>Preview
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <textarea name="settings[{{ $setting->key }}]" id="setting-{{ $setting->key }}" rows="8" data-html-code
                                                              class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full font-mono text-xs border-2 border-gray-300 rounded-md">{{ $setting->value }}</textarea>
                                                    <div data-html-preview class="hidden rounded-md border-2 border-gray-300 bg-white p-4">
                                                        <div class="footer-html {{ $previewClass }}"></div>
                                                    </div>
                                                    <p class="text-xs text-gray-400 mt-1">
                                                        Anything else (other tags, class, style, scripts) is removed when you save.
                                                    </p>
                                                </div>
                                            @elseif($setting->type === 'string' && in_array($setting->key, ['social.share_description', 'site.hq_address']))
                                                <textarea name="settings[{{ $setting->key }}]" id="setting-{{ $setting->key }}" rows="3" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full max-w-lg sm:text-sm border-2 border-gray-300 rounded-md">{{ $setting->value }}</textarea>
                                            @elseif($setting->type === 'string')
                                                <input type="text" name="settings[{{ $setting->key }}]" id="setting-{{ $setting->key }}" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full max-w-lg sm:text-sm border-2 border-gray-300 rounded-md" value="{{ $setting->value }}">
                                            @elseif($setting->type === 'int')
                                                <input type="number" name="settings[{{ $setting->key }}]" id="setting-{{ $setting->key }}" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-2 border-gray-300  rounded-md @if($setting->key === 'membership.dormant_after_months') max-w-xs @else max-w-lg @endif" value="{{ $setting->value }}">
                                            @elseif($setting->type === 'bool')
                                                <select name="settings[{{ $setting->key }}]" id="setting-{{ $setting->key }}" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full max-w-xs sm:text-sm border-2 border-gray-300  rounded-md">
                                                    <option value="1" @if($setting->value) selected @endif>True</option>
                                                    <option value="0" @if(!$setting->value) selected @endif>False</option>
                                                </select>
                                            @else
                                                <textarea name="settings[{{ $setting->key }}]" id="setting-{{ $setting->key }}" rows="4" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-2 border-gray-300 rounded-md">{{ $setting->value }}</textarea>
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-8 flex justify-end">
                <button type="submit" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Save Settings
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // ── HTML SETTINGS: CODE / PREVIEW TOGGLE ──────────────────
                // The preview is built node by node from a DOMParser document
                // (which never runs scripts), keeping only what
                // App\Services\SettingHtmlSanitizer keeps — so it shows what
                // will actually be saved, and nothing typed here can execute.
                const ALLOWED   = ['strong', 'b', 'em', 'br', 'p', 'ul', 'li', 'a'];
                const UNWRAPPED = @json(\App\Services\SettingHtmlSanitizer::UNWRAPPED);
                const LINK_OK   = /^(https?:|mailto:)/i;

                function copyAllowed(source, target) {
                    source.childNodes.forEach(node => {
                        if (node.nodeType === Node.TEXT_NODE) {
                            target.appendChild(document.createTextNode(node.textContent));
                            return;
                        }
                        if (node.nodeType !== Node.ELEMENT_NODE) return;

                        const tag = node.tagName.toLowerCase();

                        if (UNWRAPPED.includes(tag)) {
                            copyAllowed(node, target);
                        } else if (ALLOWED.includes(tag)) {
                            const el = document.createElement(tag);
                            if (tag === 'a') {
                                const href = (node.getAttribute('href') || '').trim();
                                if (LINK_OK.test(href)) el.setAttribute('href', href);
                                ['target', 'rel'].forEach(attr => {
                                    if (node.hasAttribute(attr)) el.setAttribute(attr, node.getAttribute(attr));
                                });
                            }
                            copyAllowed(node, el);
                            target.appendChild(el);
                        }
                        // Anything else is dropped with its content.
                    });
                }

                function renderPreview(textarea, preview) {
                    const doc = new DOMParser().parseFromString(textarea.value, 'text/html');
                    preview.replaceChildren();
                    copyAllowed(doc.body, preview);
                }

                document.querySelectorAll('[data-html-setting]').forEach(wrap => {
                    const textarea    = wrap.querySelector('[data-html-code]');
                    const previewBox  = wrap.querySelector('[data-html-preview]');
                    const preview     = previewBox.firstElementChild;
                    const tabs        = wrap.querySelectorAll('[data-html-tab]');

                    function setTab(tab) {
                        tabs.forEach(btn => {
                            const active = btn.dataset.htmlTab === tab;
                            btn.classList.toggle('bg-indigo-600', active);
                            btn.classList.toggle('text-white', active);
                            btn.classList.toggle('bg-white', !active);
                            btn.classList.toggle('text-gray-600', !active);
                        });

                        if (tab === 'preview') renderPreview(textarea, preview);
                        textarea.classList.toggle('hidden', tab !== 'code');
                        previewBox.classList.toggle('hidden', tab !== 'preview');
                    }

                    tabs.forEach(btn => btn.addEventListener('click', () => setTab(btn.dataset.htmlTab)));
                });
            });
        </script>
    @endpush
</x-layouts.admin>
