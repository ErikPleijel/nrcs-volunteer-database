<x-layouts.admin>
    <x-slot name="title">Training Types</x-slot>

    <x-slot name="pageHeader">
        <i class="fas fa-graduation-cap mr-3 mb-6"></i> Training Types Create
    </x-slot>


    <div class="container mx-auto px-4 py-6">


        <div class="bg-white shadow-md rounded-lg">
            <form action="{{ route('training-types.store') }}" method="POST" class="p-6">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Name -->
                    <div class="md:col-span-2">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                            Training Type Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               id="name"
                               name="name"
                               value="{{ old('name') }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('name') border-red-500 @enderror"
                               required>
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Validity Years Limit -->
                    <div>
                        <label for="validity_years_limit" class="block text-sm font-medium text-gray-700 mb-2">
                            Validity (Years)
                        </label>
                        <input type="number"
                               id="validity_years_limit"
                               name="validity_years_limit"
                               value="{{ old('validity_years_limit') }}"
                               min="1"
                               max="99"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('validity_years_limit') border-red-500 @enderror"
                               placeholder="Leave empty for no expiry">
                        @error('validity_years_limit')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-sm text-gray-500">Leave empty if the training type has no expiry</p>
                    </div>

                    <!-- Checkboxes -->
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <input type="checkbox"
                                   id="is_active"
                                   name="is_active"
                                   value="1"
                                   {{ old('is_active') ? 'checked' : 'checked' }}
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="is_active" class="ml-2 block text-sm text-gray-900">
                                Active
                            </label>
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox"
                                   id="certificate_hq_only"
                                   name="certificate_hq_only"
                                   value="1"
                                   {{ old('certificate_hq_only') ? 'checked' : '' }}
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="certificate_hq_only" class="ml-2 block text-sm text-gray-900">
                                Certificate can only be issued by HQ
                            </label>
                        </div>

                        <div>
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" name="is_first_aid" value="1"
                                       {{ old('is_first_aid') ? 'checked' : '' }}
                                       class="rounded border-gray-300">
                                <span class="text-sm font-medium text-gray-700">First Aid training</span>
                            </label>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Used to identify first-aid-related training types across filters and reports.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-4 mt-6 pt-6 border-t border-gray-200">
                    <a href="{{ route('training-types.index') }}"
                       class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                        Cancel
                    </a>
                    <button type="submit"
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Create Training Type
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
