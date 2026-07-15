<?php

namespace App\Services\Reports;

use App\Models\TaskForce;
use App\Models\TaskForceMember;
use App\Models\TaskForceType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TaskForceStatsService
{
    protected $cachePrefix = 'task_force_stats_';
    protected $cacheTtl = 300; // 5 minutes

    /**
     * Get comprehensive task force statistics
     */
    public function getComprehensiveStats()
    {
        return Cache::remember($this->cachePrefix . 'comprehensive', $this->cacheTtl, function () {
            return [
                'total_task_forces' => $this->getTotalTaskForces(),
                'active_task_forces' => $this->getActiveTaskForces(),
                'inactive_task_forces' => $this->getInactiveTaskForces(),
                'total_task_force_members' => $this->getTotalTaskForceMembers(),
                'active_task_force_members' => $this->getActiveTaskForceMembers(),
                'task_force_types_count' => $this->getTaskForceTypesCount(),
                'task_forces_with_leaders' => $this->getTaskForcesWithLeaders(),
                'task_forces_with_complete_leadership' => $this->getTaskForcesWithCompleteLeadership(),
                'task_forces_by_type' => $this->getTaskForcesByType(),
                'task_forces_by_branch' => $this->getTaskForcesByBranch(),
                'task_force_leadership_stats' => $this->getTaskForceLeadershipStats(),
                'recent_task_forces' => $this->getRecentTaskForces(),
            ];
        });
    }

    /**
     * Get total number of task forces
     */
    public function getTotalTaskForces()
    {
        return TaskForce::count();
    }

    /**
     * Get number of active task forces
     */
    public function getActiveTaskForces()
    {
        return TaskForce::active()->count();
    }

    /**
     * Get number of inactive task forces
     */
    public function getInactiveTaskForces()
    {
        return TaskForce::inactive()->count();
    }

    /**
     * Get total number of task force members
     */
    public function getTotalTaskForceMembers()
    {
        return TaskForceMember::count();
    }

    /**
     * Get number of members in active task forces
     */
    public function getActiveTaskForceMembers()
    {
        return TaskForceMember::whereHas('taskForce', function ($query) {
            $query->where('inactive', false);
        })->count();
    }

    /**
     * Get total number of task force types
     */
    public function getTaskForceTypesCount()
    {
        return TaskForceType::count();
    }

    /**
     * Get number of task forces with at least a team leader
     */
    public function getTaskForcesWithLeaders()
    {
        return TaskForce::active()->whereNotNull('team_leader_user_id')->count();
    }

    /**
     * Get number of task forces with complete leadership (both positions)
     */
    public function getTaskForcesWithCompleteLeadership()
    {
        return TaskForce::active()
            ->whereNotNull('team_leader_user_id')
            ->whereNotNull('assist_team_leader_user_id')
            ->count();
    }

    /**
     * Get task forces grouped by type
     */
    public function getTaskForcesByType()
    {
        $results = DB::table('task_forces')
            ->join('task_force_types', 'task_forces.task_force_type_id', '=', 'task_force_types.id')
            ->select([
                'task_force_types.id',
                'task_force_types.name as type_name',
                'task_force_types.level',
                DB::raw('COUNT(task_forces.id) as total_task_forces'),
                DB::raw('COUNT(CASE WHEN task_forces.inactive = false THEN 1 END) as active_task_forces'),
                DB::raw('COUNT(CASE WHEN task_forces.inactive = true THEN 1 END) as inactive_task_forces')
            ])
            ->groupBy('task_force_types.id', 'task_force_types.name', 'task_force_types.level')
            ->orderBy('task_force_types.level')
            ->orderBy('active_task_forces', 'desc')
            ->get();

        $formatted = [];
        foreach ($results as $item) {
            $formatted[] = [
                'type_id' => $item->id,
                'type_name' => $item->type_name,
                'level' => $item->level,
                'total_task_forces' => (int) $item->total_task_forces,
                'active_task_forces' => (int) $item->active_task_forces,
                'inactive_task_forces' => (int) $item->inactive_task_forces,
            ];
        }

        return $formatted;
    }

    /**
     * Get task forces grouped by branch
     */
    public function getTaskForcesByBranch()
    {
        $results = DB::table('task_forces')
            ->leftJoin('branches', 'task_forces.branch_id', '=', 'branches.id')
            ->select([
                'branches.id as branch_id',
                'branches.name as branch_name',
                DB::raw('COUNT(task_forces.id) as total_task_forces'),
                DB::raw('COUNT(CASE WHEN task_forces.inactive = false THEN 1 END) as active_task_forces'),
                DB::raw('COUNT(CASE WHEN task_forces.inactive = true THEN 1 END) as inactive_task_forces')
            ])
            ->groupBy('branches.id', 'branches.name')
            ->orderBy('active_task_forces', 'desc')
            ->get();

        $formatted = [];
        foreach ($results as $item) {
            $formatted[] = [
                'branch_id' => $item->branch_id,
                'branch_name' => $item->branch_name ?: 'No Branch Assigned',
                'total_task_forces' => (int) $item->total_task_forces,
                'active_task_forces' => (int) $item->active_task_forces,
                'inactive_task_forces' => (int) $item->inactive_task_forces,
            ];
        }

        return $formatted;
    }

    /**
     * Get leadership statistics
     */
    public function getTaskForceLeadershipStats()
    {
        $totalActive = TaskForce::active()->count();
        $withLeader = TaskForce::active()->whereNotNull('team_leader_user_id')->count();
        $withAssistant = TaskForce::active()->whereNotNull('assist_team_leader_user_id')->count();
        $withBoth = TaskForce::active()
            ->whereNotNull('team_leader_user_id')
            ->whereNotNull('assist_team_leader_user_id')
            ->count();

        return [
            'total_active_task_forces' => $totalActive,
            'task_forces_with_leader' => $withLeader,
            'task_forces_with_assistant' => $withAssistant,
            'task_forces_with_complete_leadership' => $withBoth,
            'task_forces_without_leader' => $totalActive - $withLeader,
            'leadership_completion_rate' => $totalActive > 0 ? round(($withBoth / $totalActive) * 100, 1) : 0,
        ];
    }

    /**
     * Get recently created task forces
     */
    public function getRecentTaskForces($limit = 5)
    {
        $results = DB::table('task_forces')
            ->leftJoin('task_force_types', 'task_forces.task_force_type_id', '=', 'task_force_types.id')
            ->leftJoin('branches', 'task_forces.branch_id', '=', 'branches.id')
            ->select([
                'task_forces.id',
                'task_forces.name',
                'task_forces.inactive',
                'task_forces.timestamp',
                'task_force_types.name as type_name',
                'branches.name as branch_name'
            ])
            ->orderBy('task_forces.timestamp', 'desc')
            ->limit($limit)
            ->get();

        $formatted = [];
        foreach ($results as $taskForce) {
            $timestamp = $taskForce->timestamp ? \Carbon\Carbon::parse($taskForce->timestamp) : \Carbon\Carbon::now();
            $formatted[] = [
                'id' => $taskForce->id,
                'name' => $taskForce->name,
                'type_name' => $taskForce->type_name ?: 'Unknown Type',
                'branch_name' => $taskForce->branch_name ?: 'No Branch',
                'is_active' => !$taskForce->inactive,
                'timestamp' => $timestamp,
                'created_days_ago' => $timestamp->diffInDays(now()),
            ];
        }

        return $formatted;
    }

    /**
     * Get task force statistics for a specific branch
     */
    public function getBranchStats($branchId)
    {
        return Cache::remember($this->cachePrefix . 'branch_' . $branchId, $this->cacheTtl, function () use ($branchId) {
            $taskForces = TaskForce::byBranch($branchId);
            $activeTaskForces = TaskForce::active()->byBranch($branchId);

            return [
                'total_task_forces' => $taskForces->count(),
                'active_task_forces' => $activeTaskForces->count(),
                'inactive_task_forces' => $taskForces->inactive()->count(),
                'total_members' => TaskForceMember::whereHas('taskForce', function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId);
                })->count(),
                'active_members' => TaskForceMember::whereHas('taskForce', function ($query) use ($branchId) {
                    $query->where('branch_id', $branchId)->where('inactive', false);
                })->count(),
            ];
        });
    }

    /**
     * Clear all cached statistics
     */
    public function clearAllCache(): void
    {
        $patterns = [
            'comprehensive',
            'task_forces_by_branch',
            'task_forces_by_type',
            'recent_task_forces',
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($this->cachePrefix . $pattern);
        }

        // Clear branch-specific caches
        $branches = \App\Models\Branch::pluck('id');
        foreach ($branches as $branchId) {
            Cache::forget($this->cachePrefix . 'branch_' . $branchId);
        }
    }
}
