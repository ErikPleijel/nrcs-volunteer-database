<x-layouts.admin title="Create Activity Type">

    <x-slot name="pageHeader">
        <i class="fas fa-users mr-3"></i>  Volunteering
    </x-slot>
    <x-slot name="subHeader">
        CREATE NEW ACTIVITY TYPE
    </x-slot>

    <x-slot name="backLink">
        <a href="{{ route('activities.index') }}"
           class="btn-backlink">
            ←  Back to FIND & FILTER
        </a>
    </x-slot>


    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">


            <div class="bg-white shadow-md rounded-lg p-6">
                <form action="{{ route('activity-types.store') }}" method="POST">
                    @csrf

                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Name *</label>
                        <input type="text"
                               id="name"
                               name="name"
                               value="{{ old('name') }}"
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
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('description') border-red-500 @enderror">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label class="flex items-center">
                            <input type="checkbox"
                                   name="is_active"
                                   value="1"
                                   {{ old('is_active', true) ? 'checked' : '' }}
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
                            Create Activity Type
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
