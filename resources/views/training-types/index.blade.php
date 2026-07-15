
<x-layouts.admin>
    <x-slot name="title">Training Types</x-slot>

    <x-slot name="pageHeader">
        <i class="fas fa-graduation-cap mr-3 mb-6"></i> Training Types
    </x-slot>
    <x-audit-notice />

    <div class="flex justify-center mb-4">
        <x-help-popup trigger-class="help-btn">
            <x-slot:trigger><i class="fas fa-question-circle text-base mr-1"></i>Guide</x-slot:trigger>

            {{-- Header --}}
            <div class="-mt-8 mb-4 text-center">
                <i class="fas fa-circle-question text-xl text-blue-500"></i>
                <h3 class="mt-1 text-base font-semibold text-gray-900">Training Type Guidelines</h3>
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
                                <li>A <span class="font-semibold">training type</span> defines a kind of training that can be recorded against a person, e.g. First Aid, Disaster Management, or Leadership.</li>
                                <li>Each type can have a <span class="font-semibold">validity period</span> — after this many years, a completed training expires.</li>
                                <li>Click <span class="font-semibold">Add New Training Type</span> to create one.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Edit guidelines --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'edit_guidelines' ? null : 'edit_guidelines'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-pen mr-2 text-indigo-400"></i>Editing an existing type</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'edit_guidelines' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'edit_guidelines'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Only correct small errors here — spelling or capitalization, e.g. <span class="font-mono text-xs">Basic frst aid → Basic First Aid</span>.</li>
                                <li>Don't change what a type actually means, e.g. <span class="font-mono text-xs">Community First Aid → Child Protection</span> — this would misrepresent trainings already recorded under it.</li>
                                <li>If you need a genuinely different training type, create a new one instead of repurposing an existing one.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Deactivate a training type --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'deactivate' ? null : 'deactivate'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-box-archive mr-2 text-red-400"></i>Deactivate a training type</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'deactivate' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'deactivate'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Click <span class="font-semibold">Edit</span>, untick <span class="font-semibold">Active</span>, then click <span class="font-semibold">Update Training Type</span>.</li>
                                <li>This removes it from the list used when recording new trainings — past trainings recorded under it are unaffected.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- First Aid & HQ Only flags --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'flags' ? null : 'flags'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-flag mr-2 text-amber-400"></i>First Aid &amp; HQ Only flags</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'flags' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'flags'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li><span class="font-semibold">First Aid training</span> marks this type as a First Aid course — used to identify first-aid-related trainings across filters and reports.</li>
                                <li><span class="font-semibold">Certificate can only be issued by HQ</span> restricts printing this type's certificate to headquarters — branches won't see a print option for it.</li>
                            </ul>
                        </div>
                    </div>

                </div>{{-- end accordion --}}
            </div>{{-- end max-w-3xl --}}

        </x-help-popup>
    </div>


    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ session('error') }}
        </div>
    @endif

    <div class="flex justify-between items-center mb-6">
        <a href="{{ route('training-types.create') }}"
           class="btn-addCategory">
            <i class="fas fa-plus mr-2"></i>
            Add New Training Type
        </a>
    </div>

    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <table class="min-w-full table-auto">
            <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-1/6">Group</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Validity</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">HQ Only</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">First Aid</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trainings</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
            @forelse($trainingTypes as $trainingType)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="font-medium text-gray-900 line-clamp-2">{{ $trainingType->name }}</div>
                        @if($trainingType->description)
                            <div class="text-sm text-gray-500 truncate">{{ Str::limit($trainingType->description, 50) }}</div>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ $trainingType->group->group_name ?? 'N/A' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ $trainingType->validity_display }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    {{ $trainingType->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $trainingType->is_active ? 'Active' : 'Inactive' }}
                                </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm {{ $trainingType->certificate_hq_only ? 'text-orange-600' : 'text-gray-500' }}">
                                    {{ $trainingType->certificate_hq_only ? 'Yes' : 'No' }}
                                </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm {{ $trainingType->is_first_aid ? 'text-green-600 font-medium' : 'text-gray-400' }}">
                            {{ $trainingType->is_first_aid ? 'Yes' : '—' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                        {{ $trainingType->total_trainings_count }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <div class="flex-space-x-3">
                            <a href="{{ route('training-types.edit', $trainingType) }}"
                               class="btn-primary">
                                Edit
                            </a>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                        No training types found.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
    </div>
</x-layouts.admin>
