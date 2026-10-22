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
</x-layouts.admin>
