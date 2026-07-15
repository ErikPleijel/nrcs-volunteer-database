<x-layouts.admin>
    <x-slot name="title">Edit Training Type</x-slot>

    <x-slot name="pageHeader">
        <i class="fas fa-graduation-cap mr-3 mb-6"></i> Training Types Edit
    </x-slot>


    <div class="container mx-auto px-4 py-6">


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
            <p class="ml-4 font-mono">Basic frst aid → Basic First Aid</p>
            <p class="mt-2">❌ Example (not allowed):</p>
            <p class="ml-4 font-mono">Community First Aid → Child protection</p>
        </div>

        <div class="bg-white shadow-md rounded-lg">
            <form action="{{ route('training-types.update', $trainingType) }}" method="POST" class="p-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Name -->
                    <div class="md:col-span-2">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                            Training Type Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               id="name"
                               name="name"
                               value="{{ old('name', $trainingType->name) }}"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('name') border-red-500 @enderror"
                               required>
                        @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Training Group Dropdown --}}
                    <div>
                        <label for="group_id" class="block text-sm font-medium text-gray-700 mb-2">
                            Training Group
                        </label>
                        <select id="group_id"
                                name="group_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('group_id') border-red-500 @enderror">
                            <option value="">-- Select Group --</option>
                            @foreach($trainingGroups as $group)
                                <option value="{{ $group->id }}"
                                    {{ (old('group_id', $trainingType->group_id) == $group->id) ? 'selected' : '' }}>
                                    {{ $group->group_name }}
                                </option>
                            @endforeach
                        </select>
                        @error('group_id')
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
                               value="{{ old('validity_years_limit', $trainingType->validity_years_limit) }}"
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
                                   {{ old('is_active', $trainingType->is_active) ? 'checked' : '' }}
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
                                   {{ old('certificate_hq_only', $trainingType->certificate_hq_only) ? 'checked' : '' }}
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="certificate_hq_only" class="ml-2 block text-sm text-gray-900">
                                Certificate can only be issued by HQ
                            </label>
                        </div>

                        <div>
                            <label class="inline-flex items-center gap-2">
                                <input type="checkbox" name="is_first_aid" value="1"
                                       {{ old('is_first_aid', $trainingType->is_first_aid) ? 'checked' : '' }}
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
                        Update Training Type
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
