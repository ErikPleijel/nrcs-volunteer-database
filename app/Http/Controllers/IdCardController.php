<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Division;
use App\Models\IdCardPrint;
use App\Models\RedCrossUnit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

        // Filter: only users with all required data for printing
        if ($request->boolean('printable_only')) {
            $query->whereNotNull('picture')
                ->whereNotNull('signature')
                ->whereNotNull('first_name')
                ->whereNotNull('last_name')
                ->whereNotNull('national_id_number')
                ->whereNotNull('branch_id')
                ->whereNotNull('division_id')
                ->whereHas('currentMembershipPayment', function ($q) {
                    $q->personal()->whereHas('membershipFee');
                });
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
        $query->with([
            'branch', 'division', 'idCardPrints',
            'currentMembershipPayment' => fn ($q) => $q->personal(),
            'currentMembershipPayment.membershipFee',
        ]);

        // Apply sorting. When filtering for "needs print", sort by the latest payment date as a proxy for urgency.
        if ($request->boolean('needs_id_card_printed')) {
            $query->orderBy('last_id_card_payment_date', 'desc');
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

        $validityMonths = range(12, 60, 6);

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return view('id-cards.prepare-bulk-print', compact('users', 'branches', 'divisions', 'redCrossUnits', 'validityMonths', 'accessLevel', 'userBranchId', 'userDivisionId'));
    }

    /**
     * Prepare user data and display the ID card print view.
     *
     * @return \Illuminate\View\View
     */
    public function printCard(User $user)
    {
        // Eager load relationships for efficiency
        $user->load([
            'branch', 'division',
            'currentMembershipPayment' => fn ($q) => $q->personal(),
            'currentMembershipPayment.membershipFee',
        ]);

        $payment = $user->currentMembershipPayment;

        // The verification URL for the QR code and text
        $verificationUrl = url("/idcheck/{$user->id_check_token}");

        // Prepare the data array for the blade view
        $data = [
            'dbcode' => $user->user_id_reference,
            'lastname' => $user->last_name,
            'firstname' => $user->first_name,
            'national_id_number' => $user->national_id_number ?? 'N/A',
            'membership_type' => $payment && $payment->membershipFee ? $payment->membershipFee->name : 'Member',
            'branch' => $user->branch ? $user->branch->name : 'N/A',
            'division' => $user->division ? $user->division->name : 'N/A',
            'expdate' => $payment ? Carbon::parse($payment->expiry_date)->format('M Y') : 'N/A',
            'picture' => $user->profile_photo_url,
            'signature' => $user->signature_url,
            'producer_code' => Auth::check() ? 'ID-'.Auth::id() : 'SYSTEM',
            'verification_url' => $verificationUrl,
            'qr_image' => 'data:image/svg+xml;base64,'.base64_encode(QrCode::format('svg')->size(200)->generate($verificationUrl)),
            'img_bg' => asset('images/id-card/IDbackground.JPG'),
            'img_header' => asset('images/id-card/IDcardHeader.png'),
            'img_logo' => asset('images/id-card/NRCS_logo.jpg'),
            'img_sg_signature' => asset('images/id-card/sg-signature.png'),
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
            'branch', 'division',
            'currentMembershipPayment' => fn ($q) => $q->personal(),
            'currentMembershipPayment.membershipFee',
        ])->whereIn('id', $userIds)->get();

        $cardsData = [];
        foreach ($users as $user) {
            $payment = $user->currentMembershipPayment;
            $verificationUrl = url("/idcheck/{$user->id_check_token}");

            $expdate = 'N/A';
            $validityMonthsForUser = $validityMonthsMap[$user->id] ?? null;

            if ($validityMonthsForUser && is_numeric($validityMonthsForUser)) {
                $expdate = Carbon::now()->addMonths((int) $validityMonthsForUser)->format('M Y');
            } elseif ($payment) {
                $expdate = Carbon::parse($payment->expiry_date)->format('M Y');
            }

            $cardsData[] = [
                'dbcode' => $user->user_id_reference,
                'lastname' => $user->last_name,
                'firstname' => $user->first_name,
                'national_id_number' => $user->national_id_number ?? 'N/A',
                'membership_type' => $payment && $payment->membershipFee ? $payment->membershipFee->name : 'Member',
                'branch' => $user->branch ? $user->branch->name : 'N/A',
                'division' => $user->division ? $user->division->name : 'N/A',
                'expdate' => $expdate,
                'picture' => $user->profile_photo_url,
                'signature' => $user->signature_url,
                'producer_code' => Auth::check() ? 'ID-'.Auth::id() : 'SYSTEM',
                'verification_url' => $verificationUrl,
                'qr_image' => 'data:image/svg+xml;base64,'.base64_encode(QrCode::format('svg')->size(200)->generate($verificationUrl)),
                'img_bg' => asset('images/id-card/IDbackground.JPG'),
                'img_header' => asset('images/id-card/IDcardHeader.png'),
                'img_logo' => asset('images/id-card/NRCS_logo.jpg'),
                'img_sg_signature' => asset('images/id-card/sg-signature.png'),
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

        foreach ($userIds as $userId) {
            $user = User::with(['currentMembershipPayment' => fn ($q) => $q->personal()])->find($userId);

            if (! $user) {
                // Skip if user not found, or log an error
                continue;
            }

            $expiryDate = null;
            $validityMonthsToStore = $validityMonthsMap[$user->id] ?? null;

            if ($validityMonthsToStore && is_numeric($validityMonthsToStore)) {
                // If a specific validity (e.g., 12, 24 months) is chosen for this user
                $validityMonthsToStore = (int) $validityMonthsToStore;
                $expiryDate = Carbon::now()->addMonths($validityMonthsToStore);
            } else {
                // If "Use Membership Expiry" is chosen for this user (validityMonthsToStore is empty or invalid)
                if ($user->currentMembershipPayment && $user->currentMembershipPayment->expiry_date) {
                    $expiryDate = Carbon::parse($user->currentMembershipPayment->expiry_date);
                    // For now, if membership expiry is used, we set validity_months to null in the record.
                    $validityMonthsToStore = null;
                }
            }

            // Create the IdCardPrint record
            IdCardPrint::create([
                'user_id' => $userId,
                'printed_by_user_id' => $printedBy,
                'printed_at' => $printedAt,
                'status' => 'printed', // As requested
                'validity_months' => $validityMonthsToStore,
                'expiry_date' => $expiryDate,
                'notes' => null, // As requested
            ]);
        }

        return redirect()->back()->with('success', 'Selected ID cards marked as printed successfully and records saved.');
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
            'branch', 'division', 'trainings.trainingType',
            'currentMembershipPayment' => fn ($q) => $q->personal(),
            'currentMembershipPayment.membershipFee',
        ]);

        $payment = $user->currentMembershipPayment;

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
            'membership_type' => $payment && $payment->membershipFee ? $payment->membershipFee->name : 'Member',
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
