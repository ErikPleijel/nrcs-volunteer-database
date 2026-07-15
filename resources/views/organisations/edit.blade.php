<x-layouts.admin title="Edit Organisation">
    <x-slot name="pageHeader">
        <i class="fas fa-industry mr-3 mb-6"></i> Edit Organisation
    </x-slot>



    <div class="show-page-container">
        <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b">
                    <h2 class="text-2xl font-bold text-gray-900">{{ $organisation->name }}</h2>
                    <p class="text-sm text-gray-400 mt-0.5 font-mono">{{ $organisation->org_reference }}</p>
                </div>

                <div class="p-6">

                    @if(session('error'))
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form action="{{ route('organisations.update', $organisation) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <!-- Name -->
                        <div class="form-section">
                            <label for="name" class="form-label">
                                Name <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   name="name"
                                   id="name"
                                   value="{{ old('name', $organisation->name) }}"
                                   class="form-input @error('name') form-input-error @enderror"
                                   required>
                            @error('name')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Short Name -->
                        <div class="form-section">
                            <label for="short_name" class="form-label">
                                Short Name / Acronym
                            </label>
                            <input type="text"
                                   name="short_name"
                                   id="short_name"
                                   value="{{ old('short_name', $organisation->short_name) }}"
                                   class="form-input @error('short_name') form-input-error @enderror">
                            @error('short_name')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Registration Number -->
                        <div class="form-section">
                            <label for="registration_number" class="form-label">
                                Registration Number
                            </label>
                            <input type="text"
                                   name="registration_number"
                                   id="registration_number"
                                   value="{{ old('registration_number', $organisation->registration_number) }}"
                                   class="form-input @error('registration_number') form-input-error @enderror">
                            @error('registration_number')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Email -->
                        <div class="form-section">
                            <label for="email" class="form-label">
                                Email Address
                            </label>
                            <input type="email"
                                   name="email"
                                   id="email"
                                   value="{{ old('email', $organisation->email) }}"
                                   class="form-input @error('email') form-input-error @enderror">
                            @error('email')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Phone -->
                        <div class="form-section">
                            <label for="phone" class="form-label">
                                Phone
                            </label>
                            <input type="text"
                                   name="phone"
                                   id="phone"
                                   value="{{ old('phone', $organisation->phone) }}"
                                   class="form-input @error('phone') form-input-error @enderror">
                            @error('phone')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Address -->
                        <div class="form-section">
                            <label for="address" class="form-label">
                                Address
                            </label>
                            <textarea name="address"
                                      id="address"
                                      rows="3"
                                      class="form-textarea @error('address') form-textarea-error @enderror">{{ old('address', $organisation->address) }}</textarea>
                            @error('address')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Description -->
                        <div class="form-section">
                            <label for="description" class="form-label">
                                Description
                            </label>
                            <textarea name="description"
                                      id="description"
                                      rows="3"
                                      class="form-textarea @error('description') form-textarea-error @enderror">{{ old('description', $organisation->description) }}</textarea>
                            @error('description')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex justify-end items-center gap-3 pt-6 border-t border-gray-200">
                            <a href="{{ route('organisations.show', $organisation) }}" class="btn-cancel">
                                Cancel
                            </a>
                            <button type="submit" class="btn-primary">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
    </div>
</x-layouts.admin>
