<x-layouts.admin title="Edit Membership Fee">

    <x-slot name="pageHeader">
        <i class="fas fa-users mr-3"></i>  Membership
    </x-slot>
    <x-slot name="subHeader">
        CHANGE FEE AMOUNT
    </x-slot>

    <x-slot name="backLink">
        <a href="{{ route('membership-fees.index') }}"
           class="btn-backlink">
            ←  Back to MEMBERSHIP TYPES OVERVIEW
        </a>
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

        <!-- Edit Form -->
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <div class="bg-yellow-600 px-6 py-4">
                <h2 class="text-xl font-semibold text-white flex items-center">
                    <i class="fas fa-edit mr-3"></i>
                    Edit Membership Fee: {{ $membershipFee->name }}
                </h2>
            </div>

            <form action="{{ route('membership-fees.update', $membershipFee) }}" method="POST" class="p-6">
                @csrf
                @method('PUT')

                <!-- Note for editing -->
                <div class="important-note" role="alert">
                    <p class="font-bold">Important Note:</p>
                    <p>On this page you can either update a fee amount or deactivate a fee. Updating the amount will create a new record and automatically deactivate the old one. This ensures that past and ongoing payments remain linked to the correct fee amount. For any other changes, use “Create New Membership Category” on the previous page.</p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Left Column -->
                    <div class="space-y-6">
                        <div class="border-b border-gray-200 pb-4">
                            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-info-circle mr-2 text-blue-600"></i>
                                Basic Information
                            </h3>
                        </div>

                        <!-- Name (Disabled) -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-tag mr-2"></i>Fee Name *
                            </label>
                            <input type="text"
                                   name="name"
                                   id="name"
                                   value="{{ old('name', $membershipFee->name) }}"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-500 cursor-not-allowed"
                                   placeholder="Enter fee name"
                                   disabled>
                            <input type="hidden" name="name" value="{{ $membershipFee->name }}"> {{-- Send disabled value --}}
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
                                       value="{{ old('amount', (int)$membershipFee->amount) }}" {{-- Cast to int --}}
                                       step="1"
                                       min="0"
                                       class="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent @error('amount') border-red-500 @enderror"
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
                                <i class="fas fa-id-card mr-2"></i>ID Card Fee (Optional)
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">₦</span>
                                <input type="number"
                                       name="id_card_fee"
                                       id="id_card_fee"
                                       value="{{ old('id_card_fee', (int)$membershipFee->id_card_fee) }}" {{-- Cast to int --}}
                                       step="1"
                                       min="0"
                                       class="w-full pl-8 pr-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent @error('id_card_fee') border-red-500 @enderror">
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
                        <div class="border-b border-gray-200 pb-4">
                            <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-cogs mr-2 text-gray-600"></i>
                                Configuration
                            </h3>
                        </div>

                        <!-- Validity Years (Disabled) -->
                        <div>
                            <label for="validity_years" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-calendar-alt mr-2"></i>Validity Period (Years) *
                            </label>
                            <select name="validity_years"
                                    id="validity_years"
                                    class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-100 text-gray-500 cursor-not-allowed"
                                    disabled>
                                <option value="">Select validity period</option>
                                @for($i = 1; $i <= 10; $i++)
                                    <option value="{{ $i }}" {{ old('validity_years', $membershipFee->validity_years) == $i ? 'selected' : '' }}>
                                        {{ $i }} Year{{ $i > 1 ? 's' : '' }}
                                    </option>
                                @endfor
                            </select>
                            <input type="hidden" name="validity_years" value="{{ $membershipFee->validity_years }}"> {{-- Send disabled value --}}
                            @error('validity_years')
                                <p class="mt-1 text-sm text-red-600 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <!-- Fee Type (Disabled) -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                <i class="fas fa-users mr-2"></i>Fee Type *
                            </label>
                            <div class="space-y-3">
                                <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-not-allowed bg-gray-100">
                                    <input type="radio"
                                           name="for_organizations"
                                           value="0"
                                           class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 cursor-not-allowed"
                                           {{ old('for_organizations', $membershipFee->for_organizations) == '0' ? 'checked' : '' }}
                                           disabled>
                                    <div class="ml-3 text-gray-500">
                                        <div class="flex items-center">
                                            <i class="fas fa-user mr-2 text-green-600"></i>
                                            <span class="font-medium">Individual Membership</span>
                                        </div>
                                        <p class="text-sm">For individual members</p>
                                    </div>
                                </label>

                                <label class="flex items-center p-3 border border-gray-300 rounded-lg cursor-not-allowed bg-gray-100">
                                    <input type="radio"
                                           name="for_organizations"
                                           value="1"
                                           class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 cursor-not-allowed"
                                           {{ old('for_organizations', $membershipFee->for_organizations) == '1' ? 'checked' : '' }}
                                           disabled>
                                    <div class="ml-3 text-gray-500">
                                        <div class="flex items-center">
                                            <i class="fas fa-building mr-2 text-blue-600"></i>
                                            <span class="font-medium">Organization Membership</span>
                                        </div>
                                        <p class="text-sm">For organization members</p>
                                    </div>
                                </label>
                            </div>
                            <input type="hidden" name="for_organizations" value="{{ (int) $membershipFee->for_organizations }}">
                            @error('for_organizations')
                                <p class="mt-1 text-sm text-red-600 flex items-center">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    {{ $message }}
                                </p>
                            @enderror
                        </div>

                        <!-- Status -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                <i class="fas fa-toggle-on mr-2"></i>Status
                            </label>
                            <div class="flex items-center space-x-4">
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox"
                                           name="is_active"
                                           value="1"
                                           class="h-5 w-5 text-red-600 focus:ring-red-500 border-gray-300 rounded"
                                           {{ old('is_active', $membershipFee->is_active) ? 'checked' : '' }}>
                                    <span class="ml-2 text-sm font-medium text-gray-700">Active</span>
                                </label>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Only active fees can be used for new memberships</p>
                        </div>

                        <!-- Volunteer Fee -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                <i class="fas fa-hand-holding-heart mr-2"></i>Volunteer Fee
                            </label>
                            <div class="flex items-center space-x-4">
                                <label class="flex items-center cursor-pointer">
                                    <input type="checkbox"
                                           name="is_volunteer_fee"
                                           value="1"
                                           class="h-5 w-5 text-red-600 focus:ring-red-500 border-gray-300 rounded"
                                           {{ old('is_volunteer_fee', $membershipFee->is_volunteer_fee) ? 'checked' : '' }}>
                                    <span class="ml-2 text-sm font-medium text-gray-700">This is a volunteer fee</span>
                                </label>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Volunteer fees are reported separately from member fees</p>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-8 flex justify-end space-x-3">
                    <a href="{{ route('membership-fees.show', $membershipFee) }}"
                       class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </a>
                    <button type="submit"
                            class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors flex items-center">
                        <i class="fas fa-save mr-2"></i>
                        Update Membership Fee
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
