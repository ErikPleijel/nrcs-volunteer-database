<x-layouts.admin title="Certificates">
    <x-slot name="pageHeader">
        <div class="flex items-center">
            <i class="fas fa-certificate mr-2"></i>
            <span>Certificates</span>
        </div>
    </x-slot>

    <x-slot name="subHeader">
        Certificate Generator
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

                    {{-- Understand certificate types --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'types' ? null : 'types'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-certificate mr-2 text-indigo-400"></i>Understand certificate types</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'types' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'types'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Training certificates come in two kinds: <span class="font-semibold">Attendance</span> confirms someone was present.</li>
                                <li><span class="font-semibold">Competence</span> is a certification — it confirms they've been assessed and met the standard.</li>
                                <li>Membership, Volunteering, and Donation certificates are separate types, selected from the same <span class="font-semibold">Certificate Type</span> dropdown.</li>
                            </ul>
                        </div>
                    </div>

                    {{-- Search & filter --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'filter' ? null : 'filter'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-filter mr-2 text-sky-400"></i>Search &amp; filter certificates</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'filter' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'filter'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Choose a <span class="font-semibold">Certificate Type</span> first — Membership, Training, Volunteering, or Donation.</li>
                                <li>Use <span class="font-semibold">Search User</span> to find one specific person by name — handy if you just want to print for a single person.</li>
                                <li>Use <span class="font-semibold">Branch → Division → Red Cross Unit</span> to bulk-select an entire group at once.</li>
                                <li>Use <span class="font-semibold">Certificate Status</span> to isolate Already Printed or Never Printed records.</li>
                            </ul>
                        </div>
                    </div>

                    @can('print_certificates')
                        {{-- Select certificates --}}
                        <div class="rounded-md border border-gray-200 overflow-hidden">
                            <button type="button"
                                    @click="open = open === 'select' ? null : 'select'"
                                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                <span><i class="fas fa-check-square mr-2 text-amber-400"></i>Select &amp; deselect certificates</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                   :class="open === 'select' ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open === 'select'" x-collapse class="px-4 py-3 bg-white">
                                <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                    <li>Tick the checkbox on a card to select it for printing.</li>
                                    <li>Click <span class="font-semibold">Select All</span> to select every card on the page, or <span class="font-semibold">Deselect All</span> to clear your selection.</li>
                                    <li>The counter at the top shows how many are currently selected.</li>
                                    <li>Some certificates show <span class="font-semibold">"Printed at HQ"</span> instead of a checkbox — these can only be printed centrally and aren't selectable here.</li>
                                </ul>
                            </div>
                        </div>

                        {{-- Set up signatures --}}
                        <div class="rounded-md border border-gray-200 overflow-hidden">
                            <button type="button"
                                    @click="open = open === 'signatures' ? null : 'signatures'"
                                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                <span><i class="fas fa-signature mr-2 text-violet-400"></i>Set up signatures</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                   :class="open === 'signatures' ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open === 'signatures'" x-collapse class="px-4 py-3 bg-white">
                                <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                    <li>You can preset the <span class="font-semibold">Title</span> and <span class="font-semibold">Name</span> for up to two signatures that appear on the printed certificate.</li>
                                    <li>Choose <span class="font-semibold">Line only</span> if you want a blank signature line with no title, or leave Signature 2 as <span class="font-semibold">No second signature</span> if only one is needed.</li>
                                    <li><span class="font-semibold">Pre-printed Signature</span> (an actual signature image) is only available at <span class="font-semibold">HQ</span> — branch-level users only see the Title and Name fields.</li>
                                </ul>
                            </div>
                        </div>

                        {{-- Print & record --}}
                        <div class="rounded-md border border-gray-200 overflow-hidden">
                            <button type="button"
                                    @click="open = open === 'print' ? null : 'print'"
                                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                <span><i class="fas fa-print mr-2 text-green-500"></i>Print &amp; record certificates</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                   :class="open === 'print' ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open === 'print'" x-collapse class="px-4 py-3 bg-white">
                                <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                    <li>You can print on <span class="font-semibold">blank paper</span> or <span class="font-semibold">pre-printed paper</span>: choose <span class="font-semibold">Print for pre-printed paper</span> if you're using paper with the logo/frame already on it, or <span class="font-semibold">Print with logo &amp; frame</span> for plain paper.</li>
                                    <li>For pre-printed paper, use the <span class="font-semibold">layout editor</span> (top right) to adjust text position — your setting is remembered for next time.</li>
                                    <li>🔶 <span class="font-semibold">Do not leave this page</span> after printing — you still need to confirm it worked.</li>
                                    <li>Click <span class="font-semibold">Mark as Printed</span> to record the print in the database.</li>
                                    <li>Without this step, the system won't know the certificates were printed, even if they physically were.</li>
                                </ul>
                            </div>
                        </div>

                        {{-- Print history --}}
                        <div class="rounded-md border border-gray-200 overflow-hidden">
                            <button type="button"
                                    @click="open = open === 'history' ? null : 'history'"
                                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                <span><i class="fas fa-file-alt mr-2 text-gray-500"></i>Check print history &amp; fix mistakes</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                   :class="open === 'history' ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open === 'history'" x-collapse class="px-4 py-3 bg-white">
                                <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                    <li>Click <span class="font-semibold">View Print History</span> to see everything that's been marked as printed.</li>
                                    <li>If a certificate was <span class="font-semibold">accidentally marked as printed</span>, you can correct that mistake from this history page.</li>
                                </ul>
                            </div>
                        </div>
                    @endcan

                </div>{{-- end accordion --}}
            </div>{{-- end max-w-3xl --}}

        </x-help-popup>
    </div>

    @can('print_certificates')
    <x-slot name="button1">
        <a href="{{ route('organisations.certificates.index') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-building mr-1"></i>Certificates for organisations
        </a>
    </x-slot>
    @endcan

    <div class="container mx-auto px-4 py-6">
        <!-- Filter Form -->
        <div class="filter-container">
            <div class="filter-form-content">
                <form action="{{ route('certificates.index') }}" method="GET" id="filter-form" class="filter-form">

                    <div class="filter-grid filter-grid-5">
                        <!-- Column 1: Search + (optional) Certificate Type -->
                        <div class="flex flex-col gap-2">
                            <!-- Certificate Type -->
                            <div>
                                <label for="certificate_type" class="filter-label">
                                    Certificate Type
                                </label>
                                <select
                                    name="certificate_type"
                                    id="certificate_type"
                                    class="filter-select {{ ($certificateType ?? 'training_competence') !== 'training_competence' ? 'filter-active' : '' }}"
                                >
                                    @foreach($certificateTypes as $typeValue => $typeLabel)
                                        <option value="{{ $typeValue }}" @selected(($certificateType ?? 'training_competence') === $typeValue)>
                                            {{ $typeLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Search -->
                            <div>
                                <label for="search" class="filter-label">
                                    Search User
                                </label>
                                <input type="text"
                                       name="search"
                                       id="search"
                                       class="filter-input {{ request('search') ? 'filter-active' : '' }}"
                                       value="{{ request('search') }}"
                                       placeholder="Name...">
                            </div>
                        </div>

                        <!-- Column 2: Branch / Division / Unit -->
                        <div class="flex flex-col gap-2">
                            <!-- Branch -->
                            <div>
                                <label for="branch_id" class="filter-label-small">
                                    Branch
                                </label>
                                <select name="branch_id"
                                        id="branch_id"
                                        class="filter-select-small disabled:bg-gray-200 disabled:opacity-75 {{ $accessLevel === 'national' && request('branch_id') ? 'filter-active' : '' }}"
                                        @if($accessLevel !== 'national') disabled @endif>
                                    @if($accessLevel === 'national')
                                        <option value="">All Branches</option>
                                        @foreach($branches as $branch)
                                            <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>
                                                {{ $branch->name }}
                                            </option>
                                        @endforeach
                                    @else
                                        @php $userBranch = $branches->firstWhere('id', $userBranchId); @endphp
                                        @if($userBranch)
                                            <option value="{{ $userBranch->id }}" selected>{{ $userBranch->name }}</option>
                                        @endif
                                    @endif
                                </select>
                                @if($accessLevel !== 'national')
                                    <input type="hidden" name="branch_id" value="{{ $userBranchId }}">
                                @endif
                            </div>

                            <!-- Division -->
                            <div>
                                <label for="division_id" class="filter-label-small">
                                    Division
                                </label>
                                <select name="division_id"
                                        id="division_id"
                                        class="filter-select-small disabled:bg-gray-200 disabled:opacity-75 {{ in_array($accessLevel, ['national', 'branch']) && request('division_id') ? 'filter-active' : '' }}"
                                        @if(!in_array($accessLevel, ['national', 'branch'])) disabled
                                        @elseif($accessLevel === 'national' && !request('branch_id')) disabled @endif>
                                    @if(in_array($accessLevel, ['national', 'branch']))
                                        <option value="">All Divisions</option>
                                        @foreach($divisions as $division)
                                            <option value="{{ $division->id }}" @selected(request('division_id') == $division->id)>
                                                {{ $division->name }}
                                            </option>
                                        @endforeach
                                    @else
                                        @php $userDivision = $divisions->firstWhere('id', $userDivisionId); @endphp
                                        @if($userDivision)
                                            <option value="{{ $userDivision->id }}" selected>{{ $userDivision->name }}</option>
                                        @endif
                                    @endif
                                </select>
                                @if(!in_array($accessLevel, ['national', 'branch']))
                                    <input type="hidden" name="division_id" value="{{ $userDivisionId }}">
                                @endif
                            </div>

                            <!-- Red Cross Unit -->
                            <div>
                                <label for="red_cross_unit_id" class="filter-label-small">
                                    Red Cross Unit
                                </label>
                                <select name="red_cross_unit_id"
                                        id="red_cross_unit_id"
                                        class="filter-select-small {{ request('red_cross_unit_id') ? 'filter-active' : '' }}"
                                        @if(!request('division_id')) disabled @endif>
                                    <option value="">All Units</option>
                                    @foreach($redCrossUnits as $unit)
                                        <option value="{{ $unit->id }}" @selected(request('red_cross_unit_id') == $unit->id)>
                                            {{ $unit->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Column 3: Other filters -->
                        <div class="flex flex-col gap-2">
                            <!-- Training Type -->
                            <div id="training-type-filter-container">
                                <label for="training_type_id" class="filter-label-small">
                                    Training Type
                                </label>
                                <select name="training_type_id"
                                        id="training_type_id"
                                        class="filter-select-small {{ request('training_type_id') ? 'filter-active' : '' }}">
                                    <option value="">All Types</option>
                                    @foreach($trainingTypes as $type)
                                        <option value="{{ $type->id }}" @selected(request('training_type_id') == $type->id)>
                                            {{ $type->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Print status filter -->
                            <div>
                                <label for="print_status" class="filter-label-small">
                                    Certificate Status
                                </label>
                                <select name="print_status"
                                        id="print_status"
                                        class="filter-select-small {{ request('print_status') ? 'filter-active' : '' }}">
                                    <option value="" @selected(!request('print_status'))>All</option>
                                    <option value="printed" @selected(request('print_status') === 'printed')>Already printed</option>
                                    <option value="not_printed" @selected(request('print_status') === 'not_printed')>Never printed</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="filter-actions">
                        <div class="filter-button-group">
                            <button type="submit" class="filter-btn-primary">
                                <i class="fas fa-search mr-1"></i>Filter
                            </button>

                            <a href="{{ route('certificates.index') }}"
                               class="filter-btn-secondary filter-btn-secondary-active">
                                <i class="fas fa-times mr-1"></i>Clear
                            </a>
                        </div>
                    </div>

                </form>
            </div>
        </div>



    @if($records->isEmpty())
            <div class="text-center py-12">
                <p class="text-gray-500 text-lg">No records found matching your criteria.</p>
            </div>
        @else
            <form id="bulk-print-form"
                  action="{{ route('certificates.bulk.print.plain') }}"
                  method="POST"
                  target="_blank">
                @csrf

                {{-- Preserve selected certificate type in bulk print --}}
                <input type="hidden" name="certificate_type" value="{{ $certificateType ?? 'training_competence' }}">

                <div class="flex flex-col md:flex-row md:items-start md:justify-between mb-4 gap-4">
                        @can('print_certificates')
                    <div class="flex items-center flex-wrap gap-3">
                        <button type="button"
                                id="select-all"
                                class="btn-bulk-select">
                            Select All
                        </button>

                        <button type="button"
                                id="deselect-all"
                                class="btn-bulk-select">
                            Deselect All
                        </button>

                        <span id="selection-counter" class="bulk-selection-counter">
                            0 users selected
                        </span>
                    </div>
                    @else
                    <div></div>
                    @endcan



                    <div class="flex flex-col gap-3 md:items-end">
                        @can('print_certificates')
                        {{-- Signature columns --}}
                        <div class="flex flex-col sm:flex-row gap-4">
                            {{-- Signature 1 --}}
                            <div class="flex flex-col gap-2">
                                <div class="text-xs font-bold text-gray-600 uppercase tracking-wide">Signature 1</div>
                                <div>
                                    <label for="signature_1_title_id" class="block text-xs text-gray-600">Title</label>
                                    <select name="signature_1_title_id" id="signature_1_title_id"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 text-xs">
                                        <option value="" @selected($selectedSign1Id == '')>— Select title —</option>
                                        <option value="_line_only_" @selected($selectedSign1Id == '_line_only_')>Line only (no title)</option>
                                        @foreach($signatureTitles as $title)
                                            <option value="{{ $title->id }}" @selected($selectedSign1Id == $title->id)>{{ $title->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="signature_1_name" class="block text-xs text-gray-600">Name</label>
                                    <input type="text" name="signature_1_name" id="signature_1_name"
                                           value="{{ request('signature_1_name') }}"
                                           placeholder="e.g. John Smith"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 text-xs">
                                </div>
                                @if($accessLevel !== 'branch')
                                <div>
                                    <label for="signature_1_image" class="block text-xs text-gray-600">Pre-printed Signature</label>
                                    <select name="signature_1_image" id="signature_1_image"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 text-xs">
                                        <option value="">— No image —</option>
                                        @foreach($signatureImages as $filename)
                                            <option value="{{ $filename }}" @selected(request('signature_1_image') === $filename)>{{ $filename }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @endif
                            </div>

                            {{-- Signature 2 --}}
                            <div class="flex flex-col gap-2">
                                <div class="text-xs font-bold text-gray-600 uppercase tracking-wide">Signature 2</div>
                                <div>
                                    <label for="signature_2_title_id" class="block text-xs text-gray-600">Title</label>
                                    <select name="signature_2_title_id" id="signature_2_title_id"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 text-xs">
                                        <option value="_none_" @selected($selectedSign2Id == '_none_')>— No second signature —</option>
                                        <option value="_line_only_" @selected($selectedSign2Id == '_line_only_')>Line only (no title)</option>
                                        @foreach($signatureTitles as $title)
                                            <option value="{{ $title->id }}" @selected($selectedSign2Id == $title->id)>{{ $title->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="signature_2_name" class="block text-xs text-gray-600">Name</label>
                                    <input type="text" name="signature_2_name" id="signature_2_name"
                                           value="{{ request('signature_2_name') }}"
                                           placeholder="e.g. John Smith"
                                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 text-xs">
                                </div>
                                @if($accessLevel !== 'branch')
                                <div>
                                    <label for="signature_2_image" class="block text-xs text-gray-600">Pre-printed Signature</label>
                                    <select name="signature_2_image" id="signature_2_image"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500 text-xs">
                                        <option value="">— No image —</option>
                                        @foreach($signatureImages as $filename)
                                            <option value="{{ $filename }}" @selected(request('signature_2_image') === $filename)>{{ $filename }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endcan

                        {{-- Action Buttons --}}
                        <div class="flex flex-wrap gap-2 justify-end mt-2">
                            <a href="{{ route('certificates.prints-report') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-md hover:bg-gray-50 transition">
                                <i class="fas fa-file-alt mr-1"></i> View Print History
                            </a>

                            @can('print_certificates')
                            <!-- Button: Pre-printed papers (plain) -->
                            <button
                                id="bulk-print-plain"
                                type="submit"
                                class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-semibold rounded-md hover:bg-gray-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                disabled
                                formaction="{{ route('certificates.bulk.print.plain') }}"
                            >
                                <i class="fas fa-print mr-2"></i>
                                Print for pre-printed paper
                            </button>

                            <!-- Button: With logo & frame (branded) -->
                            <button
                                id="bulk-print-branded"
                                type="submit"
                                class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-md hover:bg-red-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                disabled
                                formaction="{{ in_array($certificateType, ['volunteering', 'donation']) ? route('certificates.bulk_print_branded_portrait') : route('certificates.bulk.print.branded') }}"
                            >
                                <i class="fas fa-certificate mr-2"></i>
                                Print with logo & frame
                            </button>

                            <!-- Button: Mark as Printed + stop pulse -->
                            <div class="flex items-center gap-2">
                                <button
                                    id="mark-as-printed"
                                    type="button"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                    disabled
                                >
                                    <i class="fas fa-check-circle mr-2"></i>
                                    Mark as Printed
                                </button>

                                <button type="button"
                                        id="stop-pulse-btn"
                                        disabled
                                        title="Stop reminder"
                                        class="w-9 h-9 flex items-center justify-center rounded bg-gray-300 text-gray-500 cursor-not-allowed transition-colors"
                                        onclick="stopPulse()">
                                    <i class="fas fa-stop text-sm"></i>
                                </button>
                            </div>
                            @endcan
                        </div>
                    </div>
                </div>

                {{-- This heading is now dynamic based on the certificate type --}}
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">
                    {{ $certificateTypes[$certificateType] ?? 'Records' }}
                </h2>


                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    @foreach($records as $item)
                        <div class="bg-white rounded-lg shadow-lg overflow-hidden relative">
                            <div class="p-5">
                                @can('print_certificates')
                                <div class="absolute top-4 right-4">
                                    @if($certificateType === 'training_competence' && !empty($item->trainingType->certificate_hq_only) && $accessLevel !== 'national')
                                        <span class="text-xs font-semibold text-indigo-700 leading-tight text-right block">Printed<br>at HQ</span>
                                    @else
                                        <input type="checkbox"
                                               name="training_ids[]"
                                               value="{{ $item->id }}"
                                               class="h-5 w-5 text-red-600 border-gray-300 rounded focus:ring-red-500 bulk-checkbox">
                                    @endif
                                </div>
                                @endcan


                                @php
                                    // Define the $user object to avoid repetition and handle both item types
                                    $user = in_array($certificateType, ['donation', 'volunteering']) ? $item : $item->user;
                                @endphp

                                @php
                                    $lookupKey = in_array($certificateType, ['training_competence', 'training_attendance'])
                                        ? $item->id
                                        : (in_array($certificateType, ['volunteering', 'donation'])
                                            ? $item->id
                                            : $item->user_id);
                                    $isPrinted = isset($printedKeys[$lookupKey]);
                                @endphp

                                <div class="flex items-start justify-between pr-8 gap-2">
                                    <div class="font-bold text-lg text-gray-800">
                                        {{ $user->full_name }}
                                    </div>
                                    @if($isPrinted)
                                        <span class="flex-shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200">
                                            <i class="fas fa-circle-check text-green-500"></i> Printed
                                        </span>
                                    @else
                                        <span class="flex-shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500 border border-gray-200">
                                            <i class="fas fa-circle text-gray-300"></i> Not printed
                                        </span>
                                    @endif
                                </div>

                                {{-- Add the User ID reference below the name --}}
                                <p class="text-xs text-gray-500 break-words mb-2">
                                    {{ $user->user_id_reference }}
                                </p>

                                @switch($certificateType)
                                    @case('membership')
                                        <p class="text-gray-700 text-base">
                                            <span class="font-semibold">Membership:</span>
                                            {{ $item->membershipFee->name ?? 'N/A' }}
                                        </p>
                                        <p class="text-gray-600 text-sm">
                                            <span class="font-semibold">Expires:</span>
                                            {{ $item->expiry_date ? $item->expiry_date->format('M d, Y') : 'N/A' }}
                                        </p>
                                        @break

                                    @case('volunteering')
                                        <p class="text-gray-700 text-base">
                                            <span class="font-semibold">Total Activities:</span>
                                            {{ $item->activities_count }}
                                        </p>
                                        <p class="text-gray-600 text-sm">
                                            <span class="font-semibold">Total Hours:</span>
                                            {{ $item->activities_sum_hours ?? 0 }}
                                        </p>
                                        @break

                                    @case('donation')
                                        <p class="text-gray-700 text-base">
                                            <span class="font-semibold">Total In-Kind Donations:</span>
                                            {{ $item->in_kind_donations_count }}
                                        </p>
                                        <p class="text-gray-700 text-base">
                                            <span class="font-semibold">Total Cash Donations:</span>
                                            {{ $item->cash_donations_count }}
                                        </p>
                                        <p class="text-gray-600 text-sm">
                                            <span class="font-semibold">Total Cash Donated:</span>
                                            ₦{{ number_format($item->donations_sum_amount ?? 0, 2) }}
                                        </p>
                                        @break

                                    @default
                                        <p class="text-gray-700 text-base">
                                            <span class="font-semibold">Training:</span>
                                            {{ $item->trainingType->name ?? 'N/A' }}
                                        </p>
                                        <p class="text-gray-600 text-sm">
                                            <span class="font-semibold">Date:</span>
                                            {{ $item->training_date ? $item->training_date->format('M d, Y') : 'N/A' }}
                                        </p>
                                @endswitch
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $records->links() }}
                </div>
            </form>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Dropdown dependency logic for Branch -> Division -> Unit
            const branchSelect = document.getElementById('branch_id');
            const divisionSelect = document.getElementById('division_id');
            const unitSelect = document.getElementById('red_cross_unit_id');
            const divisionsByBranchUrl = '/divisions/by-branch';
            const unitsByDivisionUrl = '/red-cross-units/by-division';

            if (branchSelect) {
                branchSelect.addEventListener('change', function () {
                    const branchId = this.value;

                    divisionSelect.innerHTML = '<option value="">All Divisions</option>';
                    divisionSelect.disabled = true;
                    if (unitSelect) {
                        unitSelect.innerHTML = '<option value="">All Units</option>';
                        unitSelect.disabled = true;
                    }

                    if (branchId) {
                        fetch(`${divisionsByBranchUrl}?branch_id=${branchId}`)
                            .then(response => response.json())
                            .then(data => {
                                divisionSelect.disabled = false;
                                data.forEach(division => {
                                    divisionSelect.add(new Option(division.name, division.id));
                                });
                            })
                            .catch(error => console.error('Error fetching divisions:', error));
                    }
                });
            }

            if (divisionSelect) {
                divisionSelect.addEventListener('change', function () {
                    const divisionId = this.value;

                    if (unitSelect) {
                        unitSelect.innerHTML = '<option value="">All Units</option>';
                        unitSelect.disabled = true;

                        if (divisionId) {
                            fetch(`${unitsByDivisionUrl}?division_id=${divisionId}`)
                                .then(response => response.json())
                                .then(data => {
                                    unitSelect.disabled = false;
                                    data.forEach(unit => {
                                        unitSelect.add(new Option(unit.name, unit.id));
                                    });
                                })
                                .catch(error => console.error('Error fetching units:', error));
                        }
                    }
                });
            }

            // Bulk selection and print button logic
            const checkboxes = document.querySelectorAll('.bulk-checkbox');
            const printPlainButton = document.getElementById('bulk-print-plain');
            const printBrandedButton = document.getElementById('bulk-print-branded');
            const markAsPrintedButton = document.getElementById('mark-as-printed');
            const selectAllButton = document.getElementById('select-all');
            const deselectAllButton = document.getElementById('deselect-all');
            const selectionCounter = document.getElementById('selection-counter');

            function updateSelectionState() {
                const checkedCount = document.querySelectorAll('.bulk-checkbox:checked').length;
                const enabled = checkedCount > 0;

                if (printPlainButton) printPlainButton.disabled = !enabled;
                if (printBrandedButton) printBrandedButton.disabled = !enabled;
                if (markAsPrintedButton) markAsPrintedButton.disabled = !enabled;

                if (selectionCounter) {
                    selectionCounter.textContent = `${checkedCount} user${checkedCount !== 1 ? 's' : ''} selected`;
                }
            }

            checkboxes.forEach(checkbox => checkbox.addEventListener('change', updateSelectionState));
            if (selectAllButton) selectAllButton.addEventListener('click', () => {
                checkboxes.forEach(c => c.checked = true);
                updateSelectionState();
            });
            if (deselectAllButton) deselectAllButton.addEventListener('click', () => {
                checkboxes.forEach(c => c.checked = false);
                updateSelectionState();
            });
            updateSelectionState();

            // Logic for "Mark as Printed" button
            if (markAsPrintedButton) {
                markAsPrintedButton.addEventListener('click', function (e) {
                    e.preventDefault();

                    const form = document.getElementById('bulk-print-form');
                    const formData = new FormData(form);
                    const token = form.querySelector('input[name="_token"]').value;
                    const button = this;

                    if (document.querySelectorAll('.bulk-checkbox:checked').length === 0) {
                        alert('Please select at least one record.');
                        return;
                    }

                    if (confirm('Are you sure you want to mark selected records as printed?')) {
                        button.disabled = true;
                        const icon = button.querySelector('i');
                        icon.classList.remove('fa-check-circle');
                        icon.classList.add('fa-spinner', 'fa-spin');

                        fetch("{{ route('certificates.mark-as-printed') }}", {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': token,
                                'Accept': 'application/json',
                            },
                            body: formData
                        })
                            .then(response => response.json().then(data => ({ok: response.ok, data})))
                            .then(({ok, data}) => {
                                if (!ok) {
                                    throw new Error(data.message || 'An unknown error occurred.');
                                }
                                alert(data.message);
                                // Refresh with current filter settings preserved
                                window.location.reload();
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('Error: ' + error.message);
                            })
                            .finally(() => {
                                button.disabled = false;
                                icon.classList.add('fa-check-circle');
                                icon.classList.remove('fa-spinner', 'fa-spin');
                            });
                    }
                });
            }

            // Logic to toggle the Training Type filter
            const certificateTypeSelect = document.getElementById('certificate_type');
            const trainingTypeContainer = document.getElementById('training-type-filter-container');

            function toggleTrainingTypeFilter() {
                if (trainingTypeContainer) {
                    const selectedType = certificateTypeSelect.value;
                    // Show if the selected type starts with 'training_'
                    trainingTypeContainer.style.display = selectedType.startsWith('training_') ? 'block' : 'none';
                }
            }

            if (certificateTypeSelect) {
                certificateTypeSelect.addEventListener('change', toggleTrainingTypeFilter);
            }

            // Call on page load to set the initial correct state
            toggleTrainingTypeFilter();

            // ── PULSE REMINDER ────────────────────────────────────────────────
            const markAsPrintedBtn = document.getElementById('mark-as-printed');
            const stopPulseBtnEl   = document.getElementById('stop-pulse-btn');
            const printPlainBtn    = document.getElementById('bulk-print-plain');
            const printBrandedBtn  = document.getElementById('bulk-print-branded');

            function startPulse() {
                if (!markAsPrintedBtn || !stopPulseBtnEl) return;
                markAsPrintedBtn.classList.add('btn-pulse-reminder');
                stopPulseBtnEl.disabled = false;
                stopPulseBtnEl.classList.remove('bg-gray-300', 'text-gray-500', 'cursor-not-allowed');
                stopPulseBtnEl.classList.add('bg-gray-700', 'hover:bg-gray-900', 'text-white', 'cursor-pointer');
            }

            window.stopPulse = function () {
                if (!markAsPrintedBtn || !stopPulseBtnEl) return;
                markAsPrintedBtn.classList.remove('btn-pulse-reminder');
                stopPulseBtnEl.disabled = true;
                stopPulseBtnEl.classList.remove('bg-gray-700', 'hover:bg-gray-900', 'text-white', 'cursor-pointer');
                stopPulseBtnEl.classList.add('bg-gray-300', 'text-gray-500', 'cursor-not-allowed');
            };

            if (printPlainBtn) {
                printPlainBtn.addEventListener('click', () => setTimeout(startPulse, 500));
            }
            if (printBrandedBtn) {
                printBrandedBtn.addEventListener('click', () => setTimeout(startPulse, 500));
            }
        });
    </script>

    <style>
        @keyframes pulse-shadow {
            0%   { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.7); }
            50%  { box-shadow: 0 0 0 10px rgba(37, 99, 235, 0.2); }
            100% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0); }
        }
        .btn-pulse-reminder {
            animation: pulse-shadow 1.4s ease-in-out infinite;
        }
    </style>
</x-layouts.admin>
