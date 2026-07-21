<x-layouts.admin :title="'Edit User: ' . ($user->full_name ?? 'Unknown User')">

    <style>
        @keyframes pulse-shadow {
            0%, 100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.5); }
            50%       { box-shadow: 0 0 0 18px rgba(99, 102, 241, 0); }
        }
        .btn-pulsing-shadow {
            animation: pulse-shadow 1.2s ease-in-out infinite;
        }
    </style>

    <x-slot name="pageHeader">
        <i class="fas fa-user mr-3"></i>Edit Person
    </x-slot>
    <x-slot name="subHeader">
        {{ $user->full_name }} {{ $user->getUserIdReferenceShortAttribute() ?? 'Unknown User' }}
    </x-slot>

    @if($user->isArchived())
        <div class="max-w-4xl mx-auto mb-6">
            <div class="flex items-center justify-center gap-3 bg-red-50 border-2 border-red-300 rounded-lg py-4 px-6">
                <i class="fas fa-box-archive text-red-500 text-3xl"></i>
                <span class="text-3xl font-bold tracking-wide text-red-700">ARCHIVED</span>
            </div>
        </div>
    @endif


    <div class="container mx-auto px-4 py-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column - Photo and Signature Management -->
            <div class="lg:col-span-1">
                <!-- Profile Photo Management Card -->
                <div class="bg-white shadow rounded-lg overflow-hidden mb-6">
                    <div class="px-6 py-4 bg-gray-50 border-b">
                        <h2 class="text-lg font-semibold text-gray-800">Profile Photo Management</h2>
                    </div>
                    <div class="p-6">
                        <!-- Current Profile Photo Display -->
                        <div class="flex flex-col items-center mb-6">
                            <div class="w-36 h-54 rounded-xl overflow-hidden flex items-center justify-center border-4 border-white shadow-lg
                                @if(($user->gender ?? 'male') === 'female') bg-gradient-to-br from-pink-400 to-purple-500
                                @else bg-gradient-to-br from-blue-400 to-blue-600 @endif">
                                @if($user->picture)
                                    <img src="{{ $user->profile_photo_url }}" alt="Profile Photo" class="w-full h-full object-cover">
                                @else
                                    <i class="fas fa-user text-4xl text-white"></i>
                                @endif
                            </div>
                            @if(!$user->picture)
                                <p class="text-sm text-gray-500 mt-2 text-center">No profile<br>photo uploaded</p>
                            @endif
                            <p class="text-sm text-gray-600 mt-4">Current Profile Photo</p>
                        </div>


                        @if ($errors->has('picture') || $errors->has('captured_photo'))
                            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                                <p class="text-red-600 text-sm">
                                    @foreach ($errors->all() as $error)
                                        {{ $error }}<br>
                                    @endforeach
                                </p>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('users.update-profile-picture', $user) }}" enctype="multipart/form-data">
                            @csrf

                            <div class="mb-4">
                                <label for="picture" class="block text-sm font-medium text-gray-700 mb-1">Upload New Picture</label>
                                <input type="file" id="picture" name="picture"
                                       class="block w-full text-sm text-gray-500
                                               file:mr-4 file:py-2 file:px-4
                                               file:rounded-md file:border-0
                                               file:text-sm file:font-semibold
                                               file:bg-blue-50 file:text-blue-700
                                               hover:file:bg-blue-100"
                                       accept="image/jpeg,image/png,image/jpg,image/gif">
                                <p class="form-hint" id="file_input_help">JPEG, PNG, JPG, GIF (MAX. 2MB).</p>
                            </div>

                            <div class="relative flex py-5 items-center">
                                <div class="flex-grow border-t border-gray-300"></div>
                                <span class="flex-shrink mx-4 text-gray-400 text-sm">OR</span>
                                <div class="flex-grow border-t border-gray-300"></div>
                            </div>

                            <!-- Camera Section for Profile Photo -->
                            <div class="mb-4 p-4 border-2 border-dashed border-gray-300 rounded-lg bg-gray-50">
                                <div id="camera-section-profile" class="text-center">
                                    <video id="camera-video-profile" class="w-full h-48 bg-black rounded-lg mb-3 hidden" autoplay playsinline></video>
                                    <canvas id="camera-canvas-profile" class="hidden"></canvas>
                                    <div id="camera-preview-profile" class="w-full h-48 bg-gray-200 rounded-lg mb-3 flex items-center justify-center hidden">
                                        <img id="captured-image-profile" class="max-w-full max-h-full object-contain rounded-lg" alt="Captured Photo" />
                                    </div>

                                    <div id="camera-controls-profile">
                                        <button type="button" id="start-camera-profile" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200 mb-2">
                                            📷 Take Photo
                                        </button>
                                        <div id="capture-controls-profile" class="hidden space-x-2">
                                            <button type="button" id="capture-photo-profile" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-200">
                                                📸 Capture
                                            </button>
                                            <button type="button" id="stop-camera-profile" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition duration-200">
                                                ❌ Cancel
                                            </button>
                                        </div>
                                        <div id="retake-controls-profile" class="hidden space-x-2">
                                            <button type="button" id="use-photo-profile" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-200">
                                                ✅ Use Photo
                                            </button>
                                            <button type="button" id="retake-photo-profile" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition duration-200">
                                                🔄 Retake
                                            </button>
                                        </div>
                                    </div>
                                    <input type="hidden" name="captured_photo" id="captured-photo-data-profile">
                                    <p class="text-xs text-gray-600 mt-2">Click "Take Photo" to use your camera.</p>
                                </div>
                            </div>

                            <div class="flex justify-center mt-6">
                                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md disabled:opacity-50" id="update-profile-photo-button">
                                    Update Profile Photo
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Signature Management Card -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b">
                        <h2 class="text-lg font-semibold text-gray-800">Signature Management</h2>
                    </div>
                    <div class="p-6">
                        <!-- Current Signature Display -->
                        <div class="flex flex-col items-center mb-6">
                            @if($user->hasSignature())
                                <div class="w-48 h-24 overflow-hidden flex items-center justify-center border-2 border-gray-300 bg-white shadow-sm">
                                    <img src="{{ $user->getSignatureUrlAttribute() }}" alt="User Signature" class="w-full h-full object-contain">
                                </div>
                            @else
                                <div class="w-48 h-24 flex items-center justify-center border-2 border-gray-300 bg-gray-50 text-gray-400 text-xs text-center p-2">
                                    No signature uploaded
                                </div>
                            @endif
                            <p class="text-sm text-gray-600 mt-4">Current Signature</p>
                        </div>

                        @if ($errors->has('signature_file') || $errors->has('captured_signature'))
                            <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                                <p class="text-red-600 text-sm">
                                    @foreach ($errors->all() as $error)
                                        {{ $error }}<br>
                                    @endforeach
                                </p>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('users.update-signature', $user) }}" enctype="multipart/form-data">
                            @csrf

                            <div class="mb-4">
                                <label for="signature_file" class="block text-sm font-medium text-gray-700 mb-1">Upload New Signature</label>
                                <input type="file" id="signature_file" name="signature_file"
                                       class="block w-full text-sm text-gray-500
                                               file:mr-4 file:py-2 file:px-4
                                               file:rounded-md file:border-0
                                               file:text-sm file:font-semibold
                                               file:bg-blue-50 file:text-blue-700
                                               hover:file:bg-blue-100"
                                       accept="image/jpeg,image/png,image/jpg,image/gif">
                                <p class="form-hint">PNG recommended (MAX. 1MB). Recommended dimensions: 300x150px.</p>
                            </div>

                            <div class="relative flex py-5 items-center">
                                <div class="flex-grow border-t border-gray-300"></div>
                                <span class="flex-shrink mx-4 text-gray-400 text-sm">OR</span>
                                <div class="flex-grow border-t border-gray-300"></div>
                            </div>

                            <!-- Camera Section for Signature -->
                            <div class="mb-4 p-4 border-2 border-dashed border-gray-300 rounded-lg bg-gray-50">
                                <div id="camera-section-signature" class="text-center">
                                    <video id="camera-video-signature" class="w-full h-48 bg-black rounded-lg mb-3 hidden" autoplay playsinline></video>
                                    <canvas id="camera-canvas-signature" class="hidden"></canvas>
                                    <div id="camera-preview-signature" class="w-full h-48 bg-gray-200 rounded-lg mb-3 flex items-center justify-center hidden">
                                        <img id="captured-image-signature" class="max-w-full max-h-full object-contain rounded-lg" alt="Captured Signature" />
                                    </div>

                                    <div id="camera-controls-signature">
                                        <button type="button" id="start-camera-signature" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200 mb-2">
                                            ✍️ Capture Signature
                                        </button>
                                        <div id="capture-controls-signature" class="hidden space-x-2">
                                            <button type="button" id="capture-signature" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-200">
                                                📸 Capture
                                            </button>
                                            <button type="button" id="stop-camera-signature" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition duration-200">
                                                ❌ Cancel
                                            </button>
                                        </div>
                                        <div id="retake-controls-signature" class="hidden space-x-2">
                                            <button type="button" id="use-signature" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-200">
                                                ✅ Use Signature
                                            </button>
                                            <button type="button" id="retake-signature" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition duration-200">
                                                🔄 Retake
                                            </button>
                                        </div>
                                    </div>
                                    <input type="hidden" name="captured_signature" id="captured-photo-data-signature">
                                    <p class="text-xs text-gray-600 mt-2">Use your camera to capture a signature (e.g., written on paper).</p>
                                </div>
                            </div>

                            <div class="flex justify-center mt-6">
                                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-md disabled:opacity-50" id="update-signature-button">
                                    Update Signature
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column - Edit Form -->
            <div class="lg:col-span-2">
                <!-- Edit Form -->
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <form action="{{ route('users.update', $user) }}" method="POST" class="p-6">
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

                        <!-- Organization & Location Section -->
                        <div class="form-section">
                            <h3 class="form-section-header">Organization & Location</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Branch -->
                                <div>
                                    <label for="branch_id" class="form-label">
                                        Branch <span class="text-red-500">*</span>
                                    </label>
                                    <select id="branch_id" name="branch_id" required
                                            class="form-select @error('branch_id') form-select-error @enderror">
                                        <option value="">Select a branch</option>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}" {{ old('branch_id', $user->branch_id) == $branch->id ? 'selected' : '' }}>
                                                {{ $branch->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('branch_id')
                                    <p class="form-error">{{ $message }}</p>
                                    @enderror
                                    @if($user->hasAnyRole(array_merge(\App\Models\User::BRANCH_ROLES, \App\Models\User::DIVISION_ROLES)))
                                        <div class="mt-2 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                                            <i class="fas fa-triangle-exclamation mr-1 text-amber-500"></i>
                                            This person has the role
                                            <span class="font-semibold">
                                                {{ $user->getRoleNames()->map(fn($r) => ucwords(str_replace('_', ' ', $r)))->join(', ') }}
                                            </span>.
                                            Changing their branch is blocked until the role is removed
                                            via <a href="{{ route('users.roles.edit', ['user_id' => $user->id]) }}"
                                                   class="underline font-medium">Authorizations</a>.
                                        </div>
                                    @endif
                                </div>

                                <!-- Division -->
                                <div>
                                    <label for="division_id" class="form-label">
                                        Division <span class="text-red-500">*</span>
                                    </label>
                                    <select id="division_id" name="division_id" required
                                            class="form-select @error('division_id') form-select-error @enderror">
                                        <option value="">Select a division</option>
                                        @foreach($divisions as $division)
                                            <option value="{{ $division->id }}" {{ old('division_id', $user->division_id) == $division->id ? 'selected' : '' }}>
                                                {{ $division->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('division_id')
                                    <p class="form-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Red Cross Unit (Highlighted) -->
                                <div class="p-4 border border-blue-300 rounded-md bg-blue-50">
                                    <label for="red_cross_unit_id" class="form-label">
                                        Red Cross Unit
                                    </label>
                                    <select id="red_cross_unit_id" name="red_cross_unit_id"
                                            class="form-select @error('red_cross_unit_id') form-select-error @enderror">
                                        <option value="">NOT ASSIGNED</option> {{-- Changed to NOT ASSIGNED --}}
                                        @foreach($redCrossUnits as $unit)
                                            <option value="{{ $unit->id }}" {{ old('red_cross_unit_id', $user->red_cross_unit_id) == $unit->id ? 'selected' : '' }}>
                                                {{ $unit->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('red_cross_unit_id')
                                    <p class="form-error">{{ $message }}</p>
                                    @enderror

                                    @if($user->isUnassignedGhost())
                                        <div class="mt-2 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                                            <i class="fas fa-triangle-exclamation mr-1 text-amber-500"></i>
                                            This volunteer is currently unassigned to a Red Cross Unit.
                                            <span class="font-semibold">Please ensure they are assigned to a new unit as soon as possible.</span>

                                            <p class="mt-2">
                                                Moving to another branch? They can update their branch on My Profile, then the new branch assigns a unit — or National HQ can move them directly.
                                            </p>

                                            <p class="mt-2">
                                                Prefer they become a paying member instead? Make payment for them.
                                            </p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Personal Information Section -->
                        <div class="form-section">
                            <h3 class="form-section-header">Personal Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                                {{-- Title (optional) --}}
                                <div>
                                    <x-select-titles :value="old('title', $user->title)" />
                                </div>



                                <!-- First Name -->
                                <div>
                                    <label for="first_name" class="form-label">
                                        First Name <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text"
                                           id="first_name"
                                           name="first_name"
                                           value="{{ old('first_name', $user->first_name) }}"
                                           required
                                           class="form-input @error('first_name') form-input-error @enderror">
                                    @error('first_name')
                                    <p class="form-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Middle Name -->
                                <div>
                                    <label for="middle_name" class="form-label">
                                        Middle Name
                                    </label>
                                    <input type="text"
                                           id="middle_name"
                                           name="middle_name"
                                           value="{{ old('middle_name', $user->middle_name) }}"
                                           class="form-input @error('middle_name') form-input-error @enderror">
                                    @error('middle_name')
                                    <p class="form-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Last Name -->
                                <div>
                                    <label for="last_name" class="form-label">
                                        Last Name <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text"
                                           id="last_name"
                                           name="last_name"
                                           value="{{ old('last_name', $user->last_name) }}"
                                           required
                                           class="form-input @error('last_name') form-input-error @enderror">
                                    @error('last_name')
                                    <p class="form-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Email -->
                                <div>
                                    <label for="email" class="form-label">
                                        Email Address
                                    </label>
                                    <input type="email"
                                           id="email"
                                           name="email"
                                           value="{{ old('email', $user->email) }}"

                                           class="form-input @error('email') form-input-error @enderror">
                                    <p class="mt-1 text-sm text-yellow-600">Updating this field will require the user to verify their email again.</p>
                                    @error('email')
                                    <p class="form-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Gender -->
                                <div>
                                    <label for="gender" class="form-label">
                                        Gender <span class="text-red-500">*</span>
                                    </label>
                                    <select id="gender" name="gender"
                                            class="form-select @error('gender') form-select-error @enderror">
                                        <option value="">Select gender</option>
                                        <option value="male" {{ old('gender', $user->gender) == 'male' ? 'selected' : '' }}>Male</option>
                                        <option value="female" {{ old('gender', $user->gender) == 'female' ? 'selected' : '' }}>Female</option>
                                    </select>
                                    @error('gender')
                                    <p class="form-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Birth Year -->
                                <div>
                                    <label for="birth_year" class="form-label">
                                        Birth Year <span class="text-red-500">*</span>
                                    </label>
                                    <input type="number"
                                           id="birth_year"
                                           name="birth_year"
                                           value="{{ old('birth_year', $user->birth_year) }}"
                                           min="1900"
                                           max="{{ date('Y') - 10 }}"
                                           class="form-input @error('birth_year') form-input-error @enderror">
                                    @error('birth_year')
                                    <p class="form-error">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information Section -->
                        <div class="form-section">
                            <h3 class="form-section-header">Contact Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Telephone 1 -->
                                <div>
                                    <label for="telephone1" class="form-label">
                                        Primary Phone
                                    </label>
                                    <input type="text"
                                           id="telephone1"
                                           name="telephone1"
                                           value="{{ old('telephone1', $user->telephone1) }}"
                                           class="form-input @error('telephone1') form-input-error @enderror">
                                    @error('telephone1')
                                    <p class="form-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Telephone 2 -->
                                <div>
                                    <label for="telephone2" class="form-label">
                                        Secondary Phone
                                    </label>
                                    <input type="text"
                                           id="telephone2"
                                           name="telephone2"
                                           value="{{ old('telephone2', $user->telephone2) }}"
                                           class="form-input @error('telephone2') form-input-error @enderror">
                                    @error('telephone2')
                                    <p class="form-error">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Residential Address -->
                            <div class="mt-6">
                                <label for="residential_address" class="form-label">
                                    Residential Address
                                </label>
                                <textarea id="residential_address"
                                          name="residential_address"
                                          rows="3"
                                          class="form-textarea @error('residential_address') form-textarea-error @enderror">{{ old('residential_address', $user->residential_address) }}</textarea>
                                @error('residential_address')
                                <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Organization/Workplace (Moved) -->
                            <div class="mt-6">
                                <label for="organisation" class="form-label">
                                    Organization/Workplace
                                </label>
                                <input type="text"
                                       id="organisation"
                                       name="organisation"
                                       value="{{ old('organisation', $user->organisation) }}"
                                       class="form-input @error('organisation') form-input-error @enderror">
                                @error('organisation')
                                <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Workplace Address (Added, logically connected to Organization/Workplace) -->
                            <div class="mt-6">
                                <label for="workplace_address" class="form-label">
                                    Workplace Address
                                </label>
                                <textarea id="workplace_address"
                                          name="workplace_address"
                                          rows="3"
                                          class="form-textarea @error('workplace_address') form-textarea-error @enderror">{{ old('workplace_address', $user->workplace_address) }}</textarea>
                                @error('workplace_address')
                                <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>



                        <!-- Additional Information Section -->
                        <div class="form-section">
                            <h3 class="form-section-header">Additional Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Marital Status -->
                                <div>
                                    <label for="marital_status" class="form-label">
                                        Marital Status
                                    </label>
                                    <select id="marital_status" name="marital_status"
                                            class="form-select @error('marital_status') form-select-error @enderror">
                                        <option value="">Select status</option>
                                        <option value="single" {{ old('marital_status', $user->marital_status) == 'single' ? 'selected' : '' }}>Single</option>
                                        <option value="married" {{ old('marital_status', $user->marital_status) == 'married' ? 'selected' : '' }}>Married</option>
                                        <option value="other" {{ old('marital_status', $user->marital_status) == 'other' ? 'selected' : '' }}>Other</option>
                                    </select>

                                    @error('marital_status')
                                    <p class="form-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Profession Select (Occupation) -->
                                <div>
                                    @include('includes.profession-select', ['currentOccupation' => old('occupation', $user->occupation)])
                                </div>

                                <!-- Discipline -->
                                <div>
                                    <label for="disciplin" class="form-label">
                                        Education/Training
                                    </label>
                                    <input type="text"
                                           id="disciplin"
                                           name="disciplin"
                                           value="{{ old('disciplin', $user->disciplin) }}"
                                           class="form-input @error('disciplin') form-input-error @enderror">
                                    @error('disciplin')
                                    <p class="form-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- National ID -->
                                <div>
                                    <label for="national_id_number" class="form-label">
                                        National ID Number
                                    </label>
                                    <input type="text"
                                           id="national_id_number"
                                           name="national_id_number"
                                           value="{{ old('national_id_number', $user->national_id_number) }}"
                                           class="form-input @error('national_id_number') form-input-error @enderror">
                                    @error('national_id_number')
                                    <p class="form-error">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <!-- Personal Info -->
                            <div class="mt-6">
                                <label for="personal_info" class="form-label">
                                    Personal Information
                                </label>
                                <textarea id="personal_info"
                                          name="personal_info"
                                          rows="3"
                                          class="form-textarea @error('personal_info') form-textarea-error @enderror">{{ old('personal_info', $user->personal_info) }}</textarea>
                                @error('personal_info')
                                <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Preferences Section -->
                        <div class="form-section">
                            <h3 class="form-section-header">Contribution Preference <span class="text-red-500">*</span></h3>
                            @php
                                $currentContributionType = $user->can_contribute_volunteering ? 'volunteering' : ($user->can_contribute_member ? 'member' : null);
                            @endphp
                            @error('contribution_type')
                            <div class="mb-3 p-3 bg-red-50 border border-red-200 rounded-md">
                                <p class="text-red-600 text-sm">{{ $message }}</p>
                            </div>
                            @enderror
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <label for="contribution_volunteering" class="flex items-start p-4 border border-gray-200 rounded-lg hover:border-blue-300 transition-colors duration-200 cursor-pointer @error('contribution_type') border-red-300 @enderror">
                                    <div class="flex items-center h-5">
                                        <input type="radio"
                                               id="contribution_volunteering"
                                               name="contribution_type"
                                               value="volunteering"
                                               class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                               {{ old('contribution_type', $currentContributionType) === 'volunteering' ? 'checked' : '' }}>
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <span class="font-medium text-gray-700">Volunteer Services</span>
                                        <p class="text-gray-500 mt-1">Available for volunteering</p>
                                    </div>
                                </label>
                                <label for="contribution_member" class="flex items-start p-4 border border-gray-200 rounded-lg hover:border-blue-300 transition-colors duration-200 cursor-pointer @error('contribution_type') border-red-300 @enderror">
                                    <div class="flex items-center h-5">
                                        <input type="radio"
                                               id="contribution_member"
                                               name="contribution_type"
                                               value="member"
                                               class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                               {{ old('contribution_type', $currentContributionType) === 'member' ? 'checked' : '' }}>
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <span class="font-medium text-gray-700">Active Membership</span>
                                        <p class="text-gray-500 mt-1">Available as member</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <!-- Password Section -->
                        <div class="form-section">
                            <h3 class="form-section-header">Set Password</h3>
                            @if(!old('email', $user->email))

                                <div class="mb-4 rounded bg-yellow-100 border-l-4 border-yellow-500 p-4 text-sm text-yellow-900">
                                    <p class="font-semibold">
                                        {{$user->full_name}} does not have an email address on file.
                                    </p>

                                    <p class="mt-2">
                                        <strong>Option A:</strong> Advise the user to log in using:
                                    </p>

                                    <ul class="mt-2 list-disc list-inside">
                                        <li>
                                            <strong>Phone Number:</strong> {{ $user->telephone1 }}
                                            (or whatever variant they normally use — spaces/dashes don't matter)
                                        </li>
                                        <li>
                                            <strong>Password:</strong> the password you enter here
                                        </li>
                                    </ul>

                                    {{-- Highlighted Option B --}}
                                    <div class="mt-4 rounded-md border border-green-300 bg-green-50 p-3 text-green-900">
                                        <p class="font-semibold">
                                            Option B (recommended)
                                        </p>

                                        <p class="mt-1">
                                            If the user has an email address, enter it above instead.
                                            They can then log in using their email and set their own password
                                            by clicking <em>“Forgot password”</em>.
                                        </p>
                                    </div>
                                </div>


                                <div class="flex items-center gap-3 mb-3">
                                    <button type="button" id="generate-password-btn"
                                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                        <i class="fas fa-dice mr-2"></i>Generate Password
                                    </button>
                                    <button type="button" id="toggle-password-visibility-btn"
                                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                        <i class="fas fa-eye mr-2"></i><span id="toggle-password-visibility-label">Show Password</span>
                                    </button>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="password" class="form-label">
                                            New Password
                                        </label>
                                        <input type="password"
                                               id="password"
                                               name="password"
                                               class="form-input @error('password') form-input-error @enderror">
                                        @error('password')
                                        <p class="form-error">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Password Confirmation -->
                                    <div>
                                        <label for="password_confirmation"
                                               class="form-label">
                                            Confirm New Password
                                        </label>
                                        <input type="password"
                                               id="password_confirmation"
                                               name="password_confirmation"
                                               class="form-input">
                                    </div>
                                </div>
                                <p class="form-hint">Leave blank if you don't want to provide the
                                    password.</p>

                            </div>
                            @else
                                <div class="mb-4 rounded-lg bg-blue-50 border-l-4 border-blue-400 p-5 text-base text-blue-900">
                                    <p class="font-semibold mb-2 flex items-center gap-2">
                                        <i class="fas fa-info-circle text-blue-500"></i>
                                        Forgotten password?
                                    </p>
                                    <p>If {{ $user->full_name }} has forgotten their password, advise them to go to the login page and click <em>"Forgot password"</em>. A reset link will be sent to <span class="font-medium">{{ old('email', $user->email) }}</span>.</p>
                                    <p class="mt-3">If the problem persists, verify that the email address is correct and that the person has access to that inbox. Also check spam folder.</p>
                                </div>
                            @endif
                        </div>

                        <div class="form-section">
                            <h3 class="form-section-header">Archive user</h3>
                            <div class="p-4 border border-gray-200 rounded-lg bg-gray-50">
                                <input type="hidden" name="is_inactive" value="0">
                                <label class="flex items-center space-x-3">
                                    <input type="checkbox" name="is_inactive" value="1"
                                           class="h-5 w-5 text-red-600 focus:ring-red-500 border-gray-300 rounded"
                                           {{ old('is_inactive', $user->lifecycle_status === 'archived') ? 'checked' : '' }}>
                                    <span class="text-sm text-gray-700 font-medium">Archive this user</span>
                                </label>

                                @if($user->organisations->isNotEmpty())
                                    @php
                                        $soleContactOrgs = $user->organisations->filter(function ($org) use ($user) {
                                            return $org->users->count() <= 1;
                                        });
                                    @endphp

                                    @if($soleContactOrgs->isNotEmpty())
                                        <div class="mt-2 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                                            <i class="fas fa-triangle-exclamation mr-1 text-amber-500"></i>
                                            This person is the <span class="font-semibold">only linked contact</span> for
                                            {{ $soleContactOrgs->pluck('name')->join(', ', ' and ') }}.
                                            Archiving them will prevent that organisation from making membership
                                            payments or donations until a new contact is linked.
                                        </div>
                                    @else
                                        <div class="mt-2 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-xs text-amber-800">
                                            <i class="fas fa-triangle-exclamation mr-1 text-amber-500"></i>
                                            This person is linked to {{ $user->organisations->pluck('name')->join(', ', ' and ') }}.
                                            Other contacts are also linked, so archiving them will not block the
                                            organisation's ability to make payments or donations.
                                        </div>
                                    @endif
                                @endif

                                @if($user->lifecycle_status === 'archived')
                                    <p class="mt-3 text-sm text-gray-600">
                                        Status: Archived.
                                    </p>
                                    <p class="inline-block mt-3 text-sm text-gray-600 bg-yellow-100 p-2 rounded">
                                        Uncheck to reactivate.
                                    </p>
                                @else
                                    <p class="mt-3 text-sm text-gray-600">Status: Active</p>
                                @endif
                            </div>
                        </div>

                        <!-- Communication Preferences Section -->
                        <div class="form-section">
                            <h3 class="form-section-header">Communication Preferences</h3>
                            <div class="space-y-4">
                                <div>
                                    <div class="flex items-center">
                                        <input type="hidden" name="email_opt_out" value="0">
                                        <input type="checkbox"
                                               id="email_opt_out"
                                               name="email_opt_out"
                                               value="1"
                                               {{ old('email_opt_out', $user->email_opt_out) ? 'checked' : '' }}
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <label for="email_opt_out" class="ml-2 block text-sm text-gray-900">
                                            Opt out of email campaigns
                                        </label>
                                    </div>
                                    @if($user->email_opt_out_at)
                                        <p class="mt-1 ml-6 text-xs text-gray-400">Opted out on {{ $user->email_opt_out_at->format('M d, Y') }}</p>
                                    @endif
                                </div>

                                <div>
                                    <div class="flex items-center">
                                        <input type="hidden" name="sms_opt_out" value="0">
                                        <input type="checkbox"
                                               id="sms_opt_out"
                                               name="sms_opt_out"
                                               value="1"
                                               {{ old('sms_opt_out', $user->sms_opt_out) ? 'checked' : '' }}
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <label for="sms_opt_out" class="ml-2 block text-sm text-gray-900">
                                            Opt out of SMS campaigns
                                        </label>
                                    </div>
                                    @if($user->sms_opt_out_at)
                                        <p class="mt-1 ml-6 text-xs text-gray-400">Opted out on {{ $user->sms_opt_out_at->format('M d, Y') }}</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex justify-end items-center pt-6 border-t border-gray-200">
                            <div class="space-x-3">
                                <a href="{{ route('users.show', $user) }}"
                                   class="tn-cancel">
                                    Cancel
                                </a>
                                <button type="submit"
                                        class="btn-primary">
                                    Update Person
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    {{-- (Other existing HTML and Blade content) --}}

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Profile Photo Elements
                const startCameraProfileBtn = document.getElementById('start-camera-profile');
                const videoProfile = document.getElementById('camera-video-profile');
                const canvasProfile = document.getElementById('camera-canvas-profile');
                const previewProfile = document.getElementById('camera-preview-profile');
                const capturedImageProfile = document.getElementById('captured-image-profile');
                const capturePhotoProfileBtn = document.getElementById('capture-photo-profile');
                const stopCameraProfileBtn = document.getElementById('stop-camera-profile');
                const usePhotoProfileBtn = document.getElementById('use-photo-profile');
                const retakePhotoProfileBtn = document.getElementById('retake-photo-profile');
                const capturedPhotoDataProfile = document.getElementById('captured-photo-data-profile');
                const captureControlsProfile = document.getElementById('capture-controls-profile');
                const retakeControlsProfile = document.getElementById('retake-controls-profile');
                let streamProfile;

                // Signature Elements
                const startCameraSignatureBtn = document.getElementById('start-camera-signature');
                const videoSignature = document.getElementById('camera-video-signature');
                const canvasSignature = document.getElementById('camera-canvas-signature');
                const previewSignature = document.getElementById('camera-preview-signature');
                const capturedImageSignature = document.getElementById('captured-image-signature');
                const captureSignatureBtn = document.getElementById('capture-signature');
                const stopCameraSignatureBtn = document.getElementById('stop-camera-signature');
                const useSignatureBtn = document.getElementById('use-signature');
                const retakeSignatureBtn = document.getElementById('retake-signature');
                const capturedPhotoDataSignature = document.getElementById('captured-photo-data-signature');
                const captureControlsSignature = document.getElementById('capture-controls-signature');
                const retakeControlsSignature = document.getElementById('retake-controls-signature');
                let streamSignature;

                // Pulsing "Update" button elements
                const pictureFileInput = document.getElementById('picture');
                const updateProfilePhotoButton = document.getElementById('update-profile-photo-button');
                const signatureFileInput = document.getElementById('signature_file');
                const updateSignatureButton = document.getElementById('update-signature-button');

                function updateButtonPulse(fileInput, capturedDataInput, button) {
                    if (!button) return;
                    const hasContent = (fileInput && fileInput.files.length > 0) || (capturedDataInput && capturedDataInput.value !== '');
                    button.classList.toggle('btn-pulsing-shadow', hasContent);
                }

                if (pictureFileInput) {
                    pictureFileInput.addEventListener('change', () => updateButtonPulse(pictureFileInput, capturedPhotoDataProfile, updateProfilePhotoButton));
                }
                if (signatureFileInput) {
                    signatureFileInput.addEventListener('change', () => updateButtonPulse(signatureFileInput, capturedPhotoDataSignature, updateSignatureButton));
                }

                // Generic function to stop camera stream
                function stopCamera(stream) {
                    if (stream) {
                        stream.getTracks().forEach(track => track.stop());
                    }
                }

                // Generic function to reset camera-related UI
                function resetCameraUI(video, preview, capturedImage, startBtn, captureControls, retakeControls, capturedDataInput, fileInput, button) {
                    video.classList.add('hidden');
                    preview.classList.add('hidden');
                    capturedImage.src = '';
                    startBtn.classList.remove('hidden');
                    captureControls.classList.add('hidden');
                    retakeControls.classList.add('hidden');
                    capturedDataInput.value = '';
                    updateButtonPulse(fileInput, capturedDataInput, button);
                }

                // Generic function to start camera
                async function startCamera(video, type) {
                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
                        if (type === 'profile') streamProfile = stream;
                        else streamSignature = stream;

                        video.srcObject = stream;
                        video.classList.remove('hidden');
                        video.classList.add('inline-block'); // Ensure it's visible and occupies space
                        return stream;
                    } catch (err) {
                        console.error("Error accessing camera: ", err);
                        alert("Could not access the camera. Please ensure it's enabled and not in use by another application.");
                        return null;
                    }
                }

                // Profile Photo Logic
                startCameraProfileBtn.addEventListener('click', async () => {
                    stopCamera(streamSignature); // Stop other camera if active
                    resetCameraUI(videoSignature, previewSignature, capturedImageSignature, startCameraSignatureBtn, captureControlsSignature, retakeControlsSignature, capturedPhotoDataSignature, signatureFileInput, updateSignatureButton);

                    startCameraProfileBtn.classList.add('hidden');
                    const stream = await startCamera(videoProfile, 'profile');
                    if (stream) {
                        captureControlsProfile.classList.remove('hidden');
                    } else {
                        startCameraProfileBtn.classList.remove('hidden'); // Show button again if camera failed to start
                    }
                });

                capturePhotoProfileBtn.addEventListener('click', () => {
                    canvasProfile.width = videoProfile.videoWidth;
                    canvasProfile.height = videoProfile.videoHeight;
                    const context = canvasProfile.getContext('2d');
                    context.drawImage(videoProfile, 0, 0, canvasProfile.width, canvasProfile.height);

                    const dataUrl = canvasProfile.toDataURL('image/jpeg', 0.9); // Adjust quality as needed
                    capturedImageProfile.src = dataUrl;
                    capturedPhotoDataProfile.value = dataUrl; // Store Base64 data

                    videoProfile.classList.add('hidden');
                    previewProfile.classList.remove('hidden');
                    captureControlsProfile.classList.add('hidden');
                    retakeControlsProfile.classList.remove('hidden');
                    stopCamera(streamProfile); // Stop the video stream after capturing
                    updateButtonPulse(pictureFileInput, capturedPhotoDataProfile, updateProfilePhotoButton);
                });

                stopCameraProfileBtn.addEventListener('click', () => {
                    stopCamera(streamProfile);
                    resetCameraUI(videoProfile, previewProfile, capturedImageProfile, startCameraProfileBtn, captureControlsProfile, retakeControlsProfile, capturedPhotoDataProfile, pictureFileInput, updateProfilePhotoButton);
                });

                retakePhotoProfileBtn.addEventListener('click', async () => {
                    resetCameraUI(videoProfile, previewProfile, capturedImageProfile, startCameraProfileBtn, captureControlsProfile, retakeControlsProfile, capturedPhotoDataProfile, pictureFileInput, updateProfilePhotoButton);
                    startCameraProfileBtn.classList.add('hidden'); // Keep hidden until camera stream is ready
                    const stream = await startCamera(videoProfile, 'profile');
                    if (stream) {
                        captureControlsProfile.classList.remove('hidden');
                    } else {
                        startCameraProfileBtn.classList.remove('hidden'); // Show button again if camera failed to start
                    }
                });

                usePhotoProfileBtn.addEventListener('click', () => {
                    // Do nothing, the hidden input already holds the data.
                    // The form will submit this data when the main form is submitted.
                    alert('Photo ready for upload. Click Update Profile Picture.');
                });


                // Signature Logic
                startCameraSignatureBtn.addEventListener('click', async () => {
                    stopCamera(streamProfile); // Stop other camera if active
                    resetCameraUI(videoProfile, previewProfile, capturedImageProfile, startCameraProfileBtn, captureControlsProfile, retakeControlsProfile, capturedPhotoDataProfile, pictureFileInput, updateProfilePhotoButton);

                    startCameraSignatureBtn.classList.add('hidden');
                    const stream = await startCamera(videoSignature, 'signature');
                    if (stream) {
                        captureControlsSignature.classList.remove('hidden');
                    } else {
                        startCameraSignatureBtn.classList.remove('hidden'); // Show button again if camera failed to start
                    }
                });

                captureSignatureBtn.addEventListener('click', () => {
                    canvasSignature.width = videoSignature.videoWidth;
                    canvasSignature.height = videoSignature.videoHeight;
                    const context = canvasSignature.getContext('2d');
                    context.drawImage(videoSignature, 0, 0, canvasSignature.width, canvasSignature.height);

                    const dataUrl = canvasSignature.toDataURL('image/png'); // Signatures are typically PNG
                    capturedImageSignature.src = dataUrl;
                    capturedPhotoDataSignature.value = dataUrl; // Store Base64 data

                    videoSignature.classList.add('hidden');
                    previewSignature.classList.remove('hidden');
                    captureControlsSignature.classList.add('hidden');
                    retakeControlsSignature.classList.remove('hidden');
                    stopCamera(streamSignature); // Stop the video stream after capturing
                    updateButtonPulse(signatureFileInput, capturedPhotoDataSignature, updateSignatureButton);
                });

                stopCameraSignatureBtn.addEventListener('click', () => {
                    stopCamera(streamSignature);
                    resetCameraUI(videoSignature, previewSignature, capturedImageSignature, startCameraSignatureBtn, captureControlsSignature, retakeControlsSignature, capturedPhotoDataSignature, signatureFileInput, updateSignatureButton);
                });

                retakeSignatureBtn.addEventListener('click', async () => {
                    resetCameraUI(videoSignature, previewSignature, capturedImageSignature, startCameraSignatureBtn, captureControlsSignature, retakeControlsSignature, capturedPhotoDataSignature, signatureFileInput, updateSignatureButton);
                    startCameraSignatureBtn.classList.add('hidden');
                    const stream = await startCamera(videoSignature, 'signature');
                    if (stream) {
                        captureControlsSignature.classList.remove('hidden');
                    } else {
                        startCameraSignatureBtn.classList.remove('hidden');
                    }
                });

                useSignatureBtn.addEventListener('click', () => {
                    // Do nothing, the hidden input already holds the data.
                    alert('Signature ready for upload when you update the signature.');
                });


                // --- Cascading Dropdowns for Branch, Division, Red Cross Unit ---
                const branchSelect = document.getElementById('branch_id');
                const divisionSelect = document.getElementById('division_id');
                const redCrossUnitSelect = document.getElementById('red_cross_unit_id');

                // Store initially selected values from the user object (provided by Laravel)
                const initialSelectedBranch = "{{ old('branch_id', $user->branch_id) }}";
                const initialSelectedDivision = "{{ old('division_id', $user->division_id) }}";
                const initialSelectedRedCrossUnit = "{{ old('red_cross_unit_id', $user->red_cross_unit_id) }}";

                // Function to reset and disable a select element
                function resetAndDisableSelect(selectElement, placeholderText) {
                    selectElement.innerHTML = `<option value="">${placeholderText}</option>`;
                    selectElement.disabled = true;
                    selectElement.value = ''; // Ensure no stale value is selected
                }

                // Function to populate divisions based on branch
                async function populateDivisions(branchId, selectedDivisionId = '') {
                    resetAndDisableSelect(divisionSelect, 'Select a Branch first');
                    resetAndDisableSelect(redCrossUnitSelect, 'Select a Division first');

                    if (!branchId) {
                        return; // No branch selected, keep dependents disabled
                    }

                    divisionSelect.disabled = false;
                    divisionSelect.innerHTML = '<option value="">Select a Division</option>'; // Default option for divisions

                    try {
                        // Use the dedicated route for divisions
                        const response = await fetch(`/divisions/by-branch?branch_id=${branchId}`);
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        const divisions = await response.json();

                        divisions.forEach(division => {
                            const option = document.createElement('option');
                            option.value = division.id;
                            option.textContent = division.name;
                            divisionSelect.appendChild(option);
                        });

                        // Pre-select division if an initial value is provided and exists in the new options
                        if (selectedDivisionId && Array.from(divisionSelect.options).some(option => option.value == selectedDivisionId)) {
                            divisionSelect.value = selectedDivisionId;
                            // Now populate red cross units for the pre-selected division
                            populateRedCrossUnits(selectedDivisionId, initialSelectedRedCrossUnit);
                        } else {
                            // If no pre-selected division or it's not valid for the current branch, ensure units are reset
                            resetAndDisableSelect(redCrossUnitSelect, 'Select a Division first');
                        }

                    } catch (error) {
                        console.error('Error fetching divisions:', error);
                        // Optionally, display an error message to the user
                        divisionSelect.innerHTML = '<option value="">Error loading divisions</option>';
                        divisionSelect.disabled = true;
                        resetAndDisableSelect(redCrossUnitSelect, 'Select a Division first');
                    }
                }

                // Function to populate Red Cross Units based on division
                async function populateRedCrossUnits(divisionId, selectedUnitId = '') {
                    resetAndDisableSelect(redCrossUnitSelect, 'Select a Division first');

                    if (!divisionId) {
                        return; // No division selected, keep disabled
                    }

                    redCrossUnitSelect.disabled = false;
                    redCrossUnitSelect.innerHTML = '<option value="">NOT ASSIGNED</option>'; // Always add "NOT ASSIGNED" option

                    try {
                        // Use the dedicated route for red cross units
                        const response = await fetch(`/red-cross-units/by-division?division_id=${divisionId}`);
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        const units = await response.json();

                        if (units.length === 0) {
                            // If no units found, just keep "NOT ASSIGNED" as the only option
                            redCrossUnitSelect.disabled = false; // Keep enabled to allow "NOT ASSIGNED"
                            // If selectedUnitId is empty, ensure 'NOT ASSIGNED' is selected.
                            if (!selectedUnitId) {
                                redCrossUnitSelect.value = '';
                            }
                            return;
                        }

                        units.forEach(unit => {
                            const option = document.createElement('option');
                            option.value = unit.id;
                            option.textContent = unit.name;
                            redCrossUnitSelect.appendChild(option);
                        });

                        // Pre-select red cross unit if an initial value is provided and exists in the new options
                        // Or if selectedUnitId is empty (meaning 'NOT ASSIGNED' was intended)
                        if (selectedUnitId && Array.from(redCrossUnitSelect.options).some(option => option.value == selectedUnitId)) {
                            redCrossUnitSelect.value = selectedUnitId;
                        } else if (!selectedUnitId) {
                            // Ensure 'NOT ASSIGNED' is selected if user_red_cross_unit_id is null/empty
                            redCrossUnitSelect.value = '';
                        }
                    } catch (error) {
                        console.error('Error fetching Red Cross Units:', error);
                        // Optionally, display an error message to the user
                        redCrossUnitSelect.innerHTML = '<option value="">Error loading units</option>';
                        redCrossUnitSelect.disabled = true;
                    }
                }

                // Event listener for branch changes
                branchSelect.addEventListener('change', function () {
                    const branchId = this.value;
                    populateDivisions(branchId);
                });

                // Event listener for division changes
                divisionSelect.addEventListener('change', function () {
                    const divisionId = this.value;
                    populateRedCrossUnits(divisionId);
                });

                // Initial setup and population on page load
                // Only try to populate if a branch was already selected (e.g., on form load for an existing user)
                if (initialSelectedBranch) {
                    populateDivisions(initialSelectedBranch, initialSelectedDivision);
                } else {
                    // If no branch is pre-selected, ensure both division and unit dropdowns are disabled
                    resetAndDisableSelect(divisionSelect, 'Select a Branch first');
                    // Initialize Red Cross Unit select with "NOT ASSIGNED" if no division is selected
                    redCrossUnitSelect.innerHTML = '<option value="">NOT ASSIGNED</option>';
                    redCrossUnitSelect.disabled = true;
                }

                // --- Generate Password / Show-Hide Password ---
                const generatePasswordBtn = document.getElementById('generate-password-btn');
                const togglePasswordBtn = document.getElementById('toggle-password-visibility-btn');
                const togglePasswordLabel = document.getElementById('toggle-password-visibility-label');
                const passwordField = document.getElementById('password');
                const passwordConfirmField = document.getElementById('password_confirmation');

                function generateEasyPassword(length = 8) {
                    // Lowercase only (no case-sensitivity confusion on a phone keyboard),
                    // excluding visually-similar characters: 0/o, 1/l/i.
                    const lower = 'abcdefghjkmnpqrstuvwxyz';
                    const digits = '23456789';
                    const all = lower + digits;

                    function randomChar(set) {
                        const bytes = new Uint32Array(1);
                        crypto.getRandomValues(bytes);
                        return set[bytes[0] % set.length];
                    }

                    let password;
                    do {
                        password = Array.from({ length }, () => randomChar(all)).join('');
                    } while (!/[a-z]/.test(password) || !/[0-9]/.test(password));

                    return password;
                }

                if (generatePasswordBtn && passwordField && passwordConfirmField) {
                    generatePasswordBtn.addEventListener('click', () => {
                        const newPassword = generateEasyPassword();
                        passwordField.value = newPassword;
                        passwordConfirmField.value = newPassword;

                        // Reveal the password so the admin can read it back to the user
                        passwordField.type = 'text';
                        passwordConfirmField.type = 'text';
                        if (togglePasswordLabel) togglePasswordLabel.textContent = 'Hide Password';
                    });
                }

                if (togglePasswordBtn && passwordField && passwordConfirmField) {
                    togglePasswordBtn.addEventListener('click', () => {
                        const showing = passwordField.type === 'text';
                        passwordField.type = showing ? 'password' : 'text';
                        passwordConfirmField.type = showing ? 'password' : 'text';
                        if (togglePasswordLabel) togglePasswordLabel.textContent = showing ? 'Show Password' : 'Hide Password';
                    });
                }
            });
        </script>
    @endpush
</x-layouts.admin>
