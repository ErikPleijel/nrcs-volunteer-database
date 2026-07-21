<x-layouts.admin title="Branches">

    <x-slot name="pageHeader">
        <i class="fas fa-sitemap mr-3"></i> Branches
    </x-slot>

    <x-slot name="subHeader">
        Find & Filter
    </x-slot>

    <div class="flex justify-center mb-4">
        <x-help-popup trigger-class="help-btn">
            <x-slot:trigger><i class="fas fa-question-circle text-base mr-1"></i>Guide</x-slot:trigger>

            {{-- Header --}}
            <div class="-mt-8 mb-4 text-center">
                <i class="fas fa-question-circle text-xl text-sky-500"></i>
                <h3 class="mt-1 text-base font-semibold text-gray-900">How do I...</h3>
            </div>

            {{-- Accordion --}}
            <div class="max-w-3xl mx-auto">
                <div x-data="{ open: null }" class="space-y-1 text-sm mb-4">

                    {{-- Read the branches table --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'table' ? null : 'table'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-table mr-2 text-indigo-400"></i>Read the branches table</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'table' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'table'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Rows highlighted in <span class="font-semibold text-red-500">red</span> are missing key information — a physical/postal address, phone, email, or public contacts.</li>
                                <li><span class="font-semibold">Divisions</span>, <span class="font-semibold">RC Units</span>, <span class="font-semibold">Volunteers</span>, and <span class="font-semibold">Members</span> show live counts for each branch.</li>
                                <li>Click <span class="font-semibold">View</span> to see the branch's full details, or <span class="font-semibold">Edit</span> to update its information.</li>

                            </ul>
                        </div>
                    </div>

                    {{-- Edit branch information --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'edit' ? null : 'edit'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-pencil mr-2 text-amber-400"></i>Edit branch information</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'edit' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'edit'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li><span class="font-semibold">Name</span>, <span class="font-semibold">Code</span>, and <span class="font-semibold">Zone</span> are fixed and can't be edited here.</li>
                                <li>You can update <span class="font-semibold">Physical Address</span>, <span class="font-semibold">Postal Address</span>, <span class="font-semibold">Telephone</span>, and <span class="font-semibold">Email</span>.</li>
                                <li><span class="font-semibold">Projects</span> is just a statistic — it's shown in the map popup on the public welcome page, and doesn't affect anything else in the system.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Set up public contacts --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'contacts' ? null : 'contacts'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-address-card mr-2 text-violet-400"></i>Set up public contact persons</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'contacts' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'contacts'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>You can assign up to <span class="font-semibold">6 contact persons</span> for a branch, shown on profile and public-facing views.</li>
                                <li>These give the public a real point of contact for the branch — someone to reach out to, rather than just a generic office phone number.</li>
                                <li>Pick a person from the dropdown for each slot, and optionally give them a <span class="font-semibold">Position/Title</span>, e.g. "Branch Secretary" or "Branch Chairperson".</li>
                                <li>Only people who already hold a role in this branch or its divisions can be selected as a contact.</li>
                                <li>Leave a slot as <span class="font-semibold">"— None —"</span> if you don't need all 6.</li>
                            </ul>
                        </div>
                    </div>

                </div>{{-- end accordion --}}
            </div>{{-- end max-w-3xl --}}

        </x-help-popup>
    </div>


    <div class="container mx-auto px-4 py-6">

        <div class="table-container">
            @if($branches->count() > 0)
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead class="table-header">
                            <tr class="table-header-row">
                                <th class="table-header-cell">Name</th>
                                <th class="table-header-cell">Code</th>
                                <th class="table-header-cell">Physical Address</th>
                                <th class="table-header-cell">Postal Address</th>
                                <th class="table-header-cell">Contact</th>
                                <th class="table-header-cell">Contacts</th>
                                <th class="table-header-cell hyphens-auto" lang="en">Di&shy;vi&shy;sions</th>
                                <th class="table-header-cell hyphens-auto" lang="en">RC Units</th>
                                <th class="table-header-cell hyphens-auto" lang="en">Vol&shy;un&shy;teers</th>
                                <th class="table-header-cell hyphens-auto" lang="en">Mem&shy;bers</th>
                                <th class="table-header-cell">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="table-body">
                            @foreach($branches as $branch)
                                <tr class="table-body-row">

                                    <td class="table-body-cell">
                                        <div class="table-field-main">{{ $branch->name }}</div>
                                    </td>

                                    <td class="table-body-cell">
                                        <div class="table-field-main">{{ $branch->code }}</div>
                                    </td>

                                    <td class="table-body-cell {{ !$branch->physical_address ? 'bg-red-50' : '' }}">
                                        <div class="table-field-main {{ !$branch->physical_address ? 'text-red-400 italic' : '' }} max-w-[120px] truncate" title="{{ $branch->physical_address ?: 'Not provided' }}">
                                            {{ $branch->physical_address ?: 'Not provided' }}
                                        </div>
                                    </td>

                                    <td class="table-body-cell {{ !$branch->postal_address ? 'bg-red-50' : '' }}">
                                        <div class="table-field-main {{ !$branch->postal_address ? 'text-red-400 italic' : '' }} max-w-[120px] truncate" title="{{ $branch->postal_address ?: 'Not provided' }}">
                                            {{ $branch->postal_address ?: 'Not provided' }}
                                        </div>
                                    </td>

                                    @php $missingContact = !$branch->telephone && !$branch->email; @endphp
                                    <td class="table-body-cell {{ $missingContact ? 'bg-red-50' : '' }}">
                                        <div class="table-field-main {{ !$branch->telephone ? 'text-red-400 italic' : '' }}">
                                            {{ $branch->telephone ?: 'No telephone' }}
                                        </div>
                                        <div class="table-field-sub break-all {{ !$branch->email ? 'text-red-400 italic' : '' }}">
                                            {{ $branch->email ?: 'No email' }}
                                        </div>
                                    </td>

                                    <td class="table-body-cell {{ $branch->public_contacts_count === 0 ? 'bg-red-50' : '' }}">
                                        @if($branch->public_contacts_count > 0)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                {{ $branch->public_contacts_count }} {{ $branch->public_contacts_count === 1 ? 'contact' : 'contacts' }}
                                            </span>
                                        @else
                                            <span class="text-red-400 italic text-xs">None</span>
                                        @endif
                                    </td>

                                    <td class="table-body-cell">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            {{ $branch->divisions_count }}
                                        </span>
                                    </td>

                                    <td class="table-body-cell">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $branch->rc_units_count }}
                                        </span>
                                    </td>

                                    <td class="table-body-cell">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            {{ $branch->volunteers_count }}
                                        </span>
                                    </td>

                                    <td class="table-body-cell">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                            {{ $branch->members_count }}
                                        </span>
                                    </td>

                                    <td class="table-body-cell-no-wrap">
                                        <div class="flex gap-2 items-center">
                                            @can('view_branch_information')
                                                <a href="{{ route('branches.show', $branch) }}"
                                                   class="btn-primary inline-flex items-center gap-1"
                                                  >
                                                    <i class="fas fa-eye mr-1"></i>View
                                                </a>
                                            @endcan
                                        </div>
                                    </td>

                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

            @else
                <div class="table-empty-state">
                    <i class="fas fa-sitemap text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No branches found.</h3>
                </div>
            @endif
        </div>



    </div>

</x-layouts.admin>
