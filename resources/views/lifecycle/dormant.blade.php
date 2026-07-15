<x-lifecycle.layout
    title="Dormant"

>
    {{-- Summary row --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="rounded-lg border border-gray-200 bg-white p-6 lg:col-span-2">
            <h2 class="howto-h2">What is “Dormant”?</h2>

            <p class="mt-2 text-base text-gray-700">
                People in <span class="font-semibold">Dormant</span> were previously active, but they haven’t shown activity for a period of time.
            </p>

            <div class="mt-3 rounded-md bg-amber-50 border border-amber-200 p-4">
                <div class="text-xl font-semibold text-amber-900">Objective</div>
                <div class="mt-1 text-base text-amber-900">
                    Reconnect and offer a simple next step. Archive when appropriate.
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="rounded-md bg-gray-50 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Typical reasons</div>
                    <ul class="mt-2 space-y-1 text-sm text-gray-700 list-disc pl-5">
                        <li>No recent volunteer activity / training</li>
                        <li>Membership not renewed / fee not paid</li>
                        <li>Moved location or changed availability</li>
                    </ul>
                </div>

                <div class="rounded-md bg-gray-50 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Recommended actions</div>
                    <ul class="mt-2 space-y-1 text-sm text-gray-700 list-disc pl-5">
                        <li>Send a reactivation message (one clear next step)</li>
                        <li>Offer a low-friction return path (reply YES / click link)</li>
                        <li>Archive if confirmed inactive</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- How-to section --}}
    <div class="rounded-lg border border-gray-200 bg-white p-6">
        <h2 class="howto-h2">How to reactivate dormant members</h2>

        <div class="mt-4">
            <h3 class="howto-h3 flex w-full cursor-pointer select-none items-center justify-between rounded-md border border-gray-300 bg-gray-50 px-4 py-3 hover:bg-gray-100 transition-colors" onclick="toggleSection(this)">
                Run a reactivation campaign
                <span class="toggle-triangle inline-block text-xs text-black transition-transform">▼</span>
            </h3>
            <div class="toggle-content hidden">

                <p class="mt-1 text-base text-gray-600">Use this when you want to reach a group of dormant persons at once via email or SMS.</p>

                <ol class="howto-ol mt-4">
                    <li>Go to <span class="font-semibold">Persons</span>.</li>
                    <li>Set the <span class="font-semibold">filter</span> to <span class="font-semibold">Lifecycle Status → Dormant</span>. You can narrow the list further using filters such as Division or Gender.</li>
                    <li>Click <span class="font-semibold">"Make campaign from this filter"</span> and follow the wizard to compose and send your message.</li>
                    <li>When someone contacts you, use <span class="font-semibold">Search</span> in Persons to find them.</li>
                    <li>Click <span class="font-semibold">View</span> to review their record.</li>
                    <li>Have a dialogue and guide them on how they can get involved again.</li>
                </ol>

            </div>
        </div>

        <div class="mt-6">
            <h3 class="howto-h3 flex w-full cursor-pointer select-none items-center justify-between rounded-md border border-gray-300 bg-gray-50 px-4 py-3 hover:bg-gray-100 transition-colors" onclick="toggleSection(this)">
                Follow up individually
                <span class="toggle-triangle inline-block text-xs text-black transition-transform">▼</span>
            </h3>
            <div class="toggle-content hidden">
                <p class="mt-1 text-base text-gray-600">Use this when you want to work through dormant persons one by one and register the outcome of each conversation.</p>

                <ol class="howto-ol mt-4">
                    <li>Go to <span class="font-semibold">Persons</span>.</li>
                    <li>Set the <span class="font-semibold">filter</span> to <span class="font-semibold">Lifecycle Status → Dormant</span>. You can narrow the list further using filters such as Division or Gender.</li>
                    <li>Go through the list one by one. Click <span class="font-semibold">View</span> to review their record before making contact.</li>
                    <li>Have a dialogue and guide them on how they can get involved again.</li>
                    <li>Click <span class="font-semibold">Edit</span> to register the outcome and make any necessary changes.</li>
                </ol>
            </div>
        </div>

        {{-- Note --}}
        <div class="mt-6 rounded-md bg-gray-50 border border-gray-200 p-4 text-base text-gray-700">
            <span class="font-semibold">Note:</span> If a person is no longer interested in the Red Cross: click <span class="font-semibold">Edit</span>, scroll to the bottom of the page, tick <span class="font-semibold">"Archive this user"</span>, and click <span class="font-semibold">Update person</span>. This keeps the database clean without permanently deleting the record. The person can be reactivated later if needed.
        </div>
    </div>

    <script>
        function toggleSection(heading) {
            const content = heading.nextElementSibling;
            const triangle = heading.querySelector('.toggle-triangle');
            content.classList.toggle('hidden');
            triangle.classList.toggle('rotate-180');
        }
    </script>

</x-lifecycle.layout>
