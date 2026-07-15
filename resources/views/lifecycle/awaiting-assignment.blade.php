<x-lifecycle.layout title="Awaiting engagement">

    {{-- Summary row --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="rounded-lg border border-gray-200 bg-white p-6 lg:col-span-2">
            <h2 class="howto-h2">What is "Awaiting engagement"?</h2>

            <p class="mt-4 text-base text-gray-700">
                These are people who have registered an account but have not yet been properly brought into the organisation.
            </p>

            <div class="mt-3 rounded-md bg-amber-50 border border-amber-200 p-4">
                <div class="text-xl font-semibold text-amber-900">Objective</div>
                <div class="mt-1 text-base text-amber-900">
                    Make first contact and guide them to their next step.
                </div>
            </div>

            <p class="mt-4 text-base text-gray-700">
                On the registration form, they answered: <span class="font-medium">"How would you like to contribute?"</span>
                (they could tick one or both).
            </p>

            <ul class="mt-4 space-y-1 text-base text-gray-700 list-disc pl-5">
                <li>
                    <span class="font-semibold">Volunteer Services</span> — "I can contribute my time and skills as a volunteer."
                </li>
                <li>
                    <span class="font-semibold">Active Membership</span> — "I want to be an active member of the Red Cross."
                </li>
            </ul>

            <div class="mt-3 text-base font-semibold uppercase tracking-wide text-gray-500">Volunteers</div>
            <p class="mt-1 text-base text-gray-700">
                People interested in volunteering need to be placed in a Red Cross Unit. Once placed, they can begin volunteering and receive the necessary training.
            </p>

            <div class="mt-3 text-base font-semibold uppercase tracking-wide text-gray-500">Members</div>
            <p class="mt-1 text-base text-gray-700">
                People interested in membership need clear guidance on how to pay their membership fee and what to expect next.
            </p>
        </div>
    </div>

    {{-- How-to section --}}
    <div class="rounded-lg border border-gray-200 bg-white p-6">
        <h2 class="howto-h2">How to handle pending engagement</h2>

        <div class="mt-4">
            <h3 class="howto-h3 flex w-full cursor-pointer select-none items-center justify-between rounded-md border border-gray-300 bg-gray-50 px-4 py-3 hover:bg-gray-100 transition-colors" onclick="toggleSection(this)">
                Run an onboarding campaign
                <span class="toggle-triangle inline-block text-xs text-black transition-transform">▼</span>
            </h3>
            <div class="toggle-content hidden">

                <p class="mt-1 text-base text-gray-600">Use this when you want to reach a group of people at once via email or SMS.</p>

                <div class="mt-4 flex items-start gap-2">

                    <div class="rounded-lg border-2 border-blue-200 bg-white p-4 flex flex-col gap-2 w-44 shrink-0">
                        <span class="text-xs font-medium text-blue-700 bg-blue-50 rounded px-2 py-0.5 w-fit">Step 1</span>
                        <p class="text-sm font-semibold text-gray-800 leading-snug">Create campaign</p>
                        <p class="text-xs text-gray-600 leading-relaxed">Filter your target group and send a message via the campaign wizard.</p>
                    </div>

                    <div class="flex items-center pt-8 text-gray-500 text-2xl font-bold shrink-0">→</div>

                    <div class="rounded-lg border-2 border-teal-200 bg-white p-4 flex flex-col gap-2 w-44 shrink-0">
                        <span class="text-xs font-medium text-teal-700 bg-teal-50 rounded px-2 py-0.5 w-fit">Step 2</span>
                        <p class="text-sm font-semibold text-gray-800 leading-snug">People respond</p>
                        <p class="text-xs text-gray-600 leading-relaxed">Recipients contact the branch to express their interest.</p>
                    </div>

                    <div class="flex items-center pt-8 text-gray-500 text-2xl font-bold shrink-0">→</div>

                    <div class="rounded-lg border-2 border-teal-200 bg-white p-4 flex flex-col gap-2 w-44 shrink-0">
                        <span class="text-xs font-medium text-teal-700 bg-teal-50 rounded px-2 py-0.5 w-fit">Step 3</span>
                        <p class="text-sm font-semibold text-gray-800 leading-snug">Have the dialogue</p>
                        <p class="text-xs text-gray-600 leading-relaxed">Speak with them and clarify their interest and next step.</p>
                    </div>

                    <div class="flex items-center pt-8 text-gray-500 text-2xl font-bold shrink-0">→</div>

                    <div class="rounded-lg border-2 border-purple-200 bg-white p-4 flex flex-col gap-2 w-44 shrink-0">
                        <span class="text-xs font-medium text-purple-700 bg-purple-50 rounded px-2 py-0.5 w-fit">Step 4</span>
                        <p class="text-sm font-semibold text-gray-800 leading-snug">Register the outcome</p>
                        <p class="text-xs text-gray-600 leading-relaxed">Open their record, click Edit, and update accordingly.</p>
                    </div>

                </div>

                <ol class="howto-ol mt-4">
                    <li>Go to <span class="font-semibold">Persons</span>.</li>
                    <li>Set the <span class="font-semibold">filter</span> to <span class="font-semibold">Lifecycle Status → Awaiting Engagement</span>. You can narrow the list further using filters such as Division or Gender.</li>
                    <li>Click <span class="font-semibold">"Make campaign from this filter"</span> and follow the wizard to compose and send your message.</li>
                    <li>When someone contacts you, use <span class="font-semibold">Search</span> in Persons to find them.</li>
                    <li>Click <span class="font-semibold">View</span> to see what they entered in the registration form.</li>
                    <li>Have a dialogue and guide them on their next step — joining a unit, paying the membership fee, etc.</li>
                    <li>Click <span class="font-semibold">Edit</span> to assign them to a Red Cross Unit or make any other necessary changes.</li>
                </ol>

            </div>
        </div>

        <div class="mt-6">
            <h3 class="howto-h3 flex w-full cursor-pointer select-none items-center justify-between rounded-md border border-gray-300 bg-gray-50 px-4 py-3 hover:bg-gray-100 transition-colors" onclick="toggleSection(this)">
                Follow up individually
                <span class="toggle-triangle inline-block text-xs text-black transition-transform">▼</span>
            </h3>
            <div class="toggle-content hidden">
                <p class="mt-1 text-base text-gray-600">Use this when you want to work through pending persons one by one and register the outcome of each conversation.</p>

                <div class="mt-4 flex items-start gap-2">

                    <div class="rounded-lg border-2 border-blue-200 bg-white p-4 flex flex-col gap-2 w-44 shrink-0">
                        <span class="text-xs font-medium text-blue-700 bg-blue-50 rounded px-2 py-0.5 w-fit">Step 1</span>
                        <p class="text-sm font-semibold text-gray-800 leading-snug">Filter your list</p>
                        <p class="text-xs text-gray-600 leading-relaxed">Find persons awaiting engagement using Lifecycle Status and other filters.</p>
                    </div>

                    <div class="flex items-center pt-8 text-gray-500 text-2xl font-bold shrink-0">→</div>

                    <div class="rounded-lg border-2 border-teal-200 bg-white p-4 flex flex-col gap-2 w-44 shrink-0">
                        <span class="text-xs font-medium text-teal-700 bg-teal-50 rounded px-2 py-0.5 w-fit">Step 2</span>
                        <p class="text-sm font-semibold text-gray-800 leading-snug">Contact and converse</p>
                        <p class="text-xs text-gray-600 leading-relaxed">Reach out one by one. Review their registration form before the conversation.</p>
                    </div>

                    <div class="flex items-center pt-8 text-gray-500 text-2xl font-bold shrink-0">→</div>

                    <div class="rounded-lg border-2 border-purple-200 bg-white p-4 flex flex-col gap-2 w-44 shrink-0">
                        <span class="text-xs font-medium text-purple-700 bg-purple-50 rounded px-2 py-0.5 w-fit">Step 3</span>
                        <p class="text-sm font-semibold text-gray-800 leading-snug">Register the outcome</p>
                        <p class="text-xs text-gray-600 leading-relaxed">Open their record, click Edit, and update accordingly.</p>
                    </div>

                </div>

                <ol class="howto-ol mt-4">
                    <li>Go to <span class="font-semibold">Persons</span>.</li>
                    <li>Set the <span class="font-semibold">filter</span> to <span class="font-semibold">Lifecycle Status → Awaiting Engagement</span>. You can narrow the list further using filters such as Division or Gender.</li>
                    <li>Go through the list one by one. Click <span class="font-semibold">View</span> to see what the person entered in the registration form, then contact them.</li>
                    <li>Guide them on their next step and click <span class="font-semibold">Edit</span> to register the outcome:
                        <ul class="mt-1 list-disc pl-5 space-y-1">
                            <li><span class="font-semibold">Volunteers:</span> assign them to the appropriate Red Cross Unit, then click <span class="font-semibold">Update person</span>.</li>
                            <li><span class="font-semibold">Members:</span> advise them on how to pay the membership fee and confirm what happens next.</li>
                        </ul>
                    </li>
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
