<x-layouts.admin title="Organisation Certificates">
    <x-slot name="pageHeader">
        <div class="flex items-center">
            <i class="fas fa-certificate mr-2"></i>
            <span>Certificates</span>
        </div>
    </x-slot>

    <x-slot name="subHeader">
        Organisation Certificate Generator
    </x-slot>

    <x-slot name="button1">
        <a href="{{ route('certificates.index') }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-user mr-1"></i>Certificates for persons
        </a>
    </x-slot>

    <div class="container mx-auto px-4 py-6">
        <!-- Filter Form -->
        <div class="filter-container">
            <div class="filter-form-content">
                <form action="{{ route('organisations.certificates.index') }}" method="GET" id="filter-form" class="filter-form">

                    <div class="filter-grid filter-grid-4">
                        <!-- Column 1: Certificate Type + Search -->
                        <div class="flex flex-col gap-2">
                            <div>
                                <label for="certificate_type" class="filter-label">Certificate Type</label>
                                <select name="certificate_type" id="certificate_type" class="filter-select">
                                    @foreach($certificateTypes as $typeValue => $typeLabel)
                                        <option value="{{ $typeValue }}" @selected($certificateType === $typeValue)>
                                            {{ $typeLabel }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="search" class="filter-label">Search Organisation</label>
                                <input type="text"
                                       name="search"
                                       id="search"
                                       class="filter-input"
                                       value="{{ request('search') }}"
                                       placeholder="Organisation name or ID...">
                            </div>
                        </div>

                        <!-- Column 2: Branch -->
                        <div>
                            <label for="branch_id" class="filter-label-small">Branch</label>
                            <select name="branch_id"
                                    id="branch_id"
                                    class="filter-select disabled:bg-gray-200 disabled:opacity-75"
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
                    </div>

                    <div class="filter-actions">
                        <div class="filter-button-group">
                            <button type="submit" class="filter-btn-primary">
                                <i class="fas fa-search mr-1"></i>Filter
                            </button>
                            <a href="{{ route('organisations.certificates.index') }}"
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
                <p class="text-gray-500 text-lg">No organisations found matching your criteria.</p>
            </div>
        @else
            <form id="bulk-print-form"
                  action="{{ route('organisations.certificates.print.plain') }}"
                  method="POST"
                  target="_blank">
                @csrf

                <input type="hidden" name="certificate_type" value="{{ $certificateType }}">

                <div class="flex flex-col md:flex-row md:items-start md:justify-between mb-4 gap-4">
                    <div class="flex items-center flex-wrap gap-3">


                        <button type="button"
                                id="select-all"
                                class="inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Select All
                        </button>

                        <button type="button"
                                id="deselect-all"
                                class="inline-flex items-center px-3 py-1 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Deselect All
                        </button>

                        <span id="selection-counter" class="text-sm font-medium text-gray-700">
                            0 organisations selected
                        </span>
                    </div>

                    <div class="flex flex-col gap-3 md:items-end">
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
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="flex flex-wrap gap-2 justify-end mt-2">
                            <a href="{{ route('certificates.prints-report') }}?certificate_type={{ $certificateType }}"
                               class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-md hover:bg-gray-50 transition">
                                <i class="fas fa-file-alt mr-1"></i> View Print History
                            </a>

                            <button
                                id="bulk-print-plain"
                                type="submit"
                                class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-semibold rounded-md hover:bg-gray-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                disabled
                                formaction="{{ route('organisations.certificates.print.plain') }}"
                            >
                                <i class="fas fa-print mr-2"></i>
                                Print for pre-printed paper
                            </button>

                            <button
                                id="bulk-print-branded"
                                type="submit"
                                class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-md hover:bg-red-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                disabled
                                formaction="{{ route('organisations.certificates.print.branded') }}"
                            >
                                <i class="fas fa-certificate mr-2"></i>
                                Print with logo & frame
                            </button>

                            <button
                                id="mark-as-printed"
                                type="button"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-md hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                disabled
                            >
                                <i class="fas fa-check-circle mr-2"></i>
                                Mark as Printed
                            </button>
                        </div>
                    </div>
                </div>

                <h2 class="text-2xl font-semibold text-gray-800 mb-6">
                    {{ $certificateTypes[$certificateType] ?? 'Records' }}
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    @foreach($records as $organisation)
                        <div class="bg-white rounded-lg shadow-lg overflow-hidden relative">
                            <div class="p-5">
                                <div class="absolute top-4 right-4">
                                    <input type="checkbox"
                                           name="training_ids[]"
                                           value="{{ $organisation->id }}"
                                           class="h-5 w-5 text-red-600 border-gray-300 rounded focus:ring-red-500 bulk-checkbox">
                                </div>

                                <div class="font-bold text-lg text-gray-800 pr-8">
                                    {{ $organisation->name }}
                                </div>
                                <p class="text-xs text-gray-500 break-words mb-2">
                                    {{ $organisation->branch->name ?? '—' }}
                                </p>

                                @switch($certificateType)
                                    @case('membership')
                                        @if($organisation->activeMembership)
                                            <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-0.5 rounded-full font-medium mb-1">
                                                Active Member
                                            </span>
                                            <p class="text-gray-700 text-base">
                                                <span class="font-semibold">Fee:</span>
                                                {{ $organisation->activeMembership->membershipFee->name ?? 'N/A' }}
                                            </p>
                                            <p class="text-gray-600 text-sm">
                                                <span class="font-semibold">Expires:</span>
                                                {{ $organisation->activeMembership->expiry_date?->format('M d, Y') ?? 'N/A' }}
                                            </p>
                                        @endif
                                        @break

                                    @case('donation')
                                        <p class="text-gray-700 text-base">
                                            <span class="font-semibold">Cash Donated:</span>
                                            ₦{{ number_format($organisation->donations_sum_amount ?? 0, 0) }}
                                        </p>
                                        @if(($organisation->in_kind_donations_count ?? 0) > 0)
                                            <p class="text-gray-600 text-sm">
                                                <span class="font-semibold">In-Kind:</span>
                                                {{ $organisation->in_kind_donations_count }}
                                                {{ $organisation->in_kind_donations_count === 1 ? 'item' : 'items' }}
                                            </p>
                                        @endif
                                        @break
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
            const checkboxes         = document.querySelectorAll('.bulk-checkbox');
            const printPlainButton   = document.getElementById('bulk-print-plain');
            const printBrandedButton = document.getElementById('bulk-print-branded');
            const markAsPrintedButton = document.getElementById('mark-as-printed');
            const selectAllButton    = document.getElementById('select-all');
            const deselectAllButton  = document.getElementById('deselect-all');
            const selectionCounter   = document.getElementById('selection-counter');

            function updateSelectionState() {
                const checkedCount = document.querySelectorAll('.bulk-checkbox:checked').length;
                const enabled = checkedCount > 0;

                if (printPlainButton)    printPlainButton.disabled    = !enabled;
                if (printBrandedButton)  printBrandedButton.disabled  = !enabled;
                if (markAsPrintedButton) markAsPrintedButton.disabled = !enabled;

                if (selectionCounter) {
                    selectionCounter.textContent = `${checkedCount} organisation${checkedCount !== 1 ? 's' : ''} selected`;
                }
            }

            checkboxes.forEach(cb => cb.addEventListener('change', updateSelectionState));

            if (selectAllButton) selectAllButton.addEventListener('click', () => {
                checkboxes.forEach(c => c.checked = true);
                updateSelectionState();
            });

            if (deselectAllButton) deselectAllButton.addEventListener('click', () => {
                checkboxes.forEach(c => c.checked = false);
                updateSelectionState();
            });

            updateSelectionState();

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
                                document.querySelectorAll('.bulk-checkbox').forEach(c => c.checked = false);
                                updateSelectionState();
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
        });
    </script>
</x-layouts.admin>
