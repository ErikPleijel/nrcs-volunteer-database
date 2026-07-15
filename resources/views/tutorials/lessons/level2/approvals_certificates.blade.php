<x-layouts.admin :title="$lessonTitle">
    <x-slot name="pageHeader"><i class="fas fa-stamp mr-3"></i> {{ $lessonTitle }}</x-slot>
    <x-slot name="subHeader">Level 2: Branch Administration</x-slot>

    <div class="p-4 md:p-6">
        <a href="{{ route('tutorials.level', 2) }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 mb-6">
            <i class="fas fa-arrow-left"></i> Back to Level 2 lessons
        </a>

        <x-tutorial.player :lessonKey="$lessonKey" :title="$lessonTitle">

            {{-- Slide — Approving or rejecting records --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-approvals_certificates-approving.jpg"
                imageAlt="A record's Approvals tab showing Approve and Reject buttons, and the rejection reason popup"
                heading="Approving records"
                audio="tutorials/audio/level2-approvals_certificates-approving.mp3">
                <div class="space-y-4">
                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                        <p class="text-sm text-gray-700">
                            <i class="fas fa-hourglass-half text-amber-500 mr-1"></i>
                            Payments, volunteering logs, trainings, and donations all need approval before they count.
                        </p>
                    </div>

                    <x-tutorial.step n="1">Check the bottom of the <strong>Dashboard</strong> for a count of pending approvals.</x-tutorial.step>
                    <x-tutorial.step n="2">Go to the relevant records list — for example, <strong>Payment Records</strong> — and open the <strong>Approvals</strong> tab.</x-tutorial.step>
                    <x-tutorial.step n="3">Click <strong>Approve</strong>, or <strong>Reject</strong>.
                        <span class="block mt-1 text-sm text-gray-500">Rejecting opens a popup asking for a reason — the submitter is notified with it.</span>
                    </x-tutorial.step>
                </div>
            </x-tutorial.split-slide>



            {{-- Slide — Printing certificates --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-approvals_certificates-print_certificates.jpg"
                imageAlt="A person's record with a Print certificate button, and the Certificate Generator page with Select All and the two print buttons"
                heading="Printing certificates"
                audio="tutorials/audio/level2-approvals_certificates-print_certificates.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">One at a time: <strong>Persons</strong> → find the person → <strong>View</strong> → scroll to the record → <strong>Print certificate</strong>.</x-tutorial.step>
                    <x-tutorial.step n="2">In bulk: on the <strong>Certificate Generator</strong> page, tick the certificates you want — or <strong>Select All</strong>.</x-tutorial.step>
                    <x-tutorial.step n="3">Choose <strong>Print for pre-printed paper</strong>, or <strong>Print with logo &amp; frame</strong>.
                        <span class="block mt-1 text-sm text-gray-500">Pre-printed paper assumes you already have branded stationery — this only prints the text. With logo &amp; frame prints the full certificate design.</span>
                    </x-tutorial.step>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — After printing: layout, marking, types --}}
            {{-- Slide — Notes on printing --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-approvals_certificates-after_printing.jpg"
                imageAlt="The layout editor in the top right corner of the Certificate Generator page, and the Mark as Printed and View Print History buttons"
                heading="Notes on printing"
                audio="tutorials/audio/level2-approvals_certificates-after_printing.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">For pre-printed paper, use the <strong>layout editor</strong> (top right) to adjust text position — your setting is remembered for next time.</x-tutorial.step>
                    <x-tutorial.step n="2">Once printed successfully, click <strong>Mark as Printed</strong> before leaving the page — this is how the database keeps a record.</x-tutorial.step>
                    <x-tutorial.step n="3">Click <strong>View Print History</strong> to see past printouts, and correct any "Mark as Printed" entered by mistake.</x-tutorial.step>

                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                        <p class="text-sm text-gray-700">
                            <i class="fas fa-graduation-cap text-indigo-400 mr-1"></i>
                            Training certificates come in two kinds. <strong>Attendance</strong> confirms someone was present. <strong>Competence</strong> is a certification — it confirms they've been assessed and met the standard.
                        </p>
                    </div>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-building-flag text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            Some certificates can only be printed at <strong>HQ level</strong>.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Lesson complete --}}
            <x-tutorial.slide audio="tutorials/audio/level2-approvals_certificates-complete.mp3">
                <div class="text-center max-w-xl mx-auto">

                    {{-- Check emblem --}}
                    <div class="flex justify-center mb-6" data-reveal>
                        <div class="w-24 h-24 rounded-full bg-green-50 border-4 border-green-100 flex items-center justify-center">
                            <i class="fas fa-circle-check text-5xl text-green-500"></i>
                        </div>
                    </div>

                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Lesson complete</h2>
                    <p class="text-gray-600 mb-8" data-reveal>You can now approve records and manage certificates.</p>

                    {{-- Recap pills --}}
                    <div class="flex flex-wrap justify-center gap-3 mb-8" data-reveal>
            <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-check-double text-green-500"></i> Approve or reject
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-certificate text-green-500"></i> Print certificates
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-bookmark text-green-500"></i> Mark as printed
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-file-alt text-green-500"></i> Print history
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
