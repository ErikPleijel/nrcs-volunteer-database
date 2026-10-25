<x-layouts.admin title="Courses">

    <x-slot name="pageHeader">
        <i class="fas fa-chalkboard-user mr-3"></i> Courses
    </x-slot>

    <x-slot name="subHeader">
        Planned, ongoing and past training courses
    </x-slot>

    <x-slot name="button1">
        <div class="flex items-center gap-2">
            @can('add_courses')
                @if(in_array($accessLevel, ['national', 'branch'], true))
                    <a href="{{ route('courses.create') }}" class="btn-add">
                        <i class="fas fa-plus mr-2"></i>New Course
                    </a>
                @endif
            @endcan
        </div>
    </x-slot>

    {{-- Guide --}}
    <div class="flex justify-center mb-4">
        <x-help-popup trigger-class="help-btn">
            <x-slot:trigger><i class="fas fa-question-circle text-base mr-1"></i>Guide</x-slot:trigger>

            {{-- Header --}}
            <div class="-mt-8 mb-4 text-center">
                <i class="fas fa-question-circle text-xl text-sky-500"></i>
                <h3 class="mt-1 text-base font-semibold text-gray-900">Courses</h3>
            </div>

            {{-- Accordion --}}
            <div class="max-w-3xl mx-auto">
                <div x-data="{ open: null }" class="space-y-1 text-sm mb-4">

                    {{-- What is a course? --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'what' ? null : 'what'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-chalkboard-user mr-2 text-indigo-400"></i>What is a course?</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'what' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'what'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>A course is a planned training that people attend. It has a name, a training type, start and end dates, a place and an organiser.</li>
                                <li>People are added as <span class="font-semibold">participants</span> or <span class="font-semibold">facilitators</span>. Facilitators who are not in the database can be named in <span class="font-semibold">External facilitators</span>.</li>
                                <li>The status shows where the course is: <span class="font-semibold">Planned</span>, <span class="font-semibold">Ongoing</span> (today falls within its dates), <span class="font-semibold">Ended, not closed</span>, <span class="font-semibold">Closed</span> or <span class="font-semibold">Cancelled</span>.</li>
                                <li>Use <span class="font-semibold">Show</span> to switch between Upcoming, Past and Cancelled courses.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- National, branch and division courses --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'levels' ? null : 'levels'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-sitemap mr-2 text-violet-400"></i>National, branch and division courses: what is the difference?</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'levels' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'levels'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li><span class="font-semibold">National course:</span> organised by National HQ, with no branch. Every branch and division can see it.</li>
                                <li><span class="font-semibold">Branch course:</span> organised by one branch. Only that branch's staff and National HQ can see it.</li>
                                <li><span class="font-semibold">Division course:</span> a branch course organised for one division. The branch, that division and National HQ can see it. Other divisions in the branch cannot.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Who can create and edit a course? --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'create' ? null : 'create'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-pen mr-2 text-blue-400"></i>Who can create and edit a course?</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'create' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'create'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>National and branch administrators: National DB Administrator, National DB Assistant, Branch Secretary and Branch DB Administrator.</li>
                                <li>National staff can create national courses and courses for any branch. Branch staff create courses for their own branch only, with or without a division.</li>
                                <li>Branch staff can edit, close and cancel their own branch's courses, but not national courses.</li>
                                <li>Branch DB assistants and division assistants cannot create or edit courses. They can add people and record results (see below).</li>
                                <li>A course can only be edited while it is planned.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Who can add participants and facilitators? --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'add_people' ? null : 'add_people'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-user-plus mr-2 text-emerald-400"></i>Who can add participants and facilitators?</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'add_people' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'add_people'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Open the course → <span class="font-semibold">Add people</span> → search by name or database ID → add the person as participant or facilitator.</li>
                                <li><span class="font-semibold">The organisers</span> (National HQ, or the organising branch's staff) can add anyone from any branch.</li>
                                <li><span class="font-semibold">Other branches</span> can add people from their own branch to a national course.</li>
                                <li><span class="font-semibold">Division staff</span> can add people from their own division, but only to a course organised for their division. On other courses they see the list but cannot change it.</li>
                                <li>Archived and anonymized people cannot be added. People who are pending engagement can be added.</li>
                                <li>People can only be added or removed while the course is planned.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Can people from other branches take part? --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'other_branches' ? null : 'other_branches'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-right-left mr-2 text-violet-400"></i>Can people from other branches take part?</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'other_branches' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'other_branches'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Yes. Participants can come from any branch. The organisers add them, or for a national course, each branch can add its own people.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Who can see the participants? --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'see_people' ? null : 'see_people'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-eye mr-2 text-sky-400"></i>Who can see the participants?</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'see_people' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'see_people'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Everyone who can see the course sees all its participants and facilitators, including people from other branches.</li>
                                <li>The list shows <span class="font-semibold">name, branch and division only</span>. It shows no phone, email or photo. A name only links to the person's page if you are allowed to open that page anyway.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- How do I record results? --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'results' ? null : 'results'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-list-check mr-2 text-amber-400"></i>How do I record results (Passed, Failed, Absent)?</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'results' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'results'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>From the <span class="font-semibold">start date</span>, the organisers can choose Passed, Failed or Absent for each participant and click <span class="font-semibold">Save results</span>. Facilitators get no result.</li>
                                <li>You can change a result, or clear it, until the course is closed.</li>
                                <li>On a national course, only National HQ records results. On a branch course, the branch's staff (including assistants) and National HQ do. On a division course, that division's staff can too.</li>
                                <li>Staff who only added their own people to someone else's course cannot record results.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- When can I close a course? --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'close' ? null : 'close'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-flag-checkered mr-2 text-green-500"></i>When can I close a course, and what happens then?</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'close' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'close'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>The organisers can close a course from its <span class="font-semibold">end date</span> (the last day counts), once <span class="font-semibold">every participant has a result</span>. Until then, the course page explains what is missing.</li>
                                <li>Closing creates one training record per participant who passed:
                                    <ul class="list-circle pl-4 mt-1 space-y-0.5">
                                        <li>training type: the course's training type</li>
                                        <li>date: the start date</li>
                                        <li>duration: the number of days, first and last day included</li>
                                        <li>reference: the course name</li>
                                        <li>branch and division: the person's own</li>
                                    </ul>
                                </li>
                                <li>Failed and absent results get no training record.</li>
                                <li>A course can be closed only once. After that it cannot be changed.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- How are the training records approved? --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'approve' ? null : 'approve'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-check-double mr-2 text-emerald-400"></i>How are the training records approved?</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'approve' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'approve'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>The new records are <span class="font-semibold">pending</span>. They go through the normal training approval and also show up in each person's branch's Approvals tab.</li>
                                <li>On the closed course, <span class="font-semibold">Approve all from this course</span> approves them all at once. It is available to approvers of the organising branch and to national approvers, including for participants from other branches.</li>
                                <li>The person who closed the course cannot approve its records (four-eyes). Another approver must do it.</li>
                                <li>Archived people are skipped. Approve those on their own review page.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- What if a training record is rejected? --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'rejected' ? null : 'rejected'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-circle-xmark mr-2 text-rose-400"></i>What if a training record is rejected?</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'rejected' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'rejected'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>A rejection is final. The record cannot be edited or sent again, and the course stays closed.</li>
                                <li>To correct it, enter a new training record by hand in the normal way. That record is not linked to the course.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- How do I print the certificates? --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'certificates' ? null : 'certificates'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-certificate mr-2 text-amber-400"></i>How do I print the certificates for a course?</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'certificates' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'certificates'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Once at least one record is approved, a closed course shows a <span class="font-semibold">Certificates</span> button. It opens the Certificates page filtered to that course, with all its certificates on one page.</li>
                                <li>Organisers who can print certificates can print the whole course, including people from other branches. Everyone else prints only the people in their own area.</li>
                                <li>Training types marked <span class="font-semibold">HQ only</span>: <span class="font-semibold">competence</span> certificates are printed by National HQ only. <span class="font-semibold">Attendance</span> certificates can be printed at branch level.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- How do I send an SMS or email? --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'message' ? null : 'message'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-envelope mr-2 text-blue-400"></i>How do I send an SMS or email to the participants?</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'message' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'message'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>On the course page, under <span class="font-semibold">Send a message</span>, choose Everyone on the course, Participants, Facilitators, or (after closing) Participants who passed.</li>
                                <li>This opens the normal campaign wizard. The message is sent after National HQ approves it.</li>
                                <li>Organisers reach everyone on the course. Other branches reach only their own branch's people.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- What happens when a course is cancelled? --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'cancel' ? null : 'cancel'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-ban mr-2 text-gray-400"></i>What happens when a course is cancelled?</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'cancel' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'cancel'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>The organisers can cancel a planned course. It moves to the <span class="font-semibold">Cancelled</span> list and can no longer be changed, closed or messaged. No training records are created.</li>
                                <li>It disappears from the participants' upcoming courses. <span class="font-semibold">They are not told automatically</span>, so send them a message <span class="font-semibold">before</span> you cancel.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Where do participants see their upcoming courses? --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'upcoming' ? null : 'upcoming'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-calendar-days mr-2 text-indigo-400"></i>Where do participants see their upcoming courses?</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'upcoming' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'upcoming'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>On <span class="font-semibold">My Profile</span>, under the seven fundamental principles, each upcoming course appears as one line, for example "You are registered for … at …". These lines link to the <span class="font-semibold">Your upcoming courses</span> card further down.</li>
                                <li>Staff also see upcoming courses in their area on the <span class="font-semibold">Dashboard</span>.</li>
                                <li>Volunteers do not see the Courses page itself.</li>
                            </ul>
                        </div>
                    </div>

                </div>
            </div>
        </x-help-popup>
    </div>

    {{-- Filters --}}
    <div class="container mx-auto px-4 py-6">
        <div class="filter-container">
            <div class="filter-form-content">
                <form method="GET" action="{{ route('courses.index') }}" class="filter-form">
                    <div class="filter-grid filter-grid-4">
                        <div>
                            <label for="search" class="filter-label">Search</label>
                            <input type="text" name="search" id="search" value="{{ request('search') }}"
                                   placeholder="Search by course name..."
                                   class="filter-input {{ request('search') ? 'filter-active' : '' }}">
                        </div>

                        <div>
                            <label for="status" class="filter-label">Show</label>
                            <select name="status" id="status"
                                    class="filter-select {{ $status !== 'upcoming' ? 'filter-active' : '' }}">
                                <option value="upcoming" @selected($status === 'upcoming')>Upcoming and ongoing</option>
                                <option value="past" @selected($status === 'past')>Past</option>
                                <option value="cancelled" @selected($status === 'cancelled')>Cancelled</option>
                            </select>
                        </div>

                        @if($accessLevel === 'national')
                            <div>
                                <label for="branch_id" class="filter-label">Organising branch</label>
                                <select name="branch_id" id="branch_id"
                                        class="filter-select {{ request('branch_id') ? 'filter-active' : '' }}">
                                    <option value="">All</option>
                                    <option value="national" @selected(request('branch_id') === 'national')>National courses</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected((string) request('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div>
                            <label for="training_type_id" class="filter-label">Training type</label>
                            <select name="training_type_id" id="training_type_id"
                                    class="filter-select {{ request('training_type_id') ? 'filter-active' : '' }}">
                                <option value="">All types</option>
                                @foreach($trainingTypes as $type)
                                    <option value="{{ $type->id }}" @selected((string) request('training_type_id') === (string) $type->id)>{{ $type->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="filter-actions">
                        <div class="filter-button-group">
                            <button type="submit" class="filter-btn-primary">
                                <i class="fas fa-search mr-1"></i>Filter
                            </button>
                            <a href="{{ route('courses.index') }}" class="filter-btn-secondary filter-btn-secondary-active">
                                <i class="fas fa-times mr-1"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="container mx-auto px-4 pb-6">

        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        <div class="table-container">
            @if($courses->count() > 0)
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead class="table-header">
                        <tr class="table-header-row">
                            <th class="table-header-cell">Course</th>
                            <th class="table-header-cell">Dates</th>
                            <th class="table-header-cell">Organised by</th>
                            <th class="table-header-cell">Participants</th>
                            <th class="table-header-cell">Status</th>
                            <th class="table-header-cell">Actions</th>
                        </tr>
                        </thead>
                        <tbody class="table-body">
                        @foreach($courses as $course)
                            <tr class="table-body-row">
                                <td class="table-body-cell" style="white-space: normal; word-break: break-word; max-width: 260px;">
                                    <div class="table-field-main">{{ $course->name }}</div>
                                    <div class="table-field-sub">{{ $course->trainingType->name ?? '—' }}</div>
                                </td>
                                <td class="table-body-cell">
                                    <div class="table-field-main">{{ $course->date_range }}</div>
                                    @if($course->place)
                                        <div class="table-field-sub">{{ $course->place }}</div>
                                    @endif
                                </td>
                                <td class="table-body-cell">
                                    <div class="table-field-main">{{ $course->branch->name ?? 'National' }}</div>
                                    @if($course->division)
                                        <div class="table-field-sub">{{ $course->division->name }}</div>
                                    @endif
                                </td>
                                <td class="table-body-cell">
                                    <div class="table-field-main">{{ $course->participants_count }}</div>
                                </td>
                                <td class="table-body-cell">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $course->status_badge_class }}">
                                        {{ $course->status_label }}
                                    </span>
                                </td>
                                <td class="table-body-cell-no-wrap">
                                    <a href="{{ route('courses.show', $course) }}" class="btn-primary whitespace-nowrap">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="table-pagination">
                    {{ $courses->links() }}
                </div>
            @else
                <div class="table-empty-state">
                    <i class="fas fa-chalkboard-user text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No courses found.</h3>
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
