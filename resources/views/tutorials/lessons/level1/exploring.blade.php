<x-layouts.admin :title="$lessonTitle">
    <x-slot name="pageHeader"><i class="fas fa-compass mr-3"></i> {{ $lessonTitle }}</x-slot>
    <x-slot name="subHeader">Level 1: Getting Started</x-slot>

    <div class="p-4 md:p-6">
        <a href="{{ route('tutorials.level', 1) }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 mb-6">
            <i class="fas fa-arrow-left"></i> Back to Level 1 lessons
        </a>

        <x-tutorial.player :lessonKey="$lessonKey" :title="$lessonTitle">

            {{-- Slide — Intro --}}
            <x-tutorial.slide audio="tutorials/audio/level1-exploring-intro.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Exploring the database</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        Beyond people and records, the database has views for groups, branches, cards, certificates, and stats.
                    </p>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <i class="fas fa-people-group text-2xl text-indigo-400 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">Units &amp; Task Forces</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <i class="fas fa-building-flag text-2xl text-indigo-400 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">Branches &amp; Divisions</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <i class="fas fa-id-card text-2xl text-indigo-400 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">ID Cards</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <i class="fas fa-certificate text-2xl text-indigo-400 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">Certificates</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4 col-span-2 sm:col-span-1" data-reveal>
                            <i class="fas fa-gauge-high text-2xl text-indigo-400 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">Dashboard</p>
                        </div>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — Red Cross Units & Task Forces --}}
            <x-tutorial.slide audio="tutorials/audio/level1-exploring-units_taskforces.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Units and Task Forces</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        Two views for groups — search, filter, and view the people inside.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-left mb-6">

                        {{-- Red Cross Units --}}
                        <div class="rounded-xl bg-red-50 border border-red-200 p-5" data-reveal>
                            <p class="font-semibold text-red-800 mb-1">
                                <i class="fas fa-shield-alt mr-1"></i> Red Cross Units
                            </p>
                            <p class="text-sm text-gray-700">The permanent home base for volunteers, within a division.</p>
                        </div>

                        {{-- Task Forces --}}
                        <div class="rounded-xl bg-indigo-50 border border-indigo-200 p-5" data-reveal>
                            <p class="font-semibold text-indigo-800 mb-1">
                                <i class="fas fa-users-gear mr-1"></i> Task Forces
                            </p>
                            <p class="text-sm text-gray-700">A temporary team, open to anyone.</p>
                        </div>
                    </div>

                    {{-- Shared workflow --}}
                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-5 mb-6" data-reveal>
                        <p class="font-semibold text-gray-800 mb-3 text-sm">
                            <i class="fas fa-compass text-gray-400 mr-1"></i> In both views, you can:
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="flex items-center gap-2 justify-center">
                                <i class="fas fa-sliders text-gray-400"></i>
                                <span class="text-sm text-gray-700">Filter by branch</span>
                            </div>
                            <div class="flex items-center gap-2 justify-center">
                                <i class="fas fa-magnifying-glass text-gray-400"></i>
                                <span class="text-sm text-gray-700">Search by name</span>
                            </div>
                            <div class="flex items-center gap-2 justify-center">
                                <i class="fas fa-up-right-from-square text-gray-400"></i>
                                <span class="text-sm text-gray-700">Click View for members</span>
                            </div>
                        </div>
                    </div>

                    {{-- Reminder --}}
                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3 text-left" data-reveal>
                        <i class="fas fa-rotate text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            Remember: a <strong>unit</strong> is where volunteers permanently belong. A <strong>task force</strong> is temporary, and dissolved once the work is done.
                        </p>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — Branches & Divisions --}}
            <x-tutorial.slide audio="tutorials/audio/level1-exploring-branches_divisions.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Branches and Divisions</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        Two more views — for the structure above units, with contact details and stats.
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-left mb-6">

                        {{-- Branches --}}
                        <div class="rounded-xl bg-red-50 border border-red-200 p-5" data-reveal>
                            <p class="font-semibold text-red-800 mb-1">
                                <i class="fas fa-sitemap mr-1"></i> Branches
                            </p>
                            <p class="text-sm text-gray-700">The state-level structure — one step below National.</p>
                        </div>

                        {{-- Divisions --}}
                        <div class="rounded-xl bg-indigo-50 border border-indigo-200 p-5" data-reveal>
                            <p class="font-semibold text-indigo-800 mb-1">
                                <i class="fas fa-layer-group mr-1"></i> Divisions
                            </p>
                            <p class="text-sm text-gray-700">The layer between a branch and its Red Cross Units.</p>
                        </div>
                    </div>

                    {{-- What you can see --}}
                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-5 mb-6" data-reveal>
                        <p class="font-semibold text-gray-800 mb-3 text-sm">
                            <i class="fas fa-compass text-gray-400 mr-1"></i> In both views, you can see:
                        </p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="flex items-center gap-2 justify-center">
                                <i class="fas fa-address-book text-gray-400"></i>
                                <span class="text-sm text-gray-700">Contact details</span>
                            </div>
                            <div class="flex items-center gap-2 justify-center">
                                <i class="fas fa-chart-column text-gray-400"></i>
                                <span class="text-sm text-gray-700">Members &amp; volunteers</span>
                            </div>
                            <div class="flex items-center gap-2 justify-center">
                                <i class="fas fa-up-right-from-square text-gray-400"></i>
                                <span class="text-sm text-gray-700">Click View for more</span>
                            </div>
                        </div>
                    </div>

                    {{-- Key contacts note --}}
                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3 text-left" data-reveal>
                        <i class="fas fa-id-badge text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            Every branch lists its <strong>key contact persons</strong> — so you always know who to reach there.
                        </p>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — ID Cards: is it ready to print? --}}
            <x-tutorial.slide audio="tutorials/audio/level1-exploring-idcards_ready.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Is the ID card ready to print?</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        Four things must be in place before a card can be printed.
                    </p>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4 text-center" data-reveal>
                            <i class="fas fa-image text-2xl text-indigo-400 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">Photo</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4 text-center" data-reveal>
                            <i class="fas fa-signature text-2xl text-indigo-400 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">Signature</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4 text-center" data-reveal>
                            <i class="fas fa-hand-holding-dollar text-2xl text-indigo-400 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">Membership fee paid</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4 text-center" data-reveal>
                            <i class="fas fa-id-badge text-2xl text-indigo-400 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">National ID</p>
                        </div>
                    </div>

                    {{-- Visual: card border meaning --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-left mb-6">
                        <div class="rounded-xl border-2 border-red-500 bg-white p-4" data-reveal>
                            <p class="text-sm font-semibold text-red-600 mb-1">
                                <i class="fas fa-triangle-exclamation mr-1"></i> Red border
                            </p>
                            <p class="text-xs text-gray-600">Something's missing — this card can't be printed yet.</p>
                        </div>
                        <div class="rounded-xl border-2 border-blue-500 bg-blue-50 p-4" data-reveal>
                            <p class="text-sm font-semibold text-blue-700 mb-1">
                                <i class="fas fa-circle-check mr-1"></i> Blue border
                            </p>
                            <p class="text-xs text-gray-600">Everything's in place — ready for printing.</p>
                        </div>
                    </div>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3 text-left" data-reveal>
                        <i class="fas fa-lightbulb text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            Prepare everything first — <strong>before</strong> requesting HQ to print.
                        </p>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — ID Cards: filtering, grouping, and print history --}}
            <x-tutorial.split-slide
                image="tutorials/img/level1-exploring-idcards_workflow.jpg"
                imageAlt="The ID Card Printing screen showing filters, a Red Cross Unit filter selected, and the View Print History button"
                heading="Finding cards and tracking prints"
                audio="tutorials/audio/level1-exploring-idcards_workflow.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Use <strong>Search</strong> or the <strong>Branch / Division / Unit</strong> filters to narrow the list.</x-tutorial.step>
                    <x-tutorial.step n="2">Tip: choosing a <strong>Division</strong> or <strong>Red Cross Unit</strong> groups the view — easier than scanning individuals one by one.</x-tutorial.step>
                    <x-tutorial.step n="3">Press <strong>View Print History</strong> to see which cards have already been printed.</x-tutorial.step>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-circle-info text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            This view is for finding and checking readiness — printing itself is covered separately.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Dashboard overview --}}
            <x-tutorial.slide audio="tutorials/audio/level1-exploring-dashboard_overview.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>The Dashboard</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        Your home base for exploring the national database at a glance.
                    </p>

                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-5 mb-6 text-left" data-reveal>
                        <p class="font-semibold text-gray-800 mb-2 text-sm">
                            <i class="fas fa-sliders text-indigo-400 mr-1"></i> Select branch
                        </p>
                        <p class="text-sm text-gray-600">
                            By default, you see your own branch. Use the dropdown to switch to any other branch — or back to <strong>National</strong> — and compare.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="rounded-xl bg-indigo-50 border border-indigo-200 p-4" data-reveal>
                            <i class="fas fa-chart-column text-2xl text-indigo-500 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">Key statistics</p>
                            <p class="text-xs text-gray-500">Members, volunteers, revenue, and more.</p>
                        </div>
                        <div class="rounded-xl bg-green-50 border border-green-200 p-4" data-reveal>
                            <i class="fas fa-heart-pulse text-2xl text-green-500 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">Lifecycle overview</p>
                            <p class="text-xs text-gray-500">Who needs your attention right now.</p>
                        </div>
                        <div class="rounded-xl bg-amber-50 border border-amber-200 p-4" data-reveal>
                            <i class="fas fa-map-location-dot text-2xl text-amber-500 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">Trends &amp; maps</p>
                            <p class="text-xs text-gray-500">Deeper reports and heat maps.</p>
                        </div>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — Lifecycle Overview --}}
            <x-tutorial.slide audio="tutorials/audio/level1-exploring-dashboard_lifecycle.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Lifecycle Overview</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        Three boxes show where everyone stands — and where they need help.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                        <div class="rounded-xl bg-sky-50 border border-sky-200 p-4" data-reveal>
                            <i class="fas fa-seedling text-2xl text-sky-500 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">Pending engagement</p>
                            <p class="text-xs text-gray-500">Needs first contact and guidance.</p>
                        </div>
                        <div class="rounded-xl bg-green-50 border border-green-300 p-4" data-reveal>
                            <i class="fas fa-heart-pulse text-2xl text-green-500 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">Active</p>
                            <p class="text-xs text-gray-500">Engaged and contributing.</p>
                        </div>
                        <div class="rounded-xl bg-amber-50 border border-amber-200 p-4" data-reveal>
                            <i class="fas fa-moon text-2xl text-amber-500 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">Dormant</p>
                            <p class="text-xs text-gray-500">Was active, has gone quiet.</p>
                        </div>
                    </div>

                    <div class="rounded-xl bg-green-50 border border-green-200 p-4 flex items-start gap-3 text-left mb-4" data-reveal>
                        <i class="fas fa-bullseye text-green-600 mt-1"></i>
                        <p class="text-sm text-green-900">
                            <strong>The challenge:</strong> keep the Active box growing — move people from Pending and Dormant into Active.
                        </p>
                    </div>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3 text-left" data-reveal>
                        <i class="fas fa-bolt text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            Click <strong>Take action</strong> on any box for step-by-step tips on what to do next.
                        </p>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — Trends, Statistics & Heat Maps --}}
            <x-tutorial.slide audio="tutorials/audio/level1-exploring-dashboard_trends.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Trends, Statistics &amp; Heat Maps</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        Further down the dashboard, dig deeper into the numbers.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-5 text-left" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1">
                                <i class="fas fa-chart-line text-indigo-400 mr-1"></i> Trends &amp; Statistics
                            </p>
                            <p class="text-sm text-gray-600">Reports on membership, volunteers, finances, donations, training, and registrations.</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-5 text-left" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1">
                                <i class="fas fa-map-location-dot text-indigo-400 mr-1"></i> Heat Maps
                            </p>
                            <p class="text-sm text-gray-600">Bubble charts showing which branches and divisions are most active.</p>
                        </div>
                    </div>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3 text-left" data-reveal>
                        <i class="fas fa-lightbulb text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            Bigger bubbles mean more activity — a quick way to spot where things are thriving, and where they aren't.
                        </p>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — Lesson & Level 1 complete --}}
            <x-tutorial.slide audio="tutorials/audio/level1-exploring-complete.mp3">
                <div class="text-center max-w-xl mx-auto">

                    {{-- Check emblem --}}
                    <div class="flex justify-center mb-6" data-reveal>
                        <div class="w-24 h-24 rounded-full bg-green-50 border-4 border-green-100 flex items-center justify-center">
                            <i class="fas fa-circle-check text-5xl text-green-500"></i>
                        </div>
                    </div>

                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Lesson complete</h2>
                    <p class="text-gray-600 mb-8" data-reveal>You can now find your way around units, branches, ID cards, certificates, and the dashboard.</p>

                    {{-- Recap pills — this lesson --}}
                    <div class="flex flex-wrap justify-center gap-3 mb-8" data-reveal>
            <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-shield-alt text-green-500"></i> Units &amp; Task Forces
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-sitemap text-green-500"></i> Branches &amp; Divisions
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-id-card text-green-500"></i> ID Cards
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-certificate text-green-500"></i> Certificates
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-gauge-high text-green-500"></i> The Dashboard
            </span>
                    </div>

                    {{-- Level 1 complete banner --}}
                    <div class="rounded-xl bg-red-50 border-2 border-red-200 p-5 mb-6" data-reveal>
                        <p class="font-bold text-red-700 text-lg mb-2">
                            <i class="fas fa-trophy mr-1"></i> Level 1 complete!
                        </p>
                        <p class="text-sm text-red-900">
                            You've covered the basics: definitions and lifecycle, finding people, registering records, and exploring the database.
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
