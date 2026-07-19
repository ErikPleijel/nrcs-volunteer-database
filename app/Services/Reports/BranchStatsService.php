<?php

namespace App\Services\Reports;

use App\Models\Branch;
use App\Models\Membership;
use App\Models\MembershipPayment;
use App\Models\RedCrossUnit;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
            $branches = Branch::active()->withCoordinates()->get();
            $branchIds = $branches->pluck('id');

            // Looser "volunteer" definition than User::scopeVolunteers() —
            // no is_active check on the RC unit, no lifecycle_status filter —
            // preserved as-is from the original per-branch primary query.
            $volunteerCounts = DB::table('users')
                ->join('red_cross_units', 'users.red_cross_unit_id', '=', 'red_cross_units.id')
                ->join('divisions', 'red_cross_units.division_id', '=', 'divisions.id')
                ->whereIn('divisions.branch_id', $branchIds)
                ->groupBy('divisions.branch_id')
                ->selectRaw('divisions.branch_id, COUNT(*) as cnt')
                ->pluck('cnt', 'branch_id');

            $rcuCounts = DB::table('red_cross_units')
                ->join('divisions', 'red_cross_units.division_id', '=', 'divisions.id')
                ->whereIn('divisions.branch_id', $branchIds)
                ->groupBy('divisions.branch_id')
                ->selectRaw('divisions.branch_id, COUNT(*) as cnt')
                ->pluck('cnt', 'branch_id');

            $taskForceCounts = DB::table('task_forces')
                ->whereIn('branch_id', $branchIds)
                ->where('inactive', false)
                ->groupBy('branch_id')
                ->selectRaw('branch_id, COUNT(*) as cnt')
                ->pluck('cnt', 'branch_id');

            // Activity carries the Approvable trait's ApprovedScope global scope
            // (approval_status = 'approved'), applied automatically whenever queried
            // through Eloquent. Raw DB::table() bypasses that, so it's added explicitly
            // here to match what Activity::where(...) actually produces.
            $activityHours = DB::table('activities')
                ->whereIn('branch_id', $branchIds)
                ->where('is_deleted', false)
                ->where('approval_status', 'approved')
                ->groupBy('branch_id')
                ->selectRaw('branch_id, SUM(hours) as total_hours')
                ->pluck('total_hours', 'branch_id');

            // Matches MembershipPayment::scopeValid() (expiry_date >= today, is_deleted
            // = false) PLUS the Approvable trait's ApprovedScope global scope
            // (approval_status = 'approved') that MembershipPayment::valid() gets for
            // free through Eloquent — added explicitly here since DB::table() bypasses
            // Eloquent's global scopes entirely.
            $memberCounts = DB::table('membership_payments')
                ->whereIn('branch_id', $branchIds)
                ->where('expiry_date', '>=', now()->toDateString())
                ->where('is_deleted', false)
                ->where('approval_status', 'approved')
                ->groupBy('branch_id')
                ->selectRaw('branch_id, COUNT(DISTINCT user_id) as cnt')
                ->pluck('cnt', 'branch_id');

            $branchStats = [];
            foreach ($branches as $branch) {
                $branchStats[$branch->id] = [
                    'volunteers' => (int) ($volunteerCounts[$branch->id] ?? 0),
                    'red_cross_units' => (int) ($rcuCounts[$branch->id] ?? 0),
                    'task_forces' => (int) ($taskForceCounts[$branch->id] ?? 0),
                    'activity_hours' => (int) ($activityHours[$branch->id] ?? 0),
                    'members' => (int) ($memberCounts[$branch->id] ?? 0),
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
