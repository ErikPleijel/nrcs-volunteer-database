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
                <div class="space-y-2 text-base text-gray-700">
                    <div>
                        <strong>Email:</strong> info@redcrossnigeria.org
                    </div>
                    <div class="mt-4">
                        <strong>Phone Number (TOLL FREE)</strong><br>
                        (+234) 803 123 0430 (MTN)<br>
                        (+234) 809 993 7357 (9Mobile)
                    </div>
                    <div class="mt-4">
                        <a href="https://www.redcrossnigeria.org/contact-us" class="text-red-600 transition hover:opacity-75 font-medium" target="_blank">Contact form </a>
                    </div>
                </div>
            </div>

            <div>
                <p class="font-medium text-gray-900">Quick Links</p>
                <ul class="mt-6 space-y-4 text-sm">
                    <li><a href="https://www.redcrossnigeria.org" class="text-gray-700 transition hover:opacity-75" target="_blank">Nigerian Red Cross website</a></li>
                    <li><a href="https://www.redcrossnigeria.org/about-us" class="text-gray-700 transition hover:opacity-75" target="_blank">About us</a></li>
                </ul>
            </div>
        </div>

        <div class="mt-8 border-t border-gray-100 pt-8">
            <p class="text-center text-gray-500 text-sm">
                Copyright © Nigerian Red Cross Society. All Rights Reserved.
            </p>
        </div>
    </div>
</footer>
