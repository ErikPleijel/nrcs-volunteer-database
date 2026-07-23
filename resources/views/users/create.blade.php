<x-layouts.admin title="Registration Form (Admin)">
    <x-slot name="pageHeader">
        <i class="fas fa-user mr-3"></i> Persons
    </x-slot>

    <x-slot name="subHeader">
        Register a new person
    </x-slot>

    <x-slot name="button1">
        <a href="{{ route('users.index') }}" class="btn-cancel">
            <i class="fas fa-arrow-left mr-1"></i> Back to List
        </a>
    </x-slot>

    <div class="container mx-auto px-4 py-6">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white shadow-md rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">


                    <div class="mt-6 mx-auto max-w-xl text-center">
                        <p class="text-2xl font-bold text-amber-800">Important!</p>
                        <ul class="mt-3 text-sm text-amber-900 text-left space-y-2 list-disc list-inside">
                            <li>Only use this form for people <strong>without an email address</strong>.</li>
                            <li>If they have an email — use the <strong>registration form on the welcome page</strong>.</li>
                            <li>If this person shall be a <strong>member</strong> — register a membership payment <u>directly after</u>.</li>
                            <li>If this person shall be a <strong>volunteer</strong> — assign them to a Red Cross Unit.</li>
                            <li>If <strong>neither</strong> a payment nor a unit assignment is made, this registration will be <strong>removed within a few days</strong>.</li>
                        </ul>
                    </div>
                </div>


                <!-- Error Summary Section -->
                @if ($errors->any())
                    <div class="mx-6 mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">
                                    Please correct the following errors:
                                </h3>
                                <div class="mt-2 text-sm text-red-700">
                                    <ul class="list-disc list-inside space-y-1">
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('users.store') }}" class="p-6">
                    @csrf

                    <!-- Personal Information Section -->
                    <div class="mb-8">
                        <h2 class="text-lg font-medium text-gray-800 mb-4 pb-2 border-b">Personal Information</h2>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">




                            <!-- First Name -->
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">
                                    First Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="first_name" name="first_name"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('first_name') border-red-500 @enderror"
                                       value="{{ old('first_name') }}" required>
                                @error('first_name')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Middle Name -->
                            <div>
                                <label for="middle_name" class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                                <input type="text" id="middle_name" name="middle_name"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       value="{{ old('middle_name') }}">
                            </div>

                            <!-- Last Name -->
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">
                                    Last Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="last_name" name="last_name"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('last_name') border-red-500 @enderror"
                                       value="{{ old('last_name') }}" required>
                                @error('last_name')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Title (optional) --}}
                            <div>
                                <x-select-titles :value="old('title')" />
                            </div>

                            <!-- Gender -->
                            <div>
                                <label for="gender" class="block text-sm font-medium text-gray-700 mb-1">
                                    Gender <span class="text-red-500">*</span>
                                </label>
                                <select id="gender" name="gender"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('gender') border-red-500 @enderror" required>
                                    <option value="">Select Gender</option>
                                    <option value="male" {{ old('gender') == 'male' ? 'selected' : '' }}>Male</option>
                                    <option value="female" {{ old('gender') == 'female' ? 'selected' : '' }}>Female</option>
                                </select>
                                @error('gender')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Birth Year -->
                            <div>
                                <label for="birth_year" class="block text-sm font-medium text-gray-700 mb-1">
                                    Birth Year <span class="text-red-500">*</span>
                                </label>
                                <select id="birth_year" name="birth_year"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('birth_year') border-red-500 @enderror"
                                        required>
                                    <option value="" disabled {{ old('birth_year') ? '' : 'selected' }}>Select Year</option>
                                    @for ($year = date('Y'); $year >= 1900; $year--)
                                        <option value="{{ $year }}" {{ old('birth_year') == $year ? 'selected' : '' }}>
                                            {{ $year }}
                                        </option>
                                    @endfor
                                </select>
                                @error('birth_year')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Marital Status -->
                            <div>
                                <label for="marital_status" class="block text-sm font-medium text-gray-700 mb-1">Marital Status</label>
                                <select id="marital_status" name="marital_status"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">Select Status</option>
                                    <option value="single" {{ old('marital_status') == 'single' ? 'selected' : '' }}>Single</option>
                                    <option value="married" {{ old('marital_status') == 'married' ? 'selected' : '' }}>Married</option>
                                    <option value="other" {{ old('marital_status') == 'other' ? 'selected' : '' }}>Other</option>
                                </select>
                            </div>

                            <!-- National ID Number -->
                            <div class="mb-4">
                                <label for="national_id_number" class="block text-sm font-medium text-gray-700 mb-1">
                                    National ID Number
                                </label>
                                <input type="text" id="national_id_number" name="national_id_number"
                                       class="w-full md:w-1/2 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('national_id_number') border-red-500 @enderror"
                                       value="{{ old('national_id_number') }}" maxlength="255">
                                @error('national_id_number')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Professional Information Section -->
                    <div class="mb-8">
                        <h2 class="text-lg font-medium text-gray-800 mb-4 pb-2 border-b">Professional Information</h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <!-- Occupation -->
                            <div>
                                @include('includes.profession-select', ['currentOccupation' => old('occupation')])
                            </div>

                            <!-- Discipline -->
                            <div>
                                <label for="disciplin" class="block text-sm font-medium text-gray-700 mb-1">Education/Training</label>
                                <input type="text" id="disciplin" name="disciplin"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       value="{{ old('disciplin') }}">
                            </div>

                        </div>

                        <!-- Additional Information Section -->
                        <div class="mb-8">

                            <div>
                                <label for="personal_info" class="block text-sm font-medium text-gray-700 mb-1">
                                    Work and Interests
                                </label>
                                <textarea id="personal_info" name="personal_info" rows="4"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                          placeholder="Briefly describe work and area of interests">{{ old('personal_info') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information Section -->
                    <div class="mb-8">
                        <h2 class="text-lg font-medium text-gray-800 mb-4 pb-2 border-b">Contact Information</h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <!-- No email notice -->
                            <div class="md:col-span-2">
                                <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                                    <p class="text-lg font-bold text-red-800">No email address</p>
                                    <p class="mt-1 text-sm text-red-700">
                                        If this person has an email address, do not use this form.
                                        Ask them to register on the welcome page instead.
                                    </p>
                                </div>
                            </div>

                            <!-- Telephone 1 -->
                            <div>
                                <label for="telephone1" class="block text-sm font-medium text-gray-700 mb-1">
                                    Primary Phone <span class="text-red-500">*</span>
                                </label>
                                <input type="tel" id="telephone1" name="telephone1"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       value="{{ old('telephone1') }}">
                            </div>

                            <!-- Telephone 2 -->
                            <div>
                                <label for="telephone2" class="block text-sm font-medium text-gray-700 mb-1">
                                    Secondary Phone
                                </label>
                                <input type="tel" id="telephone2" name="telephone2"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       value="{{ old('telephone2') }}">
                            </div>

                            <!-- Organisation -->
                            <div>
                                <label for="organisation" class="block text-sm font-medium text-gray-700 mb-1">Organisation/Company</label>
                                <input type="text" id="organisation" name="organisation"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       value="{{ old('organisation') }}">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <!-- Residential Address -->
                            <div>
                                <label for="residential_address" class="block text-sm font-medium text-gray-700 mb-1">
                                    Residential Address
                                </label>
                                <textarea id="residential_address" name="residential_address" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('residential_address') }}</textarea>
                            </div>

                            <!-- Workplace Address -->
                            <div>
                                <label for="workplace_address" class="block text-sm font-medium text-gray-700 mb-1">
                                    Workplace Address
                                </label>
                                <textarea id="workplace_address" name="workplace_address" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('workplace_address') }}</textarea>
                            </div>
                        </div>
                    </div>



                    <!-- Red Cross Information Section -->
                    <div class="mb-8">
                        <h2 class="text-lg font-medium text-gray-800 mb-4 pb-2 border-b">Red Cross Information</h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <!-- Branch -->
                            <div>
                                <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-1">
                                    Branch <span class="text-red-500">*</span>
                                </label>
                                @php
                                    // Lock the dropdown if the user is not a national-level admin and there is one branch to choose from.
                                    $isLocked = $accessLevel !== 'national' && $branches->count() === 1;
                                @endphp
                                <select id="branch_id" name="branch_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @if($isLocked) bg-gray-100 cursor-not-allowed @endif @error('branch_id') border-red-500 @enderror"
                                        required
                                        @if($isLocked) disabled @endif>
                                    @if($accessLevel === 'national')
                                        <option value="">Select Branch</option>
                                    @endif
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}"
                                                {{-- Pre-select if it's the only option, or if it's the old submitted value. --}}
                                                @if($isLocked || old('branch_id') == $branch->id) selected @endif>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @if($isLocked)
                                    {{-- If the select is disabled, its value is not submitted. We need this hidden input. --}}
                                    <input type="hidden" name="branch_id" value="{{ $branches->first()->id }}">
                                @endif
                                @error('branch_id')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>


                            <!-- Division -->
                            <div>
                                <label for="division_id" class="block text-sm font-medium text-gray-700 mb-1">
                                    Division <span class="text-red-500">*</span>
                                </label>
                                <select id="division_id" name="division_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('division_id') border-red-500 @enderror"
                                        required
                                        @if(empty(old('branch_id')) && count($divisions) === 0) disabled @endif>
                                    <option value="">{{ (empty(old('branch_id')) && count($divisions) === 0) ? 'Select Branch First' : 'Select Division' }}</option>
                                    @foreach($divisions as $division)
                                        <option value="{{ $division->id }}" {{ old('division_id') == $division->id ? 'selected' : '' }}>
                                            {{ $division->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('division_id')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>


                            <!-- Red Cross Unit -->
                            <div>
                                <label for="red_cross_unit_id" class="block text-sm font-medium text-gray-700 mb-1">
                                    Red Cross Unit
                                </label>
                                <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1 mb-1">
                                    <strong>Volunteer?</strong> Assign a unit. &nbsp;|&nbsp;
                                    <strong>Member?</strong> Leave this empty.
                                </p>
                                <select id="red_cross_unit_id" name="red_cross_unit_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                        @if(empty(old('division_id')) && count($redCrossUnits) === 0) disabled @endif>
                                    <option value="">{{ (empty(old('division_id')) && count($redCrossUnits) === 0) ? 'Select Division First' : 'Select Unit (Optional)' }}</option>
                                    @foreach($redCrossUnits as $unit)
                                        <option value="{{ $unit->id }}" {{ old('red_cross_unit_id') == $unit->id ? 'selected' : '' }}>
                                            {{ $unit->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Contribution Options -->
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Contribution Preference <span class="text-red-500">*</span>
                            </label>
                            @error('contribution_type')
                            <div class="mb-2 p-2 bg-red-50 border border-red-200 rounded-md">
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
                                               {{ old('contribution_type') === 'volunteering' ? 'checked' : '' }}>
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <span class="font-medium text-gray-700">Volunteer Services</span>
                                        <p class="text-gray-500 mt-1">Can contribute through volunteer services</p>
                                    </div>
                                </label>
                                <label for="contribution_member" class="flex items-start p-4 border border-gray-200 rounded-lg hover:border-blue-300 transition-colors duration-200 cursor-pointer @error('contribution_type') border-red-300 @enderror">
                                    <div class="flex items-center h-5">
                                        <input type="radio"
                                               id="contribution_member"
                                               name="contribution_type"
                                               value="member"
                                               class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500"
                                               {{ old('contribution_type') === 'member' ? 'checked' : '' }}>
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <span class="font-medium text-gray-700">Active Membership</span>
                                        <p class="text-gray-500 mt-1">Can contribute through active membership</p>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Account Information Section -->
                    <div class="mb-8">
                        <h2 class="text-lg font-medium text-gray-800 mb-4 pb-2 border-b">Account Information</h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <!-- Password -->
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                                    Password <span class="text-red-500">*</span>
                                </label>
                                <input type="password" id="password" name="password"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('password') border-red-500 @enderror" required>
                                @error('password')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Confirm Password -->
                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                                    Confirm Password
                                </label>
                                <input type="password" id="password_confirmation" name="password_confirmation"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" >
                            </div>
                        </div>

                        <div class="flex items-center gap-3 mb-4">
                            <button type="button" id="generate-password-btn"
                                    class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                <i class="fas fa-dice mr-2"></i>Generate Password
                            </button>
                            <button type="button" id="toggle-password-visibility-btn"
                                    class="inline-flex items-center px-3 py-1.5 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                <i class="fas fa-eye mr-2"></i><span id="toggle-password-visibility-label">Show Password</span>
                            </button>
                        </div>

                        <p id="password-generated-note" class="hidden mb-4 text-sm text-yellow-900 bg-blue-200 border-l-4 border-blue-300 rounded px-3 py-2">
                            <i class="fas fa-circle-info mr-1"></i>Password generated — make sure to share it with the person before they leave.
                        </p>

                        <div class="mb-4 rounded bg-yellow-50 border-l-4 border-yellow-500 p-4 text-sm text-yellow-900">
                            <p class="font-semibold">
                                If the user does not have an email address, they can log in using:
                            </p>

                            <ul class="mt-2 list-disc list-inside">
                                <li>
                                    <strong>Phone Number:</strong> their phone number as entered above
                                    (spaces/dashes don't matter)
                                </li>
                                <li>
                                    <strong>Password:</strong> the password you enter here
                                </li>
                            </ul>


                        </div>
                    </div>

                    <div class="mb-8">
                        <div class="mt-8 rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-900">
                            <div class="flex items-start gap-3">
                                <div class="text-yellow-600 mt-0.5">
                                    <i class="fas fa-circle-info"></i>
                                </div>

                                <div class="space-y-2">
                                    <p class="font-semibold">
                                        Important notice for administrators
                                    </p>

                                    <p>
                                        Please ensure that the user is informed about the Nigeria Red Cross
                                        <span class="font-medium">Code of Conduct</span>.
                                    </p>

                                    <p>
                                        Uploading of the profile photo and signature is completed
                                        <span class="font-medium">after registration</span>,
                                        by clicking <span class="font-semibold">“Edit Person”</span>
                                        on the next page.
                                    </p>
                                </div>
                            </div>
                        </div>

                    </div>



                    <!-- Data Protection Attestation -->
                    <div class="mb-8">
                        <h2 class="text-lg font-medium text-gray-800 mb-4 pb-2 border-b">Data Protection Attestation</h2>

                        <div class="space-y-4">
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input
                                        id="admin_consent_confirmed"
                                        name="admin_consent_confirmed"
                                        type="checkbox"
                                        value="1"
                                        class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 @error('admin_consent_confirmed') ring-2 ring-red-500 @enderror"
                                        required
                                    >
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="admin_consent_confirmed" class="font-medium text-gray-700 cursor-pointer">
                                        I confirm that this person has been informed of the Nigerian Red Cross Society
                                        Code of Conduct and has given their consent for their personal data to be
                                        collected and stored in accordance with the Nigeria Data Protection Act 2023.
                                        <span class="text-red-500">*</span>
                                    </label>
                                    @error('admin_consent_confirmed')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input
                                        id="admin_consent_form"
                                        name="admin_consent_form"
                                        type="checkbox"
                                        value="1"
                                        class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 @error('admin_consent_form') ring-2 ring-red-500 @enderror"
                                        required
                                    >
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="admin_consent_form" class="font-medium text-gray-700 cursor-pointer">
                                        I confirm that the form of consent has been recorded (verbal confirmation,
                                        signed paper form, or other documented means).
                                        <span class="text-red-500">*</span>
                                    </label>
                                    @error('admin_consent_form')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="mt-2">
                                <label for="consent_notes" class="block text-sm font-medium text-gray-700 mb-1">
                                    Form of consent (optional — e.g. verbal, signed paper form)
                                </label>
                                <input
                                    type="text"
                                    id="consent_notes"
                                    name="consent_notes"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    value="{{ old('consent_notes') }}"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="border-t pt-6 flex items-center justify-end gap-3">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-plus mr-1"></i>Register Person
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const branchSelect = document.getElementById('branch_id');
            const divisionSelect = document.getElementById('division_id');
            const redCrossUnitSelect = document.getElementById('red_cross_unit_id');

            branchSelect.addEventListener('change', async function () {
                const branchId = this.value;
                // Clear and disable division and unit selects
                divisionSelect.innerHTML = '<option value="">Select Branch First</option>';
                divisionSelect.disabled = true;
                redCrossUnitSelect.innerHTML = '<option value="">Select Division First</option>';
                redCrossUnitSelect.disabled = true;

                if (branchId) {
                    divisionSelect.innerHTML = '<option value="">Loading...</option>';
                    divisionSelect.disabled = false;
                    try {
                        const response = await fetch(`/divisions/by-branch?branch_id=${branchId}`);
                        const divisions = await response.json();

                        divisionSelect.innerHTML = '<option value="">Select Division</option>';
                        divisions.forEach(division => {
                            const option = document.createElement('option');
                            option.value = division.id;
                            option.textContent = division.name;
                            divisionSelect.appendChild(option);
                        });
                    } catch (error) {
                        divisionSelect.innerHTML = '<option value="">Error loading divisions</option>';
                    }
                }
            });

            divisionSelect.addEventListener('change', async function () {
                const divisionId = this.value;
                // Clear and disable unit select
                redCrossUnitSelect.innerHTML = '<option value="">Select Division First</option>';
                redCrossUnitSelect.disabled = true;

                if (divisionId) {
                    redCrossUnitSelect.innerHTML = '<option value="">Loading...</option>';
                    redCrossUnitSelect.disabled = false;
                    try {
                        const response = await fetch(`/red-cross-units/by-division?division_id=${divisionId}`);
                        const units = await response.json();

                        redCrossUnitSelect.innerHTML = '<option value="">Select Unit (Optional)</option>';
                        units.forEach(unit => {
                            const option = document.createElement('option');
                            option.value = unit.id;
                            option.textContent = unit.name;
                            redCrossUnitSelect.appendChild(option);
                        });
                    } catch (error) {
                        redCrossUnitSelect.innerHTML = '<option value="">Error loading units</option>';
                    }
                }
            });

            // --- Generate Password / Show-Hide Password ---
            const generatePasswordBtn = document.getElementById('generate-password-btn');
            const togglePasswordBtn = document.getElementById('toggle-password-visibility-btn');
            const togglePasswordLabel = document.getElementById('toggle-password-visibility-label');
            const passwordField = document.getElementById('password');
            const passwordConfirmField = document.getElementById('password_confirmation');
            const passwordGeneratedNote = document.getElementById('password-generated-note');

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
                    if (passwordGeneratedNote) passwordGeneratedNote.classList.remove('hidden');
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
</x-layouts.admin>
