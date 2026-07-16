<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesRecordApproval;
use App\Models\Branch;
use App\Models\Division;
use App\Models\Donation;
use App\Models\Organisation; // Import RedCrossUnit for filtering
use App\Models\RedCrossUnit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View; // Import the Auth facade for Auth::id()

class DonationController extends Controller
{
    use HandlesRecordApproval;

    protected function approvalModelClass(): string
    {
        return Donation::class;
    }

    protected function approvalLabel(): string
    {
        return 'Donation';
    }

    protected function approvalRouteName(): string
    {
        return 'donations';
    }

    protected function approvalPermission(): string
    {
        return 'approve_donations';
    }

    /**
     * Display a listing of donations.
     */
    public function index(Request $request): View
    {
        $query = Donation::with(['user.redCrossUnit', 'branch', 'division', 'submittedByUser']);

        // Trashed filter (Soft Deletes)
        $trashedFilter = $request->get('trashed');
        switch ($trashedFilter) {
            case 'only':
                $query->onlyTrashed();
                break;
            case 'with':
                $query->withTrashed();
                break;
            default:
                // Active only (default) -> no change needed (SoftDeletes applies)
                break;
        }

        // Get current user's access level and scoped ID
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();

        $userBranchId = null;
        $userDivisionId = null;

        if ($accessLevel === 'branch') {
            $userBranchId = $scopedId;
        } elseif ($accessLevel === 'division') {
            $userDivisionId = $scopedId;
            $userDivision = Division::find($scopedId);
            if ($userDivision) {
                $userBranchId = $userDivision->branch_id;
            }
        }

        // Apply global access level filters to the main query
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
                // national sees all
        }

        // Donations record the submitter in entered_by_user_id (see Approvable::submitterColumn()),
        // NOT submitted_by_user_id — point the "My Records" filter at the right column.
        $submittedByColumn = (new Donation)->submitterColumn();

        // Determine if any filters are applied
        $hasFilters = $request->filled('search') ||
            $request->filled('my_records') ||
            ($accessLevel === 'national' && $request->filled('branch_id')) ||
            (in_array($accessLevel, ['national', 'branch']) && $request->filled('division_id')) ||
            $request->filled('red_cross_unit_id') ||
            $request->get('sort_by', 'date_desc') !== 'date_desc' ||
            in_array($trashedFilter, ['only', 'with']);

        // Handle "My Records" filter
        if ($submittedByColumn && $request->filled('my_records') && $request->my_records == '1') {
            $query->where($submittedByColumn, auth()->id());
        }

        // Handle search functionality
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $searchWords = preg_split('/\s+/', $searchTerm, -1, PREG_SPLIT_NO_EMPTY);

            $query->where(function ($mainQuery) use ($searchWords) {
                foreach ($searchWords as $word) {
                    $mainQuery->where(function ($wordQuery) use ($word) {

                        // 1) Donation fields
                        $wordQuery->orWhere('reference', 'LIKE', "%{$word}%")
                            ->orWhere('purpose', 'LIKE', "%{$word}%")
                            ->orWhere('donation_item', 'LIKE', "%{$word}%");

                        if (is_numeric($word)) {
                            $wordQuery->orWhere('donations.id', (int) $word)
                                ->orWhere('donations.user_id', (int) $word);
                        }

                        // 2) Branch name
                        $wordQuery->orWhereHas('branch', function ($branchQuery) use ($word) {
                            $branchQuery->where('name', 'LIKE', "%{$word}%");
                        });

                        // 3) Division name
                        $wordQuery->orWhereHas('division', function ($divisionQuery) use ($word) {
                            $divisionQuery->where('name', 'LIKE', "%{$word}%");
                        });

                        // 4) User + Unit (only if user exists)
                        $wordQuery->orWhere(function ($userExistenceQuery) use ($word) {
                            $userExistenceQuery->whereNotNull('user_id')
                                ->where(function ($userAndUnitQuery) use ($word) {
                                    $userAndUnitQuery->whereHas('user', function ($userInnerQuery) use ($word) {
                                        $userInnerQuery->where('first_name', 'LIKE', "%{$word}%")
                                            ->orWhere('last_name', 'LIKE', "%{$word}%")
                                            ->orWhere('middle_name', 'LIKE', "%{$word}%")
                                            ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$word}%"])
                                            ->orWhereRaw("CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?", ["%{$word}%"])
                                            ->orWhereRaw("CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name) LIKE ?", ["%{$word}%"]);
                                    })
                                        ->orWhereHas('user.redCrossUnit', function ($redCrossUnitQuery) use ($word) {
                                            $redCrossUnitQuery->where('name', 'LIKE', "%{$word}%");
                                        });
                                });
                        });

                        // 5) Anonymous keyword
                        if (strtolower($word) === 'anonymous') {
                            $wordQuery->orWhere('anonymous', true);
                        }
                    });
                }
            });
        }

        // Branch filter (only if not restricted by access level)
        if ($accessLevel === 'national' && $request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // Division filter (only if not restricted by access level)
        if (in_array($accessLevel, ['national', 'branch']) && $request->filled('division_id')) {
            $query->where('division_id', $request->division_id);
        }

        // Red Cross Unit filter (via the donation's user->red_cross_unit_id)
        if ($request->filled('red_cross_unit_id')) {
            $query->whereHas('user', function ($userQuery) use ($request) {
                $userQuery->where('red_cross_unit_id', $request->red_cross_unit_id);
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'date_desc');

        switch ($sortBy) {
            case 'date_asc':
                $query->orderBy('date_donation', 'asc');
                break;
            case 'date_desc':
            default:
                $query->orderBy('date_donation', 'desc');
                break;
            case 'amount_asc':
                $query->orderBy('amount', 'asc')->orderBy('date_donation', 'desc');
                break;
            case 'amount_desc':
                $query->orderBy('amount', 'desc')->orderBy('date_donation', 'desc');
                break;
        }

        $donations = $query->paginate(15)
            ->appends($request->query());

        $totalRecords = $donations->total();

        // Filter options for dropdowns based on access level
        $branches = collect();
        $divisions = collect();
        $redCrossUnits = collect();

        switch ($accessLevel) {
            case 'national':
                $branches = Branch::select('id', 'name')->orderBy('name')->get();
                if ($request->filled('branch_id')) {
                    $divisions = Division::where('branch_id', $request->branch_id)
                        ->select('id', 'name')->orderBy('name')->get();
                }
                break;

            case 'branch':
                if ($userBranchId) {
                    $branches = Branch::where('id', $userBranchId)
                        ->select('id', 'name')->orderBy('name')->get();
                    $divisions = Division::where('branch_id', $userBranchId)
                        ->select('id', 'name')->orderBy('name')->get();
                }
                break;

            case 'division':
                if ($userDivisionId) {
                    $userDivision = Division::find($userDivisionId);
                    if ($userDivision) {
                        $branches = Branch::where('id', $userDivision->branch_id)
                            ->select('id', 'name')->orderBy('name')->get();
                        $divisions = Division::where('id', $userDivisionId)
                            ->select('id', 'name')->orderBy('name')->get();
                    }
                }
                break;
        }

        // Populate Red Cross Units based on division_id or default division scope
        if ($request->filled('division_id')) {
            if ($accessLevel === 'division' && $userDivisionId && (string) $userDivisionId !== (string) $request->division_id) {
                $redCrossUnits = collect();
            } else {
                $redCrossUnits = RedCrossUnit::where('division_id', $request->division_id)
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get();
            }
        } elseif ($accessLevel === 'division' && $userDivisionId) {
            $redCrossUnits = RedCrossUnit::where('division_id', $userDivisionId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        }

        $pendingApprovalCount = $user->can('approve_donations')
            ? Donation::eligibleForApproval($user)->count()
            : 0;

        return view('donations.index', compact(
            'donations',
            'branches',
            'divisions',
            'redCrossUnits',
            'totalRecords',
            'hasFilters',
            'accessLevel',
            'scopedId',
            'userBranchId',
            'userDivisionId',
            'pendingApprovalCount'
        ));
    }

    /**
     * Show the donation creation form for an organisation.
     */
    public function createForOrganisation(Organisation $organisation): View
    {
        $organisation->load(['users', 'branch']);

        return view('donations.create_for_organisation', compact('organisation'));
    }

    /**
     * Show the form for creating a new donation.
     */
    public function create(?User $user = null): View
    {
        if ($user) {
            $viewer = Auth::user();
            $accessLevel = $viewer->getAccessLevel();
            $scopedId = $viewer->getScopedId();

            // Authorization check to ensure user can be viewed.
            switch ($accessLevel) {
                case 'branch':
                    if ($user->branch_id != $scopedId) {
                        abort(403, 'You are not authorized to view this user.');
                    }
                    break;
                case 'division':
                    if ($user->division_id != $scopedId) {
                        abort(403, 'You are not authorized to view this user.');
                    }
                    break;
                    // 'national' can view all users.
            }

            $user->load(['branch:id,name,code', 'division:id,name']);
        }

        // For the donations.create view overhaul, we don't need to fetch all users, branches, divisions initially.
        // These will be dynamically loaded via searchUsers and set via JavaScript.
        // However, we do need to fetch 'myRecentDonations' for the table at the bottom.

        // Submitter's own recent entries — show ALL statuses (pending/approved/rejected)
        // so they can withdraw pending ones and see why rejected ones were declined.
        $myRecentDonations = Donation::withAnyApprovalStatus()
            ->with(['user.branch', 'branch', 'division'])
            ->where('entered_by_user_id', Auth::id())
            ->where('is_deleted', false)
            ->whereHas('user')
            ->orderBy('created_at', 'desc')
            ->orderBy('date_donation', 'desc')
            ->paginate(10, ['*'], 'my_donations');

        return view('donations.create', compact('myRecentDonations', 'user'));
    }

    /**
     * Store a newly created donation in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'organisation_id' => 'nullable|exists:organisations,id',
            'amount' => 'nullable|numeric|min:0',
            'date_donation' => 'required|date',
            'in_kind_donation' => 'boolean',
            'donation_item' => 'sometimes|required_if:in_kind_donation,true|string|max:60',
            'reference' => 'nullable|string|max:45',
            'purpose' => 'nullable|string|max:60',
            'anonymous' => 'boolean',
            'branch_id' => 'nullable|exists:branches,id',
            'division_id' => 'nullable|exists:divisions,id',
        ]);

        if (! ($validated['in_kind_donation'] ?? false)) {
            $request->validate(['amount' => 'required|numeric|min:1']);
            $validated['donation_item'] = 'Naira';
        }

        $validated['entered_by_user_id'] = auth()->id();
        $validated['is_deleted'] = false;

        // Created as pending (approval_status DB default). A submission is NOT member
        // activity any more — the member is only activated on approval (Phase 2).
        $donation = Donation::create($validated);

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        if ($request->filled('organisation_id')) {
            return redirect()->route('organisations.show', $request->organisation_id)
                ->with('success', 'Donation recorded successfully.');
        }

        return redirect()->route('donations.create')
            ->with('success', 'Donation created successfully.');
    }

    /**
     * Display the specified donation.
     */
    public function show(Donation $donation): View
    {
        $donation->load(['user', 'branch', 'division', 'enteredBy', 'removedBy', 'decidedByUser']);

        $viewer = Auth::user();
        $accessLevel = $viewer->getAccessLevel();
        $scopedId = $viewer->getScopedId();

        // Check if the viewer has the appropriate scope or is the owner/submitter of the record.
        switch ($accessLevel) {
            case 'branch':
                if ($donation->branch_id != $scopedId) {
                    abort(403, 'You are not authorized to view this donation record.');
                }
                break;
            case 'division':
                if ($donation->division_id != $scopedId) {
                    abort(403, 'You are not authorized to view this donation record.');
                }
                break;
            case 'national':
                // National admins can view all records.
                break;
            default:
                // For users without a special access level, they can only view if they entered it.
                if ($viewer->id !== $donation->entered_by_user_id) {
                    abort(403, 'You are not authorized to view this donation record.');
                }
                break;
        }

        return view('donations.show', compact('donation'));
    }

    /**
     * Show the form for editing the specified donation.
     */
    public function edit(Donation $donation): View
    {
        $viewer = Auth::user();
        $accessLevel = $viewer->getAccessLevel();
        $scopedId = $viewer->getScopedId();

        // Authorization check for admin roles.
        switch ($accessLevel) {
            case 'branch':
                if ($donation->branch_id != $scopedId) {
                    abort(403, 'You are not authorized to edit this donation record.');
                }
                break;
            case 'division':
                if ($donation->division_id != $scopedId) {
                    abort(403, 'You are not authorized to edit this donation record.');
                }
                break;
            case 'national':
                // National admins can edit all records.
                break;
            default:
                // Users without admin-level access cannot edit records.
                abort(403, 'You are not authorized to edit this donation record.');
        }

        $users = User::select('id', 'first_name', 'middle_name', 'last_name')
            ->where('lifecycle_status', '!=', 'archived')
            ->orderBy('first_name')
            ->get();

        $branches = Branch::orderBy('name')->get();
        $divisions = Division::orderBy('name')->get();

        return view('donations.edit', compact('donation', 'users', 'branches', 'divisions'));
    }

    /**
     * Update the specified donation in storage.
     */
    public function update(Request $request, Donation $donation): RedirectResponse
    {
        $viewer = Auth::user();
        $accessLevel = $viewer->getAccessLevel();
        $scopedId = $viewer->getScopedId();

        // Authorization check for admin roles before any action.
        switch ($accessLevel) {
            case 'branch':
                if ($donation->branch_id != $scopedId) {
                    abort(403, 'You are not authorized to update this donation record.');
                }
                break;
            case 'division':
                if ($donation->division_id != $scopedId) {
                    abort(403, 'You are not authorized to update this donation record.');
                }
                break;
            case 'national':
                // National admins can update all records.
                break;
            default:
                // Users without admin-level access cannot update records.
                abort(403, 'You are not authorized to update this donation record.');
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'amount' => 'nullable|numeric|min:0', // Amount is nullable here, specific validation below
            'date_donation' => 'required|date',
            'in_kind_donation' => 'boolean',
            'donation_item' => 'sometimes|required_if:in_kind_donation,true|string|max:60', // Conditional validation
            'reference' => 'nullable|string|max:45',
            'purpose' => 'nullable|string|max:60',
            'anonymous' => 'boolean',
            'branch_id' => 'nullable|exists:branches,id',
            'division_id' => 'nullable|exists:divisions,id',
        ]);

        // If it's not an in-kind donation:
        // 1. Amount is required and must be at least 1.
        // 2. Donation item should be "Naira".
        if (! ($validated['in_kind_donation'] ?? false)) {
            $request->validate(['amount' => 'required|numeric|min:1']);
            $validated['donation_item'] = 'Naira'; // Set donation_item to "Naira" for cash donations
        }

        $donation->update($validated);

        // Editing an approved record demotes it back to pending for a fresh
        // approval cycle (no-op if it wasn't approved); resetApprovalOnEdit()
        // already recomputes lifecycle as part of that demotion.
        $donation->resetApprovalOnEdit();

        // Editing an APPROVED record can change freshness, so recompute lifecycle.
        // A pending record has no lifecycle effect until it is approved.
        if ($donation->isApproved()) {
            optional(User::find($donation->user_id))->recalculateLifecycle();
        }

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return redirect()->route('donations.index')
            ->with('success', 'Donation updated successfully.');
    }

    /**
     * Remove the specified donation from storage.
     */
    /**
     * Remove the specified donation from storage.
     */
    public function destroy(Donation $donation): RedirectResponse
    {
        $viewer = Auth::user();
        $accessLevel = $viewer->getAccessLevel();
        $scopedId = $viewer->getScopedId();

        // Authorization check for admin roles.
        switch ($accessLevel) {
            case 'branch':
                if ($donation->branch_id != $scopedId) {
                    abort(403, 'You are not authorized to delete this donation record.');
                }
                break;
            case 'division':
                if ($donation->division_id != $scopedId) {
                    abort(403, 'You are not authorized to delete this donation record.');
                }
                break;
            case 'national':
                // National admins can delete all records.
                break;
            default:
                // Users without admin-level access cannot delete records.
                abort(403, 'You are not authorized to delete this donation record.');
        }

        // Instead of hard delete, mark as deleted
        $donation->update([
            'is_deleted' => true,
            'removed_by_user_id' => auth()->id(),
            'removed_date' => now()->toDateString(),
        ]);

        if ($donation->user) {
            $donation->user->recalculateLifecycle();
        }

        return redirect()->route('donations.show', $donation)
            ->with('deleted', 'Donation deleted successfully.');
    }

    /**
     * Search for users based on ID or full name.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchUsers(Request $request)
    {
        $query = $request->get('query');

        if (empty($query)) {
            return response()->json([]);
        }

        $searcher = Auth::user();
        $accessLevel = $searcher->getAccessLevel();
        $scopedId = $searcher->getScopedId();

        $usersQuery = User::selectableForEntry();

        // Apply access level scope
        switch ($accessLevel) {
            case 'branch':
                $usersQuery->where('branch_id', $scopedId);
                break;
            case 'division':
                $usersQuery->where('division_id', $scopedId);
                break;
                // 'national' can search all users.
        }

        $usersQuery->where(function ($q) use ($query) {
            $q->where('id', $query)
                ->orWhere('first_name', 'LIKE', "%{$query}%")
                ->orWhere('last_name', 'LIKE', "%{$query}%")
                ->orWhere('middle_name', 'LIKE', "%{$query}%")
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$query}%"])
                ->orWhereRaw("CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?", ["%{$query}%"])
                ->orWhereRaw("CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name) LIKE ?", ["%{$query}%"]);
        });

        $users = $usersQuery
            ->select('id', 'first_name', 'middle_name', 'last_name', 'email', 'telephone1', 'branch_id', 'division_id', 'red_cross_unit_id', 'lifecycle_status')
            ->with(['branch:id,name,code', 'division:id,name', 'redCrossUnit:id,name'])
            ->limit(50)
            ->get();

        return response()->json($users);
    }
}
