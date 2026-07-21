<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Branch;
use App\Models\Division;
use App\Models\RedCrossUnit;
use App\Models\User; // Import Activity model
use Carbon\Carbon;
use Illuminate\Http\Request; // Import DB facade for raw expressions
use Illuminate\Support\Facades\Auth; // Import Carbon for date manipulation
use Illuminate\Support\Facades\DB; // Import Auth facade

class RedCrossUnitController extends Controller
{
    /**
     * Display the user's Red Cross Unit details (User-facing view)
     */
    public function myUnit()
    {
        $redCrossUnit = auth()->user()->redCrossUnit;

        if (! $redCrossUnit) {
            return view('red-cross-units.my-unit', ['redCrossUnit' => null]);
        }

        // Eager load relationships for the redCrossUnit
        $redCrossUnit->load([
            'division.branch',
            'teamLeader',
            'assistantTeamLeader',
            'activeUsers' => function ($query) {
                $query->orderBy('first_name')->orderBy('last_name');
            },
        ]);

        // Get some statistics for this unit
        $totalMembers = $redCrossUnit->activeUsers()->count();
        $activeMembers = $redCrossUnit->activeUsers()->whereNotNull('email_verified_at')->count();

        // Fetch recent activities assigned to this Red Cross Unit using the scope
        $recentActivities = Activity::forRedCrossUnit($redCrossUnit->id)
            ->with(['user', 'activityType'])
            ->where('is_deleted', false)
            ->latest('date')
            ->paginate(15);

        // Summary of activities for the last 12 months for this Red Cross Unit using the scope
        $activitiesSummary = Activity::forRedCrossUnit($redCrossUnit->id)
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

        // Fetch all members of the unit with their current membership payments and activities (for existing table)
        // Note: $redCrossUnit->activeUsers() already has orderBy('first_name')->orderBy('last_name') from the load() call above.
        $unitMembersData = $redCrossUnit->activeUsers
            ->map(function ($user) {
                $user->loadMissing([
                    'currentMembershipPayment' => fn ($q) => $q->personal(),
                    'currentMembershipPayment.membershipFee',
                    'activities' => function ($query) {
                        $query->where('date', '>=', Carbon::now()->subYear()); // Activities in the last 12 months
                    },
                ]);

                // Ensure days_to_expiry is an integer
                $daysToExpiry = $user->currentMembershipPayment->days_until_expiry ?? 'N/A';
                // If daysToExpiry is a number, cast it to int to remove decimals
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

        // Fetch all members of the unit with their trainings for the new table, sorted by first name
        // Note: $redCrossUnit->activeUsers() already has orderBy('first_name')->orderBy('last_name') from the load() call above.
        $membersWithTrainingsDetails = $redCrossUnit->activeUsers
            ->map(function ($user) {
                $user->loadMissing(['trainings.trainingType']); // Eager load trainings and their types

                $trainingsData = $user->trainings->map(function ($training) {
                    // Calculate expiry_date based on training_date and valid_years
                    $expiryDate = null;
                    if ($training->training_date && $training->valid_years !== null) {
                        $expiryDate = Carbon::parse($training->training_date)->addYears($training->valid_years);
                    }

                    $daysUntilExpiry = null;
                    if ($expiryDate) {
                        $daysUntilExpiry = now()->diffInDays($expiryDate, false);
                    }

                    $expiryStatus = 'N/A';
                    if ($daysUntilExpiry !== null) {
                        if ($daysUntilExpiry < 0) {
                            $expiryStatus = 'Expired';
                        } else {
                            $expiryStatus = $daysUntilExpiry.' days left';
                        }
                    } else {
                        $expiryStatus = 'No Expiry'; // Or similar, if valid_years is null, meaning it never expires
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

        return view('red-cross-units.my-unit', compact(
            'redCrossUnit',
            'totalMembers',
            'activeMembers',
            'recentActivities',
            'activitiesSummary',
            'unitMembersData',
            'membersWithTrainingsDetails'
        ));
    }

    /**
     * Display the tabbed tables page for the user's RC unit.
     */
    public function myUnitTables(Request $request)
    {
        $redCrossUnit = auth()->user()->redCrossUnit;

        if (! $redCrossUnit) {
            return redirect()->route('red-cross-units.my-unit');
        }

        $redCrossUnit->load([
            'users' => function ($query) {
                $query->orderBy('first_name')->orderBy('last_name');
            },
        ]);

        $recentActivities = Activity::forRedCrossUnit($redCrossUnit->id)
            ->with(['user', 'activityType'])
            ->where('is_deleted', false)
            ->latest('date')
            ->paginate(15);

        $activitiesSummary = Activity::forRedCrossUnit($redCrossUnit->id)
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

        $unitMembersData = $redCrossUnit->users
            ->map(function ($user) {
                $user->loadMissing([
                    'currentMembershipPayment' => fn ($q) => $q->personal(),
                    'currentMembershipPayment.membershipFee',
                    'activities' => function ($query) {
                        $query->where('date', '>=', Carbon::now()->subYear());
                    },
                ]);

                $daysToExpiry = $user->currentMembershipPayment->days_until_expiry ?? 'N/A';
                if (is_numeric($daysToExpiry)) {
                    $daysToExpiry = (int) $daysToExpiry;
                }

                return [
                    'id' => $user->id,
                    'full_name' => $user->full_name,
                    'membership_type' => $user->currentMembershipPayment->membershipFee->name ?? 'N/A',
                    'days_to_expiry' => $daysToExpiry,
                    'volunteering_hours_last_12_months' => $user->activities->sum('hours'),
                ];
            });

        $membersWithTrainingsDetails = $redCrossUnit->users
            ->map(function ($user) {
                $user->loadMissing(['trainings.trainingType']);

                $trainingsData = $user->trainings->map(function ($training) {
                    $expiryDate = null;
                    if ($training->training_date && $training->valid_years !== null) {
                        $expiryDate = Carbon::parse($training->training_date)->addYears($training->valid_years);
                    }

                    $daysUntilExpiry = $expiryDate ? now()->diffInDays($expiryDate, false) : null;

                    if ($daysUntilExpiry === null) {
                        $expiryStatus = 'No Expiry';
                    } elseif ($daysUntilExpiry < 0) {
                        $expiryStatus = 'Expired';
                    } else {
                        $expiryStatus = round($daysUntilExpiry).' days left';
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

        $activeTab = $request->input('tab', 'membership');

        return view('red-cross-units.my-unit-tables', compact(
            'redCrossUnit',
            'unitMembersData',
            'membersWithTrainingsDetails',
            'activitiesSummary',
            'recentActivities',
            'activeTab',
        ));
    }

    /**
     * Display the My Unit Comparison report.
     */
    public function myUnitComparison(Request $request)
    {
        $user = Auth::user();

        if (! $user->redCrossUnit) {
            return redirect()->route('red-cross-units.my-unit');
        }

        $userUnit = $user->redCrossUnit;
        $userDivisionId = $userUnit->division_id;
        $userBranchId = $userUnit->division->branch_id;

        $level = $request->input('level', 'unit');

        if (! in_array($level, ['unit', 'division', 'branch', 'national'])) {
            $level = 'unit';
        }

        $requestedBranchId = $request->input('branch_id');
        $requestedDivisionId = $request->input('division_id');

        switch ($level) {
            case 'unit':
                $targetDivisionId = $requestedDivisionId ?? $userDivisionId;
                $rows = RedCrossUnit::where('division_id', $targetDivisionId)
                    ->where('is_active', true)->orderBy('name')->get();
                $currentDivision = Division::find($targetDivisionId);
                $currentBranch = Branch::find($currentDivision?->branch_id);
                break;
            case 'division':
                $targetBranchId = $requestedBranchId ?? $userBranchId;
                $rows = Division::where('branch_id', $targetBranchId)->orderBy('name')->get();
                $currentBranch = Branch::find($targetBranchId);
                $currentDivision = null;
                break;
            case 'branch':
                $rows = Branch::orderBy('name')->get();
                $currentBranch = null;
                $currentDivision = null;
                break;
            case 'national':
                $rows = Branch::orderBy('name')->get();
                $currentBranch = null;
                $currentDivision = null;
                $level = 'branch';
                break;
        }

        $baseUsers = function ($rowItem) use ($level) {
            $q = User::query()->whereIn('lifecycle_status', ['active', 'dormant']);
            if ($level === 'unit') {
                $q->where('red_cross_unit_id', $rowItem->id);
            } elseif ($level === 'division') {
                $unitIds = RedCrossUnit::where('division_id', $rowItem->id)
                    ->where('is_active', true)->pluck('id');
                $q->whereIn('red_cross_unit_id', $unitIds);
            } elseif ($level === 'branch') {
                $unitIds = RedCrossUnit::whereHas('division', fn ($d) => $d->where('branch_id', $rowItem->id)
                )->where('is_active', true)->pluck('id');
                $q->whereIn('red_cross_unit_id', $unitIds);
            }

            return $q;
        };

        $isHighlighted = function ($rowItem) use ($level, $userUnit, $userDivisionId, $userBranchId) {
            if ($level === 'unit') {
                return $rowItem->id === $userUnit->id;
            }
            if ($level === 'division') {
                return $rowItem->id === $userDivisionId;
            }
            if ($level === 'branch') {
                return $rowItem->id === $userBranchId;
            }

            return false;
        };

        $comparisonData = [];
        foreach ($rows as $rowItem) {
            $q = $baseUsers($rowItem);
            $total = (clone $q)->count();
            $userIds = (clone $q)->pluck('id');

            $anyTraining = $total > 0
                ? round((clone $q)->whereHas('trainings', fn ($t) => $t->where('is_deleted', false)
                )->count() / $total * 100)
                : null;

            $firstAid = $total > 0
                ? round((clone $q)->whereHas('trainings', fn ($t) => $t->where('is_deleted', false)
                    ->whereHas('trainingType', fn ($tt) => $tt->where('is_first_aid', true))
                )->count() / $total * 100)
                : null;

            $totalHours = DB::table('activities')
                ->whereIn('user_id', $userIds)
                ->where('is_deleted', false)
                ->where('approval_status', 'approved') // Phase 2: only approved records are real
                ->sum('hours');

            $hoursPerVolunteer = ($total >= 5)
                ? round($totalHours / $total, 1)
                : null;

            $pctDormant = $total > 0
                ? round((clone $q)->where('lifecycle_status', 'dormant')->count() / $total * 100)
                : null;

            $comparisonData[] = [
                'id' => $rowItem->id,
                'label' => $rowItem->name,
                'highlight' => $isHighlighted($rowItem),
                'link' => match ($level) {
                    'branch' => route('red-cross-units.my-unit-comparison', ['level' => 'division', 'branch_id' => $rowItem->id]),
                    'division' => route('red-cross-units.my-unit-comparison', ['level' => 'unit', 'division_id' => $rowItem->id]),
                    default => null,
                },
                'total_volunteers' => $total,
                'pct_any_training' => $anyTraining,
                'pct_first_aid' => $firstAid,
                'total_hours' => $totalHours,
                'hours_per_volunteer' => $hoursPerVolunteer,
                'pct_dormant' => $pctDormant,
            ];
        }

        return view('red-cross-units.my-unit-comparison', compact(
            'comparisonData',
            'level',
            'currentBranch',
            'currentDivision',
            'userUnit',
            'userDivisionId',
            'userBranchId'
        ));
    }

    /**
     * Display the data completeness report for the user's RC unit.
     */
    public function myUnitReport()
    {
        $redCrossUnit = auth()->user()->redCrossUnit;

        if (! $redCrossUnit) {
            return redirect()->route('red-cross-units.my-unit')
                ->with('error', 'You are not assigned to a Red Cross Unit.');
        }

        $redCrossUnit->load([
            'division.branch',
            'teamLeader',
            'assistantTeamLeader',
            'users' => function ($query) {
                $query->with([
                        'branch', 'division',
                        'currentMembershipPayment' => fn ($q) => $q->personal(),
                        'currentMembershipPayment.membershipFee',
                    ])
                    ->orderBy('first_name')
                    ->orderBy('last_name');
            },
        ]);

        return view('red-cross-units.my-unit-report', compact('redCrossUnit'));
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $accessLevel = $user->getAccessLevel();
        $scopedId = $user->getScopedId();

        $userBranchId = null;
        $userDivisionId = null;
        $userDivisionModel = null;

        if ($accessLevel === 'branch') {
            $userBranchId = $scopedId;
        } elseif ($accessLevel === 'division') {
            $userDivisionId = $scopedId;
            $userDivisionModel = Division::find($scopedId);
            if ($userDivisionModel) {
                $userBranchId = $userDivisionModel->branch_id;
            }
        }

        $status = $request->input('status', 'active');

        $query = RedCrossUnit::with(['division.branch', 'teamLeader', 'assistantTeamLeader'])
            ->withCount('activeUsers')
            ->where('is_active', $status === 'archived' ? false : true);

        // Apply global access level filters FIRST to the query
        if ($accessLevel === 'branch' && $scopedId) {
            $query->whereHas('division', function ($q) use ($scopedId) {
                $q->where('branch_id', $scopedId);
            });
        } elseif ($accessLevel === 'division' && $scopedId) {
            $query->where('division_id', $scopedId);
        }

        // Search functionality
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        // Apply Branch Filter (from request)
        // This filter should only be applied if the user is 'national' level,
        // or if user is 'branch' level and the requested branch_id matches their scopedId.
        // If user is division-scoped, this filter is ignored, as the division_id filter (from global or request) will handle it.
        if ($request->has('branch_id') && $request->branch_id) {
            if ($accessLevel === 'national') {
                $query->whereHas('division', function ($q) use ($request) {
                    $q->where('branch_id', $request->branch_id);
                });
            } elseif ($accessLevel === 'branch' && $scopedId == $request->branch_id) {
                $query->whereHas('division', function ($q) use ($request) {
                    $q->where('branch_id', $request->branch_id);
                });
            }
        }

        // Apply Division Filter (from request)
        // This filter should only be applied if the user is 'national' or 'branch' level,
        // or if user is 'division' level and the requested division_id matches their scopedId.
        if ($request->has('division_id') && $request->division_id) {
            if (in_array($accessLevel, ['national', 'branch'])) {
                $query->where('division_id', $request->division_id);
            } elseif ($accessLevel === 'division' && $scopedId == $request->division_id) {
                $query->where('division_id', $request->division_id);
            }
        }

        $redCrossUnits = $query->orderBy('name')->paginate(15)->withQueryString();

        $branches = collect();
        $divisions = collect();

        // Populate branches and divisions based on access level
        switch ($accessLevel) {
            case 'national':
                $branches = Branch::select('id', 'name')->orderBy('name')->get();
                if ($request->filled('branch_id')) {
                    $divisions = Division::where('branch_id', $request->branch_id)
                        ->select('id', 'name')->orderBy('name')
                        ->get();
                }
                break;
            case 'branch':
                if ($scopedId) {
                    $branches = Branch::where('id', $scopedId)->select('id', 'name')->orderBy('name')->get();
                    $divisions = Division::where('branch_id', $scopedId)
                        ->select('id', 'name')->orderBy('name')
                        ->get();
                }
                break;
            case 'division':
                if ($scopedId) {
                    if ($userDivisionModel) {
                        $branches = Branch::where('id', $userDivisionModel->branch_id)->select('id', 'name')->orderBy('name')->get();
                        $divisions = Division::where('id', $scopedId)->select('id', 'name')->orderBy('name')->get();
                    }
                }
                break;
        }

        return view('red-cross-units.index', compact(
            'redCrossUnits',
            'branches', // Pass branches to the view
            'divisions', // Pass divisions to the view
            'accessLevel', // Pass access level to the view
            'scopedId',     // Pass scoped ID to the view
            'userBranchId',
            'userDivisionId',
            'status'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = Auth::user();
        // Only national and branch admins can create new units.
        if (! in_array($user->getAccessLevel(), ['national', 'branch'])) {
            abort(403, 'You are not authorized to create a new Red Cross Unit.');
        }

        $divisionsQuery = Division::with('branch')->orderBy('name');

        if ($user->getAccessLevel() === 'branch') {
            $divisionsQuery->where('branch_id', $user->getScopedId());
        }

        $divisions = $divisionsQuery->get();

        return view('red-cross-units.create', compact('divisions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        // Only national and branch admins can store new units.
        if (! in_array($user->getAccessLevel(), ['national', 'branch'])) {
            abort(403, 'You are not authorized to create a new Red Cross Unit.');
        }

        // Additional check for branch admin: ensure they are creating a unit within their own branch.
        if ($user->getAccessLevel() === 'branch') {
            $division = Division::find($request->division_id);
            if (! $division || $division->branch_id != $user->getScopedId()) {
                abort(403, 'You can only create a Red Cross Unit within your own branch.');
            }
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:red_cross_units,name',
            'division_id' => 'required|exists:divisions,id',
            'team_leader_user_id' => 'nullable|exists:users,id',
            'assistant_team_leader_user_id' => 'nullable|exists:users,id|different:team_leader_user_id',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);

        RedCrossUnit::create($validated);

        return redirect()->route('red-cross-units.index')
            ->with('success', 'Red Cross Unit created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(RedCrossUnit $redCrossUnit, Request $request)
    {
        // Authorization check using the model function
        if (! $redCrossUnit->isViewableBy(Auth::user())) {
            abort(403, 'You are not authorized to view this Red Cross Unit.');
        }

        // Display preference: show profile photos (per-browser cookie, off by default)
        $showPhotos = $request->cookie('users_show_photos') === '1';

        $redCrossUnit->load([
            'division.branch',
            'teamLeader',
            'assistantTeamLeader',
            'activeUsers' => function ($query) {
                $query->orderBy('first_name')->orderBy('last_name');
            },
        ]);

        // Get some statistics for this unit
        $totalMembers = $redCrossUnit->activeUsers()->count();
        $activeMembers = $redCrossUnit->activeUsers()->whereNotNull('email_verified_at')->count();

        // Fetch recent activities assigned to this Red Cross Unit using the scope
        $recentActivities = Activity::forRedCrossUnit($redCrossUnit->id)
            ->with(['user', 'activityType'])
            ->where('is_deleted', false)
            ->latest('date')
            ->paginate(15);

        // Summary of activities for the last 12 months for this Red Cross Unit using the scope
        $activitiesSummary = Activity::forRedCrossUnit($redCrossUnit->id)
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

        // Fetch all members of the unit with their current membership payments and activities (for existing table)
        $unitMembersData = $redCrossUnit->activeUsers()
            ->with([
                'currentMembershipPayment' => fn ($q) => $q->personal(),
                'currentMembershipPayment.membershipFee',
                'activities' => function ($query) {
                    $query->where('date', '>=', Carbon::now()->subYear()); // Activities in the last 12 months
                },
            ])
            ->orderBy('first_name')
            ->get()
            ->map(function ($user) {
                // Ensure days_to_expiry is an integer
                $daysToExpiry = $user->currentMembershipPayment->days_until_expiry ?? 'N/A';
                // If daysToExpiry is a number, cast it to int to remove decimals
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

        // Fetch all members of the unit with their trainings for the new table, sorted by first name
        $membersWithTrainingsDetails = $redCrossUnit->activeUsers()
            ->with(['trainings.trainingType']) // Eager load trainings and their types
            ->orderBy('first_name')
            ->get()
            ->map(function ($user) {
                $trainingsData = $user->trainings->map(function ($training) {
                    // Calculate expiry_date based on training_date and valid_years
                    $expiryDate = null;
                    if ($training->training_date && $training->valid_years !== null) {
                        $expiryDate = Carbon::parse($training->training_date)->addYears($training->valid_years);
                    }

                    $daysUntilExpiry = null;
                    if ($expiryDate) {
                        $daysUntilExpiry = now()->diffInDays($expiryDate, false);
                    }

                    $expiryStatus = 'N/A';
                    if ($daysUntilExpiry !== null) {
                        if ($daysUntilExpiry < 0) {
                            $expiryStatus = 'Expired';
                        } else {
                            $expiryStatus = $daysUntilExpiry.' days left';
                        }
                    } else {
                        $expiryStatus = 'No Expiry'; // Or similar, if valid_years is null, meaning it never expires
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

        return view('red-cross-units.show', compact(
            'redCrossUnit',
            'totalMembers',
            'activeMembers',
            'recentActivities',
            'activitiesSummary',
            'unitMembersData',
            'membersWithTrainingsDetails',
            'showPhotos'
        ));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(RedCrossUnit $redCrossUnit)
    {
        // Authorization check using the model function
        if (! $redCrossUnit->isViewableBy(Auth::user())) {
            abort(403, 'You are not authorized to edit this Red Cross Unit.');
        }

        // Ensure division.branch, current team leader and assistant are loaded for display purposes
        $redCrossUnit->load(['division.branch', 'teamLeader', 'assistantTeamLeader']);

        // Only get users who are members of this specific unit for leadership selection
        $unitMembers = $redCrossUnit->activeUsers()
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        // Leadership dropdown candidates: active members only, but always keep the
        // currently-assigned leader/assistant even if archived, so an untouched
        // save doesn't silently clear their assignment.
        $teamLeaderOptions = $unitMembers;
        if ($redCrossUnit->teamLeader && ! $unitMembers->contains('id', $redCrossUnit->teamLeader->id)) {
            $teamLeaderOptions = $unitMembers->concat([$redCrossUnit->teamLeader]);
        }

        $assistantTeamLeaderOptions = $unitMembers;
        if ($redCrossUnit->assistantTeamLeader && ! $unitMembers->contains('id', $redCrossUnit->assistantTeamLeader->id)) {
            $assistantTeamLeaderOptions = $unitMembers->concat([$redCrossUnit->assistantTeamLeader]);
        }

        // 'divisions' variable is no longer passed as the dropdown is removed, but we still need redCrossUnit->division loaded.
        return view('red-cross-units.edit', compact('redCrossUnit', 'unitMembers', 'teamLeaderOptions', 'assistantTeamLeaderOptions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, RedCrossUnit $redCrossUnit)
    {
        // Authorization check using the model function
        if (! $redCrossUnit->isViewableBy(Auth::user())) {
            abort(403, 'You are not authorized to update this Red Cross Unit.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:red_cross_units,name,'.$redCrossUnit->id,
            // division_id is no longer editable, so it's removed from validation rules.
            'team_leader_user_id' => 'nullable|exists:users,id',
            'assistant_team_leader_user_id' => 'nullable|exists:users,id|different:team_leader_user_id',
        ]);

        // is_active is intentionally not part of this form — it's only ever
        // changed via destroy() (deactivate) and reactivate(), so a routine
        // edit here must never touch it.

        // Remove division_id from validated data as it's not being updated from the form.
        unset($validated['division_id']);

        $redCrossUnit->update($validated);

        return redirect()->route('red-cross-units.show', $redCrossUnit)
            ->with('success', 'Red Cross Unit updated successfully.');
    }

    /**
     * Fetches divisions based on branch ID, respecting user's access level.
     */
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
        // This scenario is slightly different for Red Cross Units, as a division-level user
        // would only be able to see Red Cross Units within their *own* division,
        // and thus the divisions dropdown would already be restricted to their division.
        // However, if for some reason a division-level user could access this endpoint
        // with a branch_id that contains their division, we should still restrict.
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
        $divisionId = $request->input('division_id');

        if (! $divisionId) {
            return response()->json([]); // Return empty if no division ID is provided
        }

        $units = RedCrossUnit::where('division_id', $divisionId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($units);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RedCrossUnit $redCrossUnit)
    {
        $user = Auth::user();
        // Authorization: Only national or branch admins can delete.
        if (! in_array($user->getAccessLevel(), ['national', 'branch'])) {
            abort(403, 'You are not authorized to delete this Red Cross Unit.');
        }

        // Branch admins can only delete units within their branch. isViewableBy handles this.
        if (! $redCrossUnit->isViewableBy($user)) {
            abort(403, 'You are not authorized to delete this Red Cross Unit.');
        }

        // Check if unit has any non-archived members — archived users are
        // already inert in the system and shouldn't block deactivation.
        if ($redCrossUnit->users()->where('lifecycle_status', '!=', 'archived')->count() > 0) {
            return redirect()->route('red-cross-units.index')
                ->with('error', 'Cannot deactivate Red Cross Unit that has members. Please reassign members first.');
        }

        $redCrossUnit->update(['is_active' => false]);

        return redirect()->route('red-cross-units.index')
            ->with('success', 'Red Cross Unit deactivated successfully.');
    }

    /**
     * Reactivate a deactivated Red Cross Unit.
     */
    public function reactivate(RedCrossUnit $redCrossUnit)
    {
        // Authorization check using the model function
        if (! $redCrossUnit->isViewableBy(Auth::user())) {
            abort(403, 'You are not authorized to reactivate this Red Cross Unit.');
        }

        // Restrict reactivation to national and branch admins, matching destroy()'s authorization.
        if (! in_array(Auth::user()->getAccessLevel(), ['national', 'branch'])) {
            abort(403, 'You are not authorized to reactivate this Red Cross Unit.');
        }

        $redCrossUnit->update(['is_active' => true]);

        return redirect()->route('red-cross-units.edit', $redCrossUnit)
            ->with('success', 'Red Cross Unit has been reactivated.');
    }
}
