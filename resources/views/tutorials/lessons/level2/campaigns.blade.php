<x-layouts.admin :title="$lessonTitle">
    <x-slot name="pageHeader"><i class="fas fa-bullhorn mr-3"></i> {{ $lessonTitle }}</x-slot>
    <x-slot name="subHeader">Level 2: Branch Administration</x-slot>

    <div class="p-4 md:p-6">
        <a href="{{ route('tutorials.level', 2) }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 mb-6">
            <i class="fas fa-arrow-left"></i> Back to Level 2 lessons
        </a>

        <x-tutorial.player :lessonKey="$lessonKey" :title="$lessonTitle">

            {{-- Slide — The Campaign filter wizard: a simpler start --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-campaigns-filter_wizard.jpg"
                imageAlt="The Persons page with the Campaign filter wizard button, and the wizard panel showing strategies like Welcome newly registered persons and Re-engage dormant volunteers"
                heading="An easier way to start"
                audio="tutorials/audio/level2-campaigns-filter_wizard.mp3">
                <div class="space-y-4">
                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                        <p class="text-sm text-gray-700">
                            <i class="fas fa-wand-magic-sparkles text-indigo-400 mr-1"></i>
                            On <strong>Persons</strong>, the <strong>Campaign filter wizard</strong> button offers ready-made strategies — no need to build a filter from scratch.
                        </p>
                    </div>

                    <x-tutorial.step n="1">Click <strong>Campaign filter wizard</strong>, then pick a goal — like <strong>Welcome newly registered persons</strong>, <strong>Re-engage dormant volunteers</strong>, or <strong>Remind about expiring membership</strong>.</x-tutorial.step>
                    <x-tutorial.step n="2">Answer a few simple questions — for example, who to include, or how recently they were last contacted.</x-tutorial.step>
                    <x-tutorial.step n="3">Click <strong>Apply filter &amp; close</strong> — the right filters are set for you automatically.</x-tutorial.step>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-circle-info text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            From here, the process is the same — check the results, then click <strong>Make campaign from filter</strong>.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Starting a campaign from a filter --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-campaigns-filter_start.jpg"
                imageAlt="The Persons filter set to a Division, Volunteers, and Age group Youth, with the record count and Make campaign from filter button"
                heading="Starting a campaign from a filter"
                audio="tutorials/audio/level2-campaigns-filter_start.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Go to <strong>Persons</strong> and set a filter — for example, a <strong>Division</strong>, <strong>Volunteers</strong> only, and age group <strong>Youth</strong>.</x-tutorial.step>
                    <x-tutorial.step n="2">Click <strong>Filter</strong>, and check the <strong>number of records</strong> found.</x-tutorial.step>
                    <x-tutorial.step n="3">If the group isn't too large, click <strong>Make campaign from filter</strong>.</x-tutorial.step>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-triangle-exclamation text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            If the audience is too big, narrow the filter first — a more focused message reaches people better than a broad one.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — The campaign wizard: 5 steps --}}
            <x-tutorial.slide audio="tutorials/audio/level2-campaigns-wizard_overview.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>The campaign wizard</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        Five steps guide you from purpose to a ready-to-submit campaign.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <div class="w-8 h-8 mx-auto rounded-full bg-indigo-100 text-indigo-700 font-bold text-sm flex items-center justify-center mb-2">1</div>
                            <p class="font-semibold text-gray-800 text-sm">Purpose</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <div class="w-8 h-8 mx-auto rounded-full bg-indigo-100 text-indigo-700 font-bold text-sm flex items-center justify-center mb-2">2</div>
                            <p class="font-semibold text-gray-800 text-sm">Audience</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <div class="w-8 h-8 mx-auto rounded-full bg-indigo-100 text-indigo-700 font-bold text-sm flex items-center justify-center mb-2">3</div>
                            <p class="font-semibold text-gray-800 text-sm">Throttling</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <div class="w-8 h-8 mx-auto rounded-full bg-indigo-100 text-indigo-700 font-bold text-sm flex items-center justify-center mb-2">4</div>
                            <p class="font-semibold text-gray-800 text-sm">Message</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                            <div class="w-8 h-8 mx-auto rounded-full bg-green-100 text-green-700 font-bold text-sm flex items-center justify-center mb-2">5</div>
                            <p class="font-semibold text-gray-800 text-sm">Review</p>
                        </div>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — Step 1: Purpose --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-campaigns-step1_purpose.jpg"
                imageAlt="Wizard step 1, Purpose, showing the recipient filter summary, the Purpose dropdown, and the Channel selector"
                heading="Step 1: Purpose"
                audio="tutorials/audio/level2-campaigns-step1_purpose.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">At the top, confirm your <strong>Recipient filter</strong> — Branch, Division, Lifecycle, Age, and so on.</x-tutorial.step>
                    <x-tutorial.step n="2">Select a <strong>Purpose</strong> — this pre-fills the message in a later step.</x-tutorial.step>
                    <x-tutorial.step n="3">Choose a <strong>Channel</strong>.
                        <span class="block mt-1 text-sm text-gray-500"><strong>Email (fallback to SMS)</strong> sends SMS only when no email is on file. <strong>Email and SMS</strong> may send two messages to the same person.</span>
                    </x-tutorial.step>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Step 2: Audience --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-campaigns-step2_audience.jpg"
                imageAlt="Wizard step 2, Audience, showing the Delivery Check with email and SMS fallback counts, and the sample recipients list"
                heading="Step 2: Audience"
                audio="tutorials/audio/level2-campaigns-step2_audience.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Review the <strong>Delivery Check</strong> — it shows exactly how many people will actually be reached.</x-tutorial.step>
                    <x-tutorial.step n="2">For example: <em>Email will be sent to 55. SMS fallback will be sent to 13 (no email on file).</em></x-tutorial.step>
                    <x-tutorial.step n="3"> If the matched audience is too big, go back and adjust the filter — then create a new campaign from that filter.</x-tutorial.step>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-list-check text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            A list of <strong>sample recipients</strong> below lets you spot-check who's actually in this audience.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Step 3: Throttling --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-campaigns-step3_throttling.jpg"
                imageAlt="Wizard step 3, Throttling, showing the Send window start/end fields and the Daily cap field"
                heading="Step 3: Throttling"
                audio="tutorials/audio/level2-campaigns-step3_throttling.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">If your message encourages people to <strong>call the branch</strong>, set a <strong>call window</strong> — for example, <strong>08:00</strong> to <strong>20:00</strong> — so people know when it's OK to call.</x-tutorial.step>
                    <x-tutorial.step n="2">Optionally, set a <strong>Daily cap</strong> — for example, <strong>200</strong> — to limit how many messages go out at once.</x-tutorial.step>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-phone-volume text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            Smaller batches help avoid the branch being <strong>overwhelmed by incoming calls</strong> all at once.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Step 4: Message --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-campaigns-step4_message.jpg"
                imageAlt="Wizard step 4, Message, showing the email fields (from name, reply-to, subject, body editor) and the SMS body field with character count"
                heading="Step 4: Message"
                audio="tutorials/audio/level2-campaigns-step4_message.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Fill in <strong>From name</strong>, <strong>Reply-to email</strong>, <strong>Subject</strong>, and the <strong>Email body</strong>.
                        <span class="block mt-1 text-sm text-gray-500">Use <strong>Write</strong> for simple messages, or <strong>Code</strong> for full HTML newsletters. Insert placeholders like a person's first name from the dropdown.</span>
                    </x-tutorial.step>
                    <x-tutorial.step n="2">Write the <strong>SMS message</strong> — keep an eye on the character count, shown as chars and number of SMS parts.</x-tutorial.step>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-circle-info text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            Both email and SMS automatically get a footer appended — a profile/login link and unsubscribe info for email, an opt-out link for SMS.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Step 5: Review --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-campaigns-step5_review.jpg"
                imageAlt="Wizard step 5, Review, showing the summary panel, the Preview as recipient selector, the Readiness check, and the final checklist"
                heading="Step 5: Review"
                audio="tutorials/audio/level2-campaigns-step5_review.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Check the summary — <strong>Audience</strong>, <strong>Channel</strong>, <strong>Send window</strong>, and <strong>Daily cap</strong> — all in one place.</x-tutorial.step>
                    <x-tutorial.step n="2">Use <strong>Preview as</strong> to see the message exactly as a real recipient would — placeholders are substituted with their actual data.</x-tutorial.step>
                    <x-tutorial.step n="3">Check <strong>Readiness</strong> for any warnings, then confirm the <strong>final checklist</strong> before submitting.</x-tutorial.step>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-clipboard-check text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            <strong>What happens next:</strong> after submission, HQ reviews the campaign. Once approved, it's processed and messages are sent.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Your Campaigns: monitoring what you've sent --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-campaigns-your_campaigns.jpg"
                imageAlt="The Your Campaigns page showing the tabs Drafts, Submitted, Rejected, Approved/Sending, Sent, and a campaign card with delivery status"
                heading="Your Campaigns"
                audio="tutorials/audio/level2-campaigns-your_campaigns.mp3">
                <div class="space-y-4">
                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                        <p class="text-sm text-gray-700">
                            <i class="fas fa-bullhorn text-indigo-400 mr-1"></i>
                            This is where you come back after building a campaign — to track it, finish it, or fix it.
                        </p>
                    </div>

                    <x-tutorial.step n="1">Use the tabs to find a campaign: <strong>Drafts</strong>, <strong>Submitted</strong>, <strong>Rejected</strong>, <strong>Approved/Sending</strong>, or <strong>Sent</strong>.</x-tutorial.step>
                    <x-tutorial.step n="2">On a <strong>Draft</strong>, click <strong>Continue editing</strong>. On a <strong>Rejected</strong> campaign, click <strong>Edit campaign</strong> to fix it and resubmit.</x-tutorial.step>
                    <x-tutorial.step n="3">Watch delivery live — each card shows <strong>Queued</strong>, <strong>Sending…</strong>, or <strong>X / Y sent</strong>, with failures shown in red.</x-tutorial.step>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-trash text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            <strong>Delete</strong> is only available on Draft and Rejected campaigns — and it's permanent.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Targeting Organisations in campaigns --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-campaigns-organisations_target.jpg"
                imageAlt="The Organisations page with a filter applied and the Make campaign from filter button"
                heading="Targeting organisations"
                audio="tutorials/audio/level2-campaigns-organisations_target.mp3">
                <div class="space-y-4">
                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                        <p class="text-sm text-gray-700">
                            <i class="fas fa-industry text-indigo-400 mr-1"></i>
                            Campaigns aren't just for people — you can also target <strong>organisations</strong>.
                        </p>
                    </div>

                    <x-tutorial.step n="1">Go to <strong>Organisations</strong> and set a filter.</x-tutorial.step>
                    <x-tutorial.step n="2">Click <strong>Make campaign from filter</strong> — the same wizard you've already seen takes it from here.</x-tutorial.step>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-circle-info text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            When a campaign reaches an organisation, it's sent to <strong>both</strong> the organisation's own email/SMS contact details, and its <strong>linked contact person(s)</strong>.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Planning Tools on the Dashboard --}}
            <x-tutorial.split-slide
                image="tutorials/img/level2-campaigns-planning_tools.jpg"
                imageAlt="The Dashboard Lifecycle Overview with Pending engagement, Active, and Dormant cards each showing a Take action button, and the Planning Tools section below"
                heading="Planning your campaigns"
                audio="tutorials/audio/level2-campaigns-planning_tools.mp3">
                <div class="space-y-4">
                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                        <p class="text-sm text-gray-700">
                            <i class="fas fa-lightbulb text-amber-400 mr-1"></i>
                            Not sure where to start? The <strong>Dashboard</strong> is a good place to plan.
                        </p>
                    </div>

                    <x-tutorial.step n="1">Under <strong>Lifecycle Overview</strong>, click <strong>Take action</strong> below Pending Engagement, Active, or Dormant for tailored suggestions.</x-tutorial.step>
                    <x-tutorial.step n="2">Below that, the <strong>Planning Tools</strong> section links directly to focused reports — Welcome Campaign Planner, Expiring Membership, Re-engage Dormant, Training Statistics, ID Card Expiry Report, Red Cross Units, Donation Appreciation, and All Campaigns.</x-tutorial.step>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-route text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            These tools help you find the right audience before you ever open the campaign wizard.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Lesson complete --}}
            <x-tutorial.slide audio="tutorials/audio/level2-campaigns-complete.mp3">
                <div class="text-center max-w-xl mx-auto">

                    {{-- Check emblem --}}
                    <div class="flex justify-center mb-6" data-reveal>
                        <div class="w-24 h-24 rounded-full bg-green-50 border-4 border-green-100 flex items-center justify-center">
                            <i class="fas fa-circle-check text-5xl text-green-500"></i>
                        </div>
                    </div>

                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Lesson complete</h2>
                    <p class="text-gray-600 mb-8" data-reveal>You now know how to build, send, and track a campaign.</p>

                    {{-- Recap pills --}}
                    <div class="flex flex-wrap justify-center gap-3 mb-8" data-reveal>
            <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-wand-magic-sparkles text-green-500"></i> Filter wizard
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-route text-green-500"></i> The 5-step wizard
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-industry text-green-500"></i> Organisations
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-lightbulb text-green-500"></i> Planning tools
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-chart-line text-green-500"></i> Your Campaigns
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
