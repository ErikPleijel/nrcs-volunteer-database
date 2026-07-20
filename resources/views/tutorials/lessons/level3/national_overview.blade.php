<x-layouts.admin :title="$lessonTitle">
    <x-slot name="pageHeader"><i class="fas fa-globe-africa mr-3"></i> {{ $lessonTitle }}</x-slot>
    <x-slot name="subHeader">Level 3: National Administration</x-slot>

    <div class="p-4 md:p-6">
        <a href="{{ route('tutorials.level', 4) }}" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 mb-6">
            <i class="fas fa-arrow-left"></i> Back to Level 3 lessons
        </a>

        <x-tutorial.player :lessonKey="$lessonKey" :title="$lessonTitle">

            {{-- Slide — Campaign Approvals overview --}}
            <x-tutorial.split-slide
                image="tutorials/img/level4-national_overview-campaign_approvals.jpg"
                imageAlt="The Campaigns Management page showing the pipeline summary and the tabs: Proposed, Approved/Queued, Sending, Sent, Cancelled, Rejected"
                heading="Campaign Approvals"
                audio="tutorials/audio/level4-national_overview-campaign_approvals.mp3">
                <div class="space-y-4">
                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                        <p class="text-sm text-gray-700">
                            <i class="fas fa-sliders text-indigo-400 mr-1"></i>
                            Every campaign submitted by a branch lands here before it can be sent.
                        </p>
                    </div>

                    <x-tutorial.step n="1">The <strong>Messages Pipeline</strong> summary shows what's Queued, Sending, Failed, and Sent today — a quick health check.</x-tutorial.step>
                    <x-tutorial.step n="2">Use the tabs to move through a campaign's life: <strong>Proposed</strong> → <strong>Approved/Queued</strong> → <strong>Sending</strong> → <strong>Sent</strong>.
                        <span class="block mt-1 text-sm text-gray-500"><strong>Cancelled</strong> and <strong>Rejected</strong> hold campaigns that didn't go out.</span>
                    </x-tutorial.step>
                    <x-tutorial.step n="3">Open a campaign in <strong>Proposed</strong> to review its audience and message, then <strong>Approve</strong> or <strong>Reject</strong> with a note.</x-tutorial.step>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Reviewing the Audience --}}
            <x-tutorial.split-slide
                image="tutorials/img/level3-national_overview-review_audience.jpg"
                imageAlt="The campaign review page's Audience panel showing filters, delivery summary, and matched/email/SMS contactable counts"
                heading="Reviewing the Audience"
                audio="tutorials/audio/level3-national_overview-review_audience.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Open a campaign under <strong>Proposed</strong> to review it. Start with the <strong>Filters</strong> panel — make sure the targeting matches the campaign's purpose.</x-tutorial.step>
                    <x-tutorial.step n="2">Check the <strong>Delivery summary</strong> — <strong>Matched</strong>, <strong>Email contactable</strong>, and <strong>SMS contactable</strong> counts.</x-tutorial.step>
                    <x-tutorial.step n="3">Watch for warnings — some people may receive <strong>both</strong> an email and an SMS, and some may have <strong>no way to be reached</strong> at all.</x-tutorial.step>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Reviewing the Message --}}
            <x-tutorial.split-slide
                image="tutorials/img/level3-national_overview-review_message.jpg"
                imageAlt="The campaign review page's Message panel showing the email and SMS side by side, the Preview as recipient selector, and the Code/Preview toggle"
                heading="Reviewing the Message"
                audio="tutorials/audio/level3-national_overview-review_message.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Use <strong>Preview as recipient</strong> to see the message exactly as a real person would receive it — placeholders are filled with their actual data.</x-tutorial.step>
                    <x-tutorial.step n="2">Check both panels — <strong>Email</strong> and <strong>SMS</strong> — for tone, clarity, and correct placeholders.</x-tutorial.step>
                    <x-tutorial.step n="3">If the campaign encourages phone calls, confirm it includes a <strong>call window</strong> — times when it's OK to call.</x-tutorial.step>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-list-check text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            Use the <strong>Final checks</strong> list as your guide: tone, clear purpose, correct placeholders, allowed links, and no ambiguous wording.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Approve or Reject --}}
            <x-tutorial.split-slide
                image="tutorials/img/level3-national_overview-approve_reject.jpg"
                imageAlt="The Approve or Reject panel with the Approve button and the Reject form with a required rejection note"
                heading="Approve or Reject"
                audio="tutorials/audio/level3-national_overview-approve_reject.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Satisfied with the audience and message? Click <strong>Approve</strong> — the campaign is queued for sending.</x-tutorial.step>
                    <x-tutorial.step n="2">Not ready? Click <strong>Reject</strong> and write a <strong>rejection note</strong> explaining why.</x-tutorial.step>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-bell text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            The submitter is <strong>notified with your note</strong>, so they know exactly what to fix before resubmitting.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Queue, Build, Start: sending the campaign --}}
            <x-tutorial.split-slide
                image="tutorials/img/level3-national_overview-queue_build_send.jpg"
                imageAlt="An approved campaign showing the Queue, Build, and Start buttons in sequence"
                heading="Sending an approved campaign"
                audio="tutorials/audio/level3-national_overview-queue_build_send.mp3">
                <div class="space-y-4">
                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-4" data-reveal>
                        <p class="text-sm text-gray-700">
                            <i class="fas fa-route text-indigo-400 mr-1"></i>
                            Once approved, three steps move a campaign from waiting to actually sending.
                        </p>
                    </div>

                    <x-tutorial.step n="1"><strong>Queue</strong> — marks the campaign ready to go out. No messages are sent yet.</x-tutorial.step>
                    <x-tutorial.step n="2"><strong>Build</strong> — generates the actual list of recipients from the campaign's filter, with their contact details attached.</x-tutorial.step>
                    <x-tutorial.step n="3"><strong>Start</strong> — begins sending. The first batch goes out immediately, and sending continues from there.</x-tutorial.step>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-circle-info text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            Follow the steps in order — <strong>Queue</strong>, then <strong>Build</strong>, then <strong>Start</strong>.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>

            {{-- Slide — Monitoring a sent campaign --}}
            <x-tutorial.split-slide
                image="tutorials/img/level3-national_overview-monitor_campaign.jpg"
                imageAlt="The campaign monitor page showing the KPI cards, send progress bar, and the recipient tabs All, Pending, Sent, Failed"
                heading="Monitoring a campaign"
                audio="tutorials/audio/level3-national_overview-monitor_campaign.mp3">
                <div class="space-y-4">
                    <x-tutorial.step n="1">Once approved, open the campaign's <strong>monitor</strong> page to track delivery — <strong>Total</strong>, <strong>Pending</strong>, <strong>Sent</strong>, and <strong>Failed</strong>.</x-tutorial.step>
                    <x-tutorial.step n="2">The <strong>send progress</strong> bar shows how far along it is, along with started, last run, and completed times.</x-tutorial.step>
                    <x-tutorial.step n="3">Use the recipient tabs to search individual recipients, or focus on <strong>Failed</strong> ones.</x-tutorial.step>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3" data-reveal>
                        <i class="fas fa-arrow-rotate-right text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            On the <strong>Failed</strong> tab, you can <strong>reset recipients to pending</strong> so they're retried — optionally including bounced or undeliverable ones too.
                        </p>
                    </div>
                </div>
            </x-tutorial.split-slide>


            {{-- Slide — Authorizations: Branch level --}}
            <x-tutorial.slide audio="tutorials/audio/level4-national_overview-authorize_branch.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Authorizing branch leadership</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        At national level, you appoint the people who run each branch's database.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-left mb-6">
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-5" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1"><i class="fas fa-user-tie text-red-500 mr-1"></i> Branch Secretary</p>
                            <p class="text-sm text-gray-600">Branch administration.</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-5" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1"><i class="fas fa-user-shield text-red-500 mr-1"></i> Branch DB Administrator</p>
                            <p class="text-sm text-gray-600">Same permissions as the Branch Secretary.</p>
                        </div>
                    </div>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3 text-left mb-4" data-reveal>
                        <i class="fas fa-scale-balanced text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            Every branch must have <strong>exactly one</strong> Branch Secretary — no more, no less.
                        </p>
                    </div>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3 text-left" data-reveal>
                        <i class="fas fa-users-gear text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            To check the overall picture, go to <strong>Dashboard → Database Administration → Database Team</strong>.
                        </p>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — The National DB Administrator --}}
            <x-tutorial.slide audio="tutorials/audio/level3-national_overview-national_admin.mp3">
                <div class="max-w-2xl mx-auto text-center">
                    <div class="flex justify-center mb-6" data-reveal>
                        <div class="w-20 h-20 rounded-full bg-red-50 border-4 border-red-100 flex items-center justify-center">
                            <i class="fas fa-user-shield text-4xl text-red-600"></i>
                        </div>
                    </div>

                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>The National DB Administrator</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        The highest working role in the database itself.
                    </p>

                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-5 mb-6 text-left" data-reveal>
                        <p class="text-sm text-gray-700">
                            A National DB Administrator <strong>authorizes and oversees</strong> the whole system — including appointing Branch Secretaries and Branch DB Administrators.
                        </p>
                    </div>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3 text-left" data-reveal>
                        <i class="fas fa-user-check text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            National DB Administrators are appointed — or removed — through the <strong>Super Administrator</strong> account.
                        </p>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — Authorizations: National level --}}
            <x-tutorial.slide audio="tutorials/audio/level4-national_overview-authorize_national.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Authorizing national roles</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        Two more roles are assigned only at national level.
                    </p>

                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-5 mb-6 text-left" data-reveal>
                        <p class="font-semibold text-gray-800 mb-3">
                            <i class="fas fa-keyboard text-indigo-400 mr-1"></i> National DB Assistant
                        </p>
                        <p class="text-sm text-gray-600 mb-3">Can be given any combination of these extra permissions:</p>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="rounded-lg bg-white border border-gray-200 p-3 text-center">
                                <i class="fas fa-bullhorn text-indigo-400 mb-1"></i>
                                <p class="text-xs font-semibold text-gray-700">Approve Campaign Requests</p>
                            </div>
                            <div class="rounded-lg bg-white border border-gray-200 p-3 text-center">
                                <i class="fas fa-certificate text-indigo-400 mb-1"></i>
                                <p class="text-xs font-semibold text-gray-700">Print Certificates</p>
                            </div>
                            <div class="rounded-lg bg-white border border-gray-200 p-3 text-center">
                                <i class="fas fa-id-card text-indigo-400 mb-1"></i>
                                <p class="text-xs font-semibold text-gray-700">Print ID Cards</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3 text-left" data-reveal>
                        <i class="fas fa-eye text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            <strong>Observer:</strong> full access to statistics, but cannot change anything in the database.
                        </p>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — System governance --}}
            <x-tutorial.slide audio="tutorials/audio/level4-national_overview-governance.mp3">
                <div class="max-w-2xl mx-auto text-center">
                    <div class="flex justify-center mb-6" data-reveal>
                        <div class="w-20 h-20 rounded-full bg-red-50 border-4 border-red-100 flex items-center justify-center">
                            <i class="fas fa-landmark text-4xl text-red-600"></i>
                        </div>
                    </div>

                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>System governance</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        Where ultimate authority over the system sits.
                    </p>

                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-5 mb-6 text-left" data-reveal>
                        <p class="text-sm text-gray-700">
                            The <strong>NRCS President</strong> and <strong>Secretary General</strong> hold overall super-admin authority in this system, using their official NRCS email accounts.
                        </p>
                    </div>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3 text-left" data-reveal>
                        <i class="fas fa-circle-info text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            Day-to-day user authorization is normally handled by the appointed <strong>National DB Administrator(s)</strong> and <strong>Branch DB Administrator(s)</strong>.
                        </p>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — Settings: Membership, Site, Social --}}
            <x-tutorial.slide audio="tutorials/audio/level4-national_overview-settings_general.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>National Database Settings</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        A few of the general settings you can change here.
                    </p>



                    <div class="grid grid-cols-1 gap-4 text-left mb-4">
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-5" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1"><i class="fas fa-user-clock text-indigo-400 mr-1"></i> Membership — Months of inactivity before dormant</p>
                            <p class="text-sm text-gray-600">Controls how long a volunteer can go without activity before becoming Dormant. Members with a paid fee are handled differently — they become Dormant when their payment expires, not from this setting.</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-5" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1"><i class="fas fa-quote-left text-indigo-400 mr-1"></i> Site Motto</p>
                            <p class="text-sm text-gray-600">Displayed in the footer and on public pages.</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-5" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1"><i class="fas fa-share-nodes text-indigo-400 mr-1"></i> Social Share Snippet</p>
                            <p class="text-sm text-gray-600">HTML snippet used for social sharing meta tags.</p>
                        </div>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — Settings: Campaign --}}
            <x-tutorial.slide audio="tutorials/audio/level4-national_overview-settings_campaign.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Campaign Settings</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        These control how campaigns are sent, system-wide.
                    </p>

                    <div class="rounded-xl bg-amber-50 border-2 border-amber-300 p-5 mb-4 text-left" data-reveal>
                        <p class="font-semibold text-amber-800 mb-1"><i class="fas fa-link text-amber-600 mr-1"></i> Allowed link domains</p>
                        <p class="text-sm text-amber-900">
                            A comma-separated list of domains allowed in campaign content links — for example, <span class="font-mono">example.org, example.com</span>. Case-insensitive. Keep this list deliberate — it's a safeguard on what links can go out to members and volunteers.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-left" data-reveal>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-5">
                            <p class="font-semibold text-gray-800 mb-1"><i class="fas fa-envelope text-indigo-400 mr-1"></i> Daily email sending cap</p>
                            <p class="text-sm text-gray-600">Maximum campaign emails sent per day before pausing.</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-5">
                            <p class="font-semibold text-gray-800 mb-1"><i class="fas fa-comment-sms text-indigo-400 mr-1"></i> Daily SMS sending cap</p>
                            <p class="text-sm text-gray-600">Maximum campaign SMS sent per day before pausing.</p>
                        </div>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — Signatures & Documents settings --}}
            <x-tutorial.slide audio="tutorials/audio/level4-national_overview-settings_signatures.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Signatures &amp; Documents</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        Signature images used across ID cards and certificates.
                    </p>

                    <div class="grid grid-cols-1 gap-4 text-left">
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-5" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1"><i class="fas fa-pen-nib text-indigo-400 mr-1"></i> ID Card Signature</p>
                            <p class="text-sm text-gray-600">
                                Upload a PNG with a <strong>transparent background</strong>. Used as the Secretary General's signature on printed ID cards. The filename is fixed — uploading a new file replaces the existing one.
                            </p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-5" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1"><i class="fas fa-signature text-indigo-400 mr-1"></i> Signature Images</p>
                            <p class="text-sm text-gray-600">
                                Used on certificates, as pre-printed signatures to save time. PNG only, transparent background. Use a descriptive filename, like <span class="font-mono">charles-smith-signature.png</span>.
                            </p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-5" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1"><i class="fas fa-id-badge text-indigo-400 mr-1"></i> Signature Titles</p>
                            <p class="text-sm text-gray-600">
                                A list of titles shown on certificates — for example, <strong>Branch Chairman</strong> or <strong>Branch Health Coordinator</strong>.
                            </p>
                        </div>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — Operational Settings: Membership Fees & Training Types --}}
            <x-tutorial.slide audio="tutorials/audio/level4-national_overview-settings_operational.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Operational Settings</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        Two more settings areas that shape day-to-day records.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-left">
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-5" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1"><i class="fas fa-hand-holding-dollar text-indigo-400 mr-1"></i> Membership Fees</p>
                            <p class="text-sm text-gray-600">Change fee amounts, or add new membership fee types.</p>
                        </div>
                        <div class="rounded-xl bg-gray-50 border border-gray-100 p-5" data-reveal>
                            <p class="font-semibold text-gray-800 mb-1"><i class="fas fa-graduation-cap text-indigo-400 mr-1"></i> Training Types</p>
                            <p class="text-sm text-gray-600">Manage the kinds of trainings, and mark whether certificates for a type are <strong>HQ-print only</strong>.</p>
                        </div>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — Campaign Purposes: default templates --}}
            <x-tutorial.slide audio="tutorials/audio/level4-national_overview-settings_campaign_purposes.mp3">
                <div class="max-w-3xl mx-auto text-center">
                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Campaign Purposes</h2>
                    <p class="text-gray-600 mb-8" data-reveal>
                        The default message templates behind every campaign.
                    </p>

                    <div class="rounded-xl bg-gray-50 border border-gray-100 p-5 mb-4 text-left" data-reveal>
                        <p class="text-sm text-gray-700">
                            Each purpose — like <strong>Membership Pre-Expiry Notice</strong>, <strong>Training Invitation</strong>, or <strong>Welcome &amp; Onboarding</strong> — has a default <strong>email subject</strong>, <strong>email body</strong>, and <strong>SMS body</strong>.
                        </p>
                    </div>

                    <div class="rounded-xl bg-indigo-50/60 border border-indigo-100 p-4 flex items-start gap-3 text-left mb-4" data-reveal>
                        <i class="fas fa-wand-magic-sparkles text-indigo-400 mt-1"></i>
                        <p class="text-sm text-gray-700">
                            These are <strong>pre-filled</strong> when a branch selects a purpose in the campaign wizard — saving them from writing a message from scratch.
                        </p>
                    </div>

                    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-start gap-3 text-left" data-reveal>
                        <i class="fas fa-code text-amber-500 mt-1"></i>
                        <p class="text-sm text-amber-900">
                            Placeholders like <span class="font-mono">@{{user.first_name}}</span> are substituted automatically at send time — with the person's real name, branch, membership status, and more.
                        </p>
                    </div>
                </div>
            </x-tutorial.slide>

            {{-- Slide — Lesson complete --}}
            <x-tutorial.slide audio="tutorials/audio/level4-national_overview-complete.mp3">
                <div class="text-center max-w-xl mx-auto">

                    {{-- Check emblem --}}
                    <div class="flex justify-center mb-6" data-reveal>
                        <div class="w-24 h-24 rounded-full bg-green-50 border-4 border-green-100 flex items-center justify-center">
                            <i class="fas fa-circle-check text-5xl text-green-500"></i>
                        </div>
                    </div>

                    <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Lesson complete</h2>
                    <p class="text-gray-600 mb-8" data-reveal>You now have an overview of national-level administration.</p>

                    {{-- Recap pills --}}
                    <div class="flex flex-wrap justify-center gap-3 mb-8" data-reveal>
            <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-sliders text-green-500"></i> Campaign approvals
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-key text-green-500"></i> Authorizations
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-landmark text-green-500"></i> System governance
            </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-gear text-green-500"></i> Settings
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
