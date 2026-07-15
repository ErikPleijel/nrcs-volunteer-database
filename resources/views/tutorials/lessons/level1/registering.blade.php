<x-layouts.admin :title="$lessonTitle">
    <x-slot name="pageHeader"><i class="fas fa-pen-to-square mr-3"></i> {{ $lessonTitle }}</x-slot>
    <x-slot name="subHeader">Level 1: Getting Started</x-slot>

    <div class="p-4 md:p-6">
        <a href="{{ route('tutorials.level', 1) }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 mb-6">
            <i class="fas fa-arrow-left"></i> Back to Level 1 lessons
        </a>

        <x-tutorial.player :lessonKey="$lessonKey" :title="$lessonTitle">

            {{-- Slide — Intro --}}
            <x-tutorial.slide audio="tutorials/audio/level1-registering-intro.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Registering records</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        There are four kinds of record you can add — and who can add them depends on your role.
                    </p>

                    <div class="flex flex-col md:flex-row gap-4 items-stretch text-left">

                        {{-- Operations --}}
                        <div class="md:flex-1 rounded-xl border border-indigo-200 bg-indigo-50/40 p-4" data-reveal>
                            <p class="text-center text-sm font-semibold text-indigo-700 mb-3">
                                <i class="fas fa-clipboard-list mr-1"></i> Division DB Assistant — Operations
                            </p>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded-xl bg-white border border-gray-100 p-4 text-center">
                                    <i class="fas fa-hands-helping text-3xl text-orange-500 mb-2"></i>
                                    <p class="font-semibold text-gray-800 text-sm">Volunteering</p>
                                </div>
                                <div class="rounded-xl bg-white border border-gray-100 p-4 text-center">
                                    <i class="fas fa-graduation-cap text-3xl text-purple-500 mb-2"></i>
                                    <p class="font-semibold text-gray-800 text-sm">Training</p>
                                </div>
                            </div>
                        </div>

                        {{-- Finance --}}
                        <div class="md:flex-1 rounded-xl border border-amber-200 bg-amber-50/40 p-4" data-reveal>
                            <p class="text-center text-sm font-semibold text-amber-700 mb-1">
                                <i class="fas fa-coins mr-1"></i> Division DB Assistant — Finance
                            </p>
                            <p class="text-center text-xs text-amber-600 mb-3">Can register all four</p>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded-xl bg-white border border-gray-100 p-4 text-center">
                                    <i class="fas fa-hand-holding-dollar text-3xl text-blue-500 mb-2"></i>
                                    <p class="font-semibold text-gray-800 text-sm">Payments</p>
                                </div>
                                <div class="rounded-xl bg-white border border-gray-100 p-4 text-center">
                                    <i class="fas fa-heart text-3xl text-green-500 mb-2"></i>
                                    <p class="font-semibold text-gray-800 text-sm">Donations</p>
                                </div>
                            </div>

                        </div>
                    </div>

                    <p class="text-sm text-gray-500 mt-6" data-reveal>
                        <i class="fas fa-circle-info text-indigo-400 mr-1"></i>
                        This split applies only to <strong>Division Database Assistants</strong> — branch and national staff can register all four.
                    </p>
                </div>
            </x-tutorial.slide>

            {{-- Slide — The four-eyes rule --}}
            <x-tutorial.slide audio="tutorials/audio/level1-registering-four_eyes.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>A second person checks every record</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        Donations, payments, volunteering, and training all need two people before they count.
                    </p>

                    {{-- Two-step visual --}}
                    <div class="flex flex-col sm:flex-row items-stretch justify-center gap-4 text-left mb-6">
                        <div class="flex-1 rounded-xl bg-amber-50 border border-amber-200 p-5" data-reveal>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="w-7 h-7 rounded-full bg-amber-100 text-amber-700 font-bold text-sm flex items-center justify-center">1</span>
                                <i class="fas fa-hourglass-half text-amber-500"></i>
                            </div>
                            <p class="font-semibold text-gray-800">Someone enters it</p>
                            <p class="text-sm text-gray-600">It's saved as <strong>pending</strong> — not final yet.</p>
                        </div>

                        <div class="hidden sm:flex items-center" data-reveal>
                            <i class="fas fa-arrow-right text-gray-300 text-xl"></i>
                        </div>

                        <div class="flex-1 rounded-xl bg-green-50 border border-green-200 p-5" data-reveal>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="w-7 h-7 rounded-full bg-green-100 text-green-700 font-bold text-sm flex items-center justify-center">2</span>
                                <i class="fas fa-user-check text-green-500"></i>
                            </div>
                            <p class="font-semibold text-gray-800">A different person checks it</p>
                            <p class="text-sm text-gray-600">They review it and <strong>approve</strong> it.</p>
                        </div>
                    </div>

                    {{-- The rule --}}
                    <div class="rounded-xl bg-red-50 border border-red-200 p-4 flex items-start gap-3 text-left mb-4" data-reveal>
                        <i class="fas fa-user-slash text-red-500 mt-1"></i>
                        <p class="text-sm text-red-900">
                            <strong>You can never approve your own entry</strong> — even if you normally have approval rights. It always takes a second person.
                        </p>
                    </div>

                    {{-- Why it matters --}}
                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3 text-left" data-reveal>
                        <i class="fas fa-circle-info text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            Until approved, a record <strong>doesn't count</strong> — it won't appear in reports, totals, or statistics.
                        </p>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — Who can approve --}}
            <x-tutorial.slide audio="tutorials/audio/level1-registering-who_approves.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Who can approve a record</h2>
                    <p class="text-gray-600 mb-8" data-reveal>Not every role that can add a record can also approve one.</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-left mb-6">

                        {{-- Can approve --}}
                        <div class="rounded-xl bg-green-50 border border-green-200 p-5" data-reveal>
                            <p class="font-semibold text-green-800 mb-3"><i class="fas fa-user-check mr-1"></i> Can approve</p>
                            <ul class="space-y-2 text-sm text-gray-700">
                                <li><i class="fas fa-check text-green-500 mr-2"></i><strong>Branch Secretary</strong> — for their own branch</li>
                                <li><i class="fas fa-check text-green-500 mr-2"></i><strong>Branch DB Administrator</strong> — for their own branch</li>
                                <li><i class="fas fa-check text-green-500 mr-2"></i><strong>National DB Administrator</strong> — any branch, any record</li>
                            </ul>
                        </div>

                        {{-- Cannot approve --}}
                        <div class="rounded-xl bg-red-50 border border-red-200 p-5" data-reveal>
                            <p class="font-semibold text-red-800 mb-3"><i class="fas fa-user-slash mr-1"></i> Can only submit</p>
                            <ul class="space-y-2 text-sm text-gray-700">
                                <li><i class="fas fa-xmark text-red-400 mr-2"></i><strong>Division DB Assistant</strong> — Operations &amp; Finance</li>
                                <li><i class="fas fa-xmark text-red-400 mr-2"></i><strong>Branch DB Assistant</strong></li>
                                <li><i class="fas fa-xmark text-red-400 mr-2"></i><strong>National DB Assistant</strong></li>
                                <li><i class="fas fa-xmark text-red-400 mr-2"></i><strong>Observer</strong> (National) — read-only, can't add either</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Branch scope note --}}
                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3 text-left mb-4" data-reveal>
                        <i class="fas fa-circle-info text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            In practice: a Division DB Assistant logs a record, and a Branch Secretary or Administrator at <strong>that branch</strong> — or the National DB Administrator — approves it.
                        </p>
                    </div>

                    {{-- Four-eyes still applies --}}
                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3 text-left" data-reveal>
                        <i class="fas fa-user-group text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            <strong>The four-eyes rule still applies within a branch.</strong> If a Branch Secretary submits a record themselves, only the Branch DB Administrator or National DB Administrator can approve it.
                        </p>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — Volunteering: navigation & Add (split) --}}
            <x-tutorial.split-slide
                image="tutorials/img/level1-registering-volunteering_add.jpg"
                imageAlt="The Volunteering Log with the green Add Volunteering button, and the person search with Select"
                heading="Add a volunteering record"
                audio="tutorials/audio/level1-registering-volunteering_add.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Open <strong><i class="fas fa-hands-helping text-orange-500 mr-1"></i>Volunteering Log</strong> from the main menu.</x-tutorial.step>
                    <x-tutorial.step n="2">Press the green <strong>+ Add Volunteering</strong> button.</x-tutorial.step>
                    <x-tutorial.step n="3">In the search box, type a <strong>name</strong> or <strong>DB-number</strong>.</x-tutorial.step>
                    <x-tutorial.step n="4">The list searches as you type — click <strong>Select</strong> beside the right person.</x-tutorial.step>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Volunteering: entry form (split) --}}
            <x-tutorial.split-slide
                image="tutorials/img/level1-registering-volunteering_form.jpg"
                imageAlt="The volunteering entry form with activity type, date, hours, reference and the assignment tickboxes"
                heading="Enter the volunteering details"
                audio="tutorials/audio/level1-registering-volunteering_form.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Choose the <strong>Activity Type</strong> and set the <strong>Date</strong>.</x-tutorial.step>
                    <x-tutorial.step n="2">Enter the number of <strong>Hours</strong>, and a <strong>Reference</strong> if you have one.</x-tutorial.step>
                    <x-tutorial.step n="3">Choose where it counts — tick one:
                        <span class="block mt-1 text-sm text-gray-500">the person's <strong>Red Cross Unit</strong>, a <strong>Task Force</strong>, or <strong>neither</strong>.</span>
                    </x-tutorial.step>
                    <x-tutorial.step n="4">Press <strong>Create Activity Log</strong> to store the record.</x-tutorial.step>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-file-lines text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            <strong>Always keep a paper record.</strong> The database should never be the only place a record exists.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Training: entry form (split) --}}
            <x-tutorial.split-slide
                image="tutorials/img/level1-registering-training_form.jpg"
                imageAlt="The training entry form with training type, date, duration, valid years and reference"
                heading="Enter the training details"
                audio="tutorials/audio/level1-registering-training_form.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Choose the <strong>Training Type</strong> and set the <strong>Training Date</strong>.</x-tutorial.step>
                    <x-tutorial.step n="2">Enter the <strong>Duration</strong> in days.</x-tutorial.step>
                    <x-tutorial.step n="3">Set <strong>Valid Years</strong> — how long it stays valid.
                        <span class="block mt-1 text-sm text-gray-500">It fills in automatically for many courses; leave empty if it never expires.</span>
                    </x-tutorial.step>
                    <x-tutorial.step n="4">Add a <strong>Reference</strong> if you have one.</x-tutorial.step>
                    <x-tutorial.step n="5">Press <strong>Create Training</strong> to store the record.</x-tutorial.step>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Payments: entry form (split) --}}
            <x-tutorial.split-slide
                image="tutorials/img/level1-registering-payment_form.jpg"
                imageAlt="The payment entry form with fee, ID card, payment date, reference and the payment summary"
                heading="Enter the payment details"
                audio="tutorials/audio/level1-registering-payment_form.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Choose the <strong>Fee</strong> — the list shows the fees that fit this person (member or volunteer).</x-tutorial.step>
                    <x-tutorial.step n="2">For volunteers, tick <strong>ID Card</strong> to add one — its fee joins the total.
                        <span class="block mt-1 text-sm text-gray-500">Members don't receive ID cards here.</span>
                    </x-tutorial.step>
                    <x-tutorial.step n="3">Set the <strong>Payment Date</strong> — it shifts automatically if a still-valid membership would overlap.</x-tutorial.step>
                    <x-tutorial.step n="4">Add a <strong>Reference</strong> if you have one.</x-tutorial.step>
                    <x-tutorial.step n="5">Check the <strong>Payment Summary</strong> total, then press <strong>Register Payment</strong>.</x-tutorial.step>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-circle-info text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            The <strong>Current Payment</strong> panel shows whether the person already has valid cover — so you don't charge twice.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Donations: entry form (split) --}}
            <x-tutorial.split-slide
                image="tutorials/img/level1-registering-donation_form.jpg"
                imageAlt="The donation entry form with date, in-kind toggle, amount or item, anonymous, reference and purpose"
                heading="Enter the donation details"
                audio="tutorials/audio/level1-registering-donation_form.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Set the <strong>Donation Date</strong>.</x-tutorial.step>
                    <x-tutorial.step n="2">Choose the type:
                        <span class="block mt-1 text-sm text-gray-500">
                leave it unticked for a <strong>cash</strong> gift — enter the <strong>Amount</strong>;<br>
                or tick <strong>In-Kind Donation</strong> for goods — name the <strong>Item</strong> and how many.
            </span>
                    </x-tutorial.step>
                    <x-tutorial.step n="3">Tick <strong>Anonymous donation</strong> if the donor wishes to stay unnamed.</x-tutorial.step>
                    <x-tutorial.step n="4">Add a <strong>Reference</strong> and <strong>Purpose</strong> if you have them.</x-tutorial.step>
                    <x-tutorial.step n="5">Press <strong>Create Donation</strong> to store the record.</x-tutorial.step>
                </div>
            </x-tutorial.split-slide>


            {{-- Slide — Mistake --}}
            <x-tutorial.split-slide
                stacked
                image="tutorials/img/level1-registering-fix_mistake.jpg"
                imageAlt="The list of recent entries by the current admin user below the entry form, showing the Withdraw button on a record"
                heading="Made a mistake?"
                audio="tutorials/audio/level1-registering-fix_mistake.mp3">
                <div>
                    <p class="text-gray-600 mb-5" data-reveal>
                        Mistakes happen. A record can't be edited — so you fix it in two steps.
                    </p>

                    <div class="flex flex-col sm:flex-row items-stretch justify-center gap-4 text-left">
                        <div class="flex-1 rounded-xl bg-gray-50 border border-gray-100 p-5" data-reveal>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="w-7 h-7 rounded-full bg-red-100 text-red-700 font-bold text-sm flex items-center justify-center">1</span>
                                <i class="fas fa-rotate-left text-red-500"></i>
                            </div>
                            <p class="font-semibold text-gray-800">Withdraw the record</p>
                            <p class="text-sm text-gray-600">Find it in the list of recent entries below the form, and press Withdraw.</p>
                        </div>

                        <div class="hidden sm:flex items-center" data-reveal>
                            <i class="fas fa-arrow-right text-gray-300 text-xl"></i>
                        </div>

                        <div class="flex-1 rounded-xl bg-gray-50 border border-gray-100 p-5" data-reveal>
                            <div class="flex items-center gap-2 mb-2">
                                <span class="w-7 h-7 rounded-full bg-green-100 text-green-700 font-bold text-sm flex items-center justify-center">2</span>
                                <i class="fas fa-plus text-green-500"></i>
                            </div>
                            <p class="font-semibold text-gray-800">Add a new one</p>
                            <p class="text-sm text-gray-600">Enter a fresh record with the correct details.</p>
                        </div>
                    </div>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3 mt-4" data-reveal>
                        <i class="fas fa-circle-question text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            Don't see a Withdraw button? The record has already been approved. Contact your branch — they can delete it for you.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Alternative path via a person's record --}}
            <x-tutorial.split-slide
                image="tutorials/img/level1-registering-alt_path.jpg"
                imageAlt="A person's record scrolled to the lists, showing the add buttons in each section header"
                heading="A quicker path: from a person's record"
                audio="tutorials/audio/level1-registering-alt_path.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Open the person's record — <strong>Persons</strong>, search, then <strong>View</strong>.</x-tutorial.step>
                    <x-tutorial.step n="2">Scroll down to the <strong>lists</strong>.</x-tutorial.step>
                    <x-tutorial.step n="3">In any section, press its add button — <strong>Add Payment</strong>, <strong>Add Volunteering</strong>, <strong>Add Training</strong>, or <strong>Add Donation</strong>.</x-tutorial.step>
                    <x-tutorial.step n="4">You go straight to that entry form, with the person <strong>already selected</strong> — no need to search again.</x-tutorial.step>

                    <p class="text-sm text-gray-500" data-reveal>
                        <i class="fas fa-circle-info text-indigo-400 mr-1"></i>
                        Handy when you're already looking at someone and want to add one quick record.
                    </p>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — records list --}}
            <x-tutorial.split-slide
                stacked
                image="tutorials/img/level1-registering-records_list.jpg"
                imageAlt="A single training record row in the Training Records list"
                heading="Finding a record in the list"
                audio="tutorials/audio/level1-registering-records_list.mp3">
                <div>
                    <p class="text-gray-600 mb-5" data-reveal>
                        Each record type has its own list — here, Training Records. Open <strong>Filter &amp; Sort</strong> (hidden by default) to narrow it.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <i class="fas fa-magnifying-glass text-2xl text-indigo-400 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">Search &amp; filter</p>
                            <p class="text-xs text-gray-500">Find a record by name, reference, type, or status.</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <i class="fas fa-user text-2xl text-indigo-400 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">Entered by me</p>
                            <p class="text-xs text-gray-500">Show only the records you registered.</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <i class="fas fa-trash-can text-2xl text-indigo-400 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">Include deleted</p>
                            <p class="text-xs text-gray-500">View deleted records, or all of them together.</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <i class="fas fa-up-right-from-square text-2xl text-indigo-400 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">Open any record</p>
                            <p class="text-xs text-gray-500">Press View to see the full record.</p>
                        </div>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide  — More about references --}}
            <x-tutorial.slide audio="tutorials/audio/level1-registering-references.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Two kinds of reference</h2>
                    <p class="text-gray-600 mb-8" data-reveal>Every record carries two — one from the database, one from your paper.</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-left mb-6">

                        {{-- Database reference --}}
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-5" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1"><i class="fas fa-database text-indigo-400 mr-1"></i> Database reference</p>
                            <p class="text-sm text-gray-600 mb-3">Created automatically — one for every record.</p>
                            <div class="space-y-1.5 font-mono text-sm">
                                <div><span class="text-blue-600 font-semibold">FEE</span><span class="text-gray-500">-21395/FCT</span> <span class="font-sans text-xs text-gray-400">payment</span></div>
                                <div><span class="text-orange-600 font-semibold">VOL</span><span class="text-gray-500">-15185/FCT</span> <span class="font-sans text-xs text-gray-400">volunteering</span></div>
                                <div><span class="text-purple-600 font-semibold">TRN</span><span class="text-gray-500">-5292/FCT</span> <span class="font-sans text-xs text-gray-400">training</span></div>
                                <div><span class="text-green-600 font-semibold">DON</span><span class="text-gray-500">-256/FCT</span> <span class="font-sans text-xs text-gray-400">donation</span></div>
                            </div>
                        </div>

                        {{-- Your reference --}}
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-5" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1"><i class="fas fa-hashtag text-indigo-400 mr-1"></i> Your reference</p>
                            <p class="text-sm text-gray-600 mb-3">Optional — the number from your own paper record.</p>
                            <div class="font-mono text-sm">
                                <span class="text-gray-700 font-semibold">#1234</span>
                            </div>
                            <p class="text-xs text-gray-400 mt-2">You type this into the Reference field, if you have one.</p>
                        </div>
                    </div>

                    {{-- The rule --}}
                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3 text-left" data-reveal>
                        <i class="fas fa-link text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            <strong>Always write the database reference onto your paper record.</strong> Then the paper and the database point to each other.
                        </p>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — Certificates overview --}}
            <x-tutorial.slide audio="tutorials/audio/level1-exploring-certificates.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Certificates</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        <i class="fas fa-certificate mr-1"></i> One view for membership, training, volunteering, and donation certificates.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <i class="fas fa-sliders text-2xl text-indigo-400 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">Filter by type</p>
                            <p class="text-xs text-gray-500">Certificate type, branch, division, unit, or training type.</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <i class="fas fa-circle-check text-2xl text-green-500 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">See print status</p>
                            <p class="text-xs text-gray-500">Each card shows "Printed" or "Not printed" at a glance.</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <i class="fas fa-file-alt text-2xl text-indigo-400 mb-2"></i>
                            <p class="font-semibold text-gray-800 text-sm">View Print History</p>
                            <p class="text-xs text-gray-500">See everything that's already been printed.</p>
                        </div>
                    </div>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3 text-left mb-4" data-reveal>
                        <i class="fas fa-building-flag text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            Some certificates can be printed at <strong>branch level</strong>. Others are marked <strong>"Printed at HQ"</strong> — those are handled nationally.
                        </p>
                    </div>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3 text-left" data-reveal>
                        <i class="fas fa-graduation-cap text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            This lesson only covers finding and checking certificates. <strong>Printing them</strong> is covered in Level 2.
                        </p>
                    </div>
                </div>
            </x-tutorial.slide>


            {{-- Slide  — Lesson complete --}}
            <x-tutorial.slide audio="tutorials/audio/level1-registering-complete.mp3">
                <div class="text-center max-w-xl mx-auto">

                    {{-- Check emblem --}}
                    <div class="flex justify-center mb-6" data-reveal>
                        <div class="w-24 h-24 rounded-full bg-green-50 border-4 border-green-100 flex items-center justify-center">
                            <i class="fas fa-circle-check text-5xl text-green-500"></i>
                        </div>
                    </div>

                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Lesson complete</h2>
                    <p class="text-gray-600 mb-8" data-reveal>You can now register, find, and manage records in the database.</p>

                    {{-- Recap pills --}}
                    <div class="flex flex-wrap justify-center gap-3 mb-8" data-reveal>
            <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-pen-to-square text-green-500"></i> Register records
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-magnifying-glass text-green-500"></i> Find records
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-trash-can text-green-500"></i> Delete safely
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-file-lines text-green-500"></i> Keep paper records
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
