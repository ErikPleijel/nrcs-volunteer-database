<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Division;
use App\Models\Log as AuditLog;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\RedCrossUnit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MembershipPaymentController extends Controller
{
    use \App\Http\Controllers\Concerns\HandlesRecordApproval;
    use AuthorizesRequests;

    protected function approvalModelClass(): string
    {
        return MembershipPayment::class;
    }

    protected function approvalLabel(): string
    {
        return 'Membership payment';
    }

    protected function approvalRouteName(): string
    {
        return 'membership-payments';
    }

    protected function approvalPermission(): string
    {
        return 'approve_payments';
    }

    /**
     * Reusable method to apply filters to the MembershipPayment query.
     */
    private function getFilteredPaymentsQuery(Request $request)
    {
        $query = MembershipPayment::with(['user', 'membershipFee', 'submittedByUser', 'branch', 'division', 'user.redCrossUnit'])
            ->whereHas('user')
            ->whereHas('membershipFee');

        $trashed = $request->get('trashed');

        switch ($trashed) {
            case 'only':
                $query->where('is_deleted', true);
                break;
            case 'with':
                // Include both deleted and non-deleted records
                break;
            default:
                $query->where('is_deleted', false);
                break;
        }

        // Apply global access level filters FIRST
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();

        switch ($accessLevel) {
            case 'branch':
                if ($scopedId) {
                    $query->where('branch_id', $scopedId);
                }
                break;
            case 'division':
                if ($scopedId) {
                    $query->where('division_id', $scopedId);
                }
                break;
                // 'national' level sees all, so no additional filter here
        }

        // Handle "My Records" filter
        if ($request->filled('my_records') && $request->my_records == '1') {
            $query->where('submitted_by_user_id', auth()->id());
        }

        // Handle membership fee filter
        if ($request->filled('membership_fee_name')) {
            $query->whereHas('membershipFee', function ($q) use ($request) {
                $q->where('name', $request->membership_fee_name);
            });
        }

        // Handle branch filter (only if not restricted by user's access level)
        if ($accessLevel === 'national' && $request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // Handle division filter (only if not restricted by user's access level)
        if (in_array($accessLevel, ['national', 'branch']) && $request->filled('division_id')) {
            $query->where('division_id', $request->division_id);
        }

        // Handle Red Cross Unit filter
        if ($request->filled('red_cross_unit_id')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('red_cross_unit_id', $request->red_cross_unit_id);
            });
        }

        // Handle validity status filter
        if ($request->filled('validity_status')) {
            switch ($request->validity_status) {
                case 'valid':
                    $query->where('expiry_date', '>=', now());
                    break;
                case 'expired':
                    $query->where('expiry_date', '<', now());
                    break;
                case 'expiring_soon':
                    $query->where('expiry_date', '>=', now())
                        ->where('expiry_date', '<=', now()->addDays(30));
                    break;
            }
        }

        // Handle search functionality
        if ($request->filled('search')) {
            $searchTerm = $request->search;

            $query->where(function ($mainQuery) use ($searchTerm) {
                // Search by membership payment ID (exact match)
                $mainQuery->where('id', $searchTerm)
                    // OR search by member (user_id relationship)
                    ->orWhereHas('user', function ($userQuery) use ($searchTerm) {
                        // Search by user ID (exact match)
                        $userQuery->where('id', $searchTerm)
                            // Search by first name
                            ->orWhere('first_name', 'LIKE', "%{$searchTerm}%")
                            // Search by last name
                            ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
                            // Search by middle name
                            ->orWhere('middle_name', 'LIKE', "%{$searchTerm}%")
                            // Search by full name combination
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$searchTerm}%"])
                            ->orWhereRaw("CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?", ["%{$searchTerm}%"])
                            ->orWhereRaw("CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name) LIKE ?", ["%{$searchTerm}%"]);
                    })
                    // OR search by submitted by user (submitted_by_user_id relationship)
                    ->orWhereHas('submittedByUser', function ($submitterQuery) use ($searchTerm) {
                        // Search by submitter user ID (exact match)
                        $submitterQuery->where('id', $searchTerm)
                            // Search by submitter first name
                            ->orWhere('first_name', 'LIKE', "%{$searchTerm}%")
                            // Search by submitter last name
                            ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")
                            // Search by submitter middle name
                            ->orWhere('middle_name', 'LIKE', "%{$searchTerm}%")
                            // Search by submitter full name combination
                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%$searchTerm%"])
                            ->orWhereRaw("CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?", ["%{$searchTerm}%"])
                            ->orWhereRaw("CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name) LIKE ?", ["%$searchTerm%"]);
                    })
                    // OR search by reference field
                    ->orWhere('reference', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Handle sorting
        $sortBy = $request->get('sort_by', 'payment_date_desc'); // Default sort

        switch ($sortBy) {
            case 'expiry_date_asc':
                $query->orderBy('expiry_date', 'asc')->orderBy('payment_date', 'desc');
                break;
            case 'expiry_date_desc':
                $query->orderBy('expiry_date', 'desc')->orderBy('payment_date', 'desc');
                break;
            case 'payment_date_asc':
                $query->orderBy('payment_date', 'asc');
                break;
            case 'payment_date_desc':
            default:
                $query->orderBy('payment_date', 'desc');
                break;
        }

        return $query;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();

        $trashed = $request->get('trashed');

        $query = $this->getFilteredPaymentsQuery($request);

        // Get total count before applying filters (for display when no filters are applied)
        // This count is for the *unfiltered* total, or the total records if no filters are applied.
        $totalRecordsQuery = MembershipPayment::whereHas('user')
            ->whereHas('membershipFee');

        switch ($trashed) {
            case 'only':
                $totalRecordsQuery->where('is_deleted', true);
                break;
            case 'with':
                // include both -> no is_deleted filter
                break;
            default:
                $totalRecordsQuery->where('is_deleted', false);
                break;
        }

        // Apply access level to total records count as well
        switch ($accessLevel) {
            case 'branch':
                if ($scopedId) {
                    $totalRecordsQuery->where('branch_id', $scopedId);
                }
                break;
            case 'division':
                if ($scopedId) {
                    $totalRecordsQuery->where('division_id', $scopedId);
                }
                break;
        }
        $totalRecords = $totalRecordsQuery->count();

        $membershipPayments = $query->paginate(15)
            ->appends($request->query()); // Maintain search parameters in pagination

        // Get all membership fees for the filter dropdown
        $membershipFees = MembershipFee::select('name')
            ->distinct()
            ->orderBy('name', 'asc')
            ->get();

        $branches = collect();
        $divisions = collect();
        $redCrossUnits = collect();

        // Populate branches and divisions based on access level
        switch ($accessLevel) {
            case 'national':
                $branches = Branch::select('id', 'name')->orderBy('name')->get();
                if ($request->filled('branch_id')) {
                    $divisions = Division::where('branch_id', $request->branch_id)
                        ->select('id', 'name')->orderBy('name')->get();
                }
                break;
            case 'branch':
                if ($scopedId) {
                    $branches = Branch::where('id', $scopedId)
                        ->select('id', 'name')->orderBy('name')->get();
                    $divisions = Division::where('branch_id', $scopedId)
                        ->select('id', 'name')->orderBy('name')->get();
                }
                break;
            case 'division':
                if ($scopedId) {
                    $userDivision = Division::find($scopedId);
                    if ($userDivision) {
                        $branches = Branch::where('id', $userDivision->branch_id)
                            ->select('id', 'name')->orderBy('name')->get();
                        $divisions = Division::where('id', $scopedId)
                            ->select('id', 'name')->orderBy('name')->get();
                    }
                }
                break;
        }

        // Get Red Cross Units based on selected division (for initial load), respecting access level
        if ($request->filled('division_id')) {
            if ($accessLevel === 'division' && $scopedId != $request->division_id) {
                // If user is division-scoped and tries to filter by another division, ignore
                $redCrossUnits = collect();
            } else {
                $redCrossUnits = RedCrossUnit::where('division_id', $request->division_id)
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get();
            }
        } elseif ($accessLevel === 'division' && $scopedId) {
            // If division level user, default to their division's units
            $redCrossUnits = RedCrossUnit::where('division_id', $scopedId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        }

        // Determine if any filters are applied
        $hasFilters = $request->filled('search') ||
            $request->filled('my_records') ||
            $request->filled('membership_fee_name') ||
            $request->filled('validity_status') ||
            (Auth::user()->getAccessLevel() === 'national' && $request->filled('branch_id')) || // Only consider branch_id a filter if national level
            (in_array(Auth::user()->getAccessLevel(), ['national', 'branch']) && $request->filled('division_id')) || // Only consider division_id a filter if national or branch level
            $request->filled('red_cross_unit_id') ||
            in_array($trashed, ['only', 'with']) ||
            ($request->get('sort_by', 'payment_date_desc') != 'payment_date_desc');

        $pendingApprovalCount = $user->can('approve_payments')
            ? MembershipPayment::eligibleForApproval($user)->count()
            : 0;

        return view('membership-payments.index', compact(
            'membershipPayments',
            'membershipFees',
            'branches',
            'divisions',
            'redCrossUnits',
            'totalRecords',
            'hasFilters',
            'accessLevel', // Pass access level to the view
            'scopedId',    // Pass scoped ID to the view
            'pendingApprovalCount'
        ));
    }

    public function createForOrganisation(Organisation $organisation)
    {
        $organisation->load(['users', 'branch']);

        $membershipFees = MembershipFee::select('id', 'name', 'amount', 'id_card_fee', 'validity_years')
            ->where('for_organizations', true)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('membership-payments.create_for_organisation', compact('organisation', 'membershipFees'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(?User $user = null)
    {
        if ($user) {
            $user->load(['branch:id,name,code', 'division:id,name', 'redCrossUnit:id,name']);

            $this->authorize('view', $user);

            // Append rcu_name so the blade @json output matches the search API format
            $user->rcu_name = $user->redCrossUnit?->name;
        }

        $membershipFees = MembershipFee::select('id', 'name', 'amount', 'id_card_fee', 'validity_years', 'is_volunteer_fee')
            ->where('is_active', true)
            ->orderBy('name')
            ->orderBy('validity_years', 'asc')
            ->get();

        $branches = Branch::select('id', 'name')
            ->orderBy('name')
            ->get();

        $divisions = Division::select('id', 'name')
            ->orderBy('name')
            ->get();

        return view('membership-payments.create', compact('membershipFees', 'branches', 'divisions', 'user'));
    }

    /**
     * Return the current (latest non-deleted) membership payment for a user.
     */
    public function getCurrentMembership(User $user)
    {
        $payment = MembershipPayment::where('user_id', $user->id)
            ->where('is_deleted', false)
            ->with('membershipFee')
            ->latest('payment_date')
            ->first();

        if (! $payment) {
            return response()->json(null);
        }

        return response()->json([
            'membership_fee_id' => $payment->membership_fee_id,
            'membership_fee_name' => $payment->membershipFee->name ?? null,
            'payment_date' => $payment->payment_date?->format('Y-m-d'),
            'expiry_date' => $payment->expiry_date?->format('Y-m-d'),
            'expiry_date_display' => $payment->expiry_date?->format('M d, Y'),
            'is_expired' => $payment->expiry_date?->isPast() ?? true,
            'expires_in_days' => $payment->expiry_date
                ? (int) now()->diffInDays($payment->expiry_date, false)
                : null,
        ]);
    }

    /**
     * Search for users by ID or full name
     */
    public function searchUsers(Request $request)
    {
        $query = $request->get('query');

        if (empty($query)) {
            return response()->json([]);
        }

        // Get current user's access level and scoped ID
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();

        $usersQuery = User::selectableForEntry()->where(function ($q) use ($query) {
            // Search by user ID (exact match)
            $q->where('id', $query)
                // Search by first name
                ->orWhere('first_name', 'LIKE', "%{$query}%")
                // Search by last name
                ->orWhere('last_name', 'LIKE', "%{$query}%")
                // Search by middle name
                ->orWhere('middle_name', 'LIKE', "%{$query}%")

                // Search by full name combinations
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$query}%"])
                ->orWhereRaw("CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?", ["%{$query}%"])
                ->orWhereRaw("CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name) LIKE ?", ["%{$query}%"]);
        });

        // Apply restrictions based on the user's access level
        switch ($accessLevel) {
            case 'branch':
                if ($scopedId) {
                    $usersQuery->where('branch_id', $scopedId);
                }
                break;
            case 'division':
                if ($scopedId) {
                    $usersQuery->where('division_id', $scopedId);
                }
                break;
                // 'national' level does not have restrictions
        }

        $users = $usersQuery->select('id', 'first_name', 'middle_name', 'last_name', 'email', 'telephone1', 'branch_id', 'division_id', 'red_cross_unit_id', 'lifecycle_status', 'can_contribute_volunteering')
            ->with(['branch:id,name,code', 'division:id,name'])
            ->limit(50)
            ->get();

        // Collect RC unit IDs and fetch names in one query
        $rcuIds = $users->pluck('red_cross_unit_id')->filter()->unique()->values();
        $rcuNames = \App\Models\RedCrossUnit::whereIn('id', $rcuIds)
            ->pluck('name', 'id');

        $result = $users->map(function ($user) use ($rcuNames) {
            return [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'middle_name' => $user->middle_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'telephone1' => $user->telephone1,
                'branch_id' => $user->branch_id,
                'division_id' => $user->division_id,
                'red_cross_unit_id' => $user->red_cross_unit_id,
                'lifecycle_status' => $user->lifecycle_status,
                'can_contribute_volunteering' => (bool) $user->can_contribute_volunteering,
                'rcu_name' => $user->red_cross_unit_id ? ($rcuNames[$user->red_cross_unit_id] ?? null) : null,
                'branch' => $user->branch ? ['id' => $user->branch->id, 'name' => $user->branch->name, 'code' => $user->branch->code] : null,
                'division' => $user->division ? ['id' => $user->division->id, 'name' => $user->division->name] : null,
            ];
        });

        return response()->json($result);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $isOrgPayment = $request->filled('organisation_id');

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'organisation_id' => $isOrgPayment ? 'required|exists:organisations,id' : 'nullable|exists:organisations,id',
            'membership_fee_id' => 'required|exists:membership_fees,id',
            'payment_date' => 'required|date',
            'reference' => 'nullable|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'division_id' => 'nullable|exists:divisions,id',
            'id_card_included' => 'nullable|boolean',
        ]);

        $membershipFee = MembershipFee::findOrFail($request->membership_fee_id);
        $paymentDate = Carbon::parse($request->payment_date);
        $expiryDate = $paymentDate->copy()->addYears($membershipFee->validity_years);

        $membershipPayment = MembershipPayment::create([
            'user_id' => $request->user_id,
            'organisation_id' => $request->organisation_id ?: null,
            'payment_date' => $request->payment_date,
            'expiry_date' => $expiryDate,
            'membership_fee_id' => $request->membership_fee_id,
            'reference' => $request->reference,
            'submission_name' => auth()->user()->name,
            'submitted_by_user_id' => auth()->id(),
            'submitted_at' => now(),
            'branch_id' => $request->branch_id,
            'division_id' => $request->division_id,
            'id_card_included' => $request->boolean('id_card_included'),
            'is_deleted' => false,
        ]);

        AuditLog::write(
            'membership_payment_created',
            $membershipPayment,
            null,
            null,
            $membershipPayment->toArray(),
            "Membership payment created for user #{$membershipPayment->user_id} (fee: {$membershipFee->name}, expires {$expiryDate->toDateString()})."
        );

        // Created as pending (approval_status DB default). Submission is no longer
        // member activity — activation happens on approval (Phase 2).
        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        if ($isOrgPayment) {
            return redirect()->route('organisations.show', $request->organisation_id)
                ->with('success', 'Membership payment added successfully.');
        }

        return redirect()->route('membership-payments.create')->with('success', 'Membership payment created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(MembershipPayment $membershipPayment)
    {
        $membershipPayment->load(['user', 'membershipFee', 'submittedByUser', 'branch', 'division', 'decidedByUser']);

        if (! $membershipPayment->user) {
            return redirect()->route('membership-payments.index')
                ->with('error', 'The associated user for this payment no longer exists.');
        }

        $this->authorize('view', $membershipPayment->user);

        return view('membership-payments.show', compact('membershipPayment'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(MembershipPayment $membershipPayment)
    {
        $membershipPayment->load('user');

        if (! $membershipPayment->user) {
            return redirect()->route('membership-payments.index')
                ->with('error', 'The associated user for this payment no longer exists.');
        }

        $this->authorize('view', $membershipPayment->user);

        $users = User::select('id', 'first_name', 'last_name', 'email')
            ->orderBy('first_name')
            ->get();

        $membershipFees = MembershipFee::select('id', 'name', 'amount')
            ->orderBy('name')
            ->get();

        return view('membership-payments.edit', compact('membershipPayment', 'users', 'membershipFees'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MembershipPayment $membershipPayment)
    {
        $membershipPayment->load('user');

        if (! $membershipPayment->user) {
            return redirect()->route('membership-payments.index')
                ->with('error', 'The associated user for this payment no longer exists.');
        }

        $this->authorize('view', $membershipPayment->user);

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'membership_fee_id' => 'required|exists:membership_fees,id',
            'payment_date' => 'required|date',
            'expiry_date' => 'required|date|after:payment_date',
            'reference' => 'nullable|string|max:255',
            'submission_name' => 'nullable|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'division_id' => 'nullable|exists:divisions,id',
            'id_card_included' => 'boolean',
        ]);

        $membershipPayment->update($validated);

        // Editing an approved record demotes it back to pending for a fresh
        // approval cycle (no-op if it wasn't approved); resetApprovalOnEdit()
        // already recomputes lifecycle as part of that demotion.
        $membershipPayment->resetApprovalOnEdit();

        // Recompute lifecycle only for an APPROVED record; a pending one has no effect.
        if ($membershipPayment->isApproved()) {
            optional(User::find($membershipPayment->user_id))->recalculateLifecycle();
        }

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return redirect()->route('membership-payments.show', $membershipPayment)
            ->with('success', 'Membership payment updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MembershipPayment $membershipPayment)
    {
        $membershipPayment->load('user');

        if (! $membershipPayment->user) {
            return redirect()->route('membership-payments.index')
                ->with('error', 'The associated user for this payment no longer exists.');
        }

        $this->authorize('view', $membershipPayment->user);

        // withAnyApprovalStatus(): a pending successor still depends on this payment's
        // expiry_date and must block deletion just as an approved one would — the
        // default ApprovedScope (from Approvable) would otherwise hide it, since new
        // payments start out pending. A rejected successor is excluded: it's void,
        // per the Approvable invariant that only an approved record is "real".
        $hasSuccessor = MembershipPayment::withAnyApprovalStatus()
            ->where('user_id', $membershipPayment->user_id)
            ->where('is_deleted', false)
            ->where('id', '!=', $membershipPayment->id)
            ->where('approval_status', '!=', MembershipPayment::REJECTED)
            ->whereDate('payment_date', $membershipPayment->expiry_date->copy()->addDay())
            ->exists();

        if ($hasSuccessor) {
            return redirect()->route('membership-payments.show', $membershipPayment)
                ->with('error', 'Cannot delete this payment — a later payment was recorded starting the day after this one expires. Delete or adjust that payment first.');
        }

        // Soft delete: set is_deleted to true and record who deleted it and when
        $attributes = $membershipPayment->toArray();

        $membershipPayment->update([
            'is_deleted' => true,
            'removed_by_user_id' => auth()->id(),
            'removed_date' => now(),
        ]);

        AuditLog::write(
            'membership_payment_deleted',
            $membershipPayment,
            null,
            $attributes,
            null,
            "Membership payment #{$membershipPayment->id} deleted (was valid to {$attributes['expiry_date']})."
        );

        if ($membershipPayment->user) {
            $membershipPayment->user->recalculateLifecycle();
        }

        return redirect()
            ->route('membership-payments.show', $membershipPayment)
            ->with('deleted', 'Membership payment has been deleted successfully.');
    }

    public function getDivisionsByBranch(Request $request)
    {
        $branchId = $request->get('branch_id');
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();

        $divisionsQuery = Division::where('branch_id', $branchId);

        // If branch-level user, they can only see divisions within their scoped branch
        if ($accessLevel === 'branch' && $scopedId && $scopedId != $branchId) {
            return response()->json([]); // Return empty if trying to get divisions outside their branch
        }
        // If division-level user, they can only see their own division
        if ($accessLevel === 'division' && $scopedId) {
            $divisionsQuery->where('id', $scopedId);
        }

        $divisions = $divisionsQuery->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($divisions);
    }

    public function getRedCrossUnitsByDivision(Request $request)
    {
        $divisionId = $request->get('division_id');
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();

        $unitsQuery = RedCrossUnit::where('division_id', $divisionId);

        // If division-level user, they can only see units within their scoped division
        if ($accessLevel === 'division' && $scopedId && $scopedId != $divisionId) {
            return response()->json([]); // Return empty if trying to get units outside their division
        }

        $units = $unitsQuery->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($units);
    }
}
