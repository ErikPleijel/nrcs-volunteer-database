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

            <div class="-mt-8 mb-4 text-center">
                <i class="fas fa-circle-question text-xl text-blue-500"></i>
                <h3 class="mt-1 text-base font-semibold text-gray-900">Courses</h3>
            </div>

            <div class="max-w-3xl mx-auto text-sm text-gray-700">
                <ul class="space-y-1 list-disc pl-4">
                    <li>A <span class="font-semibold">course</span> is a planned training (dates, place, training type) that people attend.</li>
                    <li>National and branch administrators create courses. A course without a branch is a <span class="font-semibold">national course</span>.</li>
                    <li>Participants may come from any branch.</li>
                    <li>When the course is <span class="font-semibold">closed</span>, everyone who passed gets a training record. These records go through the normal training approval.</li>
                    <li>A <span class="font-semibold">cancelled</span> course stays in the list under "Cancelled" and cannot be changed.</li>
                </ul>
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
