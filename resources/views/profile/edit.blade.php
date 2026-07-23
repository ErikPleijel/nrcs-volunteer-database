<x-layouts.app>
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white shadow-md rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h1 class="text-2xl font-semibold text-gray-800">Edit Your Profile: {{ auth()->user()->full_name ?? 'Unknown User' }}</h1>
                    <p class="text-gray-600 mt-1">Update your personal and contact details</p>
                    <div class="mt-4">
                        <a href="{{ route('profile.show') }}" class="btn-backlink">
                            ← Back to Profile
                        </a>
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

                <!-- Display success message -->
                @if (session('success'))
                    <div class="mx-6 mt-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg">
                        {{ session('success') }}
                    </div>
                @endif

                <form action="{{ route('profile.update') }}" method="POST" class="p-6">
                    @csrf
                    @method('PUT')

                    <!-- Personal Information Section -->
                    <div class="mb-8">
                        <h2 class="text-lg font-medium text-gray-800 mb-4 pb-2 border-b">Personal Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Title -->
                            <div>
                                <x-select-titles :value="old('title', $user->title)" />
                            </div>


                            <!-- First Name -->
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    First Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       id="first_name"
                                       name="first_name"
                                       value="{{ old('first_name', $user->first_name) }}"
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('first_name') border-red-500 @enderror">
                                @error('first_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Middle Name -->
                            <div>
                                <label for="middle_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Middle Name
                                </label>
                                <input type="text"
                                       id="middle_name"
                                       name="middle_name"
                                       value="{{ old('middle_name', $user->middle_name) }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('middle_name') border-red-500 @enderror">
                                @error('middle_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Last Name -->
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Last Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       id="last_name"
                                       name="last_name"
                                       value="{{ old('last_name', $user->last_name) }}"
                                       required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('last_name') border-red-500 @enderror">
                                @error('last_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                    Email Address
                                </label>
                                <input type="email"
                                       id="email"
                                       name="email"
                                       value="{{ old('email', $user->email) }}"

                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('email') border-red-500 @enderror">
                                @if(!empty(old('email', $user->email)))
                                    <p class="warning-note">Updating this field will require you to verify your email
                                        again.</p>
                                @endif
                                @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Gender -->
                            <div>
                                <label for="gender" class="block text-sm font-medium text-gray-700 mb-2">
                                    Gender <span class="text-red-500">*</span>
                                </label>
                                <select id="gender" name="gender"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('gender') border-red-500 @enderror">
                                    <option value="">Select gender</option>
                                    <option value="male" {{ old('gender', $user->gender) == 'male' ? 'selected' : '' }}>Male</option>
                                    <option value="female" {{ old('gender', $user->gender) == 'female' ? 'selected' : '' }}>Female</option>
                                </select>
                                @error('gender')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Birth Year -->
                            <div>
                                <label for="birth_year" class="block text-sm font-medium text-gray-700 mb-2">
                                    Birth Year <span class="text-red-500">*</span>
                                </label>
                                <input type="number"
                                       id="birth_year"
                                       name="birth_year"
                                       value="{{ old('birth_year', $user->birth_year) }}"
                                       min="1900"
                                       max="{{ date('Y') }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('birth_year') border-red-500 @enderror">
                                @error('birth_year')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Marital Status -->
                            <div>
                                <label for="marital_status" class="block text-sm font-medium text-gray-700 mb-2">
                                    Marital Status
                                </label>
                                <select id="marital_status" name="marital_status"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('marital_status') border-red-500 @enderror">
                                    <option value="">Select marital status</option>
                                    <option value="married" {{ old('marital_status', $user->marital_status) == 'married' ? 'selected' : '' }}>Married</option>
                                    <option value="single" {{ old('marital_status', $user->marital_status) == 'single' ? 'selected' : '' }}>Single</option>
                                    <option value="other" {{ old('marital_status', $user->marital_status) == 'other' ? 'selected' : '' }}>Other</option>
                                </select>

                                @error('marital_status')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- National ID Number -->
                            <div>
                                <label for="national_id_number" class="block text-sm font-medium text-gray-700 mb-2">
                                    National ID Number
                                </label>
                                <input type="text"
                                       id="national_id_number"
                                       name="national_id_number"
                                       value="{{ old('national_id_number', $user->national_id_number) }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('national_id_number') border-red-500 @enderror">
                                @error('national_id_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Organisation -->
                            <div>
                                <label for="organisation" class="block text-sm font-medium text-gray-700 mb-2">
                                    Organization
                                </label>
                                <input type="text"
                                       id="organisation"
                                       name="organisation"
                                       value="{{ old('organisation', $user->organisation) }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('organisation') border-red-500 @enderror">
                                @error('organisation')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Education/Training -->
                            <div>
                                <label for="disciplin" class="block text-sm font-medium text-gray-700 mb-2">
                                    Education/Training
                                </label>
                                <input type="text"
                                       id="disciplin"
                                       name="disciplin"
                                       value="{{ old('disciplin', $user->disciplin) }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('disciplin') border-red-500 @enderror">
                                @error('disciplin')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Include Profession Select --}}
                            <div>
                                @include('includes.profession-select', ['currentOccupation' => old('occupation', auth()->user()->occupation)])

                            </div>
                        </div>

                        <!-- Personal Info Textarea -->
                        <div class="mt-6">
                            <label for="personal_info" class="block text-sm font-medium text-gray-700 mb-2">
                                Personal Information/Bio
                            </label>
                            <textarea id="personal_info"
                                      name="personal_info"
                                      rows="4"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('personal_info') border-red-500 @enderror">{{ old('personal_info', $user->personal_info) }}</textarea>
                            @error('personal_info')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Contact Information Section -->
                    <div class="mb-8">
                        <h2 class="text-lg font-medium text-gray-800 mb-4 pb-2 border-b">Contact Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Telephone 1 -->
                            <div>
                                <label for="telephone1" class="block text-sm font-medium text-gray-700 mb-2">
                                    Telephone 1 <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       id="telephone1"
                                       name="telephone1"
                                       value="{{ old('telephone1', $user->telephone1) }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('telephone1') border-red-500 @enderror">
                                @error('telephone1')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Telephone 2 -->
                            <div>
                                <label for="telephone2" class="block text-sm font-medium text-gray-700 mb-2">
                                    Telephone 2
                                </label>
                                <input type="text"
                                       id="telephone2"
                                       name="telephone2"
                                       value="{{ old('telephone2', $user->telephone2) }}"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('telephone2') border-red-500 @enderror">
                                @error('telephone2')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Residential Address -->
                            <div>
                                <label for="residential_address" class="block text-sm font-medium text-gray-700 mb-2">
                                    Residential Address
                                </label>
                                <textarea id="residential_address"
                                          name="residential_address"
                                          rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('residential_address') border-red-500 @enderror">{{ old('residential_address', $user->residential_address) }}</textarea>
                                @error('residential_address')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Workplace Address -->
                            <div>
                                <label for="workplace_address" class="block text-sm font-medium text-gray-700 mb-2">
                                    Workplace Address
                                </label>
                                <textarea id="workplace_address"
                                          name="workplace_address"
                                          rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('workplace_address') border-red-500 @enderror">{{ old('workplace_address', $user->workplace_address) }}</textarea>
                                @error('workplace_address')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Affiliation Section -->
                    <div class="mb-8">
                        <h2 class="text-lg font-medium text-gray-800 mb-4 pb-2 border-b">Affiliation</h2>

                        @php
                            $isRedCrossUnitMember = $user->redCrossUnit !== null;
                            $redCrossUnitName = $user->redCrossUnit ? $user->redCrossUnit->name : '';
                            $hasScopedRole = $user->hasAnyRole([
                                'branch_secretary',
                                'branch_db_administrator',
                                'branch_db_assistant',
                                'division_db_assistant_finance',
                                'division_db_assistant_operations',
                            ]);
                            $locationLocked = $isRedCrossUnitMember || $hasScopedRole;
                        @endphp

                        <button type="button" id="branchDivisionRevealBtn"
                                class="inline-flex items-center px-3 py-1.5 rounded-md border border-gray-300 text-sm text-gray-600 hover:text-blue-600 hover:border-blue-300 focus:outline-none">
                            <i class="fas fa-sitemap mr-2"></i>Want to change Branch or Division?
                        </button>

                        <div id="branchDivisionSection" class="hidden mt-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <!-- Branch Dropdown -->
                            <div>
                                <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Branch @unless($isRedCrossUnitMember)<span class="text-red-500">*</span>@endunless
                                </label>
                                <select id="branch_id" name="branch_id" required
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('branch_id') border-red-500 @enderror"
                                        @if($locationLocked) disabled @endif>
                                    <option value="">Select Branch</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ old('branch_id', $user->branch_id) == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @if($locationLocked)
                                    <input type="hidden" name="branch_id" value="{{ old('branch_id', $user->branch_id) }}">
                                @endif
                                @error('branch_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Division Dropdown (Cascading) -->
                            <div>
                                <label for="division_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Division @unless($isRedCrossUnitMember)<span class="text-red-500">*</span>@endunless
                                </label>
                                <select id="division_id" name="division_id" required
                                        data-selected-division="{{ old('division_id', $user->division_id) }}"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('division_id') border-red-500 @enderror"
                                        @if($locationLocked) disabled @endif>
                                    <option value="">Select Division</option>
                                    {{-- Options will be loaded dynamically by JavaScript --}}
                                </select>
                                @if($locationLocked)
                                    <input type="hidden" name="division_id" value="{{ old('division_id', $user->division_id) }}">
                                @endif
                                @error('division_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            @if($isRedCrossUnitMember)
                                <div class="md:col-span-2 mt-2 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">
                                    <i class="fas fa-triangle-exclamation mr-1 text-amber-500"></i>
                                    You cannot change your Branch or Division because you are assigned to Red Cross Unit:
                                    <span class="font-semibold">{{ $user->redCrossUnit->name }}</span>.
                                    <div class="mt-2 space-y-1">
                                        <p><strong>Changing division:</strong> Contact your branch administrator — they can move you and assign you to a new Red Cross Unit in one step.</p>
                                        <p><strong>Changing branch:</strong> Ask your administrator to remove your Red Cross Unit assignment first. Once that's done, come back here and select your new branch.</p>
                                    </div>
                                </div>
                            @endif

                            @if($hasScopedRole)
                                <div class="md:col-span-2 mt-2 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">
                                    <i class="fas fa-triangle-exclamation mr-1 text-amber-500"></i>
                                    You have an administrative role
                                    (<span class="font-semibold">
                                        {{ $user->getRoleNames()->map(fn($r) => ucwords(str_replace('_', ' ', $r)))->join(', ') }}
                                    </span>)
                                    that is tied to your current branch or division.
                                    Contact your branch administrator or national level
                                    before changing your location.
                                </div>
                            @endif

                            {{-- Red Cross Unit dropdown removed as per requirement --}}
                        </div>
                        </div>
                    </div>

                    <!-- Contribution Capabilities Section -->
                    <div class="mb-8">
                        @php
                            $currentContributionType = $user->can_contribute_volunteering ? 'volunteering' : ($user->can_contribute_member ? 'member' : null);
                        @endphp
                        <h2 class="text-lg font-medium text-gray-800 mb-4 pb-2 border-b">Contribution Capabilities</h2>
                        <h3 class="text-md font-medium text-gray-800 mb-2">
                            How would you like to contribute? <span class="text-red-500">*</span>
                        </h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Please select one option.
                        </p>
                        @error('contribution_type')
                        <div class="mb-3 p-3 bg-red-50 border border-red-200 rounded-md">
                            <p class="text-red-600 text-sm">{{ $message }}</p>
                        </div>
                        @enderror
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Volunteering -->
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
                                    <p class="text-gray-500 mt-1">
                                        I can contribute my time and skills as a volunteer.
                                    </p>
                                </div>
                            </label>

                            <!-- Membership -->
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
                                    <p class="text-gray-500 mt-1">
                                        I want to be an active member of the Red Cross
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Authentication Section -->
                    <div class="mb-8">
                        <h2 class="text-lg font-medium text-gray-800 mb-4 pb-2 border-b">Update Password</h2>
                        <p class="text-sm text-gray-600 mb-4">
                            If you wish to change your password, please fill in the fields below.
                            Otherwise, leave them blank.
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Password -->
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                                    New Password
                                </label>
                                <div class="relative">
                                    <input type="password"
                                           id="password"
                                           name="password"
                                           autocomplete="new-password"
                                           class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('password') border-red-500 @enderror">
                                    <button type="button" id="toggleProfilePassword"
                                            aria-label="Show password"
                                            class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600 focus:outline-none">
                                        <i class="fas fa-eye" id="toggleProfilePasswordIcon"></i>
                                    </button>
                                </div>
                                @error('password')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Confirm Password -->
                            <div>
                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">
                                    Confirm New Password
                                </label>
                                <div class="relative">
                                    <input type="password"
                                           id="password_confirmation"
                                           name="password_confirmation"
                                           autocomplete="new-password"
                                           class="w-full px-3 py-2 pr-10 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 @error('password_confirmation') border-red-500 @enderror">
                                </div>
                                @error('password_confirmation')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end pt-6 border-t border-gray-200">
                        <button type="submit" class="px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200">
                            Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const branchSelect = document.getElementById('branch_id');
            const divisionSelect = document.getElementById('division_id');

            // Check if the fields are disabled by the server-side logic
            const isFieldsDisabled = branchSelect.disabled;

            // Store initially selected values from the user object (provided by Laravel)
            const initialSelectedBranch = "{{ old('branch_id', $user->branch_id) }}";
            const initialSelectedDivision = "{{ old('division_id', $user->division_id) }}";

            // Function to reset and disable a select element
            function resetAndDisableSelect(selectElement, placeholderText) {
                selectElement.innerHTML = `<option value="">${placeholderText}</option>`;
                selectElement.disabled = true;
                selectElement.value = ''; // Ensure no stale value is selected
            }

            // Function to populate divisions based on branch
            async function populateDivisions(branchId, selectedDivisionId = '') {
                // Only perform this if the fields are NOT disabled by the server-side logic
                if (isFieldsDisabled) {
                    // If disabled, just set the selected value and keep it disabled
                    divisionSelect.innerHTML = `<option value="${selectedDivisionId}">{{ $user->division->name ?? 'Select Division' }}</option>`;
                    divisionSelect.value = selectedDivisionId;
                    divisionSelect.disabled = true;
                    return;
                }

                resetAndDisableSelect(divisionSelect, 'Select a Branch first');

                if (!branchId) {
                    return; // No branch selected, keep dependents disabled
                }

                divisionSelect.disabled = false;
                divisionSelect.innerHTML = '<option value="">Select a Division</option>'; // Default option for divisions

                try {
                    const response = await fetch(`/profile/branches/${branchId}/divisions`);
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
                    }

                } catch (error) {
                    console.error('Error fetching divisions:', error);
                    divisionSelect.innerHTML = '<option value="">Error loading divisions</option>';
                    divisionSelect.disabled = true;
                }
            }

            // Event listener for branch changes
            // Only attach if fields are not disabled
            if (!isFieldsDisabled) {
                branchSelect.addEventListener('change', function () {
                    const branchId = this.value;
                    populateDivisions(branchId);
                });
            }


            // Initial setup and population on page load
            if (initialSelectedBranch) {
                populateDivisions(initialSelectedBranch, initialSelectedDivision);
            } else {
                resetAndDisableSelect(divisionSelect, 'Select a Branch first');
            }
        });
    </script>
    <script>
        (function () {
            const passwordInput = document.getElementById('password');
            const confirmInput  = document.getElementById('password_confirmation');
            const btn           = document.getElementById('toggleProfilePassword');
            const icon          = document.getElementById('toggleProfilePasswordIcon');
            if (!passwordInput || !confirmInput || !btn) return;
            btn.addEventListener('click', function () {
                const show = passwordInput.type === 'password';
                const type = show ? 'text' : 'password';
                passwordInput.type = type;
                confirmInput.type = type;
                icon.classList.toggle('fa-eye', !show);
                icon.classList.toggle('fa-eye-slash', show);
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });
        })();
    </script>
    <script>
        (function () {
            const revealBtn = document.getElementById('branchDivisionRevealBtn');
            const section = document.getElementById('branchDivisionSection');
            if (!revealBtn || !section) return;
            revealBtn.addEventListener('click', function () {
                revealBtn.classList.add('hidden');
                section.classList.remove('hidden');
            });
        })();
    </script>
</x-layouts.app>
