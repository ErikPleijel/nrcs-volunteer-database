<x-layouts.admin :title="'Edit Activity Type: ' . $activityType->name">

    <x-slot name="pageHeader">
        <i class="fas fa-users mr-3"></i>  Volunteering
    </x-slot>
    <x-slot name="subHeader">
        EDIT ACTIVITY TYPES
    </x-slot>

    <x-slot name="backLink">
        <a href="{{ route('activity-types.index') }}"
           class="btn-backlink">
            ←  Back to ACTIVITY TYPES OVERVIEW
        </a>
    </x-slot>


    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">

            <div class="bg-yellow-600 px-6 py-4">
                <h2 class="text-xl font-semibold text-white flex items-center">
                    <i class="fas fa-edit mr-3"></i>
                    Edit Activity Type: {{ $activityType->name }}
                </h2>
            </div>

            <!-- Note for editing -->
            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6" role="alert">
                <p class="font-bold">Important Note:</p>
                <p>To remove this item from the list, uncheck ‘Active’.</p>

                <p class="font-bold mt-4">Editing Guidelines</p>
                <ul class="list-disc list-inside ml-4">
                    <li>You may correct small errors (e.g., spelling or capitalization).</li>
                    <li>Do not change the meaning of the field.</li>
                </ul>
                <p class="mt-2">✅ Example (allowed):</p>
                <p class="ml-4 font-mono">Disaster Emergensy response → Disaster Emergency Response</p>
                <p class="mt-2">❌ Example (not allowed):</p>
                <p class="ml-4 font-mono">Community Based Health → First Aid</p>
            </div>



            <div class="bg-white shadow-md rounded-lg p-6">
                <form action="{{ route('activity-types.update', $activityType) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
                        <input type="text"
                               id="name"
                               name="name"
                               value="{{ old('name', $activityType->name) }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('name') border-red-500 @enderror"
                               required>
                        @error('name')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>



                    <div class="mb-4">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea id="description"
                                  name="description"
                                  rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('description') border-red-500 @enderror">{{ old('description', $activityType->description) }}</textarea>
                        @error('description')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>


                    <div class="mb-6">
                        <label class="flex items-center">
                            <input type="checkbox"
                                   name="is_active"
                                   value="1"
                                   {{ old('is_active', $activityType->is_active) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm font-medium text-gray-700">Active</span>
                        </label>
                    </div>

                    <div class="flex justify-end space-x-4">
                        <a href="{{ route('activity-types.index') }}"
                           class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 transition duration-300">
                            Cancel
                        </a>
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-300">
                            Update Activity Type
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
