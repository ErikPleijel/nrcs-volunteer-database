<x-lifecycle.layout
    title="Active"

>
    {{-- Summary row --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="rounded-lg border border-gray-200 bg-white p-6 lg:col-span-2">
            <h2 class="howto-h2">What is “Active”?</h2>

            <p class="mt-2 text-base text-gray-700">
                People in <span class="font-semibold">Active</span> category are engaged in the organisation. They are volunteering, getting training, paying membership etc.
            </p>

            <div class="mt-3 rounded-md bg-amber-50 border border-amber-200 p-4">
                <div class="text-xl font-semibold text-amber-900">Objective</div>
                <div class="mt-1 text-base text-amber-900">
                    Keep people engaged.
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="rounded-md bg-gray-50 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Typical focus areas</div>
                    <ul class="mt-2 space-y-1 text-sm text-gray-700 list-disc pl-5">
                        <li>Retention & belonging</li>
                        <li>Training & competence</li>
                        <li>Volunteer mobilisation</li>
                        <li>Membership renewal & communications</li>
                    </ul>
                </div>

                <div class="rounded-md bg-gray-50 p-4">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Recommended actions</div>
                    <ul class="mt-2 space-y-1 text-sm text-gray-700 list-disc pl-5">
                        <li>Run a “thank you / update” message</li>
                        <li>Invite to training or next activity</li>
                        <li>Make certificates</li>
                        <li>Provide ID-cards</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- Simple "how to" --}}
    <div class="rounded-lg border border-gray-200 bg-white p-6">
        <h2 class="howto-h2">How to keep people engaged</h2>

        <div class="mt-4">
            <h3 class="howto-h3 flex w-full cursor-pointer select-none items-center justify-between rounded-md border border-gray-300 bg-gray-50 px-4 py-3 hover:bg-gray-100 transition-colors" onclick="toggleSection(this)">
                Invite persons to a training
                <span class="toggle-triangle inline-block text-xs text-black transition-transform">▼</span>
            </h3>
            <div class="toggle-content hidden">

                <p class="mt-1 text-base text-gray-600">Use this to invite people to a specific training — for example First Aid or food security.</p>

                <ol class="howto-ol mt-4">
                    <li>Go to <span class="font-semibold">Persons</span>.</li>
                    <li>Use the <span class="font-semibold">filter</span> to narrow down your target group — for example, by Division or Gender.</li>
                    <li>Add a training filter, for example:
                        <ul class="mt-1 list-disc pl-5 space-y-1">
                            <li><span class="font-semibold">Trainings → No training in basic food security</span></li>
                            <li><span class="font-semibold">First Aid Training → No First Aid</span></li>
                        </ul>
                    </li>
                    <li>Click <span class="font-semibold">"Make campaign from this filter"</span> and follow the wizard to compose and send your message.</li>
                    <li>Alternative: contact each person one by one.</li>
                </ol>

            </div>
        </div>

        <div class="mt-4">
            <h3 class="howto-h3 flex w-full cursor-pointer select-none items-center justify-between rounded-md border border-gray-300 bg-gray-50 px-4 py-3 hover:bg-gray-100 transition-colors" onclick="toggleSection(this)">
                Invite persons to a refresher training
                <span class="toggle-triangle inline-block text-xs text-black transition-transform">▼</span>
            </h3>
            <div class="toggle-content hidden">

                <p class="mt-1 text-base text-gray-600">Some trainings expire after a certain period. Use this to remind people before their certification lapses.</p>

                <ol class="howto-ol mt-4">
                    <li>Go to <span class="font-semibold">Persons</span>.</li>
                    <li>Use the <span class="font-semibold">filter</span> to narrow down your target group — for example, by Division or Gender.</li>
                    <li>Add a training filter, for example:
                        <ul class="mt-1 list-disc pl-5 space-y-1">
                            <li><span class="font-semibold">First Aid Training → Will expire in 14 days</span></li>
                            <li><span class="font-semibold">First Aid Training → Has expired First Aid</span></li>
                        </ul>
                    </li>
                    <li>Click <span class="font-semibold">"Make campaign from this filter"</span> and follow the wizard to compose and send your message.</li>
                </ol>

                <h4 class="howto-h4 mt-4">Alternative: use the Trainings View</h4>
                <ol class="howto-ol mt-4">
                    <li>Go to <span class="font-semibold">Persons</span>.</li>
                    <li>Use the <span class="font-semibold">filter</span> to narrow down your target group — for example, by Red Cross Unit.</li>
                    <li>Click <span class="font-semibold">Trainings View</span> to see an overview of each person's training history.</li>
                    <li>Contact each person individually.</li>
                </ol>

            </div>
        </div>

        <div class="mt-4">
            <h3 class="howto-h3 flex w-full cursor-pointer select-none items-center justify-between rounded-md border border-gray-300 bg-gray-50 px-4 py-3 hover:bg-gray-100 transition-colors" onclick="toggleSection(this)">
                Print certificates
                <span class="toggle-triangle inline-block text-xs text-black transition-transform">▼</span>
            </h3>
            <div class="toggle-content hidden">

                <p class="mt-1 text-base text-gray-600">Certificates are a good way to recognise people and keep them engaged. You can print certificates for trainings, membership, volunteering, and donations.</p>

                <h4 class="howto-h4 mt-4">Option 1: Bulk printing</h4>
                <ol class="howto-ol mt-4">
                    <li>Go to <span class="font-semibold">Certificates</span>.</li>
                    <li>Select <span class="font-semibold">Certificate type</span>.</li>
                    <li>Use the <span class="font-semibold">filter</span> to narrow down your target group — for example, by Division or Red Cross Unit.</li>
                    <li>Select <span class="font-semibold">Training type</span>.</li>
                    <li>Tick the persons you want to print certificates for. You can also tick <span class="font-semibold">Select all</span>.</li>
                    <li>If you have certificates with a pre-printed background, click <span class="font-semibold">Print for pre-printed paper</span>. Otherwise, click <span class="font-semibold">Print with logo &amp; frame</span>.</li>
                    <li><span class="font-semibold">Important:</span> once printed, click <span class="font-semibold">Mark as printed</span> to register this in the database.</li>
                </ol>

                <h4 class="howto-h4 mt-4">Option 2: Print for an individual</h4>
                <ol class="howto-ol mt-4">
                    <li>Go to <span class="font-semibold">Persons</span> and use <span class="font-semibold">Search</span> to find the person.</li>
                    <li>Click <span class="font-semibold">View</span> and scroll down to the certificates section.</li>
                    <li>Click the relevant <span class="font-semibold">Print certificate</span> button and print.</li>
                    <li><span class="font-semibold">Important:</span> once printed, click <span class="font-semibold">Mark as printed</span> to register this in the database.</li>
                </ol>

            </div>
        </div>

        <div class="mt-4">
            <h3 class="howto-h3 flex w-full cursor-pointer select-none items-center justify-between rounded-md border border-gray-300 bg-gray-50 px-4 py-3 hover:bg-gray-100 transition-colors" onclick="toggleSection(this)">
                Print ID cards
                <span class="toggle-triangle inline-block text-xs text-black transition-transform">▼</span>
            </h3>
            <div class="toggle-content hidden">

                <p class="mt-1 text-base text-gray-600">All volunteers should have an ID card. Printing is a two-step process: branches prepare the records, then HQ prints.</p>

                <h4 class="howto-h4 mt-4">Step 1: Preparation (done at the branch)</h4>
                <ol class="howto-ol mt-4">
                    <li>Go to <span class="font-semibold">Persons</span> and filter your target group — for example, by Red Cross Unit.</li>
                    <li>Click <span class="font-semibold">View ID Cards</span>.</li>
                    <li>Check that all required information is in place. There should be no items marked in <span style="color: red;">red</span>.</li>
                    <li>Check that the signature image looks correct.</li>
                    <li>When everything is ready, send a print request to HQ.</li>
                </ol>

                <h4 class="howto-h4 mt-4">Step 2: Printing (done at HQ)</h4>
                <ol class="howto-ol mt-4">
                    <li>Go to <span class="font-semibold">ID Cards</span>.</li>
                    <li>Filter by <span class="font-semibold">Branch</span>, <span class="font-semibold">Division</span>, or <span class="font-semibold">Red Cross Unit</span>.</li>
                    <li>Click <span class="font-semibold">Bulk Set Validity</span> to set expiry dates, or set them individually per person.</li>
                    <li>Click <span class="font-semibold">Print Selected</span>.</li>
                    <li><span class="font-semibold">Important:</span> once printed, click <span class="font-semibold">Mark as printed</span> to register this in the database.</li>
                </ol>

            </div>
        </div>

        <div class="mt-4">
            <h3 class="howto-h3 flex w-full cursor-pointer select-none items-center justify-between rounded-md border border-gray-300 bg-gray-50 px-4 py-3 hover:bg-gray-100 transition-colors" onclick="toggleSection(this)">
                Remind members about expiring membership
                <span class="toggle-triangle inline-block text-xs text-black transition-transform">▼</span>
            </h3>
            <div class="toggle-content hidden">

                <p class="mt-1 text-base text-gray-600">Send a reminder to members whose membership has expired or is about to expire.</p>

                <ol class="howto-ol mt-4">
                    <li>Go to <span class="font-semibold">Persons</span>.</li>
                    <li>Use the <span class="font-semibold">filter</span> to narrow down your target group — for example, by Division.</li>
                    <li>Add a membership filter, for example:
                        <ul class="mt-1 list-disc pl-5 space-y-1">
                            <li><span class="font-semibold">Membership → Will expire within 14 days</span></li>
                            <li><span class="font-semibold">Membership → Will expire within 28 days</span></li>
                            <li><span class="font-semibold">Membership → Expired members</span></li>
                        </ul>
                    </li>
                    <li>Click <span class="font-semibold">"Make campaign from this filter"</span> and follow the wizard to compose and send your message.</li>
                </ol>

            </div>
        </div>

        <div class="mt-4">
            <h3 class="howto-h3 flex w-full cursor-pointer select-none items-center justify-between rounded-md border border-gray-300 bg-gray-50 px-4 py-3 hover:bg-gray-100 transition-colors" onclick="toggleSection(this)">
                Send newsletters
                <span class="toggle-triangle inline-block text-xs text-black transition-transform">▼</span>
            </h3>
            <div class="toggle-content hidden">

                <p class="mt-1 text-base text-gray-600">Keep members and volunteers informed about what is happening in the organisation.</p>

                <ol class="howto-ol mt-4">
                    <li>Go to <span class="font-semibold">Persons</span>.</li>
                    <li>Use the <span class="font-semibold">filter</span> to narrow down your target group.</li>
                    <li>Click <span class="font-semibold">"Make campaign from this filter"</span> and follow the wizard to compose and send your message.</li>
                </ol>

            </div>
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
