<x-layouts.admin title="Create Membership Fee">
    <x-slot name="pageHeader">
        Create Membership Fee
    </x-slot>

    <x-slot name="pageHeader">
        <i class="fas fa-users mr-3"></i>  Membership
    </x-slot>
    <x-slot name="subHeader">
        NEW MEMBERSHIP CATEGORY
    </x-slot>



    <div class="container mx-auto px-4 py-8">


        <!-- Error Messages -->
        @if ($errors->any())
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <div class="flex items-center mb-2">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <strong>Please correct the following errors:</strong>
                </div>
                <ul class="list-disc list-inside">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Create Form -->
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <div class="bg-red-600 px-6 py-4">
                <h2 class="text-xl font-semibold text-white flex items-center">
                    <i class="fas fa-plus-circle mr-3"></i>
                    Create New Membership Fee
                </h2>
            </div>

            <form action="{{ route('membership-fees.store') }}" method="POST" class="p-6">
                @csrf

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Left Column -->
                    <div class="space-y-6">


                        <!-- Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-tag mr-2"></i>Fee Name *
                            </label>
                            <input type="text"
                                   name="name"
                                   id="name"
                                   value="{{ old('name') }}"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent @error('name') border-red-500 @enderror"
                                   placeholder="Enter fee name"
                                   required>
                            @error('name')
                                <p class="mt-1 text-sm text-red-600 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <!-- Amount -->
                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-money-bill-wave mr-2"></i>Base Amount *
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">₦</span>
                                <input type="number"
                                       name="amount"
                                       id="amount"
                                       value="{{ old('amount', 0) }}"
                                       step="10"
                                       min="0"
                                       class="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent @error('amount') border-red-500 @enderror"
                                       placeholder="0.00"
                                       required>
                            </div>
                            @error('amount')
                                <p class="mt-1 text-sm text-red-600 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <!-- ID Card Fee -->
                        <div>
                            <label for="id_card_fee" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-id-card mr-2"></i>ID Card Fee (For Volunteer Fees only)
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">₦</span>
                                <input type="number"
                                       name="id_card_fee"
                                       id="id_card_fee"
                                       value="{{ old('id_card_fee', 0) }}"
                                       step="10"
                                       min="0"
                                       class="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent @error('id_card_fee') border-red-500 @enderror"
                                       placeholder="0.00">
                            </div>
                            @error('id_card_fee')
                                <p class="mt-1 text-sm text-red-600 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    {{ $message }}
                                </p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">Additional fee for Volunteer ID card</p>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-6">


                        <!-- Validity Years -->
                        <div>
                            <label for="validity_years" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-calendar-alt mr-2"></i>Validity Period (Years) *
                            </label>
                            <select name="validity_years"
                                    id="validity_years"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent @error('validity_years') border-red-500 @enderror"
                                    required>
                                <option value="">Select validity period</option>
                                @for($i = 1; $i <= 10; $i++)
                                    <option value="{{ $i }}" {{ old('validity_years') == $i ? 'selected' : '' }}>
                                        {{ $i }} Year{{ $i > 1 ? 's' : '' }}
                                    </option>
                                @endfor
                            </select>
                            @error('validity_years')
                                <p class="mt-1 text-sm text-red-600 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <!-- Fee Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                <i class="fas fa-users mr-2"></i>Fee Type *
                            </label>
                            <div class="space-y-3">
                                <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="radio"
                                           name="for_organizations"
                                           value="0"
                                           class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300"
                                           {{ old('for_organizations', '0') == '0' ? 'checked' : '' }}>
                                    <div class="ml-3">
                                        <div class="flex items-center">
                                            <i class="fas fa-user mr-2 text-green-600"></i>
                                            <span class="font-medium text-gray-900">Individual Membership</span>
                                        </div>
                                        <p class="text-sm text-gray-500">For individual members</p>
                                    </div>
                                </label>

                                <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                                    <input type="radio"
                                           name="for_organizations"
                                           value="1"
                                           class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300"
                                           {{ old('for_organizations') == '1' ? 'checked' : '' }}>
                                    <div class="ml-3">
                                        <div class="flex items-center">
                                            <i class="fas fa-building mr-2 text-blue-600"></i>
                                            <span class="font-medium text-gray-900">Organization Membership</span>
                                        </div>
                                        <p class="text-sm text-gray-500">For organization members</p>
                                    </div>
                                </label>
                            </div>
                            @error('for_organizations')
                                <p class="mt-1 text-sm text-red-600 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>
                    </div>
                </div>

                <!-- Volunteer Fee -->
                <div class="mt-6">
                    <label class="flex items-center gap-3 cursor-pointer w-fit">
                        <input type="checkbox"
                               name="is_volunteer_fee"
                               id="is_volunteer_fee"
                               value="1"
                               {{ old('is_volunteer_fee') ? 'checked' : '' }}
                               class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded">
                        <span class="text-sm font-medium text-gray-700">
                            <i class="fas fa-hands-helping mr-1"></i>Volunteer fee
                        </span>
                    </label>
                    <p class="mt-1 text-xs text-gray-500 ml-7">Mark if this fee applies to RC unit volunteers</p>
                </div>

                <!-- Action Buttons -->
                <div class="mt-8 flex justify-end space-x-3">
                    <a href="{{ route('membership-fees.index') }}"
                       class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                            class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors flex items-center">
                        <i class="fas fa-save mr-2"></i>
                        Create Membership Fee
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
