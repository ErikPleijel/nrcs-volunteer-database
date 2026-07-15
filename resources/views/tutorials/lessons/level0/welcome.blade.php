<x-layouts.admin :title="$lessonTitle">
    <x-slot name="pageHeader">
        <i class="fas fa-rocket mr-3"></i> {{ $lessonTitle }}
    </x-slot>
    <x-slot name="subHeader">Getting Oriented</x-slot>

    <div class="p-4 md:p-6">

        <a href="{{ route('tutorials.index') }}"
           class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 mb-6">
            <i class="fas fa-arrow-left"></i> Back to lessons
        </a>

        <x-tutorial.player :lessonKey="$lessonKey" :title="$lessonTitle">

            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- Slide 1 — Hero --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- narration:
                This system is the official Nigerian Red Cross Society database for
                volunteers, members, branches, and programmes across the country.
                These tutorials will walk you through everything you need to know
                to use the system confidently. Lessons are short, and your progress
                is saved automatically.
            --}}
            <x-tutorial.slide audio="tutorials/audio/level0-welcome.mp3">
                <div class="flex flex-col items-center text-center py-6 gap-6">

                    {{-- Red cross emblem (HTML/Tailwind only — no image files) --}}
                    <div class="relative w-20 h-20" data-reveal>
                        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-6 h-full bg-red-600 rounded-sm"></div>
                        <div class="absolute left-0 top-1/2 -translate-y-1/2 h-6 w-full bg-red-600 rounded-sm"></div>
                    </div>

                    <p class="text-3xl font-bold text-gray-900 leading-tight" data-reveal>
                        NRCS Volunteer Database
                    </p>

                    <p class="text-lg text-gray-600 max-w-md" data-reveal>
                        One database for members, volunteers and programmes across Nigeria.
                    </p>

                    <p class="text-base text-gray-400" data-reveal>
                        Short lessons &middot; progress saved automatically
                    </p>

                </div>
            </x-tutorial.slide>

            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- Slide 2 — What you can do (icon grid) --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}



            <x-tutorial.slide audio="tutorials/audio/level0-dbase_overview.mp3">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center" data-reveal>
                        What you can do here
                    </h2>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">

                        <div class="flex flex-col items-center gap-2 p-4 bg-gray-50 rounded-xl border border-gray-100 text-center" data-reveal>
                            <i class="fas fa-hand-holding-dollar text-3xl text-indigo-400"></i>
                            <p class="font-semibold text-gray-800">Payments</p>
                            <p class="text-xs text-gray-500">Fees and receipts</p>
                        </div>

                        <div class="flex flex-col items-center gap-2 p-4 bg-gray-50 rounded-xl border border-gray-100 text-center" data-reveal>
                            <i class="fas fa-hands-helping text-3xl text-indigo-400"></i>
                            <p class="font-semibold text-gray-800">Volunteering</p>
                            <p class="text-xs text-gray-500">Units and field activities</p>
                        </div>

                        <div class="flex flex-col items-center gap-2 p-4 bg-gray-50 rounded-xl border border-gray-100 text-center" data-reveal>
                            <i class="fas fa-heart text-3xl text-indigo-400"></i>
                            <p class="font-semibold text-gray-800">Donations</p>
                            <p class="text-xs text-gray-500">Individual and organisational</p>
                        </div>

                        <div class="flex flex-col items-center gap-2 p-4 bg-gray-50 rounded-xl border border-gray-100 text-center" data-reveal>
                            <i class="fas fa-graduation-cap text-3xl text-indigo-400"></i>
                            <p class="font-semibold text-gray-800">Trainings</p>
                            <p class="text-xs text-gray-500">First aid and certifications</p>
                        </div>

                        <div class="flex flex-col items-center gap-2 p-4 bg-gray-50 rounded-xl border border-gray-100 text-center" data-reveal>
                            <i class="fas fa-bullhorn text-3xl text-indigo-400"></i>
                            <p class="font-semibold text-gray-800">Campaigns</p>
                            <p class="text-xs text-gray-500">Email and SMS outreach</p>
                        </div>

                        <div class="flex flex-col items-center gap-2 p-4 bg-gray-50 rounded-xl border border-gray-100 text-center" data-reveal>
                            <!-- Icon Wrapper to put them side-by-side -->
                            <div class="flex items-center gap-3">
                                <i class="fa-solid fa-id-card text-2xl text-indigo-400"></i>
                                <i class="fa-solid fa-certificate text-2xl text-indigo-400"></i>
                            </div>

                            <p class="font-semibold text-gray-800">ID-cards & Certificates</p>
                            <p class="text-xs text-gray-500">Prepare and print</p>
                        </div>

                    </div>
                </div>
            </x-tutorial.slide>

            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{--  Organisation pyramid --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}

            <x-tutorial.slide audio="tutorials/audio/level0-rc_pyramid.mp3">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-7 text-center" data-reveal>
                        One country, four levels
                    </h2>
                    <div class="flex flex-col items-center gap-2">

                        <div class="w-1/3 py-3 px-4 bg-red-50 border border-red-200 rounded-lg text-center" data-reveal>
                            <p class="font-bold text-red-800 text-sm">National</p>
                            <p class="text-xs text-red-600 mt-0.5">headquarters</p>
                        </div>

                        <div class="w-1/2 py-3 px-4 bg-red-100 border border-red-200 rounded-lg text-center" data-reveal>
                            <p class="font-bold text-red-800 text-sm">37 Branches</p>
                            <p class="text-xs text-red-600 mt-0.5">one per state + FCT</p>
                        </div>

                        <div class="w-3/4 py-3 px-4 bg-red-200 border border-red-300 rounded-lg text-center" data-reveal>
                            <p class="font-bold text-red-900 text-sm">Divisions (LGA)</p>
                            <p class="text-xs text-red-700 mt-0.5">local government areas</p>
                        </div>

                        <div class="w-full py-3 px-4 bg-red-300 border border-red-400 rounded-lg text-center" data-reveal>
                            <p class="font-bold text-red-900 text-sm">Red Cross Units (Detachments)</p>
                            <p class="text-xs text-red-800 mt-0.5">where volunteers belong</p>
                        </div>

                    </div>

                </div>
            </x-tutorial.slide >

            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- Slide 4 — Tutorial levels --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}

            <x-tutorial.slide audio="tutorials/audio/level0-tutorial_levels.mp3">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-7 text-center" data-reveal>
                        Three levels of training
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                        <div class="flex flex-col items-center gap-2 p-5 bg-indigo-50 border border-indigo-200 rounded-xl text-center" data-reveal>
                            <i class="fas fa-rocket text-2xl text-indigo-500"></i>
                            <p class="font-semibold text-indigo-900">Level 1</p>
                            <p class="text-sm text-indigo-700">For everyone — the basics</p>
                        </div>

                        <div class="flex flex-col items-center gap-2 p-5 bg-gray-50 border border-gray-200 rounded-xl text-center opacity-60" data-reveal>
                            <div class="relative inline-block">
                                <i class="fas fa-sitemap text-2xl text-gray-400"></i>
                                <i class="fas fa-lock text-xs text-gray-400 absolute -top-1 -right-3"></i>
                            </div>
                            <p class="font-semibold text-gray-600">Level 2</p>
                            <p class="text-sm text-gray-500">Branch &amp; national staff</p>
                        </div>

                        <div class="flex flex-col items-center gap-2 p-5 bg-gray-50 border border-gray-200 rounded-xl text-center opacity-60" data-reveal>
                            <div class="relative inline-block">
                                <i class="fas fa-globe-africa text-2xl text-gray-400"></i>
                                <i class="fas fa-lock text-xs text-gray-400 absolute -top-1 -right-3"></i>
                            </div>
                            <p class="font-semibold text-gray-600">Level 3</p>
                            <p class="text-sm text-gray-500">National staff only</p>
                        </div>

                    </div>

                </div>
            </x-tutorial.slide>

            <x-tutorial.slide audio="tutorials/audio/level0-roles.mp3">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center" data-reveal>Who does what?</h2>
                    <div class="max-w-3xl mx-auto space-y-4">

                        {{-- National tier --}}
                        <div class="flex flex-col md:flex-row md:items-stretch gap-3" data-reveal>
                            <div class="md:w-28 flex items-center justify-center rounded-xl bg-red-600 text-white font-semibold text-sm py-2">
                                National
                            </div>
                            <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="rounded-xl bg-gray-50 border border-gray-100 p-3 text-center">
                                    <i class="fas fa-user-shield text-2xl text-red-500 mb-1"></i>
                                    <p class="font-semibold text-gray-800 text-sm">National DB Administrator</p>
                                    <p class="text-xs text-gray-500">Authorizes &amp; oversees</p>
                                </div>
                                <div class="rounded-xl bg-gray-50 border border-gray-100 p-3 text-center">
                                    <i class="fas fa-keyboard text-2xl text-red-400 mb-1"></i>
                                    <p class="font-semibold text-gray-800 text-sm">National DB Assistant</p>
                                    <p class="text-xs text-gray-500">National data entry</p>
                                </div>
                                <div class="rounded-xl bg-gray-50 border border-gray-100 p-3 text-center">
                                    <i class="fas fa-eye text-2xl text-gray-400 mb-1"></i>
                                    <p class="font-semibold text-gray-800 text-sm">Observer</p>
                                    <p class="text-xs text-gray-500">Read-only reports</p>
                                </div>
                            </div>
                        </div>

                        {{-- Branch tier --}}
                        <div class="flex flex-col md:flex-row md:items-stretch gap-3" data-reveal>
                            <div class="md:w-28 flex items-center justify-center rounded-xl bg-red-400 text-white font-semibold text-sm py-2">
                                Branch
                            </div>
                            <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="rounded-xl bg-gray-50 border border-gray-100 p-3 text-center">
                                    <i class="fas fa-user-tie text-2xl text-red-500 mb-1"></i>
                                    <p class="font-semibold text-gray-800 text-sm">Branch Secretary</p>
                                    <p class="text-xs text-gray-500">Branch administration</p>
                                </div>
                                <div class="rounded-xl bg-gray-50 border border-gray-100 p-3 text-center">
                                    <i class="fas fa-user-shield text-2xl text-red-500 mb-1"></i>
                                    <p class="font-semibold text-gray-800 text-sm">Branch DB Administrator</p>
                                    <p class="text-xs text-gray-500">Same authority</p>
                                </div>
                                <div class="rounded-xl bg-gray-50 border border-gray-100 p-3 text-center">
                                    <i class="fas fa-keyboard text-2xl text-red-400 mb-1"></i>
                                    <p class="font-semibold text-gray-800 text-sm">Branch DB Assistant</p>
                                    <p class="text-xs text-gray-500">Branch data entry</p>
                                </div>
                            </div>
                        </div>

                        {{-- Division tier --}}
                        <div class="flex flex-col md:flex-row md:items-stretch gap-3" data-reveal>
                            <div class="md:w-28 flex items-center justify-center rounded-xl bg-red-200 text-red-900 font-semibold text-sm py-2">
                                Division
                            </div>
                            <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="rounded-xl bg-gray-50 border border-gray-100 p-3 text-center">
                                    <i class="fas fa-coins text-2xl text-amber-500 mb-1"></i>
                                    <p class="font-semibold text-gray-800 text-sm">Division Assistant — Finance</p>
                                    <p class="text-xs text-gray-500">Payments, Donations, Trainings &amp; Volunteering</p>
                                </div>
                                <div class="rounded-xl bg-gray-50 border border-gray-100 p-3 text-center">
                                    <i class="fas fa-clipboard-list text-2xl text-indigo-400 mb-1"></i>
                                    <p class="font-semibold text-gray-800 text-sm">Division Assistant — Operations</p>
                                    <p class="text-xs text-gray-500">Trainings &amp; Volunteering</p>
                                </div>
                            </div>
                        </div>

                        <p class="text-base text-gray-500 text-center pt-2" data-reveal>
                            <i class="fas fa-circle-info mr-1"></i> You only need to know your own role.
                        </p>
                    </div>
                </div>
            </x-tutorial.slide>

            <x-tutorial.slide audio="tutorials/audio/level0-member_volunteer.mp3">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center" data-reveal>Member or volunteer?</h2>
                    <div class="max-w-3xl mx-auto">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                            {{-- Member --}}
                            <div class="rounded-xl bg-gray-50 border border-gray-100 p-6 text-center" data-reveal>
                                <i class="fas fa-id-card text-4xl text-indigo-500 mb-3"></i>
                                <p class="text-lg font-bold text-gray-900 mb-2">Member</p>
                                <p class="text-sm text-gray-600">Pays a membership fee</p>
                                <p class="text-sm text-gray-600">Not in a Red Cross Unit</p>
                            </div>

                            {{-- Volunteer --}}
                            <div class="rounded-xl bg-gray-50 border border-gray-100 p-6 text-center" data-reveal>
                                <i class="fas fa-hands-helping text-4xl text-red-500 mb-3"></i>
                                <p class="text-lg font-bold text-gray-900 mb-2">Volunteer</p>
                                <p class="text-sm text-gray-600">Serves in a Red Cross Unit</p>
                                <p class="text-sm text-gray-600">Hands-on work in the field</p>
                            </div>
                        </div>

                        {{-- The deciding question --}}
                        <div class="mt-6 rounded-xl border border-gray-200 bg-white p-4 text-center" data-reveal>
                            <p class="font-semibold text-gray-800 mb-3">
                                <i class="fas fa-circle-question text-indigo-400 mr-1"></i>
                                In a Red Cross Unit?
                            </p>
                            <div class="flex flex-col sm:flex-row justify-center gap-3 text-sm">
                    <span class="inline-flex items-center justify-center gap-2 rounded-full bg-red-50 text-red-700 px-4 py-1.5 font-medium">
                        Yes <i class="fas fa-arrow-right text-xs"></i> Volunteer
                    </span>
                                <span class="inline-flex items-center justify-center gap-2 rounded-full bg-indigo-50 text-indigo-700 px-4 py-1.5 font-medium">
                        No + fee paid <i class="fas fa-arrow-right text-xs"></i> Member
                    </span>
                            </div>
                        </div>
                    </div>
                </div>
            </x-tutorial.slide>

            <x-tutorial.slide audio="tutorials/audio/level0-lifecycle.mp3">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center" data-reveal>Four lifecycle statuses</h2>
                    <div class="max-w-4xl mx-auto flex flex-col md:flex-row gap-4 items-stretch">

                        {{-- Still with us: Pending + Active + Dormant --}}
                        <div class="md:flex-[3] rounded-xl border border-green-200 bg-green-50/50 p-4" data-reveal>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="rounded-xl bg-white border border-gray-100 p-5 text-center">
                                    <i class="fas fa-seedling text-3xl text-sky-500 mb-2"></i>
                                    <p class="font-bold text-gray-900">Pending engagement</p>
                                    <p class="text-xs text-gray-500">New — to be welcomed</p>
                                </div>
                                <div class="rounded-xl bg-white border border-gray-100 p-5 text-center">
                                    <i class="fas fa-heart-pulse text-3xl text-green-500 mb-2"></i>
                                    <p class="font-bold text-gray-900">Active</p>
                                    <p class="text-xs text-gray-500">Currently involved</p>
                                </div>
                                <div class="rounded-xl bg-white border border-gray-100 p-5 text-center">
                                    <i class="fas fa-moon text-3xl text-amber-500 mb-2"></i>
                                    <p class="font-bold text-gray-900">Dormant</p>
                                    <p class="text-xs text-gray-500">Gone quiet — re-engage</p>
                                </div>
                            </div>
                            <p class="text-center text-sm font-semibold text-green-700 mt-3" data-reveal>
                                <i class="fas fa-people-group mr-1"></i> Still with us
                            </p>
                        </div>

                        {{-- No longer with us: Archived --}}
                        <div class="md:flex-1 rounded-xl border border-gray-200 bg-gray-50 p-4 flex flex-col" data-reveal>
                            <div class="rounded-xl bg-white border border-gray-100 p-5 text-center flex-1 flex flex-col justify-center">
                                <i class="fas fa-box-archive text-3xl text-gray-400 mb-2"></i>
                                <p class="font-bold text-gray-900">Archived</p>
                                <p class="text-xs text-gray-500">Has left the system</p>
                            </div>
                            <p class="text-center text-sm font-semibold text-gray-500 mt-3" data-reveal>
                                <i class="fas fa-door-open mr-1"></i> No longer with us
                            </p>
                        </div>
                    </div>


                </div>
            </x-tutorial.slide>

            <x-tutorial.slide audio="tutorials/audio/level0-groups.mp3">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center" data-reveal>Three kinds of group</h2>
                    <div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-4">

                        {{-- Red Cross Unit --}}
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-6 text-center" data-reveal>
                            <i class="fas fa-house-flag text-4xl text-red-500 mb-3"></i>
                            <p class="text-lg font-bold text-gray-900 mb-2">Red Cross Unit</p>
                            <span class="inline-block rounded-full bg-red-100 text-red-700 text-xs font-semibold px-3 py-1 mb-3">Permanent</span>
                            <p class="text-sm text-gray-600">Home base for volunteers</p>
                            <p class="text-xs text-gray-500 mt-1">Within a division</p>
                        </div>

                        {{-- Task Force --}}
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-6 text-center" data-reveal>
                            <i class="fas fa-user-clock text-4xl text-amber-500 mb-3"></i>
                            <p class="text-lg font-bold text-gray-900 mb-2">Task Force</p>
                            <span class="inline-block rounded-full bg-amber-100 text-amber-700 text-xs font-semibold px-3 py-1 mb-3">Temporary</span>
                            <p class="text-sm text-gray-600">Open to anyone</p>
                            <p class="text-xs text-gray-500 mt-1">Created by a branch</p>
                        </div>

                        {{-- Organisation --}}
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-6 text-center" data-reveal>
                            <i class="fas fa-building text-4xl text-indigo-500 mb-3"></i>
                            <p class="text-lg font-bold text-gray-900 mb-2">Organisation</p>
                            <span class="inline-block rounded-full bg-indigo-100 text-indigo-700 text-xs font-semibold px-3 py-1 mb-3">External</span>
                            <p class="text-sm text-gray-600">Company or institution</p>
                            <p class="text-xs text-gray-500 mt-1">Within a branch</p>
                        </div>
                    </div>
                </div>
            </x-tutorial.slide>


            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- — Done --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}

            <x-tutorial.slide  audio="tutorials/audio/level0-last_slide.mp3">
                <div class="flex flex-col items-center text-center py-6 gap-5">

                    <i class="fas fa-check-circle text-6xl text-green-500" data-reveal></i>

                    <p class="text-2xl font-bold text-gray-900" data-reveal>Lesson complete</p>

                    <p class="text-gray-500" data-reveal>
                        Next: Level 1 operations
                    </p>


                </div>
            </x-tutorial.slide>

        </x-tutorial.player>

    </div>
</x-layouts.admin>
