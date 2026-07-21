<x-layouts.admin title="Create Red Cross Unit">
    <x-slot name="pageHeader">
        <i class="fas fa-shield-alt mr-3"></i> Red Cross Units
    </x-slot>
    <x-slot name="subHeader">
        Register a new Red Cross unit
    </x-slot>

    <x-slot name="button1">
        <a href="{{ route('red-cross-units.index') }}" class="btn-cancel">
            <i class="fas fa-arrow-left mr-1"></i> Back to List
        </a>
    </x-slot>


    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form action="{{ route('red-cross-units.store') }}" method="POST">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Unit Name -->
                            <div class="md:col-span-2">
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Red Cross Unit Name <span class="text-red-500">*</span>
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

                            <!-- Division -->
                            <div class="md:col-span-2">
                                <label for="division_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Division <span class="text-red-500">*</span>
                                </label>
                                <select name="division_id"
                                        id="division_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('division_id') border-red-500 @enderror"
                                        required>
                                    <option value="">Select Division</option>
                                    @foreach($divisions as $division)
                                        <option value="{{ $division->id }}"
                                            {{ old('division_id') == $division->id ? 'selected' : '' }}>
                                            {{ $division->name }}
                                            @if($division->branch)
                                                ({{ $division->branch->name }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('division_id')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="border-t pt-6 flex items-center justify-end gap-3">
                            <button type="submit"
                                    class="btn-primary">
                                <i class="fas fa-plus mr-1"></i>Create Red Cross Unit
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.admin>
