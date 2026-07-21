<x-layouts.admin :title="'Edit Division: ' . $division->name">

    <x-slot name="pageHeader">
        <i class="fas fa-layer-group mr-3"></i> Divisions
    </x-slot>

    <x-slot name="subHeader">
        Editing {{ $division->name }}
    </x-slot>

    <x-slot name="button1">
        <a href="{{ route('divisions.show', $division) }}" class="btn-cancel">
            <i class="fas fa-arrow-left mr-1"></i> Back to Division
        </a>
    </x-slot>

    <div class="container mx-auto px-4 py-6">




        <!-- Edit Form Card -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <!-- Card header -->
            <div class="px-6 py-4 bg-gray-50 border-b">
                <h2 class="text-lg font-semibold text-gray-800">Edit Contact Information</h2>
            </div>

            <form action="{{ route('divisions.update', $division) }}" method="POST" class="p-6">
                @csrf
                @method('PUT')

                <!-- Display validation errors -->
                @if ($errors->any())
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Display success message -->
                @if (session('success'))
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                        {{ session('success') }}
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Physical Address -->
                    <div>
                        <label for="physical_address" class="block text-sm font-medium text-gray-700 mb-2">
                            Physical Address
                        </label>
                        <input type="text"
                               id="physical_address"
                               name="physical_address"
                               value="{{ old('physical_address', $division->physical_address) }}"
                               maxlength="150"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('physical_address') border-red-500 @enderror">
                        @error('physical_address')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Postal Address -->
                    <div>
                        <label for="postal_address" class="block text-sm font-medium text-gray-700 mb-2">
                            Postal Address
                        </label>
                        <input type="text"
                               id="postal_address"
                               name="postal_address"
                               value="{{ old('postal_address', $division->postal_address) }}"
                               maxlength="100"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('postal_address') border-red-500 @enderror">
                        @error('postal_address')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Telephone -->
                    <div>
                        <label for="telephone" class="block text-sm font-medium text-gray-700 mb-2">
                            Telephone
                        </label>
                        <input type="tel"
                               id="telephone"
                               name="telephone"
                               value="{{ old('telephone', $division->telephone) }}"
                               maxlength="30"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('telephone') border-red-500 @enderror">
                        @error('telephone')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Email
                        </label>
                        <input type="email"
                               id="email"
                               name="email"
                               value="{{ old('email', $division->email) }}"
                               maxlength="100"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('email') border-red-500 @enderror">
                        @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end items-center mt-8 pt-6 border-t border-gray-200">
                    <button type="submit"
                            class="btn-primary">
                        <i class="fas fa-check mr-1"></i>Update Division
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
