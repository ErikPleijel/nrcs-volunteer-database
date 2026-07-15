<x-layouts.admin title="Bulk ID Card Printing">
    <x-slot name="pageHeader">
        <i class="fas fa-id-card-alt mr-3"></i> Bulk ID Card Printing
    </x-slot>
    <x-slot name="subHeader">FILTER & SELECT</x-slot>

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

                    @cannot('print_idcards')
                        {{-- Prepare cards for HQ printing (non-printing branches) --}}
                        <div class="rounded-md border border-gray-200 overflow-hidden">
                            <button type="button"
                                    @click="open = open === 'prepare' ? null : 'prepare'"
                                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                <span><i class="fas fa-clipboard-check mr-2 text-indigo-400"></i>Prepare cards for printing</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                   :class="open === 'prepare' ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open === 'prepare'" x-collapse class="px-4 py-3 bg-white">
                                <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                    <li>Printing only happens at <span class="font-semibold">HQ</span> — this page is where you check whether your people's cards are <span class="font-semibold">ready</span> for it.</li>
                                    <li>A card with a <span class="font-semibold">red border</span> is missing something — usually a photo, signature, or National ID number.</li>
                                    <li>A card with a <span class="font-semibold">blue border</span> is complete and ready to print.</li>
                                    <li>Use the filters below to narrow down to your <span class="font-semibold">Division</span> or <span class="font-semibold">Red Cross Unit</span>, then fix any missing data before requesting a print run.</li>
                                    <li>Only ask HQ to print for a Division or RC Unit once <span class="font-semibold">all its cards</span> are ready — this avoids partial, repeated print requests.</li>
                                </ul>
                            </div>
                        </div>
                    @endcannot

                    {{-- Search & filter --}}
                    <div class="rounded-md border border-gray-200 overflow-hidden">
                        <button type="button"
                                @click="open = open === 'filter' ? null : 'filter'"
                                class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                            <span><i class="fas fa-filter mr-2 text-sky-400"></i>Search &amp; filter cards</span>
                            <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                               :class="open === 'filter' ? 'rotate-180' : ''"></i>
                        </button>
                        <div x-show="open === 'filter'" x-collapse class="px-4 py-3 bg-white">
                            <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                <li>Use <span class="font-semibold">Search</span> to find one specific person by name or User ID — handy if you just need to print for a single card.</li>
                                <li>Use <span class="font-semibold">Branch → Division → Red Cross Unit</span> to bulk-select an entire group at once.</li>
                                <li><span class="font-semibold">Expires In</span> narrows the list to cards expiring within a set number of months.</li>
                                <li>Tick <span class="font-semibold">Printable cards only</span> to hide anyone missing photo, signature, or National ID.</li>
                                <li>Tick <span class="font-semibold">ID paid but not printed</span> to find people who've paid for a card but don't have one yet.</li>
                            </ul>
                        </div>
                    </div>

                    @can('print_idcards')
                        {{-- Select cards --}}
                        <div class="rounded-md border border-gray-200 overflow-hidden">
                            <button type="button"
                                    @click="open = open === 'select' ? null : 'select'"
                                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                <span><i class="fas fa-check-square mr-2 text-amber-400"></i>Select cards for printing</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                   :class="open === 'select' ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open === 'select'" x-collapse class="px-4 py-3 bg-white">
                                <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                    <li>Tick the checkbox on a card to select it — only cards that are <span class="font-semibold">complete</span> (photo, signature, National ID, membership) can be selected.</li>
                                    <li>Click <span class="font-semibold">Select All</span> to select every printable card on the page, or <span class="font-semibold">Deselect All</span> to clear your selection.</li>
                                    <li>The counter at the top shows how many users are currently selected.</li>
                                </ul>
                            </div>
                        </div>

                        {{-- Set validity --}}
                        <div class="rounded-md border border-gray-200 overflow-hidden">
                            <button type="button"
                                    @click="open = open === 'validity' ? null : 'validity'"
                                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                <span><i class="fas fa-calendar-alt mr-2 text-violet-400"></i>Set the validity period</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                   :class="open === 'validity' ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open === 'validity'" x-collapse class="px-4 py-3 bg-white">
                                <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                    <li>Use <span class="font-semibold">Bulk Set Validity</span> at the top to apply the same number of months to every card at once.</li>
                                    <li>Or adjust the <span class="font-semibold">Validity (months)</span> field on an individual card to set it separately.</li>
                                    <li>The <span class="font-semibold">New ID expiry</span> date updates live as you change the validity.</li>
                                </ul>
                            </div>
                        </div>

                        {{-- Read the timeline graph --}}
                        <div class="rounded-md border border-gray-200 overflow-hidden">
                            <button type="button"
                                    @click="open = open === 'timeline' ? null : 'timeline'"
                                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                <span><i class="fas fa-chart-bar mr-2 text-emerald-400"></i>Read the timeline graph</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                   :class="open === 'timeline' ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open === 'timeline'" x-collapse class="px-4 py-3 bg-white">
                                <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                    <li>The <span class="font-semibold">top bar (Memb)</span> shows membership validity — green if valid, orange if expiring soon, red if expired.</li>
                                    <li>The <span class="font-semibold">bottom bar (ID)</span> shows the current ID card's validity in blue, or orange if it's expired.</li>
                                    <li>The dashed red line marks <span class="font-semibold">today</span>; the purple triangle marks where the new expiry will land based on your chosen validity.</li>
                                </ul>
                            </div>
                        </div>

                        {{-- Print & record --}}
                        <div class="rounded-md border border-gray-200 overflow-hidden">
                            <button type="button"
                                    @click="open = open === 'print' ? null : 'print'"
                                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                <span><i class="fas fa-print mr-2 text-green-500"></i>Print &amp; record cards</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                   :class="open === 'print' ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open === 'print'" x-collapse class="px-4 py-3 bg-white">
                                <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                    <li>Once you've selected your cards, click <span class="font-semibold">Print Selected</span> — this opens the print job in a new tab.</li>
                                    <li>🔶 <span class="font-semibold">Do not leave this page</span> after printing — you still need to confirm it worked.</li>
                                    <li>Click <span class="font-semibold">Mark as Printed</span> to record the print in the database, along with each card's new expiry date.</li>
                                    <li>Without this step, the system won't know the cards were printed, even if they physically were.</li>
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
                                    <li>If a card was <span class="font-semibold">accidentally marked as printed</span>, you can correct that mistake from this history page.</li>
                                </ul>
                            </div>
                        </div>
                    @endcan

                </div>{{-- end accordion --}}
            </div>{{-- end max-w-3xl --}}

        </x-help-popup>
    </div>

    <div class="container mx-auto px-4 py-6">
        <!-- Filters -->
        <div class="filter-container">
            <div class="filter-form-content">
                <form method="GET" action="{{ url()->current() }}" class="filter-form">

                    <div class="filter-grid filter-grid-5">
                        <!-- Column 1: Search (and empty slot to keep consistent look) -->
                        <div class="flex flex-col gap-2">
                            <div>
                                <label for="search" class="filter-label">
                                    Search
                                </label>
                                <input id="search"
                                       name="search"
                                       value="{{ request('search') }}"
                                       placeholder="Name, User ID..."
                                       class="filter-input {{ request('search') ? 'filter-active' : '' }}">
                            </div>
                        </div>

                        <!-- Column 2: Branch / Division / Unit -->
                        <div class="flex flex-col gap-2">
                            <div>
                                <label for="branch_id" class="filter-label-small">
                                    Branch
                                </label>
                                <select id="branch_id"
                                        name="branch_id"
                                        class="filter-select-small {{ $accessLevel === 'national' && request('branch_id') ? 'filter-active' : '' }}"
                                        @if($accessLevel !== 'national') disabled @endif>
                                    @if($accessLevel === 'national')
                                        <option value="">All Branches</option>
                                    @endif
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" {{ (request('branch_id') == $branch->id || $userBranchId == $branch->id) ? 'selected' : '' }}>
                                            {{ $branch->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="division_id" class="filter-label-small">
                                    Division
                                </label>
                                <select id="division_id"
                                        name="division_id"
                                        class="filter-select-small {{ !($accessLevel === 'division' || (!$userBranchId && !request('branch_id'))) && request('division_id') ? 'filter-active' : '' }}"
                                        @if($accessLevel === 'division' || (!$userBranchId && !request('branch_id'))) disabled @endif>
                                    @if($accessLevel !== 'division')
                                        <option value="">{{ ($userBranchId || request('branch_id')) ? 'All Divisions' : 'Select Branch First' }}</option>
                                    @endif
                                    @foreach($divisions as $division)
                                        <option value="{{ $division->id }}" {{ (request('division_id') == $division->id || $userDivisionId == $division->id) ? 'selected' : '' }}>
                                            {{ $division->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label for="red_cross_unit_id" class="filter-label-small">
                                    Red Cross Unit
                                </label>
                                <select id="red_cross_unit_id"
                                        name="red_cross_unit_id"
                                        class="filter-select-small {{ ($userDivisionId || request('division_id')) && request('red_cross_unit_id') ? 'filter-active' : '' }}"
                                        @if(!$userDivisionId && !request('division_id')) disabled @endif>
                                    <option value="">{{ ($userDivisionId || request('division_id')) ? 'All Units' : 'Select Division First' }}</option>
                                    @foreach($redCrossUnits as $unit)
                                        <option value="{{ $unit->id }}" {{ request('red_cross_unit_id') == $unit->id ? 'selected' : '' }}>
                                            {{ $unit->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Column 3: Other filters -->
                        <div class="flex flex-col gap-2">
                            <div>
                                <label for="expires_in_months" class="filter-label-small">
                                    Expires In
                                </label>
                                <select id="expires_in_months"
                                        name="expires_in_months"
                                        class="filter-select-small {{ request('expires_in_months') ? 'filter-active' : '' }}">
                                    <option value="">Any Time</option>
                                    @foreach(range(1, 6) as $month)
                                        <option value="{{ $month }}" {{ request('expires_in_months') == $month ? 'selected' : '' }}>
                                            {{ $month }} Month{{ $month > 1 ? 's' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="flex items-center gap-2 mt-1">
                                <input id="printable_only"
                                       name="printable_only"
                                       type="checkbox"
                                       value="1"
                                       {{ request('printable_only') ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="printable_only" class="text-sm text-gray-700 {{ request('printable_only') ? 'filter-active' : '' }}">
                                    Printable cards only
                                </label>
                            </div>

                            <div class="flex items-center gap-2 mt-1">
                                <input id="needs_id_card_printed"
                                       name="needs_id_card_printed"
                                       type="checkbox"
                                       value="1"
                                       {{ request('needs_id_card_printed') ? 'checked' : '' }}
                                       class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="needs_id_card_printed" class="text-sm text-gray-700 {{ request('needs_id_card_printed') ? 'filter-active' : '' }}">
                                    ID paid but not printed
                                </label>
                            </div>

                        </div>
                    </div>

                    <div class="filter-actions">
                        <div class="filter-button-group">
                            <button type="submit" class="filter-btn-primary">
                                <i class="fas fa-search mr-1"></i>Filter
                            </button>

                            <a href="{{ url()->current() }}"
                               class="filter-btn-secondary filter-btn-secondary-active">
                                <i class="fas fa-times mr-1"></i>Clear
                            </a>
                        </div>
                    </div>

                </form>
            </div>
        </div>


        <div class="flex justify-between items-center mb-4">
            @can('print_idcards')
                <div class="flex items-center space-x-4">
                    <button type="button" id="select-all" class="btn-bulk-select">Select All</button>
                    <button type="button" id="deselect-all" class="btn-bulk-select">Deselect All</button>
                    <span id="selection-counter" class="bulk-selection-counter">0 users selected</span>
                </div>
            @else
                <div></div>
            @endcan

            <div class="flex items-end gap-3 flex-wrap">

                @can('print_idcards')
                <div>
                    <label for="global-validity-months" class="block text-sm font-medium text-gray-700">Bulk Set Validity</label>
                    <select id="global-validity-months" class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-sm">
                        @foreach(range(12, 42) as $months)
                            <option value="{{ $months }}"{{ $months == 36 ? ' selected' : '' }}>{{ $months }} Months</option>
                        @endforeach
                    </select>
                </div>
                @endcan

                <a href="{{ route('id-cards.prints-report') }}"
                   class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded-md hover:bg-gray-50 transition">
                    <i class="fas fa-file-alt mr-2"></i> View Print History
                </a>

                @can('print_idcards')
                    <form id="print-form" method="POST" action="{{ route('id-cards.print-bulk') }}" target="_blank">
                        @csrf
                        <input type="hidden" name="user_ids" id="print-user-ids">
                        <button type="submit"
                                id="print-selected-btn"
                                class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-semibold rounded-md hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                disabled>
                            <i class="fas fa-print mr-2"></i>Print Selected
                        </button>
                    </form>

                    <div class="flex items-center gap-2">
                        <form id="record-prints-form" method="POST" action="{{ route('id-cards.record-bulk-prints') }}" onsubmit="return confirm('Mark selected as printed? This will record status and expiry dates.');">
                            @csrf
                            <input type="hidden" name="user_ids" id="record-user-ids">
                            <button type="submit"
                                    id="record-prints-btn"
                                    class="inline-flex items-center px-4 py-2 bg-purple-600 text-white text-sm font-semibold rounded-md hover:bg-purple-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                    disabled>
                                <i class="fas fa-bookmark mr-2"></i> Mark as Printed
                            </button>
                        </form>

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

        @if($users->count() > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($users as $user)
                    @php
                        $payment = $user->currentMembershipPayment;
                        $membershipType = $payment && $payment->membershipFee ? $payment->membershipFee->name : null;
                        $hasMissingData = !$user->picture || !$user->hasSignature() || !$user->national_id_number || !$membershipType || !$user->branch || !$user->division;

                        $latestIdCardPrint = $user->idCardPrints()->latest('printed_at')->first();
                        $lastPrintedDate = $latestIdCardPrint?->printed_at;
                        $idCardExpiryDate = $latestIdCardPrint?->expiry_date;

                        $isIdCardExpired = $idCardExpiryDate && $idCardExpiryDate->isPast();
                        $isIdCardValid = $idCardExpiryDate && !$idCardExpiryDate->isPast();

                        $idCardPrintDateRange = ($lastPrintedDate && $idCardExpiryDate)
                            ? $lastPrintedDate->format('M Y') . ' — ' . $idCardExpiryDate->format('M Y')
                            : '';

                        // Determine if ID card needs to be printed based on the new logic
                        $needsIdCardPrintedStatus = $user->needsIdCardPrinted();

                        // Display text for the current ID card's validity
                        $displayIdCardText = 'No ID printed'; // Default if no print history
                        $displayIdCardClass = 'text-red-500 font-bold'; // Default if no print history

                        if ($lastPrintedDate) { // Only set these if there's a print history
                            if ($isIdCardValid) {
                                $remainingIdCardMonths = (int) ceil(now()->diffInDays($idCardExpiryDate) / 30.4375);
                                $displayIdCardText = "Current ID: {$remainingIdCardMonths} mo left";
                                $displayIdCardClass = 'text-green-600 font-bold';
                            } elseif ($isIdCardExpired) {
                                $monthsSinceExpiry = (int) max(1, round(now()->diffInMonths($idCardExpiryDate, true)));
                                $displayIdCardText = "Current ID: Exp. {$monthsSinceExpiry} mo ago";
                                $displayIdCardClass = 'text-red-500';
                            }
                        }

                        $lastIdCardPaymentDate = $user->last_id_card_payment_date;
                        $lastIdCardPaymentDisplay = 'N/A';
                        if ($lastIdCardPaymentDate) {
                            $monthsAgoPayment = (int) round($lastIdCardPaymentDate->diffInMonths(now(), true));
                            $lastIdCardPaymentDisplay = $lastIdCardPaymentDate->isCurrentMonth()
                                ? 'This month.'
                                : $lastIdCardPaymentDate->format('M Y') . " ({$monthsAgoPayment} mo ago)";
                        }

                        // Membership status (combined)
                        $latestPaymentForStatus = \App\Models\MembershipPayment::where('user_id', $user->id)
                            ->where('is_deleted', false)
                            ->with('membershipFee')
                            ->latest('payment_date')
                            ->first();

                        if (!$latestPaymentForStatus) {
                            $membershipCombinedStatus = 'No membership';
                            $membershipStatusClass = 'text-red-500 font-bold';
                        } else {
                            $membershipFeeName = $latestPaymentForStatus->membershipFee->name ?? 'Unknown Membership Type';
                            $expiryDate = $latestPaymentForStatus->expiry_date;

                            if (!$expiryDate) {
                                $membershipCombinedStatus = $membershipFeeName . ' - No expiry date set';
                                $membershipStatusClass = 'text-gray-500';
                            } elseif ($expiryDate->isPast()) {
                                $monthsAgo = (int) max(1, ceil(now()->diffInDays($expiryDate, true) / 30.4375));
                                $membershipCombinedStatus = $membershipFeeName . ' - Exp. ' . $monthsAgo . ' mo ago';
                                $membershipStatusClass = 'text-red-500 font-bold';
                            } else {
                                $remainingMonths = (int) max(1, ceil(now()->diffInDays($expiryDate) / 30.4375));
                                $membershipCombinedStatus = $membershipFeeName . ' - ' . $remainingMonths . ' mo left';
                                $membershipStatusClass = $latestPaymentForStatus->expiresSoon()
                                    ? 'text-yellow-800 font-bold'
                                    : 'text-green-600 font-bold';
                            }
                        }
                    @endphp

                    <div class="user-card-container shadow border rounded-lg p-3 transition-all
                        @if($hasMissingData) border-red-500 border-2 bg-white @else border-blue-500 border-2 hover:shadow-md bg-blue-50 @endif">

                        {{-- TOP ROW: photo + signature + checkbox --}}
                        <div class="flex items-start gap-2 mb-2 relative">

                            {{-- Profile photo --}}
                            <img src="{{ $user->profile_photo_url }}" alt="Profile Photo"
                                 class="w-24 h-28 rounded-md object-cover flex-shrink-0 @if(!$user->picture) border-2 border-red-500 @endif">

                            {{-- Signature — fixed width, does not stretch --}}
                            <div class="w-32 h-28 flex-shrink-0 flex items-center justify-center border border-gray-200 rounded bg-white">
                                @if($user->hasSignature())
                                    <img src="{{ $user->getSignatureUrlAttribute() }}" alt="Signature"
                                         class="max-h-20 max-w-full object-contain">
                                @else
                                    <span class="text-red-400 text-xs text-center">No<br>signature</span>
                                @endif
                            </div>

                            {{-- View button aligned with signature --}}
                            <div class="flex-shrink-0 flex items-center h-28">
                                <a href="{{ route('users.show', $user) }}"
                                   class="btn-view"
                                   target="_blank">
                                    View<i class="fa-solid fa-up-right-from-square ml-1"></i>
                                </a>
                            </div>

                            {{-- Checkbox top-right --}}
                            @can('print_idcards')
                                @if(!$hasMissingData)
                                    <input type="checkbox"
                                           id="user-{{ $user->id }}"
                                           class="user-checkbox absolute top-0 right-0 h-5 w-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                                           value="{{ $user->id }}">
                                @endif
                            @endcan
                        </div>

                        {{-- DB reference --}}
                        <p class="text-xs text-gray-500 break-words mb-1">
                            {!! str_replace('/', '/<wbr>', $user->user_id_reference) !!}
                            @if(!$user->redCrossUnit)
                                <span class="text-red-600 font-bold ml-1">NO RED CROSS UNIT</span>
                            @endif
                        </p>

                        {{-- Two-column identity grid --}}
                        <div class="grid grid-cols-2 gap-x-2 text-xs mb-2">
                            <div class="space-y-0.5">
                                <p><span class="text-gray-500">Surname:</span> <span class="font-semibold">{{ strtoupper($user->last_name ?? '—') }}</span></p>
                                <p><span class="text-gray-500">National ID:</span> <span class="font-bold @if(!$user->national_id_number) text-red-500 @endif">{{ $user->national_id_number ?? 'MISSING' }}</span></p>
                                <p><span class="text-gray-500">Branch:</span> <span class="font-semibold">{{ strtoupper($user->branch->name ?? 'MISSING') }}</span></p>
                            </div>
                            <div class="space-y-0.5">
                                <p><span class="text-gray-500">First name:</span> <span class="font-semibold">{{ strtoupper($user->first_name ?? '—') }}</span></p>
                                <p><span class="text-gray-500">Membership:</span> <span class="font-semibold @if(!$membershipType) text-red-500 @endif">{{ $membershipType ? strtoupper($membershipType) : 'MISSING' }}</span></p>
                                <p><span class="text-gray-500">Division:</span> <span class="font-semibold">{{ strtoupper($user->division->name ?? 'MISSING') }}</span></p>
                            </div>
                        </div>

                        {{-- Timeline graphic --}}
                        <div class="timeline-container mt-3"
                             data-user-id="{{ $user->id }}"
                             data-mem-start="{{ $latestPaymentForStatus?->payment_date?->timestamp ?? '' }}"
                             data-mem-end="{{ $latestPaymentForStatus?->expiry_date?->timestamp ?? '' }}"
                             data-id-start="{{ $lastPrintedDate?->timestamp ?? '' }}"
                             data-id-end="{{ $idCardExpiryDate?->timestamp ?? '' }}"
                             data-validity="36"
                             data-show-validity="{{ $hasMissingData ? 'false' : 'true' }}">
                            <svg class="timeline-svg w-full" height="70" xmlns="http://www.w3.org/2000/svg"
                                 style="overflow:visible"></svg>
                        </div>

                        @if(!$hasMissingData)
                            <div class="mt-2 border-t border-gray-200 pt-2">
                                <div class="flex gap-4">
                                    @can('print_idcards')
                                    {{-- Left: expiry input --}}
                                    <div class="flex-shrink-0">
                                        <p class="text-xs text-gray-600">
                                            New ID expiry: <span id="expiry-display-{{ $user->id }}" class="font-semibold text-gray-800">—</span>
                                        </p>
                                        <div class="mt-1 flex items-center gap-2">
                                            <label for="validity-{{ $user->id }}" class="text-xs font-medium text-gray-700">Validity (months):</label>
                                            <input type="number"
                                                   id="validity-{{ $user->id }}"
                                                   class="user-validity-input w-16 px-2 py-1 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 text-sm"
                                                   placeholder="M" min="1" max="120"
                                                   data-user-id="{{ $user->id }}"
                                                   value="{{ old('validity-'.$user->id, 36) }}">
                                        </div>
                                    </div>
                                    @endcan

                                    {{-- Right: membership and ID validity --}}
                                    <div class="flex-1 text-xs space-y-1">
                                        @php
                                            $membValidTo = $latestPaymentForStatus?->expiry_date
                                                ? $latestPaymentForStatus->expiry_date->format('M Y')
                                                : null;
                                            $idValidTo = $idCardExpiryDate
                                                ? $idCardExpiryDate->format('M Y')
                                                : null;
                                        @endphp
                                        <p class="text-gray-600">
                                            Memb. valid to:
                                            @if($membValidTo)
                                                <span class="font-semibold {{ $latestPaymentForStatus->expiry_date->isPast() ? 'text-red-600' : 'text-green-700' }}">
                                                    {{ $membValidTo }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </p>
                                        <p class="text-gray-600">
                                            Current ID valid to:
                                            @if($idValidTo)
                                                <span class="font-semibold {{ $idCardExpiryDate->isPast() ? 'text-red-600' : 'text-indigo-700' }}">
                                                    {{ $idValidTo }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </p>
                                        <p>
                                            @if($payment && $payment->id_card_included)
                                                <span class="text-green-700">ID paid</span>
                                            @else
                                                <span class="text-red-600 font-bold text-base"><i class="fas fa-exclamation-triangle text-yellow-500 mr-1"></i>ID NOT PAID</span>
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-6">
                {{ $users->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <i class="fas fa-users text-4xl text-gray-400 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900">No Users Found</h3>
                <p class="text-sm text-gray-500">Try adjusting your filter criteria.</p>
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {

            // ── TIMELINE DRAWING ─────────────────────────────────────────────
            const WIN_BACK  = 2;   // months before today
            const WIN_TOTAL = 44;  // total months shown
            const LABEL_W   = 26;  // px reserved for labels on left

            function drawTimelines() {
                document.querySelectorAll('.timeline-container').forEach(container => {
                    drawOneTimeline(container);
                });
            }

            function drawOneTimeline(container) {
                const svg = container.querySelector('.timeline-svg');

                const now      = Date.now();
                const msPerMo  = 30.4375 * 24 * 3600 * 1000;
                const winStart = now - WIN_BACK * msPerMo;
                const winEnd   = winStart + WIN_TOTAL * msPerMo;

                const memStart  = container.dataset.memStart  ? parseInt(container.dataset.memStart)  * 1000 : null;
                const memEnd    = container.dataset.memEnd    ? parseInt(container.dataset.memEnd)    * 1000 : null;
                const idStart   = container.dataset.idStart   ? parseInt(container.dataset.idStart)   * 1000 : null;
                const idEnd     = container.dataset.idEnd     ? parseInt(container.dataset.idEnd)     * 1000 : null;
                const validity  = parseInt(container.dataset.validity || '36');

                const W      = svg.getBoundingClientRect().width || 260;
                const chartW = W - LABEL_W;

                // Y positions — extra space at bottom for triangle marker
                const axisY = 28;
                const memY  = 11;
                const idY   = 45;
                const barH  = 8;
                const triY  = 58; // triangle tip Y

                const tx     = ts => LABEL_W + ((ts - winStart) / (winEnd - winStart)) * chartW;
                const clamp  = (v, lo, hi) => Math.max(lo, Math.min(hi, v));
                const todayX = tx(now);

                // validity marker X — today + validity months
                const validityTs = now + validity * msPerMo;
                const validityX  = clamp(tx(validityTs), LABEL_W, W);
                const validityOffscreen = tx(validityTs) > W || tx(validityTs) < LABEL_W;

                let html = '';

                // ── axis line
                html += `<line x1="${LABEL_W}" y1="${axisY}" x2="${W}" y2="${axisY}" stroke="#cbd5e1" stroke-width="1.5"/>`;

                // ── quarter ticks & year labels
                const startDate = new Date(winStart);
                let tick = new Date(startDate.getFullYear(), Math.floor(startDate.getMonth() / 3) * 3, 1);
                while (tick.getTime() < winEnd) {
                    const tx2  = tx(tick.getTime());
                    if (tx2 >= LABEL_W && tx2 <= W) {
                        const isJan = tick.getMonth() === 0;
                        html += `<line x1="${tx2}" y1="${axisY - (isJan ? 6 : 4)}" x2="${tx2}" y2="${axisY + (isJan ? 6 : 4)}" stroke="#94a3b8" stroke-width="${isJan ? 1.5 : 1}"/>`;
                        if (isJan) {
                            html += `<text x="${tx2}" y="${axisY + 15}" text-anchor="middle" font-size="9" fill="#475569">${tick.getFullYear()}</text>`;
                        }
                    }
                    tick = new Date(tick.getFullYear(), tick.getMonth() + 3, 1);
                }

                // ── draw a bar helper
                function drawBar(start, end, y, color, label) {
                    if (!start || !end) {
                        html += `<text x="${LABEL_W - 3}" y="${y + 3}" text-anchor="end" font-size="9" fill="#94a3b8" font-weight="bold">${label}</text>`;
                        return;
                    }

                    let x1 = tx(start);
                    let x2 = tx(end);

                    let prefixNote = '';
                    let suffixNote = '';

                    if (x1 < LABEL_W) {
                        const mo = Math.round((winStart - start) / msPerMo);
                        prefixNote = `<text x="${LABEL_W + 2}" y="${y - barH/2 - 2}" font-size="7" fill="${color}">◀${mo}mo</text>`;
                        x1 = LABEL_W;
                    }
                    if (x2 > W) {
                        const mo = Math.round((end - winEnd) / msPerMo);
                        suffixNote = `<text x="${W - 2}" y="${y - barH/2 - 2}" text-anchor="end" font-size="7" fill="${color}">${mo}mo▶</text>`;
                        x2 = W;
                    }

                    x1 = clamp(x1, LABEL_W, W);
                    x2 = clamp(x2, LABEL_W, W);
                    const bw = Math.max(2, x2 - x1);

                    html += prefixNote + suffixNote;
                    html += `<rect x="${x1}" y="${y - barH/2}" width="${bw}" height="${barH}" rx="2" fill="${color}" opacity="0.85"/>`;
                    html += `<text x="${LABEL_W - 3}" y="${y + 3}" text-anchor="end" font-size="9" fill="#475569" font-weight="bold">${label}</text>`;
                }

                // colours
                let memColor = '#94a3b8';
                if (memStart && memEnd) {
                    memColor = memEnd < now ? '#ef4444'
                             : memEnd < now + msPerMo * 2 ? '#f59e0b'
                             : '#22c55e';
                }
                let idColor = '#94a3b8';
                if (idStart && idEnd) {
                    idColor = idEnd < now ? '#f97316' : '#6366f1';
                }

                drawBar(memStart, memEnd, memY, memColor, 'Memb');
                drawBar(idStart,  idEnd,  idY,  idColor,  'ID');

                // ── today line
                html += `<line x1="${todayX}" y1="${axisY - 20}" x2="${todayX}" y2="${axisY + 20}" stroke="#ef4444" stroke-width="1.5" stroke-dasharray="3,2"/>`;
                html += `<text x="${todayX}" y="${axisY - 22}" text-anchor="middle" font-size="8" fill="#ef4444">now</text>`;

                // ── validity triangle marker (drawn below axis, only when validity field is shown)
                const triSize = 6;
                if (container.dataset.showValidity !== 'true') { svg.innerHTML = html; return; }
                if (!validityOffscreen) {
                    const tx3 = validityX;
                    const pts = `${tx3},${triY} ${tx3 - triSize},${triY - triSize * 1.5} ${tx3 + triSize},${triY - triSize * 1.5}`;
                    html += `<polygon points="${pts}" fill="#7c3aed"/>`;
                    html += `<text x="${tx3}" y="${triY + 10}" text-anchor="middle" font-size="8" fill="#7c3aed" font-weight="bold">exp</text>`;
                } else {
                    const edgeX = tx(validityTs) > W ? W - 2 : LABEL_W + 2;
                    const arrow = tx(validityTs) > W ? '▶' : '◀';
                    const mo    = Math.abs(Math.round((validityTs - (tx(validityTs) > W ? winEnd : winStart)) / msPerMo));
                    html += `<text x="${edgeX}" y="${triY}" text-anchor="${tx(validityTs) > W ? 'end' : 'start'}" font-size="8" fill="#7c3aed">${arrow}${mo}mo</text>`;
                }

                svg.innerHTML = html;
            }

            drawTimelines();
            window.addEventListener('resize', drawTimelines);

            // ── CASCADING DROPDOWNS ───────────────────────────────────────────
            const branchSelect = document.getElementById('branch_id');
            const divisionSelect = document.getElementById('division_id');
            const redCrossUnitSelect = document.getElementById('red_cross_unit_id');

            const resetAndDisableSelect = (el, text) => {
                el.innerHTML = `<option value="">${text}</option>`;
                el.disabled = true;
            };

            branchSelect.addEventListener('change', async function () {
                const branchId = this.value;
                resetAndDisableSelect(redCrossUnitSelect, 'Select Division First');
                if (!branchId) { resetAndDisableSelect(divisionSelect, 'Select Branch First'); return; }
                divisionSelect.disabled = false;
                divisionSelect.innerHTML = '<option value="">Loading...</option>';
                try {
                    const res = await fetch(`/api/divisions/by-branch?branch_id=${branchId}`);
                    const divisions = await res.json();
                    divisionSelect.innerHTML = '<option value="">All Divisions</option>';
                    divisions.forEach(d => {
                        const opt = document.createElement('option');
                        opt.value = d.id; opt.textContent = d.name;
                        if (d.id == "{{ request('division_id') }}") opt.selected = true;
                        divisionSelect.appendChild(opt);
                    });
                } catch { resetAndDisableSelect(divisionSelect, 'Error loading divisions'); }
            });

            divisionSelect.addEventListener('change', async function () {
                const divisionId = this.value;
                if (!divisionId) { resetAndDisableSelect(redCrossUnitSelect, 'Select Division First'); return; }
                redCrossUnitSelect.disabled = false;
                redCrossUnitSelect.innerHTML = '<option value="">Loading...</option>';
                try {
                    const res = await fetch(`/red-cross-units/by-division?division_id=${divisionId}`);
                    const units = await res.json();
                    redCrossUnitSelect.innerHTML = '<option value="">All Units</option>';
                    units.forEach(u => {
                        const opt = document.createElement('option');
                        opt.value = u.id; opt.textContent = u.name;
                        if (u.id == "{{ request('red_cross_unit_id') }}") opt.selected = true;
                        redCrossUnitSelect.appendChild(opt);
                    });
                } catch { resetAndDisableSelect(redCrossUnitSelect, 'Error loading units'); }
            });

            // ── SELECTION LOGIC ───────────────────────────────────────────────
            const userCheckboxes = document.querySelectorAll('.user-checkbox');
            const selectAllBtn = document.getElementById('select-all');
            const deselectAllBtn = document.getElementById('deselect-all');
            const selectionCounter = document.getElementById('selection-counter');
            const printSelectedBtn = document.getElementById('print-selected-btn');
            const recordPrintsBtn = document.getElementById('record-prints-btn');
            const globalValiditySelect = document.getElementById('global-validity-months');
            const printUserIdsInput = document.getElementById('print-user-ids');
            const recordUserIdsInput = document.getElementById('record-user-ids');

            let selectedUsersData = new Map();

            function updateSelectionState() {
                selectedUsersData.clear();
                userCheckboxes.forEach(cb => {
                    if (cb.checked) {
                        const userId = cb.value;
                        const validityInput = document.getElementById(`validity-${userId}`);
                        selectedUsersData.set(userId, { id: userId, validity: validityInput ? validityInput.value : '' });
                    }
                });
                const arr = Array.from(selectedUsersData.values());
                const any = arr.length > 0;
                selectionCounter.textContent = `${arr.length} users selected`;
                printUserIdsInput.value = JSON.stringify(arr);
                recordUserIdsInput.value = JSON.stringify(arr);
                printSelectedBtn.disabled = !any;
                recordPrintsBtn.disabled = !any;
            }

            function updateExpiryDisplay(userId, months) {
                const display = document.getElementById(`expiry-display-${userId}`);
                if (!display) return;
                const m = parseInt(months);
                if (!m || m < 1) { display.textContent = '—'; return; }
                const d = new Date();
                d.setMonth(d.getMonth() + m);
                const monthStr = d.toLocaleString('en-GB', { month: 'short' });
                display.textContent = `${monthStr}/${d.getFullYear()}`;
            }

            function applyGlobalValidity() {
                const val = globalValiditySelect.value;
                document.querySelectorAll('.user-validity-input').forEach(input => {
                    input.value = val;
                    const userId = input.dataset.userId;
                    updateExpiryDisplay(userId, val);
                    const container = document.querySelector(`.timeline-container[data-user-id="${userId}"]`);
                    if (container) { container.dataset.validity = val; drawOneTimeline(container); }
                    const checkbox = document.getElementById(`user-${userId}`);
                    if (checkbox?.checked) selectedUsersData.set(userId, { id: userId, validity: val });
                });
                updateSelectionState();
            }

            document.querySelectorAll('.user-validity-input').forEach(input => {
                updateExpiryDisplay(input.dataset.userId, input.value);
            });

            userCheckboxes.forEach(cb => cb.addEventListener('change', updateSelectionState));

            document.querySelectorAll('.user-validity-input').forEach(input => {
                input.addEventListener('input', function () {
                    const userId = this.dataset.userId;
                    updateExpiryDisplay(userId, this.value);
                    // update timeline marker
                    const container = document.querySelector(`.timeline-container[data-user-id="${userId}"]`);
                    if (container) { container.dataset.validity = this.value; drawOneTimeline(container); }
                    const checkbox = document.getElementById(`user-${userId}`);
                    if (checkbox?.checked) selectedUsersData.set(userId, { id: userId, validity: this.value });
                    updateSelectionState();
                });
            });

            // ── PULSE REMINDER ────────────────────────────────────────────────
            const recordPrintsBtnEl = document.getElementById('record-prints-btn');
            const stopPulseBtnEl    = document.getElementById('stop-pulse-btn');
            const printForm         = document.getElementById('print-form');

            function startPulse() {
                if (!recordPrintsBtnEl || !stopPulseBtnEl) return;
                recordPrintsBtnEl.classList.add('btn-pulse-reminder');
                stopPulseBtnEl.disabled = false;
                stopPulseBtnEl.classList.remove('bg-gray-300', 'text-gray-500', 'cursor-not-allowed');
                stopPulseBtnEl.classList.add('bg-gray-700', 'hover:bg-gray-900', 'text-white', 'cursor-pointer');
            }

            window.stopPulse = function () {
                if (!recordPrintsBtnEl || !stopPulseBtnEl) return;
                recordPrintsBtnEl.classList.remove('btn-pulse-reminder');
                stopPulseBtnEl.disabled = true;
                stopPulseBtnEl.classList.remove('bg-gray-700', 'hover:bg-gray-900', 'text-white', 'cursor-pointer');
                stopPulseBtnEl.classList.add('bg-gray-300', 'text-gray-500', 'cursor-not-allowed');
            };

            if (printForm) {
                printForm.addEventListener('submit', () => {
                    setTimeout(startPulse, 500);
                });
            }

            if (selectAllBtn) {
                selectAllBtn.addEventListener('click', () => {
                    userCheckboxes.forEach(cb => {
                        const card = cb.closest('.user-card-container');
                        if (card && !card.classList.contains('border-red-500')) cb.checked = true;
                    });
                    applyGlobalValidity();
                    updateSelectionState();
                });

                deselectAllBtn.addEventListener('click', () => {
                    userCheckboxes.forEach(cb => (cb.checked = false));
                    updateSelectionState();
                });

                globalValiditySelect.addEventListener('change', applyGlobalValidity);

                if (globalValiditySelect.value) {
                    document.querySelectorAll('.user-validity-input').forEach(i => (i.value = globalValiditySelect.value));
                }
                updateSelectionState();
            }
        });
    </script>

    <style>
        @keyframes pulse-shadow {
            0%   { box-shadow: 0 0 0 0 rgba(147, 51, 234, 0.7); }
            50%  { box-shadow: 0 0 0 10px rgba(147, 51, 234, 0.2); }
            100% { box-shadow: 0 0 0 0 rgba(147, 51, 234, 0); }
        }
        .btn-pulse-reminder {
            animation: pulse-shadow 1.4s ease-in-out infinite;
        }
    </style>
</x-layouts.admin>
