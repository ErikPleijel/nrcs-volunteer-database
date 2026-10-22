<x-layouts.admin title="Edit Course">
    <x-slot name="pageHeader">
        <i class="fas fa-chalkboard-user mr-3"></i> Courses
    </x-slot>

    <x-slot name="subHeader">
        Edit {{ $course->name }}
    </x-slot>

    <x-slot name="button1">
        <a href="{{ route('courses.show', $course) }}" class="btn-cancel">
            <i class="fas fa-arrow-left mr-1"></i> Back to Course
        </a>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form action="{{ route('courses.update', $course) }}" method="POST">
                        @csrf
                        @method('PUT')

                        @include('courses._form')

                        <div class="border-t pt-6 flex items-center justify-end gap-3">
                            <a href="{{ route('courses.show', $course) }}" class="btn-cancel">Cancel</a>
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save mr-1"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
