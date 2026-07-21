<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipPayment;
use App\Models\User;
use App\Services\Reports\MembershipStatsService;
use App\Services\Reports\RedCrossUnitStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

// Import JsonResponse

class DivisionController extends Controller
{
    protected $redCrossUnitStatsService;
    protected $membershipStatsService;

    public function __construct(RedCrossUnitStatsService $redCrossUnitStatsService, MembershipStatsService $membershipStatsService)
    {
        $this->redCrossUnitStatsService = $redCrossUnitStatsService;
        $this->membershipStatsService = $membershipStatsService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $search = $request->get('search');
        $branchId = $request->get('branch_id');

        $divisionsQuery = Division::with(['branch', 'redCrossUnits'])
            ->join('branches', 'divisions.branch_id', '=', 'branches.id')
            ->select('divisions.*');

        // Apply search filter
        if ($search) {
            $divisionsQuery->where('divisions.name', 'LIKE', '%' . $search . '%');
        }

        // Apply branch filter — no access-level scoping, available to all viewers
        if ($branchId) {
            $divisionsQuery->where('divisions.branch_id', $branchId);
        }

        $divisions = $divisionsQuery
            ->orderBy('branches.name')
            ->orderBy('divisions.name')
            ->paginate(50)
            ->withQueryString(); // Preserve search parameters in pagination links

        // Per-division volunteer counts (users in active RC units) — single bulk query
        $divisionIds = $divisions->pluck('id');

        $volunteerCountsByDivision = User::select('red_cross_units.division_id', DB::raw('count(*) as count'))
            ->join('red_cross_units', 'users.red_cross_unit_id', '=', 'red_cross_units.id')
            ->where('red_cross_units.is_active', true)
            ->whereIn('red_cross_units.division_id', $divisionIds)
            ->groupBy('red_cross_units.division_id')
            ->pluck('count', 'division_id');

        // Per-division member counts (valid membership payments) — single bulk query
        $memberCountsByDivision = MembershipPayment::valid()
            ->select('division_id', DB::raw('count(distinct user_id) as count'))
            ->whereIn('division_id', $divisionIds)
            ->groupBy('division_id')
            ->pluck('count', 'division_id');

        // Statistics for the dashboard cards
        $totalDivisions = Division::count();
        $totalUnits = Division::withCount('redCrossUnits')->get()->sum('red_cross_units_count');
        $branchesWithDivisions = Division::distinct('branch_id')->count('branch_id');
        $totalVolunteers = $this->redCrossUnitStatsService->getActiveUnitVolunteersCount();
        $totalMembers = $this->membershipStatsService->getTotalMembersCount();

        $branches = Branch::orderBy('name')->get();

        return view('divisions.index', compact(
            'divisions',
            'search',
            'branchId',
            'branches',
            'volunteerCountsByDivision',
            'memberCountsByDivision',
            'totalDivisions',
            'totalUnits',
            'branchesWithDivisions',
            'totalVolunteers',
            'totalMembers'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $branches = Branch::orderBy('name')->get();
        return view('divisions.create', compact('branches'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'branch_id' => 'required|exists:branches,id',
        ]);

        Division::create($validated);

        return redirect()->route('divisions.index')
            ->with('success', 'Detachment created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Division $division): View
    {
        $division->load(['branch', 'redCrossUnits' => function ($query) {
            $query->withCount('activeUsers')->orderBy('name');
        }])->loadCount('redCrossUnits');

        return view('divisions.show', compact('division'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Division $division): View
    {
        $branches = Branch::orderBy('name')->get();
        return view('divisions.edit', compact('division', 'branches'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Division $division): RedirectResponse
    {
        $validated = $request->validate([
            'physical_address' => 'nullable|string|max:150',
            'postal_address' => 'nullable|string|max:100',
            'telephone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
        ]);

        $division->update($validated);

        return redirect()->route('divisions.show', $division)
            ->with('success', 'Division contact information updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Division $division): RedirectResponse
    {
        $division->delete();

        return redirect()->route('divisions.index')
            ->with('success', 'Detachment deleted successfully.');
    }

    /**
     * Get divisions by branch ID for AJAX requests.
     */
    /*public function getDivisionsByBranch(Request $request): JsonResponse
    {
        $branchId = $request->input('branch_id');

        if (!$branchId) {
            return response()->json([]); // Return empty if no branch ID is provided
        }

        $divisions = Division::where('branch_id', $branchId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($divisions);
    }*/

    /**
     * Return divisions for a branch, including coordinates, for the map.
     */
    public function getDivisionsForBranch(Branch $branch)
    {
        // Eager-load minimal fields (you can add more as needed)
        $divisions = $branch->divisions()
            ->select('id', 'name', 'branch_id', 'physical_address', 'latitude', 'longitude')
            ->get();

        return response()->json([
            'branch_id'  => $branch->id,
            'divisions'  => $divisions,
        ]);
    }

    public function getDivisionWithUnits(Division $division): \Illuminate\Http\JsonResponse
    {
        $division->load([
            'redCrossUnits' => function ($query) {
                $query->select('id', 'name', 'division_id')
                    ->withCount('activeUsers')                // adds active_users_count
                    ->having('active_users_count', '>', 0)     // exclude units with 0 members
                    ->orderByDesc('active_users_count');       // order by count desc
            },
        ]);

        return response()->json([
            'id'               => $division->id,
            'name'             => $division->name,
            'physical_address' => $division->physical_address,
            'units'            => $division->redCrossUnits->map(function ($unit) {
                return [
                    'id'            => $unit->id,
                    'name'          => $unit->name,
                    'members_count' => $unit->active_users_count,   // this is the sorted, non-zero count
                ];
            })->values(), // reindex array
        ]);
    }

}
