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
        <i class="fas fa-user mr-3"></i> Persons
    </x-slot>

    <x-slot name="button1">
        <a href="{{ route('users.show', $user) }}" class="btn-cancel">
            <i class="fas fa-arrow-left mr-1"></i> Back to Person
        </a>
    </x-slot>

    <x-slot name="subHeader">
        Editing {{ $user->full_name }}
    </x-slot>

    @if($user->isArchived())
        <div class="max-w-4xl mx-auto mb-6">
            <div class="flex items-center justify-center gap-3 bg-red-50 border-2 border-red-300 rounded-lg py-4 px-6">
                <i class="fas fa-box-archive text-red-500 text-3xl"></i>
                <span class="text-3xl font-bold tracking-wide text-red-700">ARCHIVED</span>
            </div>
        </div>
    @endif

    {{-- An anonymized account is permanent: no editing, no restore. --}}
    @if($user->anonymized_at !== null)
        <div class="max-w-4xl mx-auto mb-6">
            <div class="rounded-lg border border-gray-300 bg-gray-50 px-6 py-4 text-center text-gray-700">
                This account was anonymized on {{ \Illuminate\Support\Carbon::parse($user->anonymized_at)->format('M d, Y') }}.
                It can no longer be edited or restored.
            </div>
        </div>
    @else

    <div class="container mx-auto px-4 py-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column - Photo and Signature (uploaded on their own page, users/images) -->
            <div class="lg:col-span-1">
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <div class="px-6 py-4 bg-gray-50 border-b">
                        <h2 class="text-lg font-semibold text-gray-800">Photo &amp; Signature</h2>
                    </div>
                    <div class="p-6 flex flex-col items-center gap-4">
                        <div class="w-24 h-32 rounded-xl overflow-hidden flex items-center justify-center border-4 border-white shadow
                            @if(($user->gender ?? 'male') === 'female') bg-gradient-to-br from-pink-400 to-purple-500
                            @else bg-gradient-to-br from-blue-400 to-blue-600 @endif">
                            @if($user->picture)
                                <img src="{{ $user->profile_photo_url }}" alt="Profile Photo" class="w-full h-full object-cover">
                            @else
                                <i class="fas fa-user text-3xl text-white"></i>
                            @endif
                        </div>
                        @can('uploadImages', $user)
                            <a href="{{ route('users.images.edit', $user) }}" class="btn-edit">
                                <i class="fas fa-camera mr-1"></i>Upload photo or signature
                            </a>
                        @endcan
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
                                            <p class="mt-2"><strong>Moving to another branch?</strong> Option A: They update their branch on My Profile, then the new branch assigns a unit. Option B: National HQ assigns one directly.</p>
                                            <p class="mt-2"><strong>Want to become a paying member instead?</strong> Ensure they make payment.</p>
                                            <p class="mt-2"><strong>No longer active?</strong> Archive their account from the Actions menu.</p>
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
                                    <select id="birth_year"
                                            name="birth_year"
                                            class="form-select @error('birth_year') form-select-error @enderror">
                                        <option value="" disabled {{ old('birth_year', $user->birth_year) ? '' : 'selected' }}>Select Year</option>
                                        @for ($year = date('Y'); $year >= 1900; $year--)
                                            <option value="{{ $year }}" {{ old('birth_year', $user->birth_year) == $year ? 'selected' : '' }}>
                                                {{ $year }}
                                            </option>
                                        @endfor
                                    </select>
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
                                        National ID number (NIN) @if(blank(old('national_id_number', $user->national_id_number)))<span class="bg-amber-100 text-amber-900 rounded px-2 py-1 inline-block">(missing)</span>@endif
                                    </label>
                                    <input type="text"
                                           id="national_id_number"
                                           name="national_id_number"
                                           value="{{ old('national_id_number', $user->national_id_number) }}"
                                           class="scroll-mt-16 form-input @error('national_id_number') form-input-error @enderror">
                                    @error('national_id_number')
                                    <p class="form-error">{{ $message }}</p>
                                    @enderror
                                </div>

                                <!-- Membership form number -->
                                <div>
                                    <label for="red_cross_id_number" class="form-label">
                                        Membership form number
                                    </label>
                                    <input type="text"
                                           id="red_cross_id_number"
                                           name="red_cross_id_number"
                                           maxlength="50"
                                           value="{{ old('red_cross_id_number', $user->red_cross_id_number) }}"
                                           class="form-input @error('red_cross_id_number') form-input-error @enderror">
                                    <p class="text-gray-500 text-xs mt-1">Serial number from the paper membership form, with the branch code in front (e.g. KAN0456).</p>
                                    @error('red_cross_id_number')
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
                                        <p class="text-gray-500 mt-1">Available for volunteering, with or without also paying a membership fee.</p>
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
                                        <span class="font-medium text-gray-700">Supporting Membership</span>
                                        <p class="text-gray-500 mt-1">Available as a supporting member; volunteering isn't required.</p>
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
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="password" class="form-label">
                                            New Password
                                        </label>
                                        <div class="relative">
                                            <input type="password"
                                                   id="password"
                                                   name="password"
                                                   class="form-input pr-10 @error('password') form-input-error @enderror">
                                            <button type="button" id="toggle-password-visibility-btn"
                                                    aria-label="Show password"
                                                    class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 focus:outline-none">
                                                <i class="fas fa-eye" id="toggle-password-visibility-icon"></i>
                                            </button>
                                        </div>
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
                                        <div class="relative">
                                            <input type="password"
                                                   id="password_confirmation"
                                                   name="password_confirmation"
                                                   class="form-input pr-10">
                                            <button type="button" id="toggle-password-confirmation-visibility-btn"
                                                    aria-label="Show password"
                                                    class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 focus:outline-none">
                                                <i class="fas fa-eye" id="toggle-password-confirmation-visibility-icon"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <p class="form-hint"><strong>Leave blank</strong> if you don't want to change the password.</p>

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
                                        $soleContactOrgs = $user->soleContactOrganisations();
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
                                        @if($user->archived_at)
                                            Archived on {{ $user->archived_at->format('M d, Y') }} by {{ $user->archivedByLabel() }}
                                        @endif
                                    </p>
                                    <p class="inline-block mt-3 text-sm text-gray-600 bg-yellow-100 p-2 rounded">
                                        Uncheck to reactivate.
                                    </p>
                                @else
                                    <p class="mt-3 text-sm text-gray-600">Status: Active</p>
                                @endif
                            </div>

                            {{-- Permanent anonymization (UserAnonymizer). Archived, not yet
                                 anonymized, no role, and only for anonymize_user holders. --}}
                            @if($user->isArchived() && $user->anonymized_at === null
                                && $user->getRoleNames()->isEmpty() && ! $user->is_super_admin
                                && auth()->user()->can('anonymize_user'))
                                <div id="anonymize-box" class="mt-4 p-4 border-2 border-red-400 rounded-lg bg-red-50"
                                     x-data="{ confirmed: {{ old('anonymize_confirm') ? 'true' : 'false' }}, consulted: {{ old('anonymize_dpo_consulted') ? 'true' : 'false' }} }">
                                    <h4 class="text-base font-bold text-red-800">
                                        <i class="fas fa-user-secret mr-1"></i>Anonymize this account (permanent)
                                    </h4>
                                    <p class="mt-2 text-sm text-red-900">
                                        Anonymizing permanently removes this person's name, contact details, NIN, address,
                                        photo and signature. Only gender, branch/division/unit and age group are kept, for
                                        statistics. This cannot be undone. Before you anonymize an account, you must consult
                                        the Data Protection Officer (DPO).
                                    </p>

                                    <x-dpo-contact class="mt-3" />

                                    @if($errors->hasAny(['anonymize', 'anonymize_confirm', 'anonymize_dpo_consulted', 'anonymize_password']))
                                        <div class="mt-3 rounded-md bg-red-100 border border-red-300 px-3 py-2 text-sm text-red-800">
                                            @foreach(['anonymize', 'anonymize_confirm', 'anonymize_dpo_consulted', 'anonymize_password'] as $field)
                                                @error($field)<p>{{ $message }}</p>@enderror
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="mt-3 space-y-2">
                                        <label class="flex items-start gap-2 text-sm text-gray-800">
                                            <input type="checkbox" name="anonymize_confirm" value="1" x-model="confirmed"
                                                   class="mt-0.5 h-4 w-4 text-red-600 border-gray-300 rounded focus:ring-red-500">
                                            <span>Anonymize this account</span>
                                        </label>
                                        <label class="flex items-start gap-2 text-sm text-gray-800">
                                            <input type="checkbox" name="anonymize_dpo_consulted" value="1" x-model="consulted"
                                                   class="mt-0.5 h-4 w-4 text-red-600 border-gray-300 rounded focus:ring-red-500">
                                            <span>I have consulted the Data Protection Officer about this</span>
                                        </label>
                                    </div>

                                    <div class="mt-3">
                                        <label for="anonymize_password" class="block text-sm font-medium text-gray-700">
                                            Your password, to confirm
                                        </label>
                                        <input type="password" id="anonymize_password" name="anonymize_password" autocomplete="current-password"
                                               class="mt-1 w-full max-w-xs px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                                    </div>

                                    <p class="mt-3 text-xs text-gray-600">
                                        This button only anonymizes; other changes on this page are not saved.
                                    </p>
                                    <button type="submit" name="action" value="anonymize" formnovalidate
                                            class="mt-2 inline-flex items-center px-4 py-2 rounded-md text-sm font-medium bg-red-700 text-white hover:bg-red-800 disabled:opacity-50 disabled:cursor-not-allowed"
                                            :disabled="!(confirmed && consulted)">
                                        <i class="fas fa-user-secret mr-2"></i>Anonymize permanently
                                    </button>
                                </div>
                            @endif
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
                            <button type="submit"
                                    class="btn-primary">
                                <i class="fas fa-check mr-1"></i>Update Person
                            </button>
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
                const togglePasswordIcon = document.getElementById('toggle-password-visibility-icon');
                const togglePasswordConfirmBtn = document.getElementById('toggle-password-confirmation-visibility-btn');
                const togglePasswordConfirmIcon = document.getElementById('toggle-password-confirmation-visibility-icon');
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
                        if (togglePasswordIcon) togglePasswordIcon.classList.replace('fa-eye', 'fa-eye-slash');
                        if (togglePasswordConfirmIcon) togglePasswordConfirmIcon.classList.replace('fa-eye', 'fa-eye-slash');
                        if (togglePasswordBtn) togglePasswordBtn.setAttribute('aria-label', 'Hide password');
                        if (togglePasswordConfirmBtn) togglePasswordConfirmBtn.setAttribute('aria-label', 'Hide password');
                    });
                }

                if (togglePasswordBtn && passwordField) {
                    togglePasswordBtn.addEventListener('click', () => {
                        const showing = passwordField.type === 'text';
                        passwordField.type = showing ? 'password' : 'text';
                        if (togglePasswordIcon) togglePasswordIcon.classList.replace(showing ? 'fa-eye-slash' : 'fa-eye', showing ? 'fa-eye' : 'fa-eye-slash');
                        togglePasswordBtn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
                    });
                }

                if (togglePasswordConfirmBtn && passwordConfirmField) {
                    togglePasswordConfirmBtn.addEventListener('click', () => {
                        const showing = passwordConfirmField.type === 'text';
                        passwordConfirmField.type = showing ? 'password' : 'text';
                        if (togglePasswordConfirmIcon) togglePasswordConfirmIcon.classList.replace(showing ? 'fa-eye-slash' : 'fa-eye', showing ? 'fa-eye' : 'fa-eye-slash');
                        togglePasswordConfirmBtn.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
                    });
                }
            });
        </script>
    @endpush
    @endif {{-- anonymized --}}
</x-layouts.admin>
