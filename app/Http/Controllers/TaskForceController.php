<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\TaskForce;
use App\Models\TaskForceType;
use App\Models\User;
use App\Models\Branch; // Import Branch model
use App\Models\Division; // Import Division model
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; // Make sure this is imported
use Illuminate\Support\Facades\DB; // Import DB facade
use Illuminate\Support\Facades\Auth; // Import Auth facade
use Carbon\Carbon;

class TaskForceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request) // Inject Request
    {
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();

        $userBranchId = null;
        if ($accessLevel === 'branch') {
            $userBranchId = $scopedId;
        } elseif ($accessLevel === 'division') {
            $userDivision = Division::find($scopedId);
            if ($userDivision) {
                $userBranchId = $userDivision->branch_id;
            }
        }


        $query = TaskForce::with(['taskForceType', 'users', 'branch', 'teamLeader', 'assistantTeamLeader'])->orderBy('name');

        // Apply global access level filters FIRST to the query
        if ($accessLevel === 'branch' && $scopedId) {
            $query->where('branch_id', $scopedId);
        } elseif ($accessLevel === 'division' && $userBranchId) {
            $query->where('branch_id', $userBranchId);
        }

        // Search by task force name
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        // Filter by branch (from request)
        // This filter should only be applied if the user is 'national' level,
        // or if user is 'branch' level and the requested branch_id matches their scopedId.
        // For division level users, their view is already restricted to their branch.
        if ($request->filled('branch_id')) {
            if ($accessLevel === 'national') {
                $query->where('branch_id', $request->branch_id);
            } elseif ($accessLevel === 'branch' && $scopedId == $request->branch_id) {
                $query->where('branch_id', $request->branch_id);
            }
            // For division level, if they provide a branch_id that doesn't match their
            // assigned branch, the access level filter will override, resulting in no results, which is correct.
        }

        // Filter by inactive status
        $status = $request->input('status', 'active');
        $query->where('inactive', $status === 'archived' ? true : false);

        // Filter by task force type
        if ($request->filled('task_force_type_id')) {
            $query->where('task_force_type_id', $request->task_force_type_id);
        }

        $taskForces = $query->paginate(100); // Changed to paginate(100)

        $branches = collect();
        // Populate branches based on access level
        switch ($accessLevel) {
            case 'national':
                $branches = Branch::orderBy('name')->get();
                break;
            case 'branch':
                if ($scopedId) {
                    $branches = Branch::where('id', $scopedId)->orderBy('name')->get();
                }
                break;
            case 'division':
                if ($userBranchId) {
                    $branches = Branch::where('id', $userBranchId)->orderBy('name')->get();
                }
                break;
        }


        $taskForceTypes = TaskForceType::orderByLevel()->orderByName()->get();

        return view('task-forces.index', compact(
            'taskForces',
            'branches', // Pass branches to the view
            'accessLevel', // Pass access level to the view
            'scopedId',     // Pass scoped ID to the view
            'userBranchId', // Pass user's branch ID to the view for pre-selection
            'status',
            'taskForceTypes'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $user = Auth::user();
        // Only national and branch admins can create new task forces.
        if (!in_array($user->getAccessLevel(), ['national', 'branch'])) {
            abort(403, 'You are not authorized to create a new Task Force.');
        }

        $taskForceTypes = TaskForceType::orderByName()->get();

        // Scope the branch dropdown: branch admins only see their own branch,
        // national admins can pick from all branches.
        $branches = $user->getAccessLevel() === 'branch'
            ? Branch::where('id', $user->getScopedId())->orderBy('name')->get()
            : Branch::orderBy('name')->get();

        $selectedTypeId = $request->get('type');

        return view('task-forces.create', compact('taskForceTypes', 'branches', 'selectedTypeId')); // Pass branches to the view
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        // Only national and branch admins can create new task forces.
        if (!in_array($user->getAccessLevel(), ['national', 'branch'])) {
            abort(403, 'You are not authorized to create a new Task Force.');
        }

        // Branch admins can only create task forces within their own branch.
        if ($user->getAccessLevel() === 'branch' && $request->branch_id != $user->getScopedId()) {
            abort(403, 'You can only create a Task Force within your own branch.');
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255', // Removed unique validation
            'task_force_type_id' => 'required|exists:task_force_types,id',
            'branch_id' => 'required|exists:branches,id' // Add validation for branch_id
        ]);

        $taskForce = TaskForce::create($validatedData);

        return redirect()->route('task-forces.index')
            ->with('success', 'Task Force created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(TaskForce $taskForce, Request $request)
    {
        // Authorization check
        if (!$taskForce->isViewableBy(Auth::user())) {
            abort(403, 'You are not authorized to view this Task Force.');
        }

        // Display preference: show profile photos (per-browser cookie, off by default)
        $showPhotos = $request->cookie('users_show_photos') === '1';

        // Load relationships - use 'users' (belongsToMany) instead of 'members' (hasMany)
        $taskForce->load([
            'taskForceType',
            'users' // This is the belongsToMany relationship with pivot data
        ]);

        // Fetch recent activities assigned to this Task Force using the scope
        $recentActivities = Activity::forTaskForce($taskForce->id)
            ->with(['user', 'activityType'])
            ->where('is_deleted', false)
            ->latest('date')
            ->paginate(15);

        // Summary of activities for the last 12 months for this Task Force using the scope
        $activitiesSummary = Activity::forTaskForce($taskForce->id)
            ->with('activityType')
            ->where('date', '>=', now()->subYear())
            ->select('activity_type_id', DB::raw('SUM(hours) as total_hours'))
            ->groupBy('activity_type_id')
            ->get()
            ->map(function ($activity) {
                return [
                    'name' => $activity->activityType->name ?? 'Unknown Activity',
                    'total_hours' => (int) $activity->total_hours,
                ];
            })
            ->sortByDesc('total_hours');

        return view('task-forces.show', compact('taskForce', 'recentActivities', 'activitiesSummary', 'showPhotos'));
    }

    /**
     * Display a specific task force for the authenticated user.
     */
    public function myShow(TaskForce $taskForce)
    {
        // Ensure the authenticated user is a member of this task force
        // or has permission to view it. For simplicity, we'll assume here
        // that the user should only see task forces they are assigned to.
        // You might want a more robust authorization check here.
        if (!auth()->user()->taskForces->contains($taskForce)) {
            abort(403, 'Unauthorized action.');
        }

        $totalMembers = $taskForce->users->count();

        // Fetch recent activities assigned to this Task Force using the scope
        $recentActivities = Activity::forTaskForce($taskForce->id)
            ->with(['user', 'activityType'])
            ->where('is_deleted', false)
            ->latest('date')
            ->paginate(15);

        // Fetch all members of the task force with their current membership payments and activities
        $taskForceUserIds = $taskForce->users->pluck('id'); // Still needed for member-specific data
        $unitMembersData = User::whereIn('id', $taskForceUserIds)
            ->with([
                'currentMembershipPayment' => fn ($q) => $q->personal(),
                'currentMembershipPayment.membershipFee',
                'activities' => function($query) {
                    $query->where('date', '>=', Carbon::now()->subYear()); // Activities in the last 12 months
                },
            ])
            ->orderBy('first_name')
            ->get()
            ->map(function ($user) {
                $daysToExpiry = $user->currentMembershipPayment->days_until_expiry ?? 'N/A';
                if (is_numeric($daysToExpiry)) {
                    $daysToExpiry = (int) $daysToExpiry;
                }

                $membershipType = $user->currentMembershipPayment->membershipFee->name ?? 'N/A';
                $volunteeringHoursLast12Months = $user->activities->sum('hours');

                return [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'membership_type' => $membershipType,
                    'days_to_expiry' => $daysToExpiry,
                    'volunteering_hours_last_12_months' => $volunteeringHoursLast12Months,
                ];
            });

        // Fetch all members of the task force with their trainings for the new table, sorted by first name
        $membersWithTrainingsDetails = User::whereIn('id', $taskForceUserIds)
            ->with(['trainings.trainingType']) // Eager load trainings and their types
            ->orderBy('first_name')
            ->get()
            ->map(function ($user) {
                $trainingsData = $user->trainings->map(function ($training) {
                    $expiryDate = null;
                    if ($training->training_date && $training->valid_years !== null) {
                        $expiryDate = Carbon::parse($training->training_date)->addYears($training->valid_years);
                    }

                    $daysUntilExpiry = null;
                    if ($expiryDate) {
                        $daysUntilExpiry = Carbon::now()->diffInDays($expiryDate, false);
                    }

                    $expiryStatus = 'N/A';
                    if ($daysUntilExpiry !== null) {
                        if ($daysUntilExpiry < 0) {
                            $expiryStatus = 'Expired';
                        } else {
                            $expiryStatus = $daysUntilExpiry . ' days left';
                        }
                    } else {
                        $expiryStatus = 'No Expiry';
                    }

                    return [
                        'training_name' => $training->trainingType->name ?? 'Unknown Training',
                        'training_date' => $training->training_date,
                        'expiry_status' => $expiryStatus,
                    ];
                });

                return [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'trainings' => $trainingsData,
                ];
            });

        // Summary of activities for the last 12 months for this Task Force using the scope
        $activitiesSummary = Activity::forTaskForce($taskForce->id)
            ->with('activityType')
            ->where('date', '>=', Carbon::now()->subYear())
            ->select('activity_type_id', DB::raw('SUM(hours) as total_hours'))
            ->groupBy('activity_type_id')
            ->get()
            ->map(function ($activity) {
                return [
                    'name' => $activity->activityType->name ?? 'Unknown Activity',
                    'total_hours' => (int) $activity->total_hours,
                ];
            })
            ->sortByDesc('total_hours');


        return view('task-forces.my-task-force', compact(
            'taskForce',
            'totalMembers',
            'recentActivities',
            'unitMembersData',
            'membersWithTrainingsDetails',
            'activitiesSummary'
        ));
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(TaskForce $taskForce)
    {
        // Authorization check
        if (!$taskForce->isViewableBy(Auth::user())) {
            abort(403, 'You are not authorized to edit this Task Force.');
        }

        $taskForceTypes = TaskForceType::orderByLevel()->orderByName()->get();
        // Eager load users (members), team leader, assistant team leader, and branch (display only)
        $taskForce->load(['users', 'teamLeader', 'assistantTeamLeader', 'branch']);

        // Pass the currently selected team leader IDs to the view
        $currentTeamLeaderId = $taskForce->teamLeader ? $taskForce->teamLeader->id : null;
        $currentAssistantTeamLeaderId = $taskForce->assistantTeamLeader ? $taskForce->assistantTeamLeader->id : null;


        return view('task-forces.edit', compact(
            'taskForce',
            'taskForceTypes',
            'currentTeamLeaderId', // Pass this
            'currentAssistantTeamLeaderId' // Pass this
        ));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TaskForce $taskForce)
    {
        // Authorization check
        if (!$taskForce->isViewableBy(Auth::user())) {
            abort(403, 'You are not authorized to update this Task Force.');
        }
        $user = Auth::user();

        Log::info('TaskForceController@update request data for TaskForce ID ' . $taskForce->id . ':', $request->all());

        $validatedData = $request->validate([
            'name' => 'required|string|max:255', // Removed unique validation
            'task_force_type_id' => 'required|exists:task_force_types,id',
            'team_leader_user_id' => 'nullable|exists:users,id', // Update validation to match DB column name
            'assist_team_leader_user_id' => 'nullable|exists:users,id', // Update validation to match DB column name
        ]);

        $taskForce->update([
            'name' => $validatedData['name'],
            'task_force_type_id' => $validatedData['task_force_type_id'],
            'team_leader_user_id' => $validatedData['team_leader_user_id'] ?? null, // Use validated data directly
            'assist_team_leader_user_id' => $validatedData['assist_team_leader_user_id'] ?? null, // Use validated data directly
        ]);

        DB::connection()->enableQueryLog();
        Log::info('SQL Query Log Enabled for TaskForce ID ' . $taskForce->id);

        // Get current members before sync for comparison
        $currentMemberIds = $taskForce->users()->pluck('users.id')->toArray();
        Log::info('TaskForceController: Current member IDs before sync for TaskForce ID ' . $taskForce->id . ':', ['current_members' => $currentMemberIds]);

        if ($request->has('members') && is_array($request->members)) {
            $newMembers = array_map('intval', $request->members); // Ensure member IDs are integers
            Log::info('TaskForceController: New member IDs received for TaskForce ID ' . $taskForce->id . ':', ['new_members_received' => $newMembers]);

            $syncResult = $taskForce->users()->sync($newMembers);
            Log::info('TaskForceController: Members sync result for TaskForce ID ' . $taskForce->id . '.', ['sync_result' => $syncResult]);

            if (empty($syncResult['attached']) && empty($syncResult['detached']) && empty($syncResult['updated'])) {
                Log::info('TaskForceController: No actual changes detected by sync method for TaskForce ID ' . $taskForce->id . '. Current members are already synchronized or an issue prevented changes.');
            }

        } else {
            $detachCount = $taskForce->users()->detach();
            Log::info('TaskForceController: No members submitted or members array invalid for TaskForce ID ' . $taskForce->id . ', detaching all existing members.', ['detached_count' => $detachCount]);
        }

        $queries = DB::getQueryLog();
        if (empty($queries)) {
            Log::info('TaskForceController: No SQL queries were logged for the member sync operation, possibly due to no changes or an issue with query logging.');
        } else {
            foreach ($queries as $query) {
                // To reconstruct the full query string (for better readability)
                $sql = $query['query'];
                foreach ($query['bindings'] as $binding) {
                    $sql = preg_replace('/\?/', "'" . addslashes($binding) . "'", $sql, 1);
                }
                Log::info('SQL Query: ' . $sql, ['time' => $query['time']]);
            }
        }
        DB::connection()->disableQueryLog();
        Log::info('SQL Query Log Disabled for TaskForce ID ' . $taskForce->id);

        return redirect()->route('task-forces.show', $taskForce)
            ->with('success', 'Task Force and its members updated successfully.');
    }

    /**
     * Deactivate the specified resource from storage.
     */
    public function destroy(TaskForce $taskForce)
    {
        // Authorization check
        if (!$taskForce->isViewableBy(Auth::user())) {
            abort(403, 'You are not authorized to deactivate this Task Force.');
        }

        // Also restrict deactivation to national and branch admins
        if (!in_array(Auth::user()->getAccessLevel(), ['national', 'branch'])) {
            abort(403, 'You are not authorized to deactivate this Task Force.');
        }

        $taskForce->update(['inactive' => true]);

        return redirect()->route('task-forces.index')
            ->with('success', 'Task Force deactivated successfully.');
    }

    /**
     * Reactivate the specified resource.
     */
    public function reactivate(TaskForce $taskForce)
    {
        // Authorization check
        if (!$taskForce->isViewableBy(Auth::user())) {
            abort(403, 'You are not authorized to reactivate this Task Force.');
        }

        // Also restrict reactivation to national and branch admins
        if (!in_array(Auth::user()->getAccessLevel(), ['national', 'branch'])) {
            abort(403, 'You are not authorized to reactivate this Task Force.');
        }

        $taskForce->update(['inactive' => false]);

        return redirect()->route('task-forces.index')
            ->with('success', 'Task Force reactivated successfully.');
    }

    /**
     * Search for users to add as members to a task force.
     *
     * Volunteers only (canonical User::scopeVolunteers(): lifecycle_status in
     * ['active', 'dormant'], red_cross_unit_id not null, unit is_active = true) —
     * not all selectable persons, so this does NOT match the broader
     * selectableForEntry() search used by donations/payments/trainings.
     */
    public function searchUsers(Request $request, TaskForce $taskForce)
    {
        $query = $request->input('query');
        $existingMemberIds = $taskForce->users->pluck('id')->toArray();

        if (empty($query)) {
            return response()->json([]);
        }

        $users = User::selectableForEntry()
            ->volunteers()
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$query}%"])
                    ->orWhereRaw("CONCAT(first_name, ' ', IFNULL(middle_name, ''), ' ', last_name) LIKE ?", ["%{$query}%"])
                    ->orWhere('id', is_numeric($query) ? (int) $query : -1); // Add search by user ID
            })
            ->whereNotIn('id', $existingMemberIds) // Exclude already assigned members
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(10) // Limit search results
            ->with(['branch', 'division']) // Eager load relationships for display
            ->get();

        // Append the accessor attributes for the response
        $users->each(function ($user) {
            $user->append(['full_name', 'user_id_reference']);
        });

        return response()->json($users);
    }

    /**
     * Add a user as a member to the specified task force.
     */
    public function addMember(Request $request, TaskForce $taskForce)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $userId = $request->input('user_id');

        // Check if the user is already a member
        if ($taskForce->users()->where('user_id', $userId)->exists()) {
            return response()->json(['success' => false, 'message' => 'User is already a member of this task force.'], 409);
        }

        try {
            $taskForce->users()->attach($userId);
            $user = User::find($userId); // Get the user object to return
            $user->append(['full_name', 'user_id_reference']); // Append the accessor attributes
            return response()->json(['success' => true, 'message' => 'Member added successfully.', 'user' => $user]);
        } catch (\Exception $e) {
            Log::error("Failed to add member to task force: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to remove member.'], 500);
        }
    }

    /**
     * Remove a user from the specified task force.
     */
    public function removeMember(Request $request, TaskForce $taskForce)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $userId = $request->input('user_id');

        // Check if the user is actually a member
        if (!$taskForce->users()->where('user_id', $userId)->exists()) {
            return response()->json(['success' => false, 'message' => 'User is not a member of this task force.'], 404);
        }

        try {
            $user = User::find($userId); // Get user before detaching for feedback message
            $taskForce->users()->detach($userId);
            return response()->json(['success' => true, 'message' => 'Member ' . $user->full_name . ' removed successfully.']);
        } catch (\Exception $e) {
            Log::error("Failed to remove member from task force: " . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to remove member.'], 500);
        }
    }
}
