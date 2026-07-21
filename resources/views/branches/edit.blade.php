<x-layouts.admin :title="'Edit Branch: ' . $branch->name">
    <x-slot name="pageHeader">
        <i class="fas fa-sitemap mr-3"></i> Branches
    </x-slot>

    <x-slot name="subHeader">
        Editing {{ $branch->name }}
    </x-slot>

    <x-slot name="button1">
        <a href="{{ route('branches.show', $branch) }}" class="btn-cancel">
            <i class="fas fa-arrow-left mr-1"></i> Back to Branch
        </a>
    </x-slot>



    <div class="container mx-auto px-4 py-6">
        @if($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <strong>Please fix the following errors:</strong>
                <ul class="mt-2 list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-lg shadow">
            <form action="{{ route('branches.update', $branch) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="px-6 py-4 bg-gray-50 border-b">
                    <h2 class="text-xl font-semibold text-gray-800">Branch Information</h2>
                </div>

                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Left Column -->
                        <div class="space-y-4">
                            <!-- Non-editable Branch Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Branch Name</label>
                                <div class="mt-1 block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-900">
                                    {{ $branch->name }}
                                </div>
                            </div>

                            <!-- Non-editable Branch Code -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Branch Code</label>
                                <div class="mt-1 block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-900">
                                    {{ $branch->code }}
                                </div>
                            </div>

                            <!-- Non-editable Zone -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Zone</label>
                                <div class="mt-1 block w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-gray-900">
                                    {{ $branch->zone }}
                                </div>
                            </div>

                            <div>
                                <label for="physical_address" class="block text-sm font-medium text-gray-700">Physical Address</label>
                                <textarea name="physical_address" id="physical_address" rows="3"
                                          class="mt-1 block w-full rounded-md border-2 border-gray-400 shadow-sm focus:border-blue-600 focus:ring-blue-500 focus:ring-2"
                                          placeholder="Enter physical address">{{ old('physical_address', $branch->physical_address) }}</textarea>
                            </div>

                            <div>
                                <label for="postal_address" class="block text-sm font-medium text-gray-700">Postal Address</label>
                                <textarea name="postal_address" id="postal_address" rows="3"
                                          class="mt-1 block w-full rounded-md border-2 border-gray-400 shadow-sm focus:border-blue-600 focus:ring-blue-500 focus:ring-2"
                                          placeholder="Enter postal address">{{ old('postal_address', $branch->postal_address) }}</textarea>
                            </div>

                            <div>
                                <label for="projects" class="block text-sm font-medium text-gray-700">Projects</label>
                                <input type="number" min="0" name="projects" id="projects"
                                       value="{{ old('projects', $branch->projects) }}"
                                       class="mt-1 block w-full rounded-md border-2 border-gray-400 shadow-sm focus:border-blue-600 focus:ring-blue-500 focus:ring-2"
                                       placeholder="Number of projects">
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="space-y-4">
                            <div>
                                <label for="telephone" class="block text-sm font-medium text-gray-700">Telephone</label>
                                <input type="tel" name="telephone" id="telephone" value="{{ old('telephone', $branch->telephone) }}"
                                       class="mt-1 block w-full rounded-md border-2 border-gray-400 shadow-sm focus:border-blue-600 focus:ring-blue-500 focus:ring-2"
                                       placeholder="Enter telephone number">
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                                <input type="email" name="email" id="email" value="{{ old('email', $branch->email) }}"
                                       class="mt-1 block w-full rounded-md border-2 border-gray-400 shadow-sm focus:border-blue-600 focus:ring-blue-500 focus:ring-2"
                                       placeholder="Enter email address">
                            </div>
                        </div>
                    </div>

                    {{-- Contact persons (up to 6) --}}
                    <div class="mt-10 border-t pt-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">
                            Public Contact Persons (shown on profile / public views)
                        </h3>

                        <p class="text-xs text-gray-600 mb-3">
                            For each slot, pick a person (with a role in this branch or its divisions) and optionally specify their position/title,
                            e.g. "Branch Secretary", "Branch Chairperson".
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @for($i = 1; $i <= 6; $i++)
                                <div class="border rounded-md p-3 bg-gray-50">
                                    <h4 class="text-sm font-semibold text-gray-700 mb-2">
                                        Contact {{ $i }}
                                    </h4>

                                    <div class="mb-2">
                                        <label for="public_contact_user_id_{{ $i }}" class="block text-xs font-medium text-gray-700">
                                            Person
                                        </label>
                                        <select
                                            name="public_contact_user_id_{{ $i }}"
                                            id="public_contact_user_id_{{ $i }}"
                                            class="mt-1 block w-full rounded-md border-2 border-gray-400 shadow-sm focus:border-blue-600 focus:ring-blue-500 focus:ring-2 text-sm"
                                        >
                                            <option value="">— None —</option>
                                            @foreach($contactCandidates as $user)
                                                <option value="{{ $user->id }}"
                                                    @selected(old("public_contact_user_id_{$i}", $branch->{"public_contact_user_id_{$i}"} ) == $user->id)
                                                >
                                                    {{ $user->full_name }} (DB-{{ $user->id }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label for="public_contact_position_{{ $i }}" class="block text-xs font-medium text-gray-700">
                                            Position / Title
                                        </label>
                                        <input
                                            type="text"
                                            name="public_contact_position_{{ $i }}"
                                            id="public_contact_position_{{ $i }}"
                                            value="{{ old("public_contact_position_{$i}", $branch->{"public_contact_position_{$i}"} ) }}"
                                            class="mt-1 block w-full rounded-md border-2 border-gray-400 shadow-sm focus:border-blue-600 focus:ring-blue-500 focus:ring-2 text-sm"

                                        >
                                    </div>
                                </div>
                            @endfor
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end space-x-4">
                        <button type="submit"
                                class="btn-primary">
                            <i class="fas fa-check mr-1"></i>Update Branch
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
