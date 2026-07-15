<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Division;
use App\Models\RedCrossUnit;
use App\Models\Training;
use App\Models\TrainingType;
use App\Models\User; // Import RedCrossUnit model
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // Import Auth facade
use Illuminate\Http\JsonResponse; // Import Carbon for date calculations
use Illuminate\Http\Request; // Import Log facade for error logging
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TrainingController extends Controller
{
    use \App\Http\Controllers\Concerns\HandlesRecordApproval;
    use AuthorizesRequests;

    protected function approvalModelClass(): string
    {
        return Training::class;
    }

    protected function approvalLabel(): string
    {
        return 'Training';
    }

    protected function approvalRouteName(): string
    {
        return 'trainings';
    }

    protected function approvalPermission(): string
    {
        return 'approve_training';
    }

    /**
     * Display a listing of the trainings.
     */
    public function index(Request $request)
    {
        // Eager load all necessary relationships, including user's branch and division
        $query = Training::with(['user.branch', 'user.division', 'user.redCrossUnit', 'trainingType', 'branch', 'division', 'submittedByUser']);

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

        // Get current user's access level and scoped ID
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId(); // This is branch_id for 'branch', division_id for 'division'

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
                // 'national' level sees all, so no additional filter here
        }

        // Get total count before applying filters (for display when no filters are applied)
        // This count is for the *unfiltered* total, or the total records if no filters are applied.
        $totalRecordsQuery = Training::where('is_deleted', false);

        // Apply access level to total records count as well
        switch ($accessLevel) {
            case 'branch':
                if ($userBranchId) {
                    $totalRecordsQuery->where('branch_id', $userBranchId);
                }
                break;
            case 'division':
                if ($userDivisionId) {
                    $totalRecordsQuery->where('division_id', $userDivisionId);
                }
                break;
        }
        $totalRecords = $totalRecordsQuery->count(); // Adjust if 'is_deleted' filter is always applied for total

        // Determine if any filters are applied
        $hasFilters = $request->filled('search') ||
            $request->filled('my_records') ||
            $request->filled('training_type_id') ||
            ($accessLevel === 'national' && $request->filled('branch_id')) || // Only consider branch_id a filter if national level
            (in_array($accessLevel, ['national', 'branch']) && $request->filled('division_id')) || // Only consider division_id a filter if national or branch level
            $request->filled('red_cross_unit_id') ||
            $request->filled('status') || // Check if status filter is applied
            in_array($trashed, ['only', 'with']) ||
            $request->get('sort_by', 'training_date_desc') !== 'training_date_desc'; // Check if sort is not default

        // Handle "My Records" filter
        if ($request->filled('my_records') && $request->my_records == '1') {
            $query->where('submitted_by_user_id', auth()->id());
        }

        // Handle training type filter
        if ($request->filled('training_type_id')) {
            $query->where('training_type_id', $request->training_type_id);
        }

        // Handle branch filter (only if not restricted by user's access level)
        // If national, allow branch filter from request. If branch/division, filter is already applied above.
        if ($accessLevel === 'national' && $request->filled('branch_id')) {
            $query->where('branch_id', $request->branch_id);
        }

        // Handle division filter (only if not restricted by user's access level)
        // If national or branch, allow division filter from request. If division, filter is already applied above.
        if (in_array($accessLevel, ['national', 'branch']) && $request->filled('division_id')) {
            $query->where('division_id', $request->division_id);
        }

        // Handle Red Cross Unit filter (through the user relationship)
        if ($request->filled('red_cross_unit_id')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('red_cross_unit_id', $request->red_cross_unit_id);
            });
        }

        // Handle search functionality
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $searchWords = preg_split('/\s+/', $searchTerm, -1, PREG_SPLIT_NO_EMPTY);

            $query->where(function ($mainQuery) use ($searchWords) {
                foreach ($searchWords as $word) {
                    $mainQuery->where(function ($wordQuery) use ($word) {
                        // Search by member (user relationship)
                        $wordQuery->orWhereHas('user', function ($userQuery) use ($word) {
                            $userQuery->where('first_name', 'LIKE', "%{$word}%")
                                ->orWhere('last_name', 'LIKE', "%{$word}%")
                                ->orWhere('middle_name', 'LIKE', "%{$word}%")
                                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$word}%"])
                                ->orWhereRaw("CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?", ["%{$word}%"])
                                ->orWhereRaw("CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name) LIKE ?", ["%{$word}%"]);
                        })
                            // OR search by training type
                            ->orWhereHas('trainingType', function ($trainingTypeQuery) use ($word) {
                                $trainingTypeQuery->where('name', 'LIKE', "%{$word}%");
                            })
                            // OR search by branch name
                            ->orWhereHas('branch', function ($branchQuery) use ($word) {
                                $branchQuery->where('name', 'LIKE', "%{$word}%");
                            })
                            // OR search by division name
                            ->orWhereHas('division', function ($divisionQuery) use ($word) {
                                $divisionQuery->where('name', 'LIKE', "%{$word}%");
                            })
                            // OR search by Red Cross Unit name (through user)
                            ->orWhereHas('user.redCrossUnit', function ($redCrossUnitQuery) use ($word) {
                                $redCrossUnitQuery->where('name', 'LIKE', "%{$word}%");
                            })
                            // OR search by reference (original database column)
                            ->orWhere('reference', 'LIKE', "%{$word}%")
                            // OR search by the generated training_reference format
                            ->orWhereRaw("CONCAT('TRN-', trainings.id) LIKE ?", ["%{$word}%"]);

                        if (is_numeric($word)) {
                            $wordQuery->orWhere('trainings.user_id', (int) $word);
                        }
                    });
                }
            });
        }

        // Handle status filter
        if ($request->filled('status')) {
            // All these statuses imply non-deleted trainings
            if ($trashed !== 'only') {
                $query->where('is_deleted', false);
            }

            switch ($request->status) {
                case 'valid':
                    // A training is valid if it's not deleted, and either has no expiry (valid_years is null)
                    // or its expiry date is more than 30 days from now.
                    $query->where(function ($q) {
                        $q->whereNull('valid_years')
                            ->orWhere(function ($subQ) {
                                $subQ->whereNotNull('valid_years')
                                    ->whereNotNull('training_date')
                                    ->whereRaw('DATE_ADD(training_date, INTERVAL valid_years YEAR) > ?', [Carbon::now()->addDays(30)->toDateString()]);
                            });
                    });
                    break;
                case 'expired':
                    // A training is expired if it's not deleted, has an expiry, and its expiry date is in the past.
                    $query->whereNotNull('training_date')
                        ->whereNotNull('valid_years')
                        ->whereRaw('DATE_ADD(training_date, INTERVAL valid_years YEAR) < ?', [Carbon::now()->toDateString()]);
                    break;
                case 'expiring_2_weeks':
                    // Trainings expiring within 2 weeks (inclusive of today, up to 14 days from today)
                    $query->whereNotNull('training_date')
                        ->whereNotNull('valid_years')
                        ->whereRaw('DATE_ADD(training_date, INTERVAL valid_years YEAR) BETWEEN ? AND ?', [Carbon::now()->toDateString(), Carbon::now()->addDays(14)->toDateString()]);
                    break;
                case 'expiring_4_weeks':
                    // Trainings expiring within 4 weeks (inclusive of today, up to 28 days from today)
                    $query->whereNotNull('training_date')
                        ->whereNotNull('valid_years')
                        ->whereRaw('DATE_ADD(training_date, INTERVAL valid_years YEAR) BETWEEN ? AND ?', [Carbon::now()->toDateString(), Carbon::now()->addDays(28)->toDateString()]);
                    break;
            }
        }

        // Handle sorting
        $sortBy = $request->get('sort_by', 'training_date_desc'); // Default sort

        switch ($sortBy) {
            case 'training_date_asc':
                $query->orderBy('training_date', 'asc');
                break;
            case 'training_date_desc':
            default:
                $query->orderBy('training_date', 'desc');
                break;
                // Removed duration sorting options as requested
            case 'training_type_asc':
                $query->join('training_types', 'trainings.training_type_id', '=', 'training_types.id')
                    ->orderBy('training_types.name', 'asc')
                    ->orderBy('trainings.training_date', 'desc')
                    ->select('trainings.*'); // Select trainings.* to avoid column ambiguity
                break;
            case 'training_type_desc':
                $query->join('training_types', 'trainings.training_type_id', '=', 'training_types.id')
                    ->orderBy('training_types.name', 'desc')
                    ->orderBy('trainings.training_date', 'desc')
                    ->select('trainings.*'); // Select trainings.* to avoid column ambiguity
                break;
        }

        $trainings = $query->paginate(15)
            ->appends($request->query());

        // Get filter options based on access level
        $trainingTypes = TrainingType::select('id', 'name')
            ->orderBy('name')
            ->get();

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

        // Populate Red Cross Units based on division_id or pre-selected red_cross_unit_id, respecting access level
        if ($request->filled('division_id')) {
            if ($accessLevel === 'division' && $userDivisionId && (string) $userDivisionId !== (string) $request->division_id) {
                // If user is division-scoped and tries to filter by another division, ignore
                $redCrossUnits = collect();
            } else {
                $redCrossUnits = RedCrossUnit::where('division_id', $request->division_id)
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get();
            }
        } elseif ($accessLevel === 'division' && $userDivisionId) {
            // If division level user, default to their division's units
            $redCrossUnits = RedCrossUnit::where('division_id', $userDivisionId)
                ->select('id', 'name')
                ->orderBy('name')
                ->get();
        }

        $pendingApprovalCount = $user->can('approve_training')
            ? Training::eligibleForApproval($user)->count()
            : 0;

        return view('trainings.index', compact(
            'trainings',
            'trainingTypes',
            'branches',
            'divisions',
            'redCrossUnits', // Pass Red Cross Units to the view
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
     * Show the form for creating a new training.
     */
    public function create(?User $user = null)
    {
        if ($user) {
            // Authorize that the logged-in user can view the pre-selected user.
            $this->authorize('view', $user);
            // Eager load relationships needed for the form's JavaScript
            $user->load(['branch:id,name,code', 'division:id,name', 'redCrossUnit:id,name']);
        }

        // Fetch training types ordered by group name then training type name
        $trainingTypes = TrainingType::with('group')->orderByGroupThenName()->get();

        // Fetch user's recent trainings for the table at the bottom
        // Submitter's own recent entries — show ALL statuses so they can withdraw
        // pending ones and see rejection reasons.
        $myRecentTrainings = Training::withAnyApprovalStatus()
            ->with(['user', 'trainingType', 'branch', 'division'])
            ->where('submitted_by_user_id', auth()->id())
            ->where('is_deleted', false)
            ->whereHas('user')
            ->whereHas('trainingType')
            ->orderBy('created_at', 'desc')
            ->orderBy('training_date', 'desc')
            ->paginate(10, ['*'], 'my_trainings'); // Use a unique pagination name

        return view('trainings.create', compact('trainingTypes', 'myRecentTrainings', 'user'));
    }

    /**
     * Store a newly created training in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'training_type_id' => 'required|exists:training_types,id',
            'training_date' => 'required|date',
            'duration' => 'nullable|integer|min:1',
            'submission_name' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'division_id' => 'nullable|exists:divisions,id',
        ]);

        // Validity is HQ-governed per training type — derived here, never user-entered (see DECISIONS.md).
        $trainingType = TrainingType::find($validated['training_type_id']);
        $validated['valid_years'] = $trainingType?->validity_years_limit;

        $validated['submitted_at'] = now();
        $validated['submitted_by_user_id'] = auth()->id();
        $validated['is_deleted'] = false;

        $training = Training::create($validated);

        // Created as pending (approval_status DB default). Submission is no longer
        // member activity, so no markActive(). The new pending training is excluded by
        // ApprovedScope, so recalculateLastFirstAidAt() won't bump the denormalised
        // first-aid date until the record is approved.
        $user = User::find($training->user_id);
        $user->recalculateLastFirstAidAt();

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return redirect()->route('trainings.create')->with('success', 'Training record created successfully.');
    }

    /**
     * Display the specified training.
     */
    public function show(Training $training)
    {
        $training->load(['user.redCrossUnit', 'trainingType', 'branch', 'division', 'submittedByUser', 'decidedByUser']); // Eager load user.redCrossUnit

        $viewer = Auth::user();
        $accessLevel = $viewer->getAccessLevel();
        $scopedId = $viewer->getScopedId();

        // Check if the viewer has the appropriate scope or is the owner/submitter of the record.
        switch ($accessLevel) {
            case 'branch':
                if ($training->branch_id != $scopedId) {
                    abort(403, 'You are not authorized to view this training record.');
                }
                break;
            case 'division':
                if ($training->division_id != $scopedId) {
                    abort(403, 'You are not authorized to view this training record.');
                }
                break;
            case 'national':
                // National admins can view all records.
                break;
            default:
                // For users without a special access level, they can only view if they are the subject of the training or submitted it.
                if ($viewer->id !== $training->user_id && $viewer->id !== $training->submitted_by_user_id) {
                    abort(403, 'You are not authorized to view this training record.');
                }
                break;
        }

        return view('trainings.show', compact('training'));
    }

    /**
     * Show the form for editing the specified training.
     */
    public function edit(Training $training)
    {
        $viewer = Auth::user();
        $accessLevel = $viewer->getAccessLevel();
        $scopedId = $viewer->getScopedId();

        // Authorization check for admin roles.
        switch ($accessLevel) {
            case 'branch':
                if ($training->branch_id != $scopedId) {
                    abort(403, 'You are not authorized to edit this training record.');
                }
                break;
            case 'division':
                if ($training->division_id != $scopedId) {
                    abort(403, 'You are not authorized to edit this training record.');
                }
                break;
            case 'national':
                // National admins can edit all records.
                break;
            default:
                // Users without admin-level access cannot edit records.
                abort(403, 'You are not authorized to edit this training record.');
        }

        // Fetch training types ordered by group name then training type name
        $trainingTypes = TrainingType::with('group')->orderByGroupThenName()->get();
        $branches = Branch::orderBy('name')->get();
        $divisions = Division::orderBy('name')->get();
        $redCrossUnits = RedCrossUnit::orderBy('name')->get(); // Add this line

        return view('trainings.edit', compact('training', 'trainingTypes', 'branches', 'divisions', 'redCrossUnits')); // Add 'redCrossUnits'
    }

    /**
     * Update the specified training in storage.
     */
    public function update(Request $request, Training $training)
    {
        $viewer = Auth::user();
        $accessLevel = $viewer->getAccessLevel();
        $scopedId = $viewer->getScopedId();

        // Authorization check for admin roles before any action.
        switch ($accessLevel) {
            case 'branch':
                if ($training->branch_id != $scopedId) {
                    abort(403, 'You are not authorized to update this training record.');
                }
                break;
            case 'division':
                if ($training->division_id != $scopedId) {
                    abort(403, 'You are not authorized to update this training record.');
                }
                break;
            case 'national':
                // National admins can update all records.
                break;
            default:
                // Users without admin-level access cannot update records.
                abort(403, 'You are not authorized to update this training record.');
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'training_type_id' => 'required|exists:training_types,id',
            'training_date' => 'required|date',
            'duration' => 'nullable|integer|min:1',
            'valid_years' => 'nullable|integer|min:1|max:50',
            'submission_name' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'division_id' => 'nullable|exists:divisions,id',
        ]);

        // Capture the user this training belonged to BEFORE the update, in case it gets reassigned.
        $originalUserId = $training->user_id;

        $training->update($validated);

        $user = User::find($training->user_id);

        // Recompute lifecycle only for an APPROVED record; a pending one has no effect.
        if ($training->isApproved()) {
            $user->recalculateLifecycle();
        }

        // Recompute the authoritative latest first-aid date on the affected user(s).
        $user->recalculateLastFirstAidAt();
        if ($originalUserId !== $training->user_id) {
            optional(User::find($originalUserId))->recalculateLastFirstAidAt();
        }

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return redirect()->route('trainings.index')->with('success', 'Training record updated successfully.');
    }

    /**
     * Remove the specified training from storage (soft delete).
     */
    public function destroy(Training $training)
    {
        $viewer = Auth::user();
        $accessLevel = $viewer->getAccessLevel();
        $scopedId = $viewer->getScopedId();

        // Authorization check for admin roles.
        switch ($accessLevel) {
            case 'branch':
                if ($training->branch_id != $scopedId) {
                    abort(403, 'You are not authorized to delete this training record.');
                }
                break;
            case 'division':
                if ($training->division_id != $scopedId) {
                    abort(403, 'You are not authorized to delete this training record.');
                }
                break;
            case 'national':
                // National admins can delete all records.
                break;
            default:
                // Users without admin-level access cannot delete records.
                abort(403, 'You are not authorized to delete this training record.');
        }

        $training->update([
            'is_deleted' => true,
            'removed_by_user_id' => auth()->id(),
            'removed_date' => now(),
        ]);

        if ($training->user) {
            $training->user->recalculateLifecycle();
            $training->user->recalculateLastFirstAidAt();
        }

        return redirect()->route('trainings.show', $training)->with('deleted', 'Training record deleted successfully.');
    }

    /**
     * Search for users based on ID or full name.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchUsers(Request $request)
    {
        try {
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
                    ->orWhere('last_name', 'LIKE', "%{$query}%")
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

            $users = $usersQuery->select('id', 'first_name', 'middle_name', 'last_name', 'email', 'telephone1', 'branch_id', 'division_id', 'red_cross_unit_id', 'lifecycle_status')
                // Eager load Branch, Division, and RedCrossUnit for the selected user
                ->with(['branch:id,name', 'division:id,name', 'redCrossUnit:id,name'])
                ->limit(50)
                ->get();

            return response()->json($users);
        } catch (\Exception $e) {
            Log::error('Error in TrainingController@searchUsers: '.$e->getMessage(), ['exception' => $e]);

            return response()->json(['error' => 'An internal server error occurred during user search. Please check logs for details.'], 500);
        }
    }

    /**
     * Get divisions by branch ID for AJAX requests.
     */
    public function getDivisionsByBranch(Request $request): JsonResponse
    {
        $branchId = $request->input('branch_id');
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId(); // branch_id for 'branch', division_id for 'division'

        if (! $branchId) {
            return response()->json([]); // Return empty if no branch ID is provided
        }

        $divisionsQuery = Division::where('branch_id', $branchId);

        // If branch-level user, they can only see divisions within their scoped branch
        if ($accessLevel === 'branch' && $scopedId && (string) $scopedId !== (string) $branchId) {
            return response()->json([]); // Return empty if trying to get divisions outside their branch
        }
        // If division-level user, they can only see their own division
        if ($accessLevel === 'division' && $scopedId) {
            $userDivision = Division::find($scopedId); // Get the user's actual division object
            if ($userDivision && (string) $userDivision->branch_id !== (string) $branchId) {
                return response()->json([]); // Division-level user trying to get divisions for a different branch
            }
            $divisionsQuery->where('id', $scopedId); // Restrict to their specific division
        }

        $divisions = $divisionsQuery->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($divisions);
    }

    /**
     * Get Red Cross Units by division ID for AJAX requests.
     */
    public function getRedCrossUnitsByDivision(Request $request): JsonResponse
    {
        $divisionId = $request->input('division_id');
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId(); // branch_id for 'branch', division_id for 'division'

        if (! $divisionId) {
            return response()->json([]); // Return empty if no division ID is provided
        }

        $unitsQuery = RedCrossUnit::where('division_id', $divisionId);

        // If division-level user, they can only see units within their scoped division
        if ($accessLevel === 'division' && $scopedId && (string) $scopedId !== (string) $divisionId) {
            return response()->json([]); // Return empty if trying to get units outside their division
        }

        $units = $unitsQuery->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($units);
    }
}
