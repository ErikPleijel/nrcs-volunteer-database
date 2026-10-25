<x-layouts.admin title="Course">
    <x-slot name="pageHeader">
        <i class="fas fa-chalkboard-user mr-3"></i> Courses
    </x-slot>

    <x-slot name="subHeader">
        {{ $course->name }}
    </x-slot>

    <x-slot name="button1">
        <div class="flex items-center gap-2">
            <a href="{{ route('courses.index') }}" class="btn-cancel">
                <i class="fas fa-arrow-left mr-1"></i> Back to List
            </a>
            @if($canManage && $course->isPlanned())
                <a href="{{ route('courses.edit', $course) }}" class="btn-primary">
                    <i class="fas fa-pen mr-1"></i> Edit
                </a>
            @endif
            @if($hasApprovedRecords)
                @can('view_certificates')
                    <a href="{{ route('certificates.index', ['certificate_type' => 'training_competence', 'course_id' => $course->id]) }}" class="btn-primary">
                        <i class="fas fa-certificate mr-1"></i> Certificates
                    </a>
                @endcan
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    {{ session('error') }}
                </div>
            @endif
            @if(session('skipped'))
                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 rounded text-sm">
                    Not approved:
                    <ul class="list-disc pl-5 mt-1">
                        @foreach(session('skipped') as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="flex items-start justify-between gap-4 mb-4">
                    <h2 class="text-xl font-bold text-gray-900">{{ $course->name }}</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium whitespace-nowrap {{ $course->status_badge_class }}">
                        {{ $course->status_label }}
                    </span>
                </div>

                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <dt class="font-medium text-gray-500">Training type</dt>
                        <dd class="text-gray-900">{{ $course->trainingType->name ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Dates</dt>
                        <dd class="text-gray-900">{{ $course->date_range }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Place</dt>
                        <dd class="text-gray-900">{{ $course->place ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Organised by</dt>
                        <dd class="text-gray-900">
                            {{ $course->branch->name ?? 'National' }}@if($course->division), {{ $course->division->name }}@endif
                        </dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="font-medium text-gray-500">External facilitators</dt>
                        <dd class="text-gray-900 whitespace-pre-line">{{ $course->external_facilitators ?: '—' }}</dd>
                    </div>
                </dl>

                <div class="mt-6 border-t pt-4 text-xs text-gray-500 space-y-1">
                    <div>Created {{ $course->created_at?->format('j M Y') }}@if($course->createdBy) by {{ $course->createdBy->full_name }}@endif</div>
                    @if($course->isCancelled())
                        <div>Cancelled {{ $course->cancelled_at?->format('j M Y') }}@if($course->cancelledBy) by {{ $course->cancelledBy->full_name }}@endif</div>
                    @endif
                    @if($course->isClosed())
                        <div>Closed {{ $course->closed_at?->format('j M Y') }}@if($course->closedBy) by {{ $course->closedBy->full_name }}@endif</div>
                    @endif
                </div>
            </div>

            {{-- People: name, branch and division only (Decisions.md 2026-10-23) --}}
            @if($canChangePeople)
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-1">Add people</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        {{ $canAddAnyone
                            ? 'You can add people from any branch.'
                            : 'You can add people from your own '.(auth()->user()->getAccessLevel() === 'division' ? 'division' : 'branch').'.' }}
                        Search by name or database ID.
                    </p>

                    {{-- Searches without reloading (CourseParticipantController::search). The term is kept in
                         the URL, so the box is filled again after adding someone reloads the page. --}}
                    <div x-data="coursePeopleSearch(@js(route('courses.people.search', $course)), @js($search))">
                        <div class="relative mb-4">
                            <input type="text" x-model="query" @input.debounce.300ms="search()" autocomplete="off"
                                   placeholder="Name or database ID (at least 2 characters)"
                                   class="w-full px-3 py-2 pr-9 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <i x-show="loading" style="display: none;" class="fas fa-spinner fa-spin absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        </div>

                        <p x-show="error" style="display: none;" class="text-sm text-red-600">The search failed. Please try again.</p>
                        <p x-show="searched && !error && results.length === 0" style="display: none;" class="text-sm text-gray-500">No one found who can be added.</p>

                        <table x-show="results.length > 0" style="display: none;" class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                            <template x-for="person in results" :key="person.id">
                                <tr>
                                    <td class="py-2 pr-4">
                                        <div class="font-medium text-gray-900" x-text="person.name"></div>
                                        <div class="text-xs text-gray-500" x-text="(person.branch ?? '—') + ', ' + (person.division ?? '—')"></div>
                                    </td>
                                    <td class="py-2 text-right whitespace-nowrap">
                                        @foreach(['participant' => 'Add as participant', 'facilitator' => 'Add as facilitator'] as $role => $label)
                                            <form method="POST" action="{{ route('courses.participants.store', $course) }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="user_id" :value="person.id">
                                                <input type="hidden" name="role" value="{{ $role }}">
                                                <button type="submit" class="{{ $role === 'participant' ? 'btn-primary' : 'btn-cancel' }} text-xs">{{ $label }}</button>
                                            </form>
                                        @endforeach
                                    </td>
                                </tr>
                            </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            @elseif($peopleChangedBy)
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-info-circle mr-1 text-gray-400"></i>Participants are added and removed by {{ $peopleChangedBy }}.
                    </p>
                </div>
            @endif

            @foreach(['Facilitators' => $facilitators, 'Participants' => $participants] as $heading => $people)
                @php
                    $isParticipants = $heading === 'Participants';
                    $editResults = $isParticipants && $canRecordResults && $course->resultsOpen();
                    $resultLabels = ['passed' => 'Passed', 'failed' => 'Failed', 'absent' => 'Absent'];
                @endphp
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900 {{ $isParticipants ? 'mb-1' : 'mb-4' }}">{{ $heading }} ({{ $people->count() }})</h3>

                    @if($isParticipants && $people->isNotEmpty())
                        <p class="text-sm text-gray-600 mb-4">
                            Passed {{ $resultCounts['passed'] ?? 0 }} · Failed {{ $resultCounts['failed'] ?? 0 }} ·
                            Absent {{ $resultCounts['absent'] ?? 0 }} · Not recorded {{ $resultCounts['none'] ?? 0 }}
                            @if($canRecordResults && $course->isPlanned() && ! $course->resultsOpen())
                                <span class="block text-xs text-gray-500 mt-1">Results can be recorded from {{ $course->start_date->format('j M Y') }}.</span>
                            @endif
                        </p>
                    @endif

                    @if($people->isEmpty())
                        <p class="text-sm text-gray-500">None yet.</p>
                    @else
                        <table class="w-full text-sm">
                            <thead>
                            <tr class="text-left text-xs uppercase text-gray-500 border-b">
                                <th class="py-2 pr-4">Name</th>
                                <th class="py-2 pr-4">Branch</th>
                                <th class="py-2 pr-4">Division</th>
                                @if($isParticipants)<th class="py-2 pr-4">Result</th>@endif
                                @if($canChangePeople)<th class="py-2"></th>@endif
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @foreach($people as $entry)
                                <tr>
                                    <td class="py-2 pr-4">
                                        @can('view', $entry->user)
                                            <a href="{{ route('users.show', $entry->user_id) }}" class="text-blue-600 hover:underline">{{ $entry->user->full_name }}</a>
                                        @else
                                            {{ $entry->user->full_name }}
                                        @endcan
                                    </td>
                                    <td class="py-2 pr-4">{{ $entry->user->branch->name ?? '—' }}</td>
                                    <td class="py-2 pr-4">{{ $entry->user->division->name ?? '—' }}</td>
                                    @if($isParticipants)
                                        <td class="py-2 pr-4">
                                            @if($editResults)
                                                <select name="results[{{ $entry->id }}]" form="course-results-form"
                                                        aria-label="Result for {{ $entry->user->full_name }}"
                                                        class="result-select px-2 py-1 text-sm border border-gray-300 rounded-md">
                                                    <option value="">—</option>
                                                    @foreach($resultLabels as $value => $label)
                                                        <option value="{{ $value }}" @selected($entry->result === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            @else
                                                {{ $resultLabels[$entry->result] ?? '—' }}
                                            @endif
                                            @if($entry->training)
                                                <span class="ml-1 text-xs text-gray-500">· record {{ $entry->training->approval_status }}</span>
                                            @endif
                                        </td>
                                    @endif
                                    @if($canChangePeople)
                                        <td class="py-2 text-right">
                                            @if($course->canHandlePerson(auth()->user(), $entry->user))
                                                <form method="POST" action="{{ route('courses.participants.destroy', [$course, $entry]) }}"
                                                      onsubmit="return confirm(@js('Remove '.$entry->user->full_name.' from this course?'))">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-xs text-red-600 hover:text-red-800 underline">Remove</button>
                                                </form>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                            </tbody>
                        </table>

                        @if($editResults)
                            <form id="course-results-form" method="POST" action="{{ route('courses.results.update', $course) }}"
                                  class="mt-4 flex items-center justify-end gap-3">
                                @csrf
                                @method('PUT')
                                <button type="button" class="btn-cancel text-sm"
                                        onclick="document.querySelectorAll('.result-select').forEach(s => { if (!s.value) s.value = 'passed'; })">
                                    Set empty results to Passed
                                </button>
                                <button type="submit" class="btn-primary"><i class="fas fa-save mr-1"></i>Save results</button>
                            </form>
                        @endif
                    @endif
                </div>
            @endforeach

            @can('campaign_request_create')
                @if(! $course->isCancelled() && ($participants->isNotEmpty() || $facilitators->isNotEmpty()))
                    @php
                        $audiences = ['' => 'Everyone on the course', 'participant' => 'Participants', 'facilitator' => 'Facilitators'];
                        if ($course->isClosed()) {
                            $audiences['passed'] = 'Participants who passed';
                        }
                    @endphp
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-base font-semibold text-gray-900 mb-2">Send a message</h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Opens the campaign wizard (email / SMS) with this course's people as the audience.
                            @if($course->isManageableBy(auth()->user()))
                                As an organiser you reach everyone on the course, also people from other branches.
                            @else
                                As for all branch campaigns, only people in your own branch are reached.
                            @endif
                        </p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($audiences as $audience => $label)
                                <form method="POST" action="{{ route('campaigns.wizard.start') }}">
                                    @csrf
                                    <input type="hidden" name="filter_json[course_id]" value="{{ $course->id }}">
                                    <input type="hidden" name="filter_json[archived_filter]" value="not_archived">
                                    <input type="hidden" name="filter_json[audience_type]" value="both">
                                    @if($audience === 'passed')
                                        <input type="hidden" name="filter_json[course_role]" value="participant">
                                        <input type="hidden" name="filter_json[course_result]" value="passed">
                                    @elseif($audience !== '')
                                        <input type="hidden" name="filter_json[course_role]" value="{{ $audience }}">
                                    @endif
                                    <button type="submit" class="inline-flex items-center rounded-md px-3 py-1.5 text-sm font-semibold text-white bg-slate-700 hover:bg-slate-800">
                                        <i class="fas fa-envelope mr-1"></i>{{ $label }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endcan

            @if($canManage && $course->isPlanned())
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-2">Close this course</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Closing creates a training record for every participant who passed. The records then wait
                        for approval by another approver. A course can be closed only once, and after that it cannot be changed.
                    </p>
                    @if($closeBlocker)
                        <p class="text-sm text-amber-700"><i class="fas fa-circle-info mr-1"></i>{{ $closeBlocker }}</p>
                    @else
                        <form action="{{ route('courses.close', $course) }}" method="POST"
                              onsubmit="return confirm('Close this course and create the training records? This cannot be undone.')">
                            @csrf
                            <button type="submit" class="btn-primary"><i class="fas fa-flag-checkered mr-1"></i>Close course</button>
                        </form>
                    @endif
                </div>
            @endif

            @if($canApproveRecords && $pendingRecords > 0)
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-2">Training records waiting for approval ({{ $pendingRecords }})</h3>
                    @if((int) $course->closed_by_user_id === (int) auth()->id())
                        <p class="text-sm text-gray-600">You closed this course, so another approver must approve its training records.</p>
                    @else
                        <p class="text-sm text-gray-600 mb-4">
                            Approve all pending training records from this course at once, including participants from other branches.
                        </p>
                        <form action="{{ route('courses.approve-records', $course) }}" method="POST"
                              onsubmit="return confirm(@js('Approve '.$pendingRecords.' training record(s) from this course?'))">
                            @csrf
                            <button type="submit" class="btn-primary"><i class="fas fa-check-double mr-1"></i>Approve all from this course</button>
                        </form>
                    @endif
                </div>
            @endif

            @if($canManage && $course->isPlanned())
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-base font-semibold text-gray-900 mb-2">Cancel this course</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        A cancelled course stays in the list under "Cancelled" and can no longer be changed or closed.
                    </p>
                    <form action="{{ route('courses.cancel', $course) }}" method="POST"
                          onsubmit="return confirm('Cancel this course? This cannot be undone.')">
                        @csrf
                        <button type="submit" class="text-sm text-red-600 hover:text-red-800 underline">
                            Cancel course
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>

    @if($canChangePeople)
    @push('scripts')
        <script>
            // "Add people" search on the course page: at least 2 characters, results replace each other
            // (an older, slower response never overwrites a newer one).
            window.coursePeopleSearch = function (url, initialQuery) {
                return {
                    query: initialQuery,
                    results: [],
                    loading: false,
                    searched: false,
                    error: false,
                    controller: null,

                    init() {
                        if (this.query.trim().length >= 2) {
                            this.search();
                        }
                    },

                    async search() {
                        const term = this.query.trim();
                        this.rememberTerm(term);
                        this.controller?.abort();

                        if (term.length < 2) {
                            this.results = [];
                            this.searched = false;
                            this.error = false;
                            this.loading = false;
                            return;
                        }

                        this.controller = new AbortController();
                        this.loading = true;

                        try {
                            const response = await fetch(`${url}?q=${encodeURIComponent(term)}`, {
                                headers: { 'Accept': 'application/json' },
                                signal: this.controller.signal,
                            });
                            if (!response.ok) {
                                throw new Error(response.statusText);
                            }
                            this.results = (await response.json()).results;
                            this.error = false;
                        } catch (e) {
                            if (e.name === 'AbortError') {
                                return;
                            }
                            this.results = [];
                            this.error = true;
                        }

                        this.searched = true;
                        this.loading = false;
                    },

                    // Keep the term in the address, so the page comes back with it after adding or removing someone.
                    rememberTerm(term) {
                        const address = new URL(window.location.href);
                        term.length >= 2 ? address.searchParams.set('person_search', term) : address.searchParams.delete('person_search');
                        window.history.replaceState(null, '', address);
                    },
                };
            };
        </script>
    @endpush
    @endif
</x-layouts.admin>
