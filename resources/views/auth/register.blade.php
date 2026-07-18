<x-layouts.app title="NRCS Registration">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div
                class="bg-white shadow-md rounded-lg"
                x-data="codeOfConductFlow()"
                x-init="init()"
            >
                <div class="px-6 py-4 border-b border-gray-200">
                    <h1 class="text-2xl font-semibold text-gray-800">Registration Form</h1>
                    <p class="text-gray-600 mt-1">Please fill in all required fields to create your account</p>
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

                <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data" class="p-6 space-y-8">
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
                                {{-- If you pass $user here in some contexts, adjust as needed --}}
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
                                       value="{{ old('national_id_number') }}"
                                       maxlength="50">

                                @error('national_id_number')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">

                        </div>
                    </div>

                    <!-- Professional Information Section -->
                    <div class="mb-8">
                        <h2 class="text-lg font-medium text-gray-800 mb-4 pb-2 border-b">Professional Information</h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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

                            <!-- Work and interest -->
                            <div class="md:col-span-2">
                                <label for="personal_info" class="block text-sm font-medium text-gray-700 mb-1">
                                    Work and Interests
                                </label>
                                <textarea id="personal_info" name="personal_info" rows="4"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                          placeholder="Briefly describe your work and area of interests">{{ old('personal_info') }}</textarea>
                            </div>
                        </div>
                    </div>


                    <!-- Contact Information Section -->
                    <div class="mb-8">
                        <h2 class="text-lg font-medium text-gray-800 mb-4 pb-2 border-b">Contact Information</h2>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                                    Email Address <span class="text-red-500">*</span>
                                </label>
                                <input type="email" id="email" name="email"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('email') border-red-500 @enderror"
                                       value="{{ old('email') }}" required>
                                @error('email')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Primary Phone -->
                            <div>
                                <label for="telephone1" class="block text-sm font-medium text-gray-700 mb-1">
                                    Primary Phone <span class="text-red-500">*</span>
                                </label>
                                <input type="tel" id="telephone1" name="telephone1"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('telephone1') border-red-500 @enderror"
                                       value="{{ old('telephone1') }}" required>
                                @error('telephone1')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Secondary Phone -->
                            <div>
                                <label for="telephone2" class="block text-sm font-medium text-gray-700 mb-1">Secondary Phone</label>
                                <input type="tel" id="telephone2" name="telephone2"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       value="{{ old('telephone2') }}">
                            </div>

                            <!-- Organization -->
                            <div>
                                <label for="organisation" class="block text-sm font-medium text-gray-700 mb-1">Organization</label>
                                <input type="text" id="organisation" name="organisation"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                       value="{{ old('organisation') }}">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <!-- Residential Address -->
                            <div>
                                <label for="residential_address" class="block text-sm font-medium text-gray-700 mb-1">Residential Address</label>
                                <textarea id="residential_address" name="residential_address" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('residential_address') }}</textarea>
                            </div>

                            <!-- Workplace Address -->
                            <div>
                                <label for="workplace_address" class="block text-sm font-medium text-gray-700 mb-1">Workplace Address</label>
                                <textarea id="workplace_address" name="workplace_address" rows="3"
                                          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('workplace_address') }}</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Red Cross Information Section -->
                    <div class="mb-8">
                        <h2 class="text-lg font-medium text-gray-800 mb-4 pb-2 border-b">Red Cross Information</h2>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <!-- Branch -->
                            <div>
                                <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-1">Branch <span class="text-red-500">*</span></label>
                                <select id="branch_id" name="branch_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('branch_id') border-red-500 @enderror">
                                    <option value="">Select Branch</option>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('branch_id')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Division -->
                            <div>
                                <label for="division_id" class="block text-sm font-medium text-gray-700 mb-1">Division <span class="text-red-500">*</span></label>
                                <select id="division_id" name="division_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('division_id') border-red-500 @enderror"
                                        disabled>
                                    <option value="">Select Division</option>
                                </select>
                                @error('division_id')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Contribution Preferences -->
                        <div class="mt-6">
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
                                        <input id="contribution_volunteering" name="contribution_type" type="radio" value="volunteering"
                                               class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
                                               {{ old('contribution_type') === 'volunteering' ? 'checked' : '' }}>
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
                                        <input id="contribution_member" name="contribution_type" type="radio" value="member"
                                               class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300"
                                               {{ old('contribution_type') === 'member' ? 'checked' : '' }}>
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <span class="font-medium text-gray-700">Active Membership</span>
                                        <p class="text-gray-500 mt-1">
                                            I want to be an active member of the Red Cross.
                                        </p>
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
                                    Confirm Password <span class="text-red-500">*</span>
                                </label>
                                <input type="password" id="password_confirmation" name="password_confirmation"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            </div>
                        </div>
                    </div>

                    <!-- File Uploads Section -->
                    <div class="mb-8">
                        <h2 class="text-lg font-medium text-gray-800 mb-4 pb-2 border-b">Upload Photo (Optional)</h2>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Profile Picture -->
                            <div>
                                <label for="picture" class="block text-sm font-medium text-gray-700 mb-1">Profile Picture</label>

                                <!-- Camera Section -->
                                <div class="mb-4 p-4 border-2 border-dashed border-gray-300 rounded-lg bg-gray-50">
                                    <div id="camera-section" class="text-center">
                                        <video id="camera-video" class="w-full h-48 bg-black rounded-lg mb-3 hidden" autoplay playsinline></video>
                                        <canvas id="camera-canvas" class="hidden"></canvas>
                                        <div id="camera-preview" class="w-full h-48 bg-gray-200 rounded-lg mb-3 flex items-center justify-center hidden">
                                            <img id="captured-image" class="max-w-full max-h-full object-contain rounded-lg" />
                                        </div>

                                        <div id="camera-controls">
                                            <button type="button" id="start-camera" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition duration-200 mb-2">
                                                📷 Take Photo
                                            </button>
                                            <div id="capture-controls" class="hidden space-x-2">
                                                <button type="button" id="capture-photo" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-200">
                                                    📸 Capture
                                                </button>
                                                <button type="button" id="stop-camera" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition duration-200">
                                                    ❌ Cancel
                                                </button>
                                            </div>
                                            <div id="retake-controls" class="hidden space-x-2">
                                                <button type="button" id="use-photo" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-200">
                                                    ✅ Use Photo
                                                </button>
                                                <button type="button" id="retake-photo" class="bg-yellow-600 text-white px-4 py-2 rounded-lg hover:bg-yellow-700 transition duration-200">
                                                    🔄 Retake
                                                </button>
                                            </div>
                                        </div>

                                        <p class="text-xs text-gray-600 mt-2">Click "Take Photo" to use your camera.</p>
                                    </div>
                                </div>

                                <!-- File Upload Section -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Or upload from device:</label>
                                    <input type="file" id="picture" name="picture" accept="image/*"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 @error('picture') border-red-500 @enderror">
                                    <p class="text-xs text-gray-500 mt-1">JPEG, PNG, JPG, GIF (MAX. 2MB).</p>
                                </div>

                                <!-- Hidden input for captured photo -->
                                <input type="hidden" id="captured-photo-data" name="captured_photo" />

                                @error('picture')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                                @error('captured_photo')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Code of Conduct Section -->
                    <div class="mb-8">
                        <h2 class="text-lg font-medium text-gray-800 mb-2 pb-2 border-b">
                            Code of Conduct
                        </h2>

                        <p class="text-sm text-gray-600 mb-4">
                            Please read the full Code of Conduct below. You must scroll to the end,
                            then confirm the three commitments before you can create your account.
                        </p>

                        <div
                            id="coc-scroll-container"
                            class="border border-gray-200 rounded-md bg-gray-50 px-4 py-3 max-h-72 overflow-y-auto focus:outline-none focus:ring-2 focus:ring-blue-500"
                            tabindex="0"
                            role="region"
                            aria-label="Code of Conduct"
                            @scroll="onScroll($event)"
                        >
                            @include('policies.code-of-conduct')
                        </div>

                        <p class="mt-2 text-xs text-gray-500"
                           x-show="!hasScrolledToBottom"
                           x-cloak>
                            Scroll to the bottom of the Code of Conduct to enable the confirmation checkboxes.
                        </p>

                        <div class="mt-4 space-y-3">
                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input
                                        id="coc_commitment_1"
                                        name="coc_commitment_1"
                                        type="checkbox"
                                        value="1"
                                        class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                        x-model="commitment1"
                                        :disabled="!hasScrolledToBottom"
                                    >
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="coc_commitment_1" class="font-medium text-gray-700 cursor-pointer">
                                        I have read the entire Code of Conduct.
                                        <span class="text-red-500">*</span>
                                    </label>
                                    @error('coc_commitment_1')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input
                                        id="coc_commitment_2"
                                        name="coc_commitment_2"
                                        type="checkbox"
                                        value="1"
                                        class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                        x-model="commitment2"
                                        :disabled="!hasScrolledToBottom"
                                    >
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="coc_commitment_2" class="font-medium text-gray-700 cursor-pointer">
                                        I agree to follow the Code of Conduct at all times.
                                        <span class="text-red-500">*</span>
                                    </label>
                                    @error('coc_commitment_2')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input
                                        id="coc_commitment_3"
                                        name="coc_commitment_3"
                                        type="checkbox"
                                        value="1"
                                        class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                        x-model="commitment3"
                                        :disabled="!hasScrolledToBottom"
                                    >
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="coc_commitment_3" class="font-medium text-gray-700 cursor-pointer">
                                        I understand that violations may result in disciplinary action.
                                        <span class="text-red-500">*</span>
                                    </label>
                                    @error('coc_commitment_3')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="flex items-start">
                                <div class="flex items-center h-5">
                                    <input
                                        id="coc_commitment_4"
                                        name="coc_commitment_4"
                                        type="checkbox"
                                        value="1"
                                        class="h-4 w-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                        x-model="commitment4"
                                        :disabled="!hasScrolledToBottom"
                                    >
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="coc_commitment_4" class="font-medium text-gray-700 cursor-pointer">
                                        I consent to the Nigerian Red Cross Society collecting, storing, and processing my personal data (including identification details and photo) for membership and volunteer management purposes, in accordance with the Nigeria Data Protection Act 2023. I understand I may request access to or deletion of my data by contacting the NRCS Data Protection Officer.
                                        <span class="text-red-500">*</span>
                                    </label>
                                    @error('coc_commitment_4')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end pt-6 border-t border-gray-200">
                        <button type="submit"
                                class="px-6 py-3 text-white font-medium rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200
                                       bg-blue-600 hover:bg-blue-700 disabled:bg-gray-300 disabled:text-gray-600 disabled:cursor-not-allowed"
                                :disabled="!canSubmit"
                                aria-disabled="true">
                            Create Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // === CASCADING DROPDOWN FUNCTIONALITY ===
        const branchSelect = document.getElementById('branch_id');
        const divisionSelect = document.getElementById('division_id');
        const oldDivisionId = '{{ old("division_id") }}';

        if (branchSelect && divisionSelect) {
            branchSelect.addEventListener('change', function() {
                const branchId = this.value;

                divisionSelect.innerHTML = '<option value="">Select Division</option>';
                divisionSelect.disabled = true;

                if (!branchId) {
                    return;
                }

                divisionSelect.innerHTML = '<option value="">Loading divisions...</option>';

                fetch(`/register/divisions/by-branch?branch_id=${branchId}`, {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    }
                })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(divisions => {
                        divisionSelect.innerHTML = '<option value="">Select Division</option>';

                        divisions.forEach(division => {
                            const option = document.createElement('option');
                            option.value = division.id;
                            option.textContent = division.name;

                            if (oldDivisionId && oldDivisionId == division.id) {
                                option.selected = true;
                            }

                            divisionSelect.appendChild(option);
                        });

                        divisionSelect.disabled = false;
                    })
                    .catch(error => {
                        console.error('Error fetching divisions:', error);
                        divisionSelect.innerHTML = '<option value="">Error loading divisions</option>';
                        divisionSelect.disabled = false;
                    });
            });

            if (branchSelect.value) {
                branchSelect.dispatchEvent(new Event('change'));
            }
        }

        // === CAMERA FUNCTIONALITY ===
        let currentStream = null;

        const video = document.getElementById('camera-video');
        const canvas = document.getElementById('camera-canvas');
        const capturedImage = document.getElementById('captured-image');
        const preview = document.getElementById('camera-preview');
        const startCameraBtn = document.getElementById('start-camera');
        const captureBtn = document.getElementById('capture-photo');
        const stopCameraBtn = document.getElementById('stop-camera');
        const usePhotoBtn = document.getElementById('use-photo');
        const retakeBtn = document.getElementById('retake-photo');
        const captureControls = document.getElementById('capture-controls');
        const retakeControls = document.getElementById('retake-controls');
        const hiddenInput = document.getElementById('captured-photo-data');
        const fileInput = document.getElementById('picture');

        // Start camera
        startCameraBtn.addEventListener('click', async function() {
            try {
                currentStream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        width: { ideal: 640 },
                        height: { ideal: 480 },
                        facingMode: 'user'
                    }
                });

                video.srcObject = currentStream;
                video.classList.remove('hidden');
                preview.classList.add('hidden');

                startCameraBtn.classList.add('hidden');
                captureControls.classList.remove('hidden');
                retakeControls.classList.add('hidden');

            } catch (err) {
                console.error('Error accessing camera:', err);
                alert('Unable to access camera. Please make sure you have granted camera permissions and try again.');
            }
        });

        // Capture photo with size reduction and compression
        captureBtn.addEventListener('click', function() {
            const context = canvas.getContext('2d');

            const maxWidth = 400;
            const maxHeight = 300;

            let { videoWidth, videoHeight } = video;

            const scale = Math.min(maxWidth / videoWidth, maxHeight / videoHeight);

            canvas.width = videoWidth * scale;
            canvas.height = videoHeight * scale;

            context.drawImage(video, 0, 0, canvas.width, canvas.height);

            const imageData = canvas.toDataURL('image/jpeg', 0.5);
            capturedImage.src = imageData;

            video.classList.add('hidden');
            preview.classList.remove('hidden');

            captureControls.classList.add('hidden');
            retakeControls.classList.remove('hidden');
        });

        // Use captured photo
        usePhotoBtn.addEventListener('click', function() {
            hiddenInput.value = capturedImage.src;
            fileInput.value = '';

            stopCamera();

            const successMsg = document.createElement('p');
            successMsg.className = 'text-green-600 text-sm mt-2';
            successMsg.textContent = '✅ Photo captured successfully! (Optimized for small file size)';
            successMsg.id = 'capture-success-msg';

            const existingMsg = document.getElementById('capture-success-msg');
            if (existingMsg) {
                existingMsg.remove();
            }

            document.getElementById('camera-section').appendChild(successMsg);

            startCameraBtn.classList.remove('hidden');
            retakeControls.classList.add('hidden');
        });

        // Retake photo
        retakeBtn.addEventListener('click', function() {
            video.classList.remove('hidden');
            preview.classList.add('hidden');
            captureControls.classList.remove('hidden');
            retakeControls.classList.add('hidden');
        });

        // Stop camera
        stopCameraBtn.addEventListener('click', function() {
            stopCamera();
        });

        function stopCamera() {
            if (currentStream) {
                currentStream.getTracks().forEach(track => {
                    track.stop();
                });
                currentStream = null;
            }

            video.classList.add('hidden');
            preview.classList.add('hidden');
            startCameraBtn.classList.remove('hidden');
            captureControls.classList.add('hidden');
            retakeControls.classList.add('hidden');
        }

        // === FILE SIZE VALIDATION ===
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            const maxSizeMB = 2;
            const maxSizeBytes = maxSizeMB * 1024 * 1024;

            if (file) {
                hiddenInput.value = '';

                const existingMsg = document.getElementById('capture-success-msg');
                const existingError = document.getElementById('file-size-error');
                if (existingMsg) existingMsg.remove();
                if (existingError) existingError.remove();

                if (file.size > maxSizeBytes) {
                    const errorMsg = document.createElement('p');
                    errorMsg.className = 'text-red-600 text-sm mt-2';
                    errorMsg.textContent = `❌ File too large! Maximum size is ${maxSizeMB}MB. Your file is ${(file.size / (1024 * 1024)).toFixed(2)}MB.`;
                    errorMsg.id = 'file-size-error';

                    this.parentNode.appendChild(errorMsg);
                    this.value = '';

                    return;
                }

                const successMsg = document.createElement('p');
                successMsg.className = 'text-green-600 text-sm mt-2';
                successMsg.textContent = `✅ File selected (${(file.size / (1024 * 1024)).toFixed(2)}MB)`;
                successMsg.id = 'capture-success-msg';

                this.parentNode.appendChild(successMsg);

                stopCamera();
            }
        });

        // === FORM SUBMISSION VALIDATION ===
        document.querySelector('form').addEventListener('submit', function(e) {
            const fileInput = document.getElementById('picture');
            const file = fileInput.files[0];
            const maxSizeMB = 2;
            const maxSizeBytes = maxSizeMB * 1024 * 1024;

            if (file && file.size > maxSizeBytes) {
                e.preventDefault();
                alert(`File size too large! Maximum allowed is ${maxSizeMB}MB.`);
                return false;
            }
        });

        window.addEventListener('beforeunload', function() {
            stopCamera();
        });
    });

    function codeOfConductFlow() {
        return {
            hasScrolledToBottom: false,
            commitment1: false,
            commitment2: false,
            commitment3: false,
            commitment4: false,

            init() {
                // Restore state from old input on validation error
                this.commitment1 = {{ old('coc_commitment_1') ? 'true' : 'false' }};
                this.commitment2 = {{ old('coc_commitment_2') ? 'true' : 'false' }};
                this.commitment3 = {{ old('coc_commitment_3') ? 'true' : 'false' }};
                this.commitment4 = {{ old('coc_commitment_4') ? 'true' : 'false' }};

                if (this.commitment1 || this.commitment2 || this.commitment3 || this.commitment4) {
                    this.hasScrolledToBottom = true;
                }
            },

            onScroll(event) {
                const target = event.target;
                const nearBottom = target.scrollHeight - target.scrollTop - target.clientHeight <= 4;
                if (nearBottom && !this.hasScrolledToBottom) {
                    this.hasScrolledToBottom = true;
                }
            },

            get canSubmit() {
                return this.hasScrolledToBottom &&
                    this.commitment1 &&
                    this.commitment2 &&
                    this.commitment3 &&
                    this.commitment4;
            }
        }
    }
</script>
