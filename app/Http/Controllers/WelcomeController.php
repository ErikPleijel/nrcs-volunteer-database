<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Services\Reports\ActivityStatsService;
use App\Services\Reports\BranchStatsService;
use App\Services\Reports\MembershipStatsService;
use App\Services\Reports\RedCrossUnitStatsService;
use App\Services\Reports\TaskForceStatsService;

class WelcomeController extends Controller
{
    public function __construct(
        private MembershipStatsService $membershipStatsService,
        private RedCrossUnitStatsService $redCrossUnitStatsService,
        private TaskForceStatsService $taskForceStatsService,
        private ActivityStatsService $activityStatsService,
        private BranchStatsService $branchStatsService
    ) {}

    public function index()
    {

        $currentMembership = null;
        // Get key statistics from existing services
        $totalMembers = $this->membershipStatsService->getTotalMembersCount();
        $totalVolunteers = $this->redCrossUnitStatsService->getActiveUnitVolunteersCount();
        $totalRedCrossUnits = $this->redCrossUnitStatsService->getActiveUnitsCount();
        $totalTaskForces = $this->taskForceStatsService->getTotalTaskForces();
        $totalActivityHours = $this->activityStatsService->getTotalHours();


        // Get branches with coordinates for the map, including divisions
        $branches = Branch::active()
            ->withCoordinates()
            ->with(['divisions' => function($query) {
                $query->select('id', 'name', 'branch_id')->orderBy('name');
            }])
            ->select([
                'id', 'name', 'code', 'zone', 'is_active',
                'physical_address', 'telephone', 'email',
                'latitude', 'longitude', 'projects',
            ])
            ->get();

        $branchesCount = $branches->count();



        // Get statistics for all branches for the map (using direct method)
        //$branchesStats = $this->branchStatsService->getAllBranchesStatsDirectly();

        // Get statistics for all branches for the map
        $branchesStats = $this->branchStatsService->getAllBranchesStats();

        return view('welcome', compact(
            'totalMembers',
            'totalVolunteers',
            'totalRedCrossUnits',
            'totalTaskForces',
            'totalActivityHours',
            'branchesCount',
            'branches',
            'branchesStats'
        ));
    }
}
