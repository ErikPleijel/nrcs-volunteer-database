<x-layouts.admin :title="$lessonTitle">
    <x-slot name="pageHeader"><i class="fas fa-key mr-3"></i> {{ $lessonTitle }}</x-slot>
    <x-slot name="subHeader">Level 2: Branch Administration</x-slot>

    <div class="p-4 md:p-6">
        <a href="{{ route('tutorials.level', 2) }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 mb-6">
            <i class="fas fa-arrow-left"></i> Back to Level 2 lessons
        </a>

        <x-tutorial.player :lessonKey="$lessonKey" :title="$lessonTitle">

            {{-- Slide — Authorizations overview --}}
            <x-tutorial.slide audio="tutorials/audio/level2-authorizations-overview.mp3">
                <div class="max-w-2xl mx-auto text-center">
                    <div class="flex justify-center mb-6" data-reveal>
                        <div class="w-20 h-20 rounded-full bg-amber-50 border-4 border-amber-100 flex items-center justify-center">
                            <i class="fas fa-key text-4xl text-amber-500"></i>
                        </div>
                    </div>

                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Authorizations</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        Deciding who can do what in the database.
                    </p>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3 text-left mb-4" data-reveal>
                        <i class="fas fa-triangle-exclamation text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            This is an important responsibility — handle it with care.
                        </p>
                    </div>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3 text-left" data-reveal>
                        <i class="fas fa-crown text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            The <strong>NRCS President</strong> and <strong>Secretary General</strong> hold overall super-admin authority, using their official NRCS email accounts. Day-to-day authorization is handled by <strong>National</strong> and <strong>Branch DB Administrators</strong>.
                        </p>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — Branch-level roles --}}
            <x-tutorial.slide audio="tutorials/audio/level2-authorizations-branch_roles.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Roles you can authorize at branch level</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        Five roles, each with a different scope.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-left">
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1"><i class="fas fa-user-tie text-red-500 mr-1"></i> Branch Secretary</p>
                            <p class="text-sm text-gray-600">Branch administration.</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1"><i class="fas fa-user-shield text-red-500 mr-1"></i> Branch DB Administrator</p>
                            <p class="text-sm text-gray-600">Same authority as Branch Secretary, in the database.</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1"><i class="fas fa-keyboard text-indigo-400 mr-1"></i> Branch DB Assistant</p>
                            <p class="text-sm text-gray-600">Branch data entry.</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1"><i class="fas fa-coins text-amber-500 mr-1"></i> Division Assistant — Finance</p>
                            <p class="text-sm text-gray-600">Payments, Donations, Trainings &amp; Volunteering.</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1"><i class="fas fa-clipboard-list text-indigo-400 mr-1"></i> Division Assistant — Operations</p>
                            <p class="text-sm text-gray-600">Trainings &amp; Volunteering.</p>
                        </div>
                    </div>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3 text-left mt-4" data-reveal>
                        <i class="fas fa-circle-info text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            A <strong>Division Assistant</strong> — either kind — sees only their own division.
                        </p>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — Search for a person & assign a role --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-authorizations-search_assign.jpg"
                imageAlt="The Authorizations page showing the Search for a User field, the current role display, the Assign Role dropdown, and the Update Roles and Permissions button"
                heading="Search and assign a role"
                audio="tutorials/audio/level2-authorizations-search_assign.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Use <strong>Search for a User</strong> to find the person by name, email, or ID.</x-tutorial.step>
                    <x-tutorial.step n="2">Their <strong>current role</strong> is shown at the top of the form once selected.</x-tutorial.step>
                    <x-tutorial.step n="3">Choose a new role from <strong>Assign Role</strong>, then click <strong>Update Roles &amp; Permissions</strong> to save.
                        <span class="block mt-1 text-sm text-gray-500">Select <strong>"-- No Role --"</strong> to remove authorization entirely.</span>
                    </x-tutorial.step>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-rotate text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            Click <strong>Clear / Search for another user</strong> to start over with someone else.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Review Users by Role --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-authorizations-users_by_role.jpg"
                imageAlt="The Users by Role table grouped by role, showing red Direct Permissions tags next to some names and an Edit button on each row"
                heading="Reviewing Users by Role"
                audio="tutorials/audio/level2-authorizations-users_by_role.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">The table below the form shows <strong>everyone currently authorized</strong>, grouped by role.</x-tutorial.step>
                    <x-tutorial.step n="2">Extra <strong>Direct Permissions</strong> a person holds appear as red tags next to their name.</x-tutorial.step>
                    <x-tutorial.step n="3">Click <strong>Edit</strong> on any row to jump straight to that person's role form.</x-tutorial.step>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-lock text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            <strong>National DB Administrators</strong> can only be edited by a super-admin.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Lesson & Level 2 complete --}}
            <x-tutorial.slide audio="tutorials/audio/level2-authorizations-complete.mp3">
                <div class="text-center max-w-xl mx-auto">

                    {{-- Check emblem --}}
                    <div class="flex justify-center mb-6" data-reveal>
                        <div class="w-24 h-24 rounded-full bg-green-50 border-4 border-green-100 flex items-center justify-center">
                            <i class="fas fa-circle-check text-5xl text-green-500"></i>
                        </div>
                    </div>

                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Lesson complete</h2>
                    <p class="text-gray-600 mb-8" data-reveal>You now know how to manage authorizations at branch level.</p>

                    {{-- Recap pills — this lesson --}}
                    <div class="flex flex-wrap justify-center gap-3 mb-8" data-reveal>
            <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-key text-green-500"></i> Branch-level roles
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-magnifying-glass text-green-500"></i> Search &amp; assign
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-table text-green-500"></i> Users by Role
            </span>
                    </div>

                    {{-- Level 2 complete banner --}}
                    <div class="rounded-xl bg-red-50 border-2 border-red-200 p-5 mb-6" data-reveal>
                        <p class="font-bold text-red-700 text-lg mb-2">
                            <i class="fas fa-trophy mr-1"></i> Level 2 complete!
                        </p>
                        <p class="text-sm text-red-900">
                            You've covered branch administration: managing a person's record, groups and structure, approvals and certificates, keeping the database clean, campaigns, and authorizations.
                        </p>
                    </div>

                    <p class="text-sm text-gray-500" data-reveal>
                        <i class="fas fa-arrow-left text-indigo-400 mr-1"></i>
                        Use <strong>Back to lesson list</strong> to see what's next.
                    </p>
                </div>
            </x-tutorial.slide>



        </x-tutorial.player>
    </div>
</x-layouts.admin>
