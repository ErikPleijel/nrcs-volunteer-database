<x-layouts.admin :title="$lessonTitle">
    <x-slot name="pageHeader">
        <i class="fas fa-book-open mr-3"></i> {{ $lessonTitle }}
    </x-slot>
    <x-slot name="subHeader">Level 1: Getting Started</x-slot>

    <div class="p-4 md:p-6">

        <a href="{{ route('tutorials.index') }}"
           class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 mb-6">
            <i class="fas fa-arrow-left"></i> Back to lessons
        </a>

        <x-tutorial.player :lessonKey="$lessonKey" :title="$lessonTitle">

            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- Slide 1 --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            <x-tutorial.slide audio="tutorials/audio/level1-find_people-hero.mp3">
                <div class="text-center max-w-2xl mx-auto">

                    {{-- Compass emblem --}}
                    <div class="flex justify-center mb-8" data-reveal>
                        <div class="relative w-28 h-28 rounded-full bg-indigo-50 border-4 border-indigo-100 flex items-center justify-center">
                            <i class="fas fa-compass text-6xl text-indigo-500"></i>
                        </div>
                    </div>

                    <h1 class="text-3xl font-bold text-gray-900 mb-3" data-reveal>Finding people</h1>

                    <p class="text-lg text-gray-600 mb-8" data-reveal>
                        How to move through the database and find any person.
                    </p>

                    {{-- Preview pills --}}
                    <div class="flex flex-wrap justify-center gap-3" data-reveal>
                    <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                        <i class="fas fa-bars text-indigo-400"></i> The main menu
                    </span>
                                <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                        <i class="fas fa-filter text-indigo-400"></i> Searching &amp; filtering
                    </span>
                                <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                        <i class="fas fa-id-badge text-indigo-400"></i> Opening a record
                    </span>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- Slide 2 --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}

            <x-tutorial.slide audio="tutorials/audio/level1-find_people-two_tabs.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6" data-reveal>A handy tip: use two tabs</h2>

                    <div class="flex flex-col sm:flex-row items-stretch justify-center gap-4">

                        {{-- Tab 1: tutorial --}}
                        <div class="flex-1 rounded-xl border border-gray-200 overflow-hidden shadow-sm" data-reveal>
                            <div class="flex items-center gap-1.5 bg-gray-100 px-3 py-2 border-b border-gray-200">
                                <span class="w-2.5 h-2.5 rounded-full bg-red-300"></span>
                                <span class="w-2.5 h-2.5 rounded-full bg-amber-300"></span>
                                <span class="w-2.5 h-2.5 rounded-full bg-green-300"></span>
                                <span class="ml-2 text-base text-gray-500">Tab 1</span>
                            </div>
                            <div class="p-6 bg-gray-50">
                                <i class="fas fa-graduation-cap text-3xl text-indigo-500 mb-2"></i>
                                <p class="font-semibold text-gray-800">This tutorial</p>
                                <p class="text-xs text-gray-500">Follow the lessons</p>
                            </div>
                        </div>

                        {{-- Connector --}}
                        <div class="hidden sm:flex items-center" data-reveal>
                            <i class="fas fa-arrows-left-right text-gray-300 text-xl"></i>
                        </div>

                        {{-- Tab 2: database --}}
                        <div class="flex-1 rounded-xl border border-gray-200 overflow-hidden shadow-sm" data-reveal>
                            <div class="flex items-center gap-1.5 bg-gray-100 px-3 py-2 border-b border-gray-200">
                                <span class="w-2.5 h-2.5 rounded-full bg-red-300"></span>
                                <span class="w-2.5 h-2.5 rounded-full bg-amber-300"></span>
                                <span class="w-2.5 h-2.5 rounded-full bg-green-300"></span>
                                <span class="ml-2 text-base text-gray-500">Tab 2</span>
                            </div>
                            <div class="p-6 bg-gray-50">
                                <i class="fas fa-database text-3xl text-gray-500 mb-2"></i>
                                <p class="font-semibold text-gray-800">The database</p>
                                <p class="text-xs text-gray-500">Explore &amp; practise</p>
                            </div>
                        </div>
                    </div>

                    <p class="text-sm text-gray-500 mt-6" data-reveal>
                        <i class="fas fa-desktop text-indigo-400 mr-1"></i>
                        Best done on a desktop or laptop computer.
                    </p>
                </div>
            </x-tutorial.slide>

            {{-- ═══════════════════════════════════════════════════════════════ --}}
            {{-- Slide 3 --}}
            {{-- ═══════════════════════════════════════════════════════════════ --}}
            <x-tutorial.split-slide
                image="tutorials/img/level1-find_people-menu.jpg"
                imageAlt="The main menu with Persons highlighted, and the Persons page heading"
                heading="Open the Persons page"
                audio="tutorials/audio/level1-find_people-menu.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Open the <strong>main menu</strong> on the left of your screen.</x-tutorial.step>
                    <x-tutorial.step n="2">Click <strong>Persons</strong>.</x-tutorial.step>
                    <x-tutorial.step n="3">You arrive at the <strong>Persons</strong> page — headed <em>Find &amp; Filter</em>.</x-tutorial.step>
                    <p class="text-sm text-gray-500 mt-4" data-reveal>
                        <i class="fas fa-circle-info text-indigo-400 mr-1"></i>
                        This is your home base — every member and every volunteer can be found from here.
                    </p>
                </div>
            </x-tutorial.split-slide>

            <x-tutorial.split-slide
                image="tutorials/img/level1-find_people-filter_rowx.jpg"
                imageAlt="The filter row on the Persons page, with the Search box at the top left"
                heading="Search for a person"
                audio="tutorials/audio/level1-find_people-search.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Type into the <strong>Search</strong> box at the top left.</x-tutorial.step>
                    <x-tutorial.step n="2">You can search by <strong>name</strong>, <strong>email</strong>, or <strong>DB&#8209;number</strong>.</x-tutorial.step>
                    <x-tutorial.step n="3">Press the <strong>Filter</strong> button to see the results.</x-tutorial.step>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4" data-reveal>
                        <p class="text-sm font-semibold text-gray-800 mb-2">
                            <i class="fas fa-hashtag text-indigo-400 mr-1"></i> What is a DB-number?
                        </p>
                        <p class="text-xs text-gray-600 mb-3">A unique code the database gives to every person, so no two are ever confused.</p>

                        <div class="font-mono text-sm bg-white rounded-lg border border-gray-200 px-3 py-2 text-center tracking-tight">
                            <span class="text-gray-400">DB</span><span class="text-gray-300">-</span><span class="text-indigo-600 font-semibold">7442</span><span class="text-gray-300">-</span><span class="text-red-500 font-semibold">LAG</span><span class="text-gray-300">-</span><span class="text-green-600 font-semibold">Ikeja</span>
                        </div>

                        <div class="mt-3 space-y-1.5 text-xs text-gray-600">
                            <div><span class="font-mono text-indigo-600 font-semibold">7442</span> — the person's ID number</div>
                            <div><span class="font-mono text-red-500 font-semibold">LAG</span> — branch code (Lagos)</div>
                            <div><span class="font-mono text-green-600 font-semibold">Ikeja</span> — division</div>
                        </div>
                    </div>
                </div>
            </x-tutorial.split-slide>

            <x-tutorial.split-slide
                stacked
                image="tutorials/img/level1-find_people-result_row.jpg"
                imageAlt="A single person's row in the Persons list"
                heading="Reading a person's row"
                audio="tutorials/audio/level1-find_people-result_row.mp3">
                <div>
                    <p class="text-gray-600 mb-6" data-reveal>
                        Each result is one row. From left to right: who the person is, how to reach them, and how they are doing.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <i class="fas fa-user text-2xl text-indigo-400 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">Who</p>
                            <p class="text-xs text-gray-500">Photo and name</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <i class="fas fa-location-dot text-2xl text-red-400 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">Where &amp; contact</p>
                            <p class="text-xs text-gray-500">Branch, division, unit, email, phone</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <i class="fas fa-circle-check text-2xl text-green-500 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">Status pills</p>
                            <p class="text-xs text-gray-500">Lifecycle, membership, activity</p>
                        </div>
                    </div>


                    <div class="mt-8 rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-wifi text-amber-500 mt-1"></i>
                        <div class="text-sm text-amber-900">
                            <p class="font-semibold mb-1"><i class="far fa-square mr-1"></i> Show profile photo</p>
                            <p>Tick this box to display photos in the list. Photos use mobile data each time the list loads — best turned on only when you are on wifi.</p>
                        </div>
                    </div>
                </div>
            </x-tutorial.split-slide>

            <x-tutorial.split-slide
                image="tutorials/img/level1-find_people-show_identity.jpg"
                imageAlt="The Identity section at the top of a person's record"
                heading="The person's full record"
                audio="tutorials/audio/level1-find_people-show_record.mp3">
                <div>
                    <p class="text-gray-600 mb-5" data-reveal>
                        Clicking <strong>View</strong> opens everything the database knows about one person, arranged in clear sections.
                    </p>

                    <div class="space-y-2.5">
                        <div class="flex items-center gap-3" data-reveal>
                            <span class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center shrink-0"><i class="fas fa-id-badge text-gray-600 text-sm"></i></span>
                            <span class="text-sm"><strong class="text-gray-800">Identity</strong> <span class="text-gray-500">— name, photo, DB-number</span></span>
                        </div>
                        <div class="flex items-center gap-3" data-reveal>
                            <span class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center shrink-0"><i class="fas fa-phone text-gray-600 text-sm"></i></span>
                            <span class="text-sm"><strong class="text-gray-800">Contact</strong> <span class="text-gray-500">— email, phone, address</span></span>
                        </div>
                        <div class="flex items-center gap-3" data-reveal>
                            <span class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center shrink-0"><i class="fas fa-briefcase text-gray-600 text-sm"></i></span>
                            <span class="text-sm"><strong class="text-gray-800">Professional</strong> <span class="text-gray-500">— work and education</span></span>
                        </div>
                        <div class="flex items-center gap-3" data-reveal>
                            <span class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center shrink-0"><i class="fas fa-hand-holding-heart text-gray-600 text-sm"></i></span>
                            <span class="text-sm"><strong class="text-gray-800">Red Cross Affiliation</strong> <span class="text-gray-500">— branch, unit, task force</span></span>
                        </div>
                        <div class="flex items-center gap-3" data-reveal>
                            <span class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center shrink-0"><i class="fas fa-chart-line text-gray-600 text-sm"></i></span>
                            <span class="text-sm"><strong class="text-gray-800">Activity Status</strong> <span class="text-gray-500">— how active they are</span></span>
                        </div>
                        <div class="flex items-center gap-3" data-reveal>
                            <span class="w-8 h-8 rounded-lg bg-gray-100 flex items-center justify-center shrink-0"><i class="fas fa-sliders text-gray-600 text-sm"></i></span>
                            <span class="text-sm"><strong class="text-gray-800">System &amp; Registration</strong> <span class="text-gray-500">— login and sign-up details</span></span>
                        </div>
                    </div>
                </div>
            </x-tutorial.split-slide>

            <x-tutorial.split-slide
                image="tutorials/img/level1-find_people-show_lists.jpg"
                imageAlt="The Membership and history lists further down a person's record"
                heading="Their history, list by list"
                audio="tutorials/audio/level1-find_people-show_lists.mp3">
                <div>
                    <p class="text-gray-600 mb-5" data-reveal>
                        Scroll further down and you reach the person's full history — each kind of record in its own list.
                    </p>

                    <div class="space-y-2.5">
                        <div class="flex items-center gap-3" data-reveal>
                            <span class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center shrink-0"><i class="fas fa-id-card text-blue-600 text-sm"></i></span>
                            <span class="text-sm"><strong class="text-gray-800">Membership</strong> <span class="text-gray-500">— payments and current status</span></span>
                        </div>
                        <div class="flex items-center gap-3" data-reveal>
                            <span class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center shrink-0"><i class="fas fa-hands-helping text-orange-600 text-sm"></i></span>
                            <span class="text-sm"><strong class="text-gray-800">Volunteering</strong> <span class="text-gray-500">— activities and hours</span></span>
                        </div>
                        <div class="flex items-center gap-3" data-reveal>
                            <span class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center shrink-0"><i class="fas fa-graduation-cap text-purple-600 text-sm"></i></span>
                            <span class="text-sm"><strong class="text-gray-800">Training</strong> <span class="text-gray-500">— courses completed</span></span>
                        </div>
                        <div class="flex items-center gap-3" data-reveal>
                            <span class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center shrink-0"><i class="fas fa-hand-holding-heart text-green-600 text-sm"></i></span>
                            <span class="text-sm"><strong class="text-gray-800">Donations</strong> <span class="text-gray-500">— cash and in-kind gifts</span></span>
                        </div>
                        <div class="flex items-center gap-3" data-reveal>
                            <span class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center shrink-0"><i class="fas fa-envelope text-indigo-600 text-sm"></i></span>
                            <span class="text-sm"><strong class="text-gray-800">Messages Sent</strong> <span class="text-gray-500">— campaigns they received</span></span>
                        </div>
                        <div class="flex items-center gap-3" data-reveal>
                            <span class="w-8 h-8 rounded-lg bg-indigo-100 flex items-center justify-center shrink-0"><i class="fas fa-print text-indigo-600 text-sm"></i></span>
                            <span class="text-sm"><strong class="text-gray-800">Printed Certificates</strong> <span class="text-gray-500">— what has been issued</span></span>
                        </div>
                        <div class="flex items-center gap-3" data-reveal>
                            <span class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0"><i class="fas fa-id-card text-emerald-600 text-sm"></i></span>
                            <span class="text-sm"><strong class="text-gray-800">Printed ID Cards</strong> <span class="text-gray-500">— cards printed, with expiry</span></span>
                        </div>
                    </div>
                </div>
            </x-tutorial.split-slide>

            <x-tutorial.split-slide
                image="tutorials/img/level1-find_people-filtering.jpg"
                imageAlt="Gender set to Male and age 20 to 25, showing 13 results with a filter description line"
                heading="Combine filters to narrow the list"
                audio="tutorials/audio/level1-find_people-filtering.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Set one or more filters — here, <strong>Gender: Male</strong> and <strong>Age 18-35</strong>.</x-tutorial.step>
                    <x-tutorial.step n="2">Press <strong>Filter</strong>.</x-tutorial.step>
                    <x-tutorial.step n="3">The count shows how many people match — <strong>618 found</strong>.</x-tutorial.step>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4" data-reveal>
                        <p class="text-sm font-semibold text-gray-800 mb-1">
                            <i class="fas fa-list-check text-indigo-400 mr-1"></i> Read the filter description
                        </p>
                        <p class="text-xs text-gray-600">
                            The line beneath the count restates your filters in plain words — a quick check that you set what you meant.
                        </p>
                    </div>

                    <p class="text-sm text-gray-500" data-reveal>
                        <i class="fas fa-circle-info text-indigo-400 mr-1"></i>
                        By default the list shows operational people — <strong>Active &amp; Dormant</strong> — never pending or archived.
                    </p>
                </div>
            </x-tutorial.split-slide>

            <x-tutorial.slide audio="tutorials/audio/level1-find_people-complete.mp3">
                <div class="text-center max-w-xl mx-auto">

                    {{-- Check emblem --}}
                    <div class="flex justify-center mb-6" data-reveal>
                        <div class="w-24 h-24 rounded-full bg-green-50 border-4 border-green-100 flex items-center justify-center">
                            <i class="fas fa-circle-check text-5xl text-green-500"></i>
                        </div>
                    </div>

                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Lesson complete</h2>
                    <p class="text-gray-600 mb-8" data-reveal>You can now find anyone in the database and read their record.</p>

                    {{-- Recap pills --}}
                    <div class="flex flex-wrap justify-center gap-3 mb-8" data-reveal>
            <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-magnifying-glass text-green-500"></i> Search
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-filter text-green-500"></i> Filter
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-list text-green-500"></i> Read a row
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-id-badge text-green-500"></i> Open a record
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
