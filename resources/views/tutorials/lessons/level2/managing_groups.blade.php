<x-layouts.admin :title="$lessonTitle">
    <x-slot name="pageHeader"><i class="fas fa-people-roof mr-3"></i> {{ $lessonTitle }}</x-slot>
    <x-slot name="subHeader">Level 2: Branch Administration</x-slot>

    <div class="p-4 md:p-6">
        <a href="{{ route('tutorials.level', 2) }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 mb-6">
            <i class="fas fa-arrow-left"></i> Back to Level 2 lessons
        </a>

        <x-tutorial.player :lessonKey="$lessonKey" :title="$lessonTitle">

            {{-- Slide — Red Cross Units: create, archive, leadership --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-managing_groups-unit_edit.jpg"
                imageAlt="The Red Cross Unit Edit page showing the Leadership Assignment section with Team Leader and Assistant Team Leader dropdowns"
                heading="Managing Red Cross Units"
                audio="tutorials/audio/level2-managing_groups-unit_edit.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Open <strong><i class="fas fa-shield-alt text-red-500 mr-1"></i>Red Cross Units</strong> from the main menu.</x-tutorial.step>
                    <x-tutorial.step n="2">Press <strong>Add New Unit</strong> to create one, or <strong>Edit</strong> an existing one.</x-tutorial.step>
                    <x-tutorial.step n="3">Under <strong>Leadership Assignment</strong>, set the <strong>Team Leader</strong> and <strong>Assistant Team Leader</strong>.</x-tutorial.step>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-box-archive text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            A unit no longer in use can be <strong>archived</strong> from the same page — and reactivated later if needed.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Task Forces: create, archive, members --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-managing_groups-taskforce_edit.jpg"
                imageAlt="The Task Force Edit page showing the Task Force Members section with a search box and Add buttons"
                heading="Managing Task Forces"
                audio="tutorials/audio/level2-managing_groups-taskforce_edit.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Open <strong><i class="fas fa-users-gear text-indigo-500 mr-1"></i>Task Forces</strong> from the main menu — press <strong>Add New Task Force</strong> to create one.</x-tutorial.step>
                    <x-tutorial.step n="2">To add or remove people, click <strong>Edit</strong>, then use <strong>Task Force Members</strong>: search for a name and click <strong>Add</strong>.
                        <span class="block mt-1 text-sm text-gray-500">Stored instantly — no need to save separately.</span>
                    </x-tutorial.step>
                    <x-tutorial.step n="3">Archive a task force that's no longer active, to keep the database clean. Reactivate it later if needed.</x-tutorial.step>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-code-compare text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            <strong>Different from Red Cross Units:</strong> there, you change a person's unit via <strong>Persons → Edit</strong>. Here, you add and remove members directly in the task force's own <strong>Edit</strong> page.
                        </p>
                    </div>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-earth-africa text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            A task force can include people from <strong>any branch</strong> — for example, a Lagos task force can include someone from Borno.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Adding an Organisation --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-managing_groups-organisation_add.jpg"
                imageAlt="The Organisations page with the Add Organisation button, and an organisation's Show page with linked persons and a primary contact marker"
                heading="Adding an organisation"
                audio="tutorials/audio/level2-managing_groups-organisation_add.mp3">
                <div class="space-y-4">
                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                        <p class="text-sm text-gray-700">
                            <i class="fas fa-industry text-indigo-400 mr-1"></i>
                            An organisation is a company or institution that belongs to a branch — it can pay membership fees, make donations, receive certificates, and receive campaign messages.
                        </p>
                    </div>

                    <x-tutorial.step n="1">Open <strong>Organisations</strong> and press <strong>Add Organisation</strong>.
                        <span class="block mt-1 text-sm text-gray-500">Unlike persons, an organisation can only be added by a branch — it can't register itself.</span>
                    </x-tutorial.step>
                    <x-tutorial.step n="2">Fill in the form and save.</x-tutorial.step>
                    <x-tutorial.step n="3">It is necessary to <strong>link Persons</strong> to the organisation. If you link more than one, mark one as the <strong>primary contact</strong>.</x-tutorial.step>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-circle-info text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            From the organisation's page, you can add <strong>payments</strong> and <strong>donations</strong>, and view its <strong>certificates</strong> and <strong>campaigns</strong>.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Keeping Branch/Division contact details current --}}
            <x-tutorial.slide audio="tutorials/audio/level2-managing_groups-contact_details.mp3">
                <div class="max-w-2xl mx-auto text-center">
                    <div class="flex justify-center mb-6" data-reveal>
                        <div class="w-20 h-20 rounded-full bg-red-50 border-4 border-red-100 flex items-center justify-center">
                            <i class="fas fa-address-book text-4xl text-red-500"></i>
                        </div>
                    </div>

                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Keep contact details up to date</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        Branches and Divisions rely on accurate contact information.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-left mb-6">
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-5" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1">
                                <i class="fas fa-building-flag text-indigo-400 mr-1"></i> Branch &amp; Division details
                            </p>
                            <p class="text-sm text-gray-600">Address, phone, and other contact fields.</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-5" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1">
                                <i class="fas fa-id-badge text-indigo-400 mr-1"></i> Public Contact Persons
                            </p>
                            <p class="text-sm text-gray-600">Shown on profile and public views — keep names current.</p>
                        </div>
                    </div>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3 text-left" data-reveal>
                        <i class="fas fa-pen-to-square text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            Click <strong>Edit</strong> on the Branch or Division to make changes — follow the instructions on the Edit page.
                        </p>
                    </div>
                </div>
            </x-tutorial.slide>
            {{-- Slide — Lesson complete --}}
            <x-tutorial.slide audio="tutorials/audio/level2-managing_groups-complete.mp3">
                <div class="text-center max-w-xl mx-auto">

                    {{-- Check emblem --}}
                    <div class="flex justify-center mb-6" data-reveal>
                        <div class="w-24 h-24 rounded-full bg-green-50 border-4 border-green-100 flex items-center justify-center">
                            <i class="fas fa-circle-check text-5xl text-green-500"></i>
                        </div>
                    </div>

                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Lesson complete</h2>
                    <p class="text-gray-600 mb-8" data-reveal>You can now manage the groups and structure behind the database.</p>

                    {{-- Recap pills --}}
                    <div class="flex flex-wrap justify-center gap-3 mb-8" data-reveal>
            <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-shield-alt text-green-500"></i> Red Cross Units
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-users-gear text-green-500"></i> Task Forces
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-industry text-green-500"></i> Organisations
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-address-book text-green-500"></i> Contact details
            </span>
                    </div>

                    <p class="text-sm text-gray-500" data-reveal>
                        <i class="fas fa-arrow-left text-indigo-400 mr-1"></i>
                        Use <strong>Back to lesson list</strong> to continue with the next lesson.
                    </p>
                </div>
            </x-tutorial.slide>



        </x-tutorial.player>
    </div>
</x-layouts.admin>
