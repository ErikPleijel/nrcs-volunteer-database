<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Branch;
use App\Models\Division;
use App\Models\RedCrossUnit;
use App\Models\TaskForce; // Make sure to import TaskForce
use App\Models\User;
use Carbon\Carbon; // Import the Auth facade for Auth::id()
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // Import JsonResponse for the new method
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityController extends Controller
{
    use \App\Http\Controllers\Concerns\HandlesRecordApproval;
    use AuthorizesRequests;

    protected function approvalModelClass(): string
    {
        return Activity::class;
    }

    protected function approvalLabel(): string
    {
        return 'Activity';
    }

    protected function approvalRouteName(): string
    {
        return 'activities';
    }

    protected function approvalPermission(): string
    {
        return 'approve_volunteering';
    }

    /**
     * Display a listing of activities.
     */
    public function index(Request $request)
    {
        // Change 'redCrossUnit' to 'assignable' for eager loading the polymorphic relation
        $query = Activity::with(['activityType', 'user', 'submittedByUser', 'branch', 'division', 'assignable']);

        $trashedFilter = $request->get('trashed');

        switch ($trashedFilter) {
            case 'only':
                $query->onlyTrashed();
                break;
            case 'with':
                $query->withTrashed();
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

        // Determine if any filters are applied
        $hasFilters = $request->filled('search') ||
            $request->filled('my_records') ||
            $request->filled('activity_type_id') ||
            ($accessLevel === 'national' && $request->filled('branch_id')) || // Only consider branch_id a filter if national level
            (in_array($accessLevel, ['national', 'branch']) && $request->filled('division_id')) || // Only consider division_id a filter if national or branch level
            $request->filled('red_cross_unit_id') ||
            $request->get('sort_by', 'date_desc') !== 'date_desc' || // Check if sort is not default
            in_array($trashedFilter, ['only', 'with']);

        // Handle "My Records" filter
        if ($request->filled('my_records') && $request->my_records == '1') {
            $query->where('submitted_by_user_id', auth()->id());
        }

        // Handle activity type filter
        if ($request->filled('activity_type_id')) {
            $query->where('activity_type_id', $request->activity_type_id);
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

        // Handle Red Cross Unit filter - Use the specific scope for polymorphic filtering
        if ($request->filled('red_cross_unit_id')) {
            $query->forRedCrossUnit($request->red_cross_unit_id);
        }

        // Handle search functionality (unchanged)
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            // Split the search term into individual words, removing empty strings
            $searchWords = preg_split('/\s+/', $searchTerm, -1, PREG_SPLIT_NO_EMPTY);

            $query->where(function ($mainQuery) use ($searchWords) {
                foreach ($searchWords as $word) {
                    // Each word must be found somewhere in the activity's related data
                    $mainQuery->where(function ($wordQuery) use ($word) {
                        // Search by member (user_id relationship)
                        $wordQuery->orWhereHas('user', function ($userQuery) use ($word) {
                            $userQuery->where(function ($q) use ($word) {
                                $q->where('first_name', 'LIKE', "%{$word}%")
                                    ->orWhere('last_name', 'LIKE', "%{$word}%")
                                    ->orWhere('middle_name', 'LIKE', "%{$word}%")
                                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$word}%"])
                                    ->orWhereRaw("CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?", ["%{$word}%"])
                                    ->orWhereRaw("CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name) LIKE ?", ["%{$word}%"]);

                                if (is_numeric($word)) {
                                    $q->orWhere('id', $word);
                                }
                            });
                        })
                            // OR search by submitted by user
                            ->orWhereHas('submittedByUser', function ($submitterQuery) use ($word) {
                                $submitterQuery->where(function ($q) use ($word) {
                                    $q->where('first_name', 'LIKE', "%{$word}%")
                                        ->orWhere('last_name', 'LIKE', "%{$word}%")
                                        ->orWhere('middle_name', 'LIKE', "%{$word}%")
                                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$word}%"])
                                        ->orWhereRaw("CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ?", ["%{$word}%"])
                                        ->orWhereRaw("CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name) LIKE ?", ["%{$word}%"]);

                                    if (is_numeric($word)) {
                                        $q->orWhere('id', $word);
                                    }
                                });
                            })
                            // OR search by activity type
                            ->orWhereHas('activityType', function ($activityTypeQuery) use ($word) {
                                $activityTypeQuery->where('name', 'LIKE', "%{$word}%");
                            })
                            // OR search by branch name
                            ->orWhereHas('branch', function ($branchQuery) use ($word) {
                                $branchQuery->where('name', 'LIKE', "%{$word}%");
                            })
                            // OR search by division name
                            ->orWhereHas('division', function ($divisionQuery) use ($word) {
                                $divisionQuery->where('name', 'LIKE', "%{$word}%");
                            })
                            // OR search by Red Cross Unit OR TaskForce name if assignable
                            ->orWhereHasMorph('assignable', [\App\Models\RedCrossUnit::class, \App\Models\TaskForce::class], function ($assignableQuery) use ($word) {
                                $assignableQuery->where('name', 'LIKE', "%{$word}%");
                            })
                            // OR search by reference or submission name
                            ->orWhere('reference', 'LIKE', "%{$word}%")
                            ->orWhere('submission_name', 'LIKE', "%{$word}%");

                        // OR search by the activity's own id (numeric words only)
                        if (is_numeric($word)) {
                            $wordQuery->orWhere('activities.id', $word);
                        }
                    });
                }
            });
        }

        // Handle sorting (unchanged)
        $sortBy = $request->get('sort_by', 'date_desc'); // Default sort

        switch ($sortBy) {
            case 'date_asc':
                $query->orderBy('date', 'asc');
                break;
            case 'date_desc':
            default:
                $query->orderBy('date', 'desc');
                break;
            case 'hours_asc':
                $query->orderBy('hours', 'asc')->orderBy('date', 'desc');
                break;
            case 'hours_desc':
                $query->orderBy('hours', 'desc')->orderBy('date', 'desc');
                break;
            case 'activity_type_asc':
                $query->join('activity_types', 'activities.activity_type_id', '=', 'activity_types.id')
                    ->orderBy('activity_types.name', 'asc')
                    ->orderBy('activities.date', 'desc')
                    ->select('activities.*');
                break;
            case 'activity_type_desc':
                $query->join('activity_types', 'activities.activity_type_id', '=', 'activity_types.id')
                    ->orderBy('activity_types.name', 'desc')
                    ->orderBy('activities.date', 'desc')
                    ->select('activities.*');
                break;
        }

        $activities = $query->paginate(25)
            ->appends($request->query());

        $totalRecords = $activities->total();

        // Get filter options based on access level
        $activityTypes = ActivityType::select('id', 'name')
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

        $pendingApprovalCount = $user->can('approve_volunteering')
            ? Activity::eligibleForApproval($user)->count()
            : 0;

        return view('activities.index', compact(
            'activities',
            'activityTypes',
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
     * Show the form for creating a new resource.
     */
    public function create(?User $user = null)
    {
        if ($user) {
            // Authorize that the logged-in user can view the pre-selected user.
            $this->authorize('view', $user);
            // Eager load relationships needed for the form JavaScript
            $user->load(['branch:id,name,code', 'division:id,name', 'redCrossUnit:id,name', 'taskForces:id,name']);
        }

        $activityTypes = ActivityType::all();
        // Removed RedCrossUnit::all() as it's no longer used for a dropdown

        // This section for `myRecentActivities` should eager load `assignable`
        $myRecentActivities = Activity::with(['user.branch', 'activityType', 'assignable'])
            ->where('submitted_by_user_id', Auth::id())
            ->where('is_deleted', false)
            ->whereHas('user')
            ->whereHas('activityType')
            ->orderBy('date', 'desc')
            ->paginate(10, ['*'], 'my_activities');

        return view('activities.create', compact('activityTypes', 'myRecentActivities', 'user'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'user_id' => 'required|exists:users,id',
            'activity_type_id' => 'required|exists:activity_types,id',
            'date' => 'required|date',
            'hours' => 'required|numeric|min:1|max:24',
            'reference' => 'nullable|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'division_id' => 'nullable|exists:divisions,id',
            'use_user_red_cross_unit' => 'nullable|boolean',
            'user_red_cross_unit_id' => 'nullable|required_if:use_user_red_cross_unit,true|exists:red_cross_units,id',
            'use_user_task_force' => 'nullable|boolean', // New validation for task force checkbox
            'selected_task_force_id' => 'nullable|required_if:use_user_task_force,true|exists:task_forces,id', // New validation for selected task force
            'not_assigned' => 'nullable|boolean', // New validation for 'Not assigned' checkbox
        ]);

        $activity = new Activity;
        $activity->user_id = $validatedData['user_id'];
        $activity->activity_type_id = $validatedData['activity_type_id'];
        $activity->date = $validatedData['date'];
        $activity->hours = $validatedData['hours'];
        $activity->reference = $validatedData['reference'];

        // Assign branch_id and division_id directly from validated data
        $activity->branch_id = $validatedData['branch_id'];
        $activity->division_id = $validatedData['division_id'];

        $activity->submitted_by_user_id = Auth::id(); // Assign the currently authenticated user as the submitter
        $activity->submitted_at = now();

        // Handle the polymorphic relationship for 'assignable'
        // If 'not_assigned' is checked, explicitly set assignable to null.
        if (isset($validatedData['not_assigned']) && $validatedData['not_assigned']) {
            $activity->assignable_id = null;
            $activity->assignable_type = null;
        } elseif (isset($validatedData['use_user_task_force']) && $validatedData['use_user_task_force'] && $validatedData['selected_task_force_id']) {
            $taskForce = TaskForce::find($validatedData['selected_task_force_id']);
            if ($taskForce) {
                $activity->assignable()->associate($taskForce);
            }
        } elseif (isset($validatedData['use_user_red_cross_unit']) && $validatedData['use_user_red_cross_unit'] && $validatedData['user_red_cross_unit_id']) {
            $redCrossUnit = RedCrossUnit::find($validatedData['user_red_cross_unit_id']);
            if ($redCrossUnit) {
                $activity->assignable()->associate($redCrossUnit);
            }
        } else {
            // Default to null if no specific assignment is made or if 'not_assigned' is not checked.
            $activity->assignable_id = null;
            $activity->assignable_type = null;
        }

        $activity->save();

        // Created as pending (approval_status DB default). Submission is no longer
        // member activity — activation happens on approval (Phase 2).
        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return redirect()->route('activities.create')->with('success', 'Activity record entered successfully!');
    }

    /**
     * Display the specified resource.
     */
    public function show(Activity $activity)
    {
        // Your existing show logic, ensure assignable is loaded if you display it
        $activity->load(['user.branch', 'user.division', 'activityType', 'submittedByUser.branch', 'assignable', 'decidedByUser']);

        // Authorize that the logged-in user can view the activity's associated user.
        if ($activity->user) {
            $this->authorize('view', $activity->user);
        }

        $volunteerActivities = collect();
        $activitiesLimitMessage = false;

        if ($activity->user) {
            // Remove the where clause to get ALL activities including deleted ones
            $allActivities = $activity->user->activities()
                ->with(['activityType', 'assignable'])
                ->orderByDesc('date')
                ->get();

            $activitiesLimitMessage = $allActivities->count() > 100;

            $volunteerActivities = $allActivities->map(function ($activity) {
                return [
                    'date' => $activity->date ? Carbon::parse($activity->date)->format('M d, Y') : 'N/A',
                    'activity' => $activity->activityType->name ?? 'Volunteer Activity',
                    'hours' => $activity->hours ?? 0,
                    'hours_display' => $this->getActivityHours($activity),
                    'unit' => $this->getUnitName($activity),
                    'unit_type' => $this->getUnitTypeName($activity),
                    'reference' => $activity->reference ?? null,
                    'id' => $activity->id ?? null,
                    'is_deleted' => $activity->is_deleted ?? false,  // Add this line
                ];
            });
        }

        return view('activities.show', compact(
            'activity',
            'volunteerActivities',
            'activitiesLimitMessage'
        ));
    }

    /**
     * Show the form for editing the specified activity.
     */
    public function edit(Activity $activity)
    {
        // Authorize that the logged-in user can edit the activity's associated user.
        if ($activity->user) {
            $this->authorize('view', $activity->user);
        }

        $activityTypes = ActivityType::all();
        $branches = Branch::all();
        $divisions = Division::all();
        $redCrossUnits = RedCrossUnit::all();

        return view('activities.edit', compact('activity', 'activityTypes', 'branches', 'divisions', 'redCrossUnits'));
    }

    /**
     * Update the specified activity in storage.
     */
    public function update(Request $request, Activity $activity)
    {
        // Authorize that the logged-in user can update the activity's associated user.
        if ($activity->user) {
            $this->authorize('view', $activity->user);
        }

        $validated = $request->validate([
            'activity_type_id' => 'required|exists:activity_types,id',
            'user_id' => 'required|exists:users,id',
            'date' => 'required|date',
            'hours' => 'required|integer|min:1|max:24',
            'submission_name' => 'nullable|string|max:255', // Kept for update method for now, as instruction was for create
            'reference' => 'nullable|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'division_id' => 'nullable|exists:divisions,id',
            'red_cross_unit_id' => 'nullable|exists:red_cross_units,id', // Kept for update method for now, as instruction was for create
        ]);

        // Handle polymorphic relationship fields based on request input
        if ($request->filled('is_red_cross_unit') && $request->filled('red_cross_unit_id')) { // Kept for update method for now, as instruction was for create
            $validated['assignable_id'] = $validated['red_cross_unit_id'];
            $validated['assignable_type'] = \App\Models\RedCrossUnit::class;
        } else {
            // Ensure that if no specific assignable unit is provided, these are null
            $validated['assignable_id'] = null;
            $validated['assignable_type'] = null;
        }

        unset($validated['red_cross_unit_id']);
        unset($validated['is_red_cross_unit']); // Remove the checkbox value, as it's for logic

        $activity->update($validated);

        // Editing an approved record demotes it back to pending for a fresh
        // approval cycle (no-op if it wasn't approved); resetApprovalOnEdit()
        // already recomputes lifecycle as part of that demotion.
        $activity->resetApprovalOnEdit();

        // Recompute lifecycle only for an APPROVED record; a pending one has no effect.
        if ($activity->isApproved()) {
            optional(User::find($activity->user_id))->recalculateLifecycle();
        }

        if ($admin = Auth::user()) {
            $admin->touchLastAdminActivity();
        }

        return redirect()->route('activities.index')->with('success', 'Activity updated successfully.');
    }

    /**
     * Remove the specified activity from storage (soft delete).
     */
    public function destroy(Activity $activity): RedirectResponse
    {
        // Authorize that the logged-in user can delete the activity's associated user.
        if ($activity->user) {
            $this->authorize('view', $activity->user);
        }

        $viewer = Auth::user();

        // Instead of hard delete, mark as deleted
        $activity->update([
            'is_deleted' => true,
            'removed_by_user_id' => auth()->id(),
            'removed_date' => now()->toDateString(),
        ]);

        if ($activity->user) {
            $activity->user->recalculateLifecycle();
        }

        return redirect()->route('activities.show', $activity)
            ->with('success', 'Activity deleted successfully.');
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

        // Get current user's access level and scoped ID
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();

        $usersQuery = User::selectableForEntry()
            ->whereNotNull('red_cross_unit_id')
            ->where(function ($q) use ($query) {
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
            // Eager load RedCrossUnit and TaskForces (with selected fields)
            ->with(['branch:id,name', 'division:id,name', 'redCrossUnit:id,name', 'taskForces:id,name'])
            ->limit(50)
            ->get();

        return response()->json($users);
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

    /**
     * Get activity hours display text
     */
    private function getActivityHours($activity)
    {
        if ($activity->hours) {
            return $activity->hours.' hours';
        }

        return 'Duration not specified';
    }

    /**
     * Get Red Cross Unit name from activity
     */
    private function getUnitName($activity)
    {
        if ($activity->assignable) {
            return $activity->assignable->name ?? 'Unit not specified';
        }

        return 'Unit not specified';
    }

    private function getUnitTypeName($activity)
    {

        return $activity->unit_type ?? 'Not specified';
    }
}
