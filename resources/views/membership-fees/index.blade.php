<x-layouts.admin title="Membership Fees">

    <x-slot name="pageHeader">
        <i class="fas fa-users mr-3 mb-6"></i>  Membership Fee Types
    </x-slot>
    <x-audit-notice />

    <div class="flex justify-center mb-4">
        <x-help-popup trigger-class="help-btn">
            <x-slot:trigger><i class="fas fa-question-circle text-base mr-1"></i>Guide</x-slot:trigger>

            {{-- Header --}}
            <div class="-mt-8 mb-4 text-center">
                <i class="fas fa-circle-question text-xl text-blue-500"></i>
                <h3 class="mt-1 text-base font-semibold text-gray-900">Membership Fee Guidelines</h3>
            </div>

            {{-- Accordion --}}
            <div class="max-w-3xl mx-auto">
                <div x-data="{ open: null }" class="space-y-1 text-sm mb-4">

                    {{-- General information --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'general' ? null : 'general'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-circle-info mr-2 text-blue-400"></i>General information</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'general' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'general'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>On this page you can <span class="font-semibold">update a fee amount</span> or <span class="font-semibold">deactivate a fee</span>.</li>
                                <li>Updating the amount does <span class="font-semibold">not</span> edit the existing fee — it creates a new fee record and automatically deactivates the old one.</li>
                                <li>This keeps past and ongoing payments correctly linked to the fee amount that was active when they were made.</li>
                                <li>For anything else — like adding a brand-new fee category — use <span class="font-semibold">Add New Membership Category</span> on this page.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Create a new fee type --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'create' ? null : 'create'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-plus mr-2 text-indigo-400"></i>Create a new fee type</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'create' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'create'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Click <span class="font-semibold">Add New Membership Category</span>.</li>
                                <li>Fill in the name, amount, ID card fee, and validity period.</li>
                                <li>Choose whether it's for <span class="font-semibold">Individuals</span> or <span class="font-semibold">Organizations</span>, and whether it's a <span class="font-semibold">Volunteer Fee</span>.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Change the amount of an existing fee --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'change_amount' ? null : 'change_amount'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-money-bill-wave mr-2 text-green-400"></i>Change the amount of an existing fee</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'change_amount' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'change_amount'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Click <span class="font-semibold">Edit</span> on the fee, and change the amount and/or ID card fee.</li>
                                <li>Click <span class="font-semibold">Update Membership Fee</span>.</li>
                                <li>This automatically creates a new fee record with the new amount, and deactivates the old one — you'll see the old fee reappear as inactive.</li>
                                <li>Existing members already on the old fee keep their original amount — this only affects new and future payments.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Remove a membership fee type --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'remove' ? null : 'remove'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-box-archive mr-2 text-red-400"></i>Remove a membership fee type</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'remove' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'remove'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Click <span class="font-semibold">Edit</span> on the fee.</li>
                                <li>Untick <span class="font-semibold">Status Active</span>.</li>
                                <li>Click <span class="font-semibold">Update Membership Fee</span>.</li>
                                <li>The fee is deactivated, not deleted — it stays linked to any past payments, and the <span class="font-semibold">Edit</span> button disappears from the list since inactive fees can no longer be edited.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Volunteer & Organization fees --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'fee_types' ? null : 'fee_types'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-user-tag mr-2 text-violet-400"></i>Volunteer &amp; Organization fees</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'fee_types' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'fee_types'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li><span class="font-semibold">Type</span> shows whether a fee applies to individuals or organizations.</li>
                                <li><span class="font-semibold">Volunteer Fee</span> marks a fee as the one used specifically for volunteers.</li>
                            </ul>
                        </div>
                    </div>

                </div>{{-- end accordion --}}
            </div>{{-- end max-w-3xl --}}

        </x-help-popup>
    </div>


    <div class="container mx-auto px-4 py-8">
        <!-- Action Bar -->
        <div class="flex justify-between items-center mb-6">


            <a href="{{ route('membership-fees.create') }}"
               class="btn-addCategory">
                <i class="fas fa-plus mr-2"></i>
                Add New Membership Category
            </a>
        </div>

        <!-- Success Message -->
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center">
                <i class="fas fa-check-circle mr-2"></i>
                {{ session('success') }}
            </div>
        @endif



        <!-- Membership Fees Table -->
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">
                    Membership Fees List
                    {{-- Search display removed --}}
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Name
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Amount
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ID Card Fee
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Validity
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Type
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Volunteer Fee
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($membershipFees as $fee)
                        <tr class="hover:bg-gray-50 {{ !$fee->is_active ? 'text-gray-400' : '' }}">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium">{{ $fee->name }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                ₦{{ number_format($fee->amount, 0) }} {{-- Changed from 2 to 0 --}}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                ₦{{ number_format($fee->id_card_fee ?? 0, 0) }} {{-- Changed from 2 to 0 --}}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                {{ $fee->validity_years }} year{{ $fee->validity_years > 1 ? 's' : '' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $fee->for_organizations ? 'bg-blue-100' : 'bg-green-100' }} {{ !$fee->is_active ? 'text-gray-400' : ($fee->for_organizations ? 'text-blue-800' : 'text-green-800') }}">
                                    <i class="fas {{ $fee->for_organizations ? 'fa-building' : 'fa-user' }} mr-1"></i>
                                    {{ $fee->for_organizations ? 'Organization' : 'Individual' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $fee->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    <i class="fas {{ $fee->is_active ? 'fa-check' : 'fa-times' }} mr-1"></i>
                                    {{ $fee->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($fee->is_volunteer_fee)
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                        <i class="fas fa-check mr-1"></i>Yes
                                    </span>
                                @else
                                    <span class="text-gray-400 text-sm">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm font-medium">
                                <div class="flex-space-x-3">
                                    @if ($fee->is_active)
                                        <a href="{{ route('membership-fees.edit', $fee) }}"
                                           class="btn-primary">
                                            Edit
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="text-gray-400">
                                    <i class="fas fa-money-bill-wave text-4xl mb-4"></i>
                                    <p class="text-lg font-medium">No membership fees found</p>
                                    {{-- Search empty state removed --}}
                                    <p class="text-sm">Create your first membership fee to get started</p>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($membershipFees->hasPages())
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    {{ $membershipFees->appends(request()->query())->links() }}
                </div>
            @endif
        </div>


    </div>
</x-layouts.admin>
