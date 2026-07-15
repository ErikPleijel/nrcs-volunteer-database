<x-layouts.admin>
    <x-slot name="title">Task Force Types</x-slot>

    <x-slot name="pageHeader">
        <i class="fas fa-users-gear mr-3 mb-6"></i>  Task Force Types
    </x-slot>

    <x-audit-notice />

    @if (session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ session('error') }}
        </div>
    @endif

    {{-- Add New Task Force Type --}}
    <div class="bg-white shadow-md rounded-lg p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Add New Task Force Type</h2>
        <form method="POST" action="{{ route('task-force-types.store') }}" class="flex items-start gap-3">
            @csrf
            <div class="flex-1 max-w-sm">
                <input type="text"
                       name="name"
                       value="{{ old('name') }}"
                       maxlength="255"
                       placeholder="e.g. Disaster Response Team"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-500 @enderror">
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <input type="hidden" name="level" value="1">
            <button type="submit" class="btn-primary whitespace-nowrap">
                Add
            </button>
        </form>
    </div>

    {{-- Existing Task Force Types --}}
    @if($taskForceTypes->count() > 0)
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <table class="min-w-full table-auto">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task Forces</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-56">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($taskForceTypes as $taskForceType)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 text-sm text-gray-900 font-medium">
                                {{ $taskForceType->name }}
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                {{ $taskForceType->taskForces->count() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('task-force-types.show', $taskForceType) }}" class="btn-primary text-xs py-1 px-3">
                                        View
                                    </a>
                                    <a href="{{ route('task-force-types.edit', $taskForceType) }}" class="btn-primary text-xs py-1 px-3">
                                        Edit
                                    </a>
                                    @if($taskForceType->taskForces->count() == 0)
                                        <form action="{{ route('task-force-types.destroy', $taskForceType) }}" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this task force type?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs font-bold py-1 px-3 rounded">
                                                Delete
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="text-center py-8">
            <p class="text-gray-500 text-lg">No task force types found.</p>
            <a href="{{ route('task-force-types.create') }}" class="mt-4 inline-block btn-primary">
                Create First Task Force Type
            </a>
        </div>
    @endif
</x-layouts.admin>
