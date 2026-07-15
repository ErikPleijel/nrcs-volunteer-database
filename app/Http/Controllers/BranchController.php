<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\MembershipPayment;
use App\Models\RedCrossUnit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    /**
     * Display a listing of branches.
     */
    public function index(Request $request): View
    {
        $query = Branch::query();

        // Add search functionality if needed
        if ($request->has('search') && $request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('physical_address', 'like', "%{$search}%")
                  ->orWhere('postal_address', 'like', "%{$search}%");
            });
        }

        // Filter by status if needed
        if ($request->has('status') && $request->filled('status')) {
            if ($request->get('status') === 'active') {
                $query->where('is_active', true);
            } elseif ($request->get('status') === 'inactive') {
                $query->where('is_active', false);
            }
        }



        $branches = $query->withCount('divisions')
            ->with('divisions')
            ->orderBy('name')
            ->get();

        foreach ($branches as $branch) {
            $divisionIds = $branch->divisions->pluck('id');
            $branch->rc_units_count = RedCrossUnit::whereIn('division_id', $divisionIds)->count();
            $branch->volunteers_count = RedCrossUnit::whereIn('division_id', $divisionIds)->withCount('users')->get()->sum('users_count');
            $branch->members_count = MembershipPayment::valid()->whereIn('division_id', $divisionIds)->distinct('user_id')->count('user_id');
        }

        $totalDivisions = $branches->sum('divisions_count');
        $totalRcUnits = $branches->sum('rc_units_count');
        $totalVolunteers = $branches->sum('volunteers_count');
        $totalMembers = $branches->sum('members_count');

        return view('branches.index', compact(
            'branches',
            'totalDivisions',
            'totalRcUnits',
            'totalVolunteers',
            'totalMembers'
        ));
    }

    /**
     * Display the specified branch.
     */
    public function show(Branch $branch): View
    {
        // Load relationships without filtering by status since it doesn't exist
        $branch->load(['divisions', 'donations']);

        return view('branches.show', compact('branch'));
    }

    /**
     * Show the form for editing the specified branch.
     */
    public function edit(Branch $branch): View
    {
        $user = Auth::user();
        if ($user->getAccessLevel() !== 'national' && (int) $branch->id !== (int) $user->getScopedBranchId()) {
            abort(403, 'You can only edit your own branch.');
        }

        // Users who can be picked as public contacts:
        // - Have any role
        // - AND either:
        //   * belong to this branch, OR
        //   * belong to a division that belongs to this branch
        $divisionIds = $branch->divisions()->pluck('id');

        $contactCandidates = User::query()
            ->selectableForEntry()
            ->where(function ($q) use ($branch, $divisionIds) {
                $q->where('branch_id', $branch->id)
                    ->orWhereIn('division_id', $divisionIds);
            })
            ->whereHas('roles') // only users that actually have roles
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();

        return view('branches.edit', compact('branch', 'contactCandidates'));
    }


    /**
     * Update the specified branch in storage.
     */
    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $user = Auth::user();
        if ($user->getAccessLevel() !== 'national' && (int) $branch->id !== (int) $user->getScopedBranchId()) {
            abort(403, 'You can only edit your own branch.');
        }

        $validated = $request->validate([
            'physical_address' => ['nullable', 'string', 'max:500'],
            'postal_address'   => ['nullable', 'string', 'max:500'],
            'telephone'        => ['nullable', 'string', 'max:50'],
            'email'            => ['nullable', 'email', 'max:255'],
            'projects'         => ['nullable', 'integer', 'min:0'],

            // Contact persons (6 slots)
            'public_contact_user_id_1' => ['nullable', 'exists:users,id'],
            'public_contact_user_id_2' => ['nullable', 'exists:users,id'],
            'public_contact_user_id_3' => ['nullable', 'exists:users,id'],
            'public_contact_user_id_4' => ['nullable', 'exists:users,id'],
            'public_contact_user_id_5' => ['nullable', 'exists:users,id'],
            'public_contact_user_id_6' => ['nullable', 'exists:users,id'],

            'public_contact_position_1' => ['nullable', 'string', 'max:255'],
            'public_contact_position_2' => ['nullable', 'string', 'max:255'],
            'public_contact_position_3' => ['nullable', 'string', 'max:255'],
            'public_contact_position_4' => ['nullable', 'string', 'max:255'],
            'public_contact_position_5' => ['nullable', 'string', 'max:255'],
            'public_contact_position_6' => ['nullable', 'string', 'max:255'],
        ]);

        // Update only the editable fields
        $branch->update($validated);

        return redirect()
            ->route('branches.show', $branch)
            ->with('success', 'Branch updated successfully!');
    }

    /**
     * Get branches data for API (if needed for frontend components)
     */
    public function getBranchesApi(Request $request)
    {
        $query = Branch::active();

        // For map display - only branches with coordinates
        if ($request->has('with_coordinates')) {
            $query->withCoordinates();
        }

        $branches = $query->select([
            'id', 'name', 'code', 'zone',
            'latitude', 'longitude', 'telephone', 'email'
        ])->get();

        return response()->json($branches);
    }

    /**
     * Get branch statistics (if needed)
     */
    public function getStatistics(Branch $branch)
    {
        $stats = [
            'divisions_count' => $branch->divisions()->count(),
            'users_count' => $branch->users()->count(),
            'donations_count' => $branch->donations()->count(),
            'total_donations' => $branch->donations()->sum('amount'),
        ];

        return response()->json($stats);
    }
}
