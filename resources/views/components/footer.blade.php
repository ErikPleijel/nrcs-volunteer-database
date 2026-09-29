{{-- Contact Information and Quick Links content is admin-edited HTML
     (settings site.footer_*_html). Sanitized when saved and again here;
     it carries no classes, so .footer-html styles it. The settings page's
     preview reuses these classes. --}}
@php $footerSanitizer = app(\App\Services\SettingHtmlSanitizer::class); @endphp

<style>
    .footer-html > * + * { margin-top: 1rem; }
    .footer-html p { margin-bottom: 0; }
    .footer-html ul { list-style: none; margin-bottom: 0; padding: 0; }
    .footer-html li + li { margin-top: 1rem; }
    .footer-html strong, .footer-html b { font-weight: 700; }
    .footer-html em { font-style: italic; }
    .footer-html a { transition: opacity 150ms; }
    .footer-html a:hover { opacity: 0.75; }
    .footer-contact a { color: #dc2626; font-weight: 500; }  /* text-red-600 font-medium */
    .footer-links a { color: #374151; }                       /* text-gray-700 */
</style>

<footer class="bg-white border-t border-gray-200">
    <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-3">
            <div class="text-center">
                <img src="{{ asset('images/rcemblems.jpg') }}" alt="Emblems of humanity" class="mx-auto mb-4 h-20 w-auto">
                <div class="text-red-600 font-bold text-lg mb-2">
                    {{ \App\Models\Setting::get('site.motto', 'Serving Humanity') }}

                </div>
                <p class="text-gray-600 text-sm">
                    Motto of the Nigerian<br>
                    Red Cross Society
                </p>
            </div>

            <div>
                <p class="font-medium text-gray-900 mb-4">Contact Information</p>
                <div class="footer-html footer-contact text-base text-gray-700">
                    {!! $footerSanitizer->sanitize(\App\Models\Setting::get('site.footer_contact_html', \App\Support\FooterDefaults::CONTACT_HTML)) !!}
                </div>
            </div>

            <div>
                <p class="font-medium text-gray-900">Quick Links</p>
                <div class="footer-html footer-links mt-6 text-sm">
                    {!! $footerSanitizer->sanitize(\App\Models\Setting::get('site.footer_quick_links_html', \App\Support\FooterDefaults::QUICK_LINKS_HTML)) !!}
                </div>
            </div>
        </div>

        <div class="mt-8 border-t border-gray-100 pt-8">
            <p class="text-center text-gray-500 text-sm">
                Copyright © Nigerian Red Cross Society. All Rights Reserved.
            </p>
        </div>
    </div>
</footer>
