<x-layouts.admin>
    <x-slot name="pageHeader">
        <i class="fas fa-users-gear mr-3"></i>  Task Forces
    </x-slot>

    <x-slot name="subHeader">
        ADD NEW TASKFORCE
    </x-slot>

    <x-slot name="button1">
        <a href="{{ route('task-forces.index') }}" class="btn-cancel">
            <i class="fas fa-arrow-left mr-1"></i> Back to List
        </a>
    </x-slot>



    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form action="{{ route('task-forces.store') }}" method="POST">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Task Force Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       name="name"
                                       id="name"
                                       value="{{ old('name') }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('name') border-red-500 @enderror"
                                       required>
                                @error('name')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="task_force_type_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Task Force Type <span class="text-red-500">*</span>
                                </label>
                                <select name="task_force_type_id"
                                        id="task_force_type_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('task_force_type_id') border-red-500 @enderror"
                                        required>
                                    <option value="">Select Type</option>
                                    @foreach($taskForceTypes as $type)
                                        <option value="{{ $type->id }}"
                                                {{ (old('task_force_type_id') ?? $selectedTypeId) == $type->id ? 'selected' : '' }}>
                                            {{ $type->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('task_force_type_id')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- New Branch Dropdown --}}
                            <div>
                                <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Branch <span class="text-red-500">*</span>
                                </label>
                                <select name="branch_id"
                                        id="branch_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('branch_id') border-red-500 @enderror"
                                        required>
                                    <option value="">Select Branch</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}"
                                                {{ old('branch_id') == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('branch_id')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="flex justify-end space-x-4">
                            <a href="{{ route('task-forces.index') }}"
                               class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                                Cancel
                            </a>
                            <button type="submit"
                                    class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Create Task Force
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
