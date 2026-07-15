<x-layouts.admin>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Task Force Type Details') }}: {{ $taskForceType->name }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('task-force-types.edit', $taskForceType) }}" class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded">
                    Edit
                </a>
                <a href="{{ route('task-force-types.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to List
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <!-- Task Force Type Information -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-semibold mb-4">Task Force Type Information</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Name</label>
                            <p class="text-lg text-gray-900">{{ $taskForceType->name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500">Level</label>
                            <span class="inline-block bg-gray-200 text-gray-800 px-3 py-1 rounded text-sm font-medium">
                                Level {{ $taskForceType->level }}
                            </span>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500">Include in List</label>
                            @if($taskForceType->include_in_list)
                                <span class="inline-block bg-green-100 text-green-800 px-3 py-1 rounded text-sm font-medium">Yes</span>
                            @else
                                <span class="inline-block bg-red-100 text-red-800 px-3 py-1 rounded text-sm font-medium">No</span>
                            @endif
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-500">Total Task Forces</label>
                            <p class="text-lg text-gray-900">{{ $taskForceType->taskForces->count() }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Associated Task Forces -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Associated Task Forces</h3>
                        <a href="{{ route('task-forces.create') }}?type={{ $taskForceType->id }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm">
                            Add Task Force
                        </a>
                    </div>

                    @if($taskForceType->taskForces->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full table-auto">
                                <thead>
                                    <tr class="bg-gray-100">
                                        <th class="px-4 py-2 text-left">Name</th>
                                        <th class="px-4 py-2 text-left">Status</th>
                                        <th class="px-4 py-2 text-left">Created</th>
                                        <th class="px-4 py-2 text-left">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($taskForceType->taskForces as $taskForce)
                                        <tr class="border-b hover:bg-gray-50">
                                            <td class="px-4 py-2">
                                                <a href="{{ route('task-forces.show', $taskForce) }}" class="text-blue-600 hover:text-blue-900 font-medium">
                                                    {{ $taskForce->name }}
                                                </a>
                                            </td>
                                            <td class="px-4 py-2">
                                                @if($taskForce->inactive ?? false)
                                                    <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-sm">Inactive</span>
                                                @else
                                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm">Active</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-gray-600">
                                                {{ $taskForce->created_at->format('M d, Y') }}
                                            </td>
                                            <td class="px-4 py-2">
                                                <div class="flex space-x-2">
                                                    <a href="{{ route('task-forces.show', $taskForce) }}" class="text-blue-600 hover:text-blue-900">View</a>
                                                    <a href="{{ route('task-forces.edit', $taskForce) }}" class="text-yellow-600 hover:text-yellow-900">Edit</a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-8">
                            <p class="text-gray-500 text-lg">No task forces associated with this type yet.</p>
                            <a href="{{ route('task-forces.create') }}?type={{ $taskForceType->id }}" class="mt-4 inline-block bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Create First Task Force
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
