<?php

namespace App\Services\Reports;

use App\Models\Activity;
use App\Models\Branch;
use App\Models\Membership;
use App\Models\MembershipPayment;
use App\Models\RedCrossUnit;
use App\Models\TaskForce;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BranchStatsService
{
    public function __construct(
        private RedCrossUnitStatsService $redCrossUnitStatsService,
        private TaskForceStatsService $taskForceStatsService,
        private ActivityStatsService $activityStatsService,
        private MembershipStatsService $membershipStatsService
    ) {}

    /**
     * Get statistics for all branches using direct database queries
     */
    public function getAllBranchesStats(): array
    {
        return Cache::remember('all_branches_stats_direct', 1800, function () {
            $branchStats = [];

            $branches = Branch::active()->withCoordinates()->get();

            foreach ($branches as $branch) {
                $branchStats[$branch->id] = [
                    'volunteers' => $this->getVolunteersForBranch($branch->id),
                    'red_cross_units' => $this->getRedCrossUnitsForBranch($branch->id),
                    'task_forces' => $this->getTaskForcesForBranch($branch->id),
                    'activity_hours' => $this->getActivityHoursForBranch($branch->id),
                    'members' => $this->getMembersForBranch($branch->id),
                ];
            }

            return $branchStats;
        });
    }

    /**
     * Get statistics for a specific branch
     */
    public function getBranchStats(int $branchId): array
    {
        $allStats = $this->getAllBranchesStats();

        return $allStats[$branchId] ?? [
            'volunteers' => 0,
            'red_cross_units' => 0,
            'task_forces' => 0,
            'activity_hours' => 0,
            'members' => 0,
        ];
    }

    /**
     * Get volunteers count for a specific branch
     */
    private function getVolunteersForBranch(int $branchId): int
    {
        try {
            // Try to get volunteers through red cross units relationship
            $count = User::whereHas('redCrossUnit.division.branch', function ($query) use ($branchId) {
                $query->where('branches.id', $branchId);
            })->count();

            // If no volunteers found through red cross units, try direct branch assignment
            if ($count == 0) {
                $count = User::where('branch_id', $branchId)->count();
            }

            return $count;
        } catch (\Exception $e) {
            Log::warning("Error getting volunteers for branch {$branchId}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get Red Cross units count for a specific branch
     */
    private function getRedCrossUnitsForBranch(int $branchId): int
    {
        try {
            $count = RedCrossUnit::whereHas('division.branch', function ($query) use ($branchId) {
                $query->where('branches.id', $branchId);
            })->count();

            // If no units found through division relationship, try direct branch assignment
            if ($count == 0) {
                $count = RedCrossUnit::where('branch_id', $branchId)->count();
            }

            return $count;
        } catch (\Exception $e) {
            Log::warning("Error getting red cross units for branch {$branchId}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get task forces count for a specific branch
     */
    private function getTaskForcesForBranch(int $branchId): int
    {
        try {
            $count = TaskForce::where('branch_id', $branchId)
                ->where('inactive', false)
                ->count();

            return $count;
        } catch (\Exception $e) {
            Log::warning("Error getting task forces for branch {$branchId}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get total activity hours for a specific branch
     */
    private function getActivityHoursForBranch(int $branchId): int
    {
        try {
            $hours = Activity::where('branch_id', $branchId)
                ->where('is_deleted', false)
                ->sum('hours') ?? 0;

            return (int) $hours;
        } catch (\Exception $e) {
            Log::warning("Error getting activity hours for branch {$branchId}: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get members count for a specific branch - using the same logic as MembershipStatsService
     */
    private function getMembersForBranch(int $branchId): int
    {
        try {
            // Use the same valid() scope that MembershipStatsService uses
            $count = MembershipPayment::valid()
                ->where('branch_id', $branchId)
                ->distinct('user_id')
                ->count('user_id');

            // If no branch_id on membership_payments, try through division_id
            if ($count == 0) {
                $count = MembershipPayment::valid()
                    ->whereHas('division.branch', function ($query) use ($branchId) {
                        $query->where('branches.id', $branchId);
                    })
                    ->distinct('user_id')
                    ->count('user_id');
            }

            // If still 0, try through user's branch_id
            if ($count == 0) {
                $count = MembershipPayment::valid()
                    ->whereHas('user', function ($query) use ($branchId) {
                        $query->where('branch_id', $branchId);
                    })
                    ->distinct('user_id')
                    ->count('user_id');
            }

            return $count;
        } catch (\Exception $e) {
            Log::warning("Error getting members for branch {$branchId}: " . $e->getMessage());
            return 0;
        }
    }



    /**
     * Get detailed volunteers count by method
     */
    private function getVolunteersDetailedCount(int $branchId, string $method): int
    {
        try {
            switch ($method) {
                case 'red_cross_units':
                    return User::whereHas('redCrossUnit.division.branch', function ($query) use ($branchId) {
                        $query->where('branches.id', $branchId);
                    })->count();

                case 'direct':
                    return User::where('branch_id', $branchId)->count();

                default:
                    return 0;
            }
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get detailed red cross units count by method
     */
    private function getRedCrossUnitsDetailedCount(int $branchId, string $method): int
    {
        try {
            switch ($method) {
                case 'division':
                    return RedCrossUnit::whereHas('division.branch', function ($query) use ($branchId) {
                        $query->where('branches.id', $branchId);
                    })->count();

                case 'direct':
                    return RedCrossUnit::where('branch_id', $branchId)->count();

                default:
                    return 0;
            }
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get detailed members count by method
     */
    private function getMembersDetailedCount(int $branchId, string $method): int
    {
        try {
            switch ($method) {
                case 'active_payments':
                    return MembershipPayment::where('branch_id', $branchId)
                        ->whereHas('membership', function ($query) {
                            $query->where('status', 'active')
                                ->where('end_date', '>=', now());
                        })
                        ->distinct('user_id')
                        ->count('user_id');

                case 'active_direct':
                    return Membership::where('branch_id', $branchId)
                        ->where('status', 'active')
                        ->where('end_date', '>=', now())
                        ->distinct('user_id')
                        ->count('user_id');

                case 'all_payments':
                    return MembershipPayment::where('branch_id', $branchId)
                        ->distinct('user_id')
                        ->count('user_id');

                case 'all_memberships':
                    return Membership::where('branch_id', $branchId)
                        ->distinct('user_id')
                        ->count('user_id');

                case 'users_direct':
                    return User::where('branch_id', $branchId)->count();

                default:
                    return 0;
            }
        } catch (\Exception $e) {
            return 0;
        }
    }





}
