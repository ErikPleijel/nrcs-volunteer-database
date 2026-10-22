<x-layouts.admin title="New Course">
    <x-slot name="pageHeader">
        <i class="fas fa-chalkboard-user mr-3"></i> Courses
    </x-slot>

    <x-slot name="subHeader">
        Plan a new course
    </x-slot>

    <x-slot name="button1">
        <a href="{{ route('courses.index') }}" class="btn-cancel">
            <i class="fas fa-arrow-left mr-1"></i> Back to List
        </a>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form action="{{ route('courses.store') }}" method="POST">
                        @csrf

                        @include('courses._form')

                        <div class="border-t pt-6 flex items-center justify-end gap-3">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-plus mr-1"></i>Create Course
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
