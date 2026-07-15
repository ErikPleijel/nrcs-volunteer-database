<x-layouts.admin :title="$lessonTitle">
    <x-slot name="pageHeader"><i class="fas fa-broom mr-3"></i> {{ $lessonTitle }}</x-slot>
    <x-slot name="subHeader">Level 2: Branch Administration</x-slot>

    <div class="p-4 md:p-6">
        <a href="{{ route('tutorials.level', 2) }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 mb-6">
            <i class="fas fa-arrow-left"></i> Back to Level 2 lessons
        </a>

        <x-tutorial.player :lessonKey="$lessonKey" :title="$lessonTitle">

            {{-- Slide — The Archive Tool --}}
            <x-tutorial.slide audio="tutorials/audio/level2-database_cleanup-archive_tool.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>The Archive Tool</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        <i class="fas fa-archive mr-1"></i> Deactivate inactive persons in bulk, instead of one by one.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-left mb-6">
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-5" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1"><i class="fas fa-broom text-indigo-400 mr-1"></i> Cleaner database</p>
                            <p class="text-sm text-gray-600">Keeps lists focused on people who are actually reachable.</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-5" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1"><i class="fas fa-coins text-indigo-400 mr-1"></i> Lower cost</p>
                            <p class="text-sm text-gray-600">Avoids sending campaign messages to people who won't respond.</p>
                        </div>
                    </div>

                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-5 mb-6 text-left" data-reveal>
                        <p class="font-semibold text-gray-800 mb-2 text-sm"><i class="fas fa-list-check text-indigo-400 mr-1"></i> How it works</p>
                        <p class="text-sm text-gray-600">
                            Set an inactivity threshold — for example, <strong>4 years</strong>. You'll see a list of persons, their last activity, and whether they've already been targeted by a campaign. Use this to judge, person by person, whether to archive them.
                        </p>
                    </div>

                    <div class="rounded-xl bg-green-50 border border-green-200 p-4 flex items-start gap-3 text-left" data-reveal>
                        <i class="fas fa-rotate-left text-green-600 mt-1"></i>
                        <p class="text-sm text-green-900">
                            Archived users can always be <strong>reactivated</strong> later — archiving is not permanent.
                        </p>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — The Log --}}
            <x-tutorial.slide audio="tutorials/audio/level2-database_cleanup-log.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>The Log</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        <i class="fas fa-clipboard-list mr-1"></i> A history of administrative changes — who did what, and when.
                    </p>

                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-5 mb-6 text-left" data-reveal>
                        <p class="font-semibold text-gray-800 mb-3 text-sm"><i class="fas fa-list-check text-indigo-400 mr-1"></i> What's recorded here</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="flex items-start gap-2">
                                <i class="fas fa-right-left text-indigo-400 mt-1"></i>
                                <span class="text-sm text-gray-700">Moving a person between branches or divisions</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <i class="fas fa-user-shield text-indigo-400 mt-1"></i>
                                <span class="text-sm text-gray-700">Role and permission changes</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <i class="fas fa-hand-holding-dollar text-indigo-400 mt-1"></i>
                                <span class="text-sm text-gray-700">Membership fee changes</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <i class="fas fa-sliders text-indigo-400 mt-1"></i>
                                <span class="text-sm text-gray-700">App-wide setting changes</span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3 text-left mb-4" data-reveal>
                        <i class="fas fa-circle-info text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            Each entry shows who made the change, what it was, and a <strong>before-and-after snapshot</strong> of the data.
                        </p>
                    </div>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3 text-left" data-reveal>
                        <i class="fas fa-triangle-exclamation text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            This log covers <strong>administrative and settings changes</strong> — not the deletion or approval of donations, payments, trainings, or volunteering. Those are tracked on the record itself.
                        </p>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — Lesson complete --}}
            <x-tutorial.slide audio="tutorials/audio/level2-database_cleanup-complete.mp3">
                <div class="text-center max-w-xl mx-auto">

                    {{-- Check emblem --}}
                    <div class="flex justify-center mb-6" data-reveal>
                        <div class="w-24 h-24 rounded-full bg-green-50 border-4 border-green-100 flex items-center justify-center">
                            <i class="fas fa-circle-check text-5xl text-green-500"></i>
                        </div>
                    </div>

                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Lesson complete</h2>
                    <p class="text-gray-600 mb-8" data-reveal>You can now keep the database clean, and trace administrative changes.</p>

                    {{-- Recap pills --}}
                    <div class="flex flex-wrap justify-center gap-3 mb-8" data-reveal>
                    <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                        <i class="fas fa-box-archive text-green-500"></i> Bulk archive
                    </span>
                                            <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                        <i class="fas fa-clipboard-list text-green-500"></i> The Log
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
