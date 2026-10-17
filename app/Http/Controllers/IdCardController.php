<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Division;
use App\Models\IdCardPrint;
use App\Models\IdCardPrintRequest;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\RedCrossUnit;
use App\Models\Setting;
use App\Models\User;
use App\Support\ImageRejection;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class IdCardController extends Controller
{
    /**
     * Show the form for preparing bulk ID card printing.
     *
     * @return \Illuminate\View\View
     */
    public function showBulkPrintForm(Request $request)
    {
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();

        $userBranchId = null;
        $userDivisionId = null;
        $userDivision = null;

        if ($accessLevel === 'branch') {
            $userBranchId = $scopedId;
        } elseif ($accessLevel === 'division') {
            $userDivision = Division::find($scopedId);
            if ($userDivision) {
                $userDivisionId = $scopedId;
                $userBranchId = $userDivision->branch_id;
            }
        }

        $query = User::query()->selectableForEntry();

        switch ($accessLevel) {
            case 'branch':
                if ($userBranchId) {
                    $query->where('branch_id', $userBranchId);
                }
                break;
            case 'division':
                if ($userDivisionId) {
                    $query->where('division_id', $userDivisionId);
                }
                break;
        }

        // Subquery for the latest payment date to be used for sorting and filtering.
        $latestPaymentSubquery = \App\Models\MembershipPayment::select('payment_date')
            ->whereColumn('users.id', 'user_id')
            ->where('is_deleted', false)
            ->where('id_card_included', true) // Only consider payments that are for an ID card
            ->personal() // Exclude organisation-attributed payments — this is a personal ID card
            ->latest('payment_date')
            ->limit(1);

        // Add the latest payment date as a computed column to the main query.
        $query->addSelect(['*'])
            ->addSelect(['last_id_card_payment_date' => $latestPaymentSubquery]);

        // Apply standard text and dropdown filters.
        if ($request->filled('search')) {
            $searchTerm = $request->input('search');
            $likeSearchTerm = '%'.$searchTerm.'%';
            $numericSearchTerm = str_replace('%', '', $searchTerm);

            $query->where(function ($q) use ($likeSearchTerm, $numericSearchTerm) {
                $q->where('first_name', 'like', $likeSearchTerm)
                    ->orWhere('last_name', 'like', $likeSearchTerm);
                if (is_numeric($numericSearchTerm)) {
                    $q->orWhere('id', $numericSearchTerm);
                }
            });
        }

        if ($accessLevel === 'national' && $request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        if (in_array($accessLevel, ['national', 'branch']) && $request->filled('division_id')) {
            $query->where('division_id', $request->input('division_id'));
        }

        if ($request->filled('red_cross_unit_id')) {
            $query->where('red_cross_unit_id', $request->input('red_cross_unit_id'));
        }

        // Filter: only users with all required data for printing and no
        // rejected photo/signature (see User::scopeIdCardPrintable()).
        if ($request->boolean('printable_only')) {
            $query->idCardPrintable();
        }

        // Filter: cards branch/division staff marked "ready to print".
        if ($request->boolean('ready_to_print')) {
            $query->whereHas('pendingIdCardPrintRequest');
        }

        // Filter: a rejected photo or signature awaiting a new upload.
        if ($request->boolean('has_rejection')) {
            $query->withImageRejection();
        }

        // Filter: card validity in years, the same value the card's validity
        // box defaults to (User::defaultIdCardValidityMonths()).
        $validityOptions = $this->cardValidityOptions();
        $cardValidity = (int) $request->input('card_validity');
        if (array_key_exists($cardValidity, $validityOptions)) {
            $this->whereCardValidityYears($query, $cardValidity);
        }

        if ($request->filled('expires_in_months')) {
            $months = (int) $request->input('expires_in_months');
            if ($months > 0) {
                $startDate = Carbon::now()->startOfDay();
                $endDate = Carbon::now()->endOfDay()->addMonths($months);

                $query->whereHas('idCardPrints', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('expiry_date', [$startDate, $endDate])
                        ->whereRaw('id = (SELECT MAX(id) FROM id_card_prints WHERE user_id = users.id)');
                });
            }
        }

        // Apply the 'needs_id_card_printed' filter using robust subqueries.
        if ($request->boolean('needs_id_card_printed')) {
            $query->where(function ($q) {
                // Case 1: User has paid for a card but has never had one printed.
                $q->whereHas('membershipPayments', function ($paymentQuery) {
                    $paymentQuery->where('id_card_included', true)->where('is_deleted', false)->personal();
                })->whereDoesntHave('idCardPrints');

                // Case 2: User's latest ID card payment is more recent than their latest print.
                $q->orWhere(function ($orQ) {
                    $orQ->whereHas('membershipPayments', function ($paymentQuery) {
                        $paymentQuery->where('id_card_included', true)->where('is_deleted', false)->personal();
                    })
                        ->whereHas('idCardPrints') // Must have at least one print to compare against.
                        ->where( // This is the fix. Replaced `whereColumn` with `where`.
                            // Subquery for the latest payment date.
                            \App\Models\MembershipPayment::select('payment_date')
                                ->whereColumn('users.id', 'user_id')
                                ->where('id_card_included', true)
                                ->where('is_deleted', false)
                                ->personal()
                                ->latest('payment_date')
                                ->limit(1),
                            '>',
                            // Subquery for the latest print date.
                            \App\Models\IdCardPrint::select('printed_at')
                                ->whereColumn('users.id', 'user_id')
                                ->latest('printed_at')
                                ->limit(1)
                        );
                });
            });
        }

        // Eager load relationships for the view.
        // membershipPayments (newest payment first) feeds the card's membership
        // status line, so the view needs no query per card.
        $query->with([
            'branch', 'division', 'redCrossUnit', 'idCardPrints',
            'currentMembershipPayment' => fn ($q) => $q->personal(),
            'currentMembershipPayment.membershipFee',
            'membershipPayments' => fn ($q) => $q->where('is_deleted', false)->latest('payment_date'),
            'membershipPayments.membershipFee',
            'pendingIdCardPrintRequest.requestedBy',
            'pictureRejectedBy', 'signatureRejectedBy',
        ]);

        // Apply sorting. When filtering for "needs print", sort by the latest payment date as a proxy for urgency.
        if ($request->boolean('needs_id_card_printed')) {
            $query->orderBy('last_id_card_payment_date', 'desc');
        } elseif ($request->boolean('ready_to_print')) {
            // Oldest mark first, so the printers work through the queue in order.
            $query->orderBy(
                IdCardPrintRequest::select('requested_at')
                    ->whereColumn('id_card_print_requests.user_id', 'users.id')
                    ->where('status', IdCardPrintRequest::STATUS_PENDING)
                    ->limit(1)
            );
        } else {
            $query->latest(); // Default sort by user creation date.
        }

        $users = $query->paginate(24)->withQueryString();

        // Data for dropdowns, respecting access levels
        $branches = collect();
        $divisions = collect();
        $redCrossUnits = collect();

        switch ($accessLevel) {
            case 'national':
                $branches = Branch::orderBy('name')->get();
                if ($request->filled('branch_id')) {
                    $divisions = Division::where('branch_id', $request->branch_id)->orderBy('name')->get();
                }
                break;
            case 'branch':
                if ($userBranchId) {
                    $branches = Branch::where('id', $userBranchId)->orderBy('name')->get();
                    $divisions = Division::where('branch_id', $userBranchId)->orderBy('name')->get();
                }
                break;
            case 'division':
                if ($userDivision) {
                    $branches = Branch::where('id', $userDivision->branch_id)->orderBy('name')->get();
                    $divisions = Division::where('id', $userDivisionId)->orderBy('name')->get();
                }
                break;
        }

        $selectedDivisionId = $request->input('division_id', $userDivisionId);
        if ($selectedDivisionId) {
            if (! ($accessLevel === 'division' && $userDivisionId && (string) $userDivisionId !== (string) $selectedDivisionId)) {
                $redCrossUnits = RedCrossUnit::where('division_id', $selectedDivisionId)->orderBy('name')->get();
            }
        }

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        $rejectionReasons = ImageRejection::REASONS;

        return view('id-cards.prepare-bulk-print', compact('users', 'branches', 'divisions', 'redCrossUnits', 'accessLevel', 'userBranchId', 'userDivisionId', 'rejectionReasons', 'validityOptions'));
    }

    /**
     * "Card validity" filter options, years => label ("1 year", "3 years"):
     * the distinct validity_years of fees that carry an ID card fee (inactive
     * fees too — old payments still use them), plus 1 year, the default for
     * someone with no current payment. A fee with a new duration shows up
     * here automatically. validity_years is whole years, so no months.
     *
     * @return array<int, string>
     */
    private function cardValidityOptions(): array
    {
        $years = MembershipFee::where('id_card_fee', '>', 0)
            ->distinct()
            ->pluck('validity_years')
            ->map(fn ($y) => (int) $y)
            ->filter(fn (int $y) => $y > 0)
            ->push(1)
            ->unique()
            ->sort()
            ->values();

        return $years->mapWithKeys(fn (int $y) => [$y => $y === 1 ? '1 year' : "{$y} years"])->all();
    }

    /**
     * Limit to users whose card validity is $years — in SQL, mirroring
     * User::defaultIdCardValidityMonths(): the fee of the current personal
     * payment (approved, not deleted, not expired; latest expiry, then
     * highest id — the currentMembershipPayment order), validity_years;
     * 1 year when there is no such payment, or its fee is missing or 0.
     */
    private function whereCardValidityYears($query, int $years): void
    {
        $currentFeeYears = MembershipPayment::query() // ApprovedScope applies
            ->personal()
            ->leftJoin('membership_fees', 'membership_fees.id', '=', 'membership_payments.membership_fee_id')
            ->whereColumn('membership_payments.user_id', 'users.id')
            ->where('membership_payments.is_deleted', false)
            ->where('membership_payments.expiry_date', '>=', now()->toDateString())
            ->orderByDesc('membership_payments.expiry_date')
            ->orderByDesc('membership_payments.id')
            ->limit(1)
            ->select(DB::raw('NULLIF(membership_fees.validity_years, 0)'));

        $query->whereRaw(
            'COALESCE(('.$currentFeeYears->toSql().'), 1) = ?',
            [...$currentFeeYears->getBindings(), $years]
        );
    }

    /**
     * Prepare user data and display the ID card print view.
     *
     * @return \Illuminate\View\View
     */
    public function printCard(User $user)
    {
        abort_if($user->hasImageRejection(), 422, 'This card cannot be printed: the photo or signature needs to be uploaded again.');

        // Eager load relationships for efficiency
        $user->load([
            'branch', 'division', 'redCrossUnit',
            'currentMembershipPayment' => fn ($q) => $q->personal(),
            'currentMembershipPayment.membershipFee',
        ]);

        $payment = $user->currentMembershipPayment;

        // Single, isolated member/volunteer predicate — today's only available
        // signal (red_cross_unit_id IS NOT NULL, via User::isVolunteer()). If the
        // in-progress volunteer/member redefinition introduces an independent
        // is_volunteer column, swap this one line for the new check.
        $isVolunteer = $user->isVolunteer();

        // The verification URL for the QR code and text
        $verificationUrl = url("/idcheck/{$user->id_check_token}");

        // Prepare the data array for the blade view
        $data = [
            'dbcode' => $user->user_id_reference_short,
            'full_name' => $user->full_name,
            'national_id_number' => $user->national_id_number ?? 'N/A',
            'is_volunteer' => $isVolunteer,
            'card_type_label' => $isVolunteer ? 'VOLUNTEER IDENTITY CARD' : 'MEMBERSHIP IDENTITY CARD',
            'category_value' => $isVolunteer
                ? ($user->redCrossUnit->name ?? 'N/A')
                : ($payment && $payment->membershipFee ? $payment->membershipFee->name : 'Member'),
            'category_label' => $isVolunteer ? 'Red Cross unit' : 'Memb. category',
            'branch' => $user->branch ? $user->branch->name : 'N/A',
            'division' => $user->division ? $user->division->name : 'N/A',
            'expdate' => $payment ? Carbon::parse($payment->expiry_date)->format('M Y') : 'N/A',
            'picture' => $user->profile_photo_url,
            'signature' => $user->signature_url,
            'producer_code' => Auth::check() ? 'ID-'.Auth::id() : 'SYSTEM',
            'verification_url' => $verificationUrl,
            'qr_image' => 'data:image/svg+xml;base64,'.base64_encode(QrCode::format('svg')->size(200)->generate($verificationUrl)),
            'img_bg' => asset('images/id-card/IDbackground.JPG'),
            'img_logo' => asset('images/id-card/NRCS_logo.jpg'),
            'img_sg_signature' => asset('images/id-card/sg-signature.png'),
            'hq_address' => Setting::get('site.hq_address', 'National Headquarters Plot 589 T.O.S Benson Crescent Off Ngozi Okonjo Iweala, Utako District, Abuja.'),
        ];

        return view('id-cards.print', ['cards' => [$data]]);

    }

    /**
     * Prepare data for multiple users and display the bulk ID card print view.
     *
     * @return \Illuminate\View\View
     */
    public function printBulkCards(Request $request)
    {
        $selectedUsersData = json_decode($request->input('user_ids'), true);

        if (! is_array($selectedUsersData) || empty($selectedUsersData)) {
            return redirect()->back()->with('error', 'No users selected for printing or invalid selection data.');
        }

        $userIds = array_column($selectedUsersData, 'id');
        $validityMonthsMap = array_column($selectedUsersData, 'validity', 'id'); // Map user_id to validity

        $users = User::with([
            'branch', 'division', 'redCrossUnit',
            'currentMembershipPayment' => fn ($q) => $q->personal(),
            'currentMembershipPayment.membershipFee',
        ])->whereIn('id', $userIds)
            // A rejected photo or signature blocks printing (the page hides its
            // tick box; this covers a stale page or a hand-made request).
            ->whereNull('picture_rejected_at')
            ->whereNull('signature_rejected_at')
            ->get();

        if ($users->isEmpty()) {
            return redirect()->back()->with('error', 'None of the selected cards can be printed: their photo or signature needs to be uploaded again.');
        }

        $cardsData = [];
        foreach ($users as $user) {
            $payment = $user->currentMembershipPayment;
            $verificationUrl = url("/idcheck/{$user->id_check_token}");

            // Single, isolated member/volunteer predicate — see the matching
            // comment in printCard(); keep both in sync until the volunteer/
            // member redefinition work replaces this signal.
            $isVolunteer = $user->isVolunteer();

            $validityMonthsForUser = $this->resolveValidityMonths($user, $validityMonthsMap[$user->id] ?? null);
            $expdate = Carbon::now()->addMonths($validityMonthsForUser)->format('M Y');

            $cardsData[] = [
                'dbcode' => $user->user_id_reference_short,
                'full_name' => $user->full_name,
                'national_id_number' => $user->national_id_number ?? 'N/A',
                'is_volunteer' => $isVolunteer,
                'card_type_label' => $isVolunteer ? 'VOLUNTEER IDENTITY CARD' : 'MEMBERSHIP IDENTITY CARD',
                'category_value' => $isVolunteer
                    ? ($user->redCrossUnit->name ?? 'N/A')
                    : ($payment && $payment->membershipFee ? $payment->membershipFee->name : 'Member'),
                'category_label' => $isVolunteer ? 'Red Cross unit' : 'Memb. category',
                'branch' => $user->branch ? $user->branch->name : 'N/A',
                'division' => $user->division ? $user->division->name : 'N/A',
                'expdate' => $expdate,
                'picture' => $user->profile_photo_url,
                'signature' => $user->signature_url,
                'producer_code' => Auth::check() ? 'ID-'.Auth::id() : 'SYSTEM',
                'verification_url' => $verificationUrl,
                'qr_image' => 'data:image/svg+xml;base64,'.base64_encode(QrCode::format('svg')->size(200)->generate($verificationUrl)),
                'img_bg' => asset('images/id-card/IDbackground.JPG'),
                'img_logo' => asset('images/id-card/NRCS_logo.jpg'),
                'img_sg_signature' => asset('images/id-card/sg-signature.png'),
                'hq_address' => Setting::get('site.hq_address', 'National Headquarters Plot 589 T.O.S Benson Crescent Off Ngozi Okonjo Iweala, Utako District, Abuja.'),
            ];
        }

        return view('id-cards.print', ['cards' => $cardsData]);
    }

    /**
     * Record a bulk ID card print event for selected users.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function recordBulkIdCardPrints(Request $request)
    {
        // Authorize the action using a gate or policy
        if (! Auth::user()->can('print_idcards')) {
            abort(403, 'Unauthorized action.');
        }

        $selectedUsersData = json_decode($request->input('user_ids'), true);

        if (! is_array($selectedUsersData) || empty($selectedUsersData)) {
            return redirect()->back()->with('error', 'No users selected to mark as printed or invalid selection data.');
        }

        $userIds = array_column($selectedUsersData, 'id');
        $validityMonthsMap = array_column($selectedUsersData, 'validity', 'id'); // Map user_id to validity

        $printedBy = Auth::id();
        $printedAt = Carbon::now();
        $skippedRejected = 0;

        foreach ($userIds as $userId) {
            $user = User::with([
                'currentMembershipPayment' => fn ($q) => $q->personal(),
                'currentMembershipPayment.membershipFee',
            ])->find($userId);

            if (! $user) {
                // Skip if user not found, or log an error
                continue;
            }

            // A rejected photo or signature blocks printing.
            if ($user->hasImageRejection()) {
                $skippedRejected++;

                continue;
            }

            $validityMonthsToStore = $this->resolveValidityMonths($user, $validityMonthsMap[$user->id] ?? null);
            $expiryDate = $printedAt->copy()->addMonths($validityMonthsToStore);

            // Create the IdCardPrint record
            $print = IdCardPrint::create([
                'user_id' => $userId,
                'printed_by_user_id' => $printedBy,
                'printed_at' => $printedAt,
                'status' => 'printed', // As requested
                'validity_months' => $validityMonthsToStore,
                'expiry_date' => $expiryDate,
                'notes' => null, // As requested
            ]);

            // A "ready to print" mark is done once the card is printed.
            $user->idCardPrintRequests()->pending()->update([
                'status' => IdCardPrintRequest::STATUS_PRINTED,
                'id_card_print_id' => $print->id,
                'closed_by_user_id' => $printedBy,
                'closed_at' => $printedAt,
                'updated_at' => $printedAt,
            ]);
        }

        $message = 'Selected ID cards marked as printed successfully and records saved.';
        if ($skippedRejected > 0) {
            $message .= " {$skippedRejected} skipped: photo or signature needs to be uploaded again.";
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Reject a user's photo or signature ("upload again"), with a pick-list
     * reason and an optional note (required for "Other"). Blocks printing and
     * cancels an open "ready to print" mark. The image itself is kept.
     * Cleared by User::booted() when a new image is saved, or via
     * restoreImage(). Not written to the Audit Log (Decisions.md, 2026-10-02).
     */
    public function rejectImage(Request $request, User $user, string $type): JsonResponse|RedirectResponse
    {
        $this->authorizeWithinScope($user);

        $column = ImageRejection::column($type);
        $label = $type === 'photo' ? 'photo' : 'signature';
        abort_if(blank($user->{$column}), 422, "This user has no {$label} to reject.");

        $validated = $request->validate([
            'reason' => ['required', Rule::in(array_keys(ImageRejection::reasons($type)))],
            'note' => [
                Rule::requiredIf($request->input('reason') === ImageRejection::OTHER),
                'nullable', 'string', 'max:'.ImageRejection::NOTE_MAX,
            ],
        ], [
            'note.required' => 'Please describe the problem when you choose "Other".',
        ]);

        $user->{$column.'_rejected_at'} = now();
        $user->{$column.'_rejected_by_id'} = Auth::id();
        $user->{$column.'_rejection_reason'} = $validated['reason'];
        $user->{$column.'_rejection_note'} = filled($validated['note'] ?? null) ? trim($validated['note']) : null;
        $user->save();

        $cancelledMarks = $user->cancelPendingIdCardPrintRequests(IdCardPrintRequest::CANCEL_IMAGE_REJECTED, Auth::id());

        $message = "{$user->full_name}: {$label} marked \"upload again\".";

        if ($request->expectsJson()) {
            return response()->json([
                'rejected' => true,
                'type' => $type,
                'reason' => ImageRejection::describe($user, $type),
                'rejected_at' => $user->{$column.'_rejected_at'}->format('d M Y'),
                'rejected_by' => Auth::user()->full_name,
                'ready_mark_cancelled' => $cancelledMarks > 0,
                'message' => $message,
                ...$this->reviewCardState($user, $type),
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Undo a photo or signature rejection (a mis-click, or a printer who
     * disagrees). A cancelled "ready to print" mark is not restored.
     */
    public function restoreImage(Request $request, User $user, string $type): JsonResponse|RedirectResponse
    {
        $this->authorizeWithinScope($user);

        $user->clearImageRejection(ImageRejection::column($type));
        $user->save();

        $label = $type === 'photo' ? 'Photo' : 'Signature';
        $message = "{$user->full_name}: {$label} rejection undone.";

        if ($request->expectsJson()) {
            return response()->json([
                'rejected' => false,
                'type' => $type,
                'message' => $message,
                ...$this->reviewCardState($user, $type),
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * What the bulk print page needs to update one card in place after a
     * reject/undo: both images' rejection state and the re-rendered review
     * box (the same partial the page renders) for the image just changed.
     *
     * @return array{photo_rejected: bool, signature_rejected: bool, html: string}
     */
    private function reviewCardState(User $user, string $type): array
    {
        $user->load('pictureRejectedBy', 'signatureRejectedBy');

        return [
            'photo_rejected' => $user->needsPictureReupload(),
            'signature_rejected' => $user->needsSignatureReupload(),
            'html' => view('id-cards.partials.image-review', ['user' => $user, 'type' => $type])->render(),
        ];
    }

    /**
     * "Mark as ready to print" for staff who cannot print. Takes the same
     * user_ids JSON as Mark as Printed. Only complete, unrejected cards inside
     * the actor's branch/division are marked; already-marked ones are left.
     */
    public function markReadyToPrint(Request $request): RedirectResponse
    {
        $selected = json_decode((string) $request->input('user_ids'), true);
        $userIds = is_array($selected) ? array_filter(array_map('intval', array_column($selected, 'id'))) : [];

        if (empty($userIds)) {
            return redirect()->back()->with('error', 'No cards selected to mark as ready to print.');
        }

        $eligible = $this->scopedToActor(User::query())
            ->whereIn('users.id', $userIds)
            ->idCardPrintable()
            ->whereDoesntHave('pendingIdCardPrintRequest')
            ->pluck('users.id');

        $now = now();
        foreach ($eligible as $userId) {
            IdCardPrintRequest::create([
                'user_id' => $userId,
                'requested_by_user_id' => Auth::id(),
                'requested_at' => $now,
                'status' => IdCardPrintRequest::STATUS_PENDING,
            ]);
        }

        $marked = $eligible->count();
        $skipped = count(array_unique($userIds)) - $marked;
        $message = $marked === 1 ? '1 card marked as ready to print.' : "{$marked} cards marked as ready to print.";
        if ($skipped > 0) {
            $message .= " {$skipped} skipped (already marked, incomplete, or photo/signature needs to be uploaded again).";
        }

        return redirect()->back()->with($marked > 0 ? 'success' : 'error', $message);
    }

    /**
     * Remove a "ready to print" mark. Allowed for anyone who can mark cards
     * and for the printers, inside their own branch/division.
     */
    public function unmarkReadyToPrint(User $user): RedirectResponse
    {
        $actor = Auth::user();
        abort_unless($actor->can('request_idcard_print') || $actor->can('print_idcards'), 403);
        $this->authorizeWithinScope($user);

        $user->cancelPendingIdCardPrintRequests(IdCardPrintRequest::CANCEL_REMOVED, $actor->id);

        return redirect()->back()->with('success', "{$user->full_name}: ready-to-print mark removed.");
    }

    /**
     * Limit a users query to the actor's branch/division (national: all) —
     * the query form of authorizeWithinScope().
     */
    private function scopedToActor($query)
    {
        $actor = Auth::user();
        $accessLevel = $actor->getAccessLevel();
        $scopedId = $actor->getScopedId();

        return match (true) {
            $accessLevel === 'national' => $query,
            $accessLevel === 'branch' && (bool) $scopedId => $query->where('users.branch_id', $scopedId),
            $accessLevel === 'division' && (bool) $scopedId => $query->where('users.division_id', $scopedId),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * 403 unless the target user is inside the acting admin's branch/division
     * (national admins see everyone) — same access-level split as
     * bulkDeletePrints().
     */
    private function authorizeWithinScope(User $target): void
    {
        $actor = Auth::user();
        $accessLevel = $actor->getAccessLevel();
        $scopedId = $actor->getScopedId();

        $allowed = match (true) {
            $accessLevel === 'national' => true,
            $accessLevel === 'branch' => $scopedId && (int) $target->branch_id === (int) $scopedId,
            $accessLevel === 'division' => $scopedId && (int) $target->division_id === (int) $scopedId,
            default => false,
        };

        abort_unless($allowed, 403, 'This user is outside your branch or division.');
    }

    /**
     * The admin's per-card validity override when it's a positive number,
     * otherwise the user's fee-based default (see
     * User::defaultIdCardValidityMonths()). Shared by printBulkCards() and
     * recordBulkIdCardPrints() so the printed and recorded expiry agree.
     */
    private function resolveValidityMonths(User $user, mixed $submitted): int
    {
        if (is_numeric($submitted) && (int) $submitted > 0) {
            return (int) $submitted;
        }

        return $user->defaultIdCardValidityMonths();
    }

    /**
     * Verify an ID card by token and display the user's public profile.
     *
     * @param  string  $token
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function verifyId($token)
    {
        // Find the user by the verification token.
        $user = User::where('id_check_token', $token)->first();

        // If no user is found, redirect to a 'not found' page or show an error.
        if (! $user) {
            // You might want to create a dedicated 'not-found' view.
            return response()->view('id-cards.not-found', [], 404);
        }

        // Eager load necessary relationships
        $user->load([
            'branch', 'division', 'redCrossUnit', 'trainings.trainingType',
            'currentMembershipPayment' => fn ($q) => $q->personal(),
            'currentMembershipPayment.membershipFee',
        ]);

        $payment = $user->currentMembershipPayment;

        // Single, isolated member/volunteer predicate — see the matching
        // comment in printCard(); keep in sync until the volunteer/member
        // redefinition work replaces this signal.
        $isVolunteer = $user->isVolunteer();

        // Partition non-deleted trainings into first-aid vs other via the canonical is_first_aid flag.
        $nonDeletedTrainings = $user->trainings->where('is_deleted', false);

        $firstAidTrainings = $nonDeletedTrainings
            ->filter(fn ($t) => $t->trainingType && $t->trainingType->is_first_aid)
            ->sortByDesc('training_date')->values();

        $otherTrainings = $nonDeletedTrainings
            ->filter(fn ($t) => $t->trainingType && ! $t->trainingType->is_first_aid)
            ->sortByDesc('training_date')->values();

        // Total recorded volunteering hours (non-deleted activities).
        $totalVolunteeringHours = $user->activities()->where('is_deleted', false)->sum('hours');

        // Prepare the data for the view
        $data = [
            'full_name' => $user->full_name,
            'user_id_reference_short' => $user->user_id_reference_short,
            'branch' => $user->branch ? $user->branch->name : 'N/A',
            'division' => $user->division ? $user->division->name : 'N/A',
            'is_volunteer' => $isVolunteer,
            'person_type_label' => $isVolunteer ? 'Volunteer' : 'Member',
            'category_label' => $isVolunteer ? 'Red Cross Unit' : 'Membership Type',
            'category_value' => $isVolunteer
                ? ($user->redCrossUnit->name ?? 'N/A')
                : ($payment && $payment->membershipFee ? $payment->membershipFee->name : 'Member'),
            'membership_expiry' => $payment ? Carbon::parse($payment->expiry_date)->format('M Y') : 'N/A',
            'is_membership_valid' => $payment && $payment->expiry_date && Carbon::parse($payment->expiry_date)->isFuture(),
            'first_aid_trainings' => $firstAidTrainings,
            'other_trainings' => $otherTrainings,
            'total_volunteering_hours' => $totalVolunteeringHours,
        ];

        return view('id-cards.verify', ['user' => $data]);
    }

    /**
     * Show a report of all ID card print records.
     *
     * @return \Illuminate\View\View
     */
    public function showIdCardPrintsReport(Request $request)
    {
        $query = IdCardPrint::query();

        // Get current user's access level and scoped ID
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel(); // e.g., 'national', 'branch', 'division'
        $scopedId = $user->getScopedId(); // branch_id or division_id

        $userBranchId = null;
        $userDivisionId = null;

        if ($accessLevel === 'branch') {
            $userBranchId = $scopedId;
        } elseif ($accessLevel === 'division') {
            $userDivision = Division::find($scopedId);
            if ($userDivision) {
                $userDivisionId = $scopedId;
                $userBranchId = $userDivision->branch_id;
            }
        }

        // Apply global access level filters to the main query
        // If the user has a restricted access level, filter the IdCardPrint records
        if ($accessLevel === 'branch' && $userBranchId) {
            // Filter by branch of the user associated with the IdCardPrint
            $query->whereHas('user', function ($q) use ($userBranchId) {
                $q->where('branch_id', $userBranchId);
            });
        } elseif ($accessLevel === 'division' && $userDivisionId) {
            // Filter by division of the user associated with the IdCardPrint
            $query->whereHas('user', function ($q) use ($userDivisionId) { // Changed $q->whereHas to $query->whereHas
                $q->where('division_id', $userDivisionId);
            });
        }

        // Optional: Add filters for the report from request
        if ($request->filled('user_id_search')) { // Corrected input name
            $searchTerm = $request->input('user_id_search');
            $likeSearchTerm = '%'.$searchTerm.'%';
            $numericSearchTerm = str_replace('%', '', $searchTerm); // For exact ID match

            $query->whereHas('user', function ($q) use ($likeSearchTerm, $numericSearchTerm) {
                // Search by first name
                $q->where('first_name', 'like', $likeSearchTerm)
                    // Search by last name
                    ->orWhere('last_name', 'like', $likeSearchTerm);

                // If the search term looks like an ID, include an exact ID match
                if (is_numeric($numericSearchTerm)) {
                    $q->orWhere('id', $numericSearchTerm);
                }
                // The 'user_id_reference' is an accessor and cannot be directly queried in the database.
                // Searching for it directly caused an "Unknown column" error.
            });
        }
        // REMOVED STATUS FILTER
        // if ($request->filled('status')) {
        //     $query->where('status', $request->input('status'));
        // }

        if ($request->filled('branch_id')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('branch_id', $request->input('branch_id'));
            });
        }

        if ($request->filled('division_id')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('division_id', $request->input('division_id'));
            });
        }

        if ($request->filled('red_cross_unit_id')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('red_cross_unit_id', $request->input('red_cross_unit_id'));
            });
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('printed_at', [$request->input('start_date'), $request->input('end_date').' 23:59:59']); // Include end of day
        }

        // Eager load relationships for displaying user and printer information
        $idCardPrints = $query->with('user.branch', 'user.division', 'user.redCrossUnit', 'printedBy')
            ->latest('printed_at') // Order by latest print date
            ->paginate(20);

        // Data for dropdowns, respecting access levels
        // Ensure $branches is always a Collection, even if initial collect() or subsequent assignments don't explicitly run.
        // This line is added defensively to guarantee $branches is defined.
        if (! isset($branches) || ! $branches instanceof \Illuminate\Support\Collection) {
            $branches = collect();
        }

        if ($accessLevel === 'national') {
            $branches = Branch::orderBy('name')->get();
        } elseif ($accessLevel === 'branch' && $userBranchId) {
            $branches = Branch::where('id', $userBranchId)->orderBy('name')->get();
        } elseif ($accessLevel === 'division' && $userDivisionId) {
            // If division level, we need the branch for the current division
            $userDivision = Division::find($userDivisionId);
            if ($userDivision) {
                $branches = Branch::where('id', $userDivision->branch_id)->orderBy('name')->get();
            }
        }

        // Populate divisions based on selected branch or user's access level
        $selectedBranchId = $request->input('branch_id') ?? ($userBranchId ?? null);
        if ($selectedBranchId) {
            if ($accessLevel === 'national' || ($accessLevel === 'branch' && (string) $selectedBranchId === (string) $userBranchId)) {
                $divisions = Division::where('branch_id', $selectedBranchId)->orderBy('name')->get();
            } elseif ($accessLevel === 'division' && $userDivisionId) {
                // If division level, only show their division
                $divisions = Division::where('id', $userDivisionId)->orderBy('name')->get();
            }
        } else {
            // Ensure divisions is an empty collection if no branch is selected or available
            $divisions = collect();
        }

        // Populate red cross units based on selected division or user's access level
        $selectedDivisionId = $request->input('division_id') ?? ($userDivisionId ?? null);
        if ($selectedDivisionId) {
            $redCrossUnits = RedCrossUnit::where('division_id', $selectedDivisionId)->orderBy('name')->get();
        } else {
            // Ensure redCrossUnits is an empty collection if no division is selected or available
            $redCrossUnits = collect();
        }

        return view('id-cards.prints-report', compact(
            'idCardPrints',
            'branches',
            'divisions',
            'redCrossUnits',
            'accessLevel', // Pass access level to view for conditional UI
            'userBranchId',
            'userDivisionId'
        ));
    }

    /**
     * Handle bulk deletion of ID card print records.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bulkDeletePrints(Request $request)
    {
        // Authorize the action using a gate or policy
        // Example with gate:
        if (! Auth::user()->can('print_idcards')) {
            abort(403, 'Unauthorized action.');
        }

        $printIdsJson = $request->input('print_ids');

        if (empty($printIdsJson)) {
            return redirect()->back()->with('error', 'No ID card prints selected for deletion.');
        }

        $printIds = json_decode($printIdsJson, true); // Decode the JSON string back into an array

        if (! is_array($printIds) || empty($printIds)) {
            return redirect()->back()->with('error', 'Invalid selection for deletion.');
        }

        // Filter the IDs to ensure only records the user has access to are deleted.
        // This is a crucial security step.
        $query = IdCardPrint::whereIn('id', $printIds);

        // Apply access level filters for deletion as well
        $currentUser = Auth::user();
        $accessLevel = $currentUser->getAccessLevel();
        $scopedId = $currentUser->getScopedId();

        if ($accessLevel === 'branch' && $scopedId) {
            $query->whereHas('user', function ($q) use ($scopedId) {
                $q->where('branch_id', $scopedId);
            });
        } elseif ($accessLevel === 'division' && $scopedId) {
            $query->whereHas('user', function ($q) use ($scopedId) {
                $q->where('division_id', $scopedId);
            });
        }

        $deletedCount = $query->delete(); // This will perform soft delete if IdCardPrint model uses SoftDeletes

        if ($deletedCount > 0) {
            return redirect()->back()->with('success', "{$deletedCount} ID card print records successfully deleted.");
        } else {
            return redirect()->back()->with('error', 'No ID card print records were deleted. They might not exist or you might not have permission.');
        }
    }
}
