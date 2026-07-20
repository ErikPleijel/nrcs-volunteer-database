<x-layouts.admin :title="$lessonTitle">
    <x-slot name="pageHeader"><i class="fas fa-user-pen mr-3"></i> {{ $lessonTitle }}</x-slot>
    <x-slot name="subHeader">Level 2: Day-to-Day Admin</x-slot>

    <div class="p-4 md:p-6">
        <a href="{{ route('tutorials.level', 2) }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 mb-6">
            <i class="fas fa-arrow-left"></i> Back to Level 2 lessons
        </a>

        <x-tutorial.player :lessonKey="$lessonKey" :title="$lessonTitle">

            {{-- Slide — Assign a person to a Red Cross Unit --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-managing_person-assign_unit.jpg"
                imageAlt="A person's Edit page with the Red Cross Unit dropdown highlighted"
                heading="Assign to a Red Cross Unit"
                audio="tutorials/audio/level2-managing_person-assign_unit.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Find the person using <strong>Search</strong>, then click <strong>Edit</strong>.</x-tutorial.step>
                    <x-tutorial.step n="2">Select their <strong>Red Cross Unit</strong> in the dropdown.
                        <span class="block mt-1 text-sm text-gray-500">Can't find the right unit? You may need to change the <strong>Division</strong> first.</span>
                    </x-tutorial.step>
                    <x-tutorial.step n="3">Scroll down and click <strong>Update Person</strong>.</x-tutorial.step>

                    <div class="rounded-xl bg-green-50 border border-green-200 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-arrow-up-right-dots text-green-500 mt-1"></i>
                        <p class="text-sm text-green-900">
                            Once assigned, the person automatically moves out of <strong>Pending Engagement</strong> and becomes <strong>Active</strong>. This is one of two ways a person leaves Pending Engagement — the other is a qualifying membership payment, covered later in this lesson.
                        </p>
                    </div>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-star text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            This is the defining step — assigning someone to a Red Cross Unit is what makes them a <strong>volunteer</strong> in the system.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — How lifecycle status changes --}}
            <x-tutorial.slide audio="tutorials/audio/level2-managing_person-lifecycle_movements.mp3" heading="How lifecycle status changes">
                <div class="max-w-2xl mx-auto space-y-4">
                    <p class="text-center text-gray-600" data-reveal>
                        A quick recap of what actually moves a person between <strong>Pending</strong>, <strong>Active</strong>, and <strong>Dormant</strong>.
                    </p>

                    <div class="rounded-xl bg-green-50 border border-green-200 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-route text-green-500 mt-1"></i>
                        <div class="text-sm text-green-900">
                            <strong>Out of Pending:</strong>
                            <ul class="list-decimal list-inside mt-1 space-y-0.5">
                                <li>Assigning them to a Red Cross Unit</li>
                                <li>A qualifying membership payment</li>
                            </ul>
                        </div>
                    </div>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-moon text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            <strong>Active → Dormant:</strong> no activity for a long time — checked automatically overnight.
                        </p>
                    </div>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-rotate text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            <strong>Dormant → Active:</strong> entering any record for that person.
                        </p>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — Move a person to another branch or division --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-managing_person-move_branch.jpg"
                imageAlt="A person's Edit page with the Branch and Division dropdowns highlighted"
                heading="Move to another branch or division"
                audio="tutorials/audio/level2-managing_person-move_branch.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Find the person using <strong>Search</strong>, then click <strong>Edit</strong>.</x-tutorial.step>
                    <x-tutorial.step n="2">Select the new <strong>Branch</strong> and/or <strong>Division</strong> in the dropdowns.</x-tutorial.step>
                    <x-tutorial.step n="3">Scroll down and click <strong>Update Person</strong>.</x-tutorial.step>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-triangle-exclamation text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            If the person holds a <strong>database admin role</strong>, that role must be removed first — contact your Branch or HQ if needed.
                        </p>
                    </div>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-circle-info text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            Branch-level admins can only move people <strong>within their own branch</strong>.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Add a photo and signature --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-managing_person-photo_signature.jpg"
                imageAlt="A person's Edit page showing the photo upload and signature upload fields, with camera capture options"
                heading="Add a photo and signature"
                audio="tutorials/audio/level2-managing_person-photo_signature.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Find the person using <strong>Search</strong>, then click <strong>Edit</strong>.</x-tutorial.step>
                    <x-tutorial.step n="2">Under the photo: <strong>Choose File</strong> → <strong>Update Profile Photo</strong>.</x-tutorial.step>
                    <x-tutorial.step n="3">Under the signature: <strong>Choose File</strong> → <strong>Update Signature</strong>.
                        <span class="block mt-1 text-sm text-gray-500">You can also capture both directly using the built-in camera.</span>
                    </x-tutorial.step>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-circle-info text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            You do <strong>not</strong> need to click <strong>Update Person</strong> for these — they save separately.
                        </p>
                    </div>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-id-card text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            A recent, clear photo is required for printed ID cards. Use the <strong>Profile Photo &amp; Signature filter</strong> (Persons, filter) to find people missing one.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Archive a user --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-managing_person-archive_user.jpg"
                imageAlt="A person's Edit page scrolled to the 'Archive this user' checkbox"
                heading="Archive a user"
                audio="tutorials/audio/level2-managing_person-archive_user.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Find the person using <strong>Search</strong>, then click <strong>Edit</strong>.</x-tutorial.step>
                    <x-tutorial.step n="2">Scroll down and tick <strong>Archive this user</strong>.</x-tutorial.step>
                    <x-tutorial.step n="3">Click <strong>Update Person</strong>.</x-tutorial.step>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-circle-info text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            Use this for someone who has <strong>permanently left</strong> the organisation — not just gone quiet for a while.
                        </p>
                    </div>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-eye-slash text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            Archived users are hidden from the list — use the <strong>Show archived</strong> button to find them again. You cannot archive your own account.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Reactivate an archived user --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-managing_person-reactivate_user.jpg"
                imageAlt="The Persons list with the Show archived button, and an archived person's Edit page with the Archive this user checkbox unticked"
                heading="Reactivate an archived user"
                audio="tutorials/audio/level2-managing_person-reactivate_user.mp3">
                <div class="space-y-4">
                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                        <p class="text-sm text-gray-700">
                            <i class="fas fa-phone text-indigo-400 mr-1"></i>
                            A person may call you — they tried to log in, but got a message that their account is archived.
                        </p>
                    </div>

                    <x-tutorial.step n="1">Use <strong>Search</strong>, with <strong>Show archived</strong> enabled, to find them.</x-tutorial.step>
                    <x-tutorial.step n="2">Click <strong>Edit</strong>, scroll down, and <strong>untick</strong> "Archive this user".</x-tutorial.step>
                    <x-tutorial.step n="3">Click <strong>Update Person</strong>.</x-tutorial.step>

                    <div class="rounded-xl bg-green-50 border border-green-200 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-circle-check text-green-500 mt-1"></i>
                        <p class="text-sm text-green-900">
                            The person returns to <strong>Active or Dormant</strong> status.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Lesson complete --}}
            <x-tutorial.slide audio="tutorials/audio/level2-managing_person-complete.mp3">
                <div class="text-center max-w-xl mx-auto">

                    {{-- Check emblem --}}
                    <div class="flex justify-center mb-6" data-reveal>
                        <div class="w-24 h-24 rounded-full bg-green-50 border-4 border-green-100 flex items-center justify-center">
                            <i class="fas fa-circle-check text-5xl text-green-500"></i>
                        </div>
                    </div>

                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Lesson complete</h2>
                    <p class="text-gray-600 mb-8" data-reveal>You can now manage the key changes to a person's record.</p>

                    {{-- Recap pills --}}
                    <div class="flex flex-wrap justify-center gap-3 mb-8" data-reveal>
            <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-shield-alt text-green-500"></i> Assign to a unit
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-arrows-rotate text-green-500"></i> How status changes
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-sitemap text-green-500"></i> Move branch/division
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-image text-green-500"></i> Photo &amp; signature
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-box-archive text-green-500"></i> Archive &amp; reactivate
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
