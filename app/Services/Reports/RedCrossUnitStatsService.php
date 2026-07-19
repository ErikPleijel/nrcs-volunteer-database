<?php

namespace App\Services\Reports;

use App\Models\RedCrossUnit;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB; // Added DB facade for getUnitsWithManyMembers and getMostActiveUnit

class RedCrossUnitStatsService
{

    /**
     * Global TTL for all membership stats cache (in seconds).
     *
     * During dev, you can set this to 1 (or even 0 to disable caching).
     * In production, 3600 (1 hour) is a reasonable default.
     */
    private int $cacheTtl = 3600;

    /**
     * Small helper so we don't repeat Cache::remember everywhere.
     */
    private function remember(string $key, \Closure $callback)
    {
        // If TTL <= 0, skip cache entirely (useful in dev/debug)
        if ($this->cacheTtl <= 0) {
            return $callback();
        }

        return Cache::remember($key, $this->cacheTtl, $callback);
    }

    /**
     * Get the count of volunteers attached to active red cross units
     *
     * @param int|null $branchId Filter by branch ID (null for all branches)
     */
    public function getActiveUnitVolunteersCount(?int $branchId = null): int
    {
        $cacheKey = 'red_cross_volunteers_active_units' . ($branchId ? '_branch_' . $branchId : '');

        return $this->remember($cacheKey, function () use ($branchId) {
            $query = User::whereHas('redCrossUnit', function ($query) {
                $query->where('is_active', true);
            })
            ->where('lifecycle_status', '!=', 'archived');

            if ($branchId) {
                $query->whereHas('redCrossUnit.division.branch', function ($q) use ($branchId) {
                    $q->where('id', $branchId);
                });
            }

            return $query->count();
        });
    }


    public function getActiveUnitVolunteersCountAt(Carbon $date, ?int $branchId = null): int
    {
        $cacheKey = 'red_cross_volunteers_active_units_at_' . $date->format('Y_m_d')
            . ($branchId ? '_branch_' . $branchId : '');

        return $this->remember($cacheKey, function () use ($branchId, $date) {
            $cutoff = $date->copy()->endOfDay();

            $query = User::query()
                ->whereNotNull('red_cross_unit_id')
                ->whereNotNull('assigned_rcu_date')
                ->where('assigned_rcu_date', '<=', $cutoff)
                ->where('lifecycle_status', '!=', 'archived');

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            return $query->count();
        });
    }



    public function getActiveUnitVolunteersCount12MonthsAgo(?int $branchId = null): int
    {
        $date = now()->subYear(); // or ->subMonthsNoOverflow(12)

        return $this->getActiveUnitVolunteersCountAt($date, $branchId);
    }

    /**
     * Calculate the sum of volunteering hours between two dates
     *
     * @param Carbon $startDate Start date for calculating hours
     * @param Carbon $endDate End date for calculating hours
     * @param int|null $branchId Filter by branch ID (null for all branches)
     * @return float Total volunteering hours
     */
    public function getVolunteeringHoursBetweenDates(Carbon $startDate, Carbon $endDate, ?int $branchId = null): float
    {
        $cacheKey = 'volunteering_hours_' . $startDate->format('Y_m_d') . '_to_' . $endDate->format('Y_m_d')
            . ($branchId ? '_branch_' . $branchId : '');

        return $this->remember($cacheKey, function () use ($startDate, $endDate, $branchId) {
            $query = \App\Models\Activity::active()
                ->whereBetween('date', [$startDate->startOfDay(), $endDate->endOfDay()]);

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            return $query->sum('hours') ?? 0;
        });
    }

    /**
     * Get the total volunteering hours between now and 12 months ago
     *
     * @param int|null $branchId Filter by branch ID (null for all branches)
     * @return float Total volunteering hours in the last 12 months
     */
    public function getVolunteeringHoursLast12Months(?int $branchId = null): float
    {
        $endDate = now();
        $startDate = $endDate->copy()->subYear();

        return $this->getVolunteeringHoursBetweenDates($startDate, $endDate, $branchId);
    }

    /**
     * Get the total volunteering hours between 12 and 24 months ago
     *
     * @param int|null $branchId Filter by branch ID (null for all branches)
     * @return float Total volunteering hours from 12 to 24 months ago
     */
    public function getVolunteeringHoursBetween12And24MonthsAgo(?int $branchId = null): float
    {
        $endDate = now()->subYear();
        $startDate = $endDate->copy()->subYear();

        return $this->getVolunteeringHoursBetweenDates($startDate, $endDate, $branchId);
    }

    /**
     * Calculate the number of active volunteers at a specific date
     * An active volunteer is someone who has logged activities between the given date and 12 months prior
     *
     * @param Carbon $date The reference date to check for active volunteers
     * @param int|null $branchId Filter by branch ID (null for all branches)
     * @return int Count of active volunteers at the given date
     */
    public function getActiveVolunteersAtDate(Carbon $date, ?int $branchId = null): int
    {
        $cacheKey = 'active_volunteers_at_' . $date->format('Y_m_d')
            . ($branchId ? '_branch_' . $branchId : '');

        return $this->remember($cacheKey, function () use ($date, $branchId) {
            $endDate  = $date->copy()->endOfDay();
            $startDate = $date->copy()->subYear()->startOfDay();

            $query = Activity::query()
                ->active() // ->where('is_deleted', false)
                ->whereBetween('date', [$startDate, $endDate]);

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            // Count distinct volunteers
            return $query->distinct('user_id')->count('user_id');
        });
    }

    /**
     * Get the current number of active volunteers
     * An active volunteer is someone who has logged activities in the last 12 months
     *
     * @param int|null $branchId Filter by branch ID (null for all branches)
     * @return int Count of currently active volunteers
     */
    public function getCurrentActiveVolunteers(?int $branchId = null): int
    {
        return $this->getActiveVolunteersAtDate(now(), $branchId);
    }

    /**
     * Get the number of active volunteers as of 12 months ago
     * These are volunteers who logged activities between 12-24 months ago
     *
     * @param int|null $branchId Filter by branch ID (null for all branches)
     * @return int Count of volunteers who were active 12 months ago
     */
    public function getActiveVolunteers12MonthsAgo(?int $branchId = null): int
    {
        $date = now()->subYear();

        return $this->getActiveVolunteersAtDate($date, $branchId);
    }

    /**
     * Active units count (optionally filtered by branch).
     */
    public function getActiveUnitsCount(?int $branchId = null): int
    {
        $cacheKey = 'active_units' . ($branchId ? "_branch_{$branchId}" : '_all');

        return $this->remember($cacheKey, function () use ($branchId) {
            $query = RedCrossUnit::query()
                ->active()
                ->join('divisions', 'divisions.id', '=', 'red_cross_units.division_id');

            if ($branchId) {
                $query->where('divisions.branch_id', $branchId);
            }

            return (int) $query->count('red_cross_units.id');
        });
    }

    /**
     * Average number of active members per active unit.
     */
    public function getAverageMembersPerActiveUnit(?int $branchId = null): float
    {
        $cacheKey = 'avg_members_per_unit' . ($branchId ? "_branch_{$branchId}" : '_all');

        return $this->remember($cacheKey, function () use ($branchId) {
            $query = RedCrossUnit::query()
                ->active()
                ->join('divisions', 'divisions.id', '=', 'red_cross_units.division_id');

            if ($branchId) {
                $query->where('divisions.branch_id', $branchId);
            }

            // Join users — volunteers are in an RC unit and not archived
            $query->leftJoin('users', 'users.red_cross_unit_id', '=', 'red_cross_units.id')
                ->where(function ($q) {
                    $q->whereNull('users.lifecycle_status')
                      ->orWhere('users.lifecycle_status', '!=', 'archived');
                });

            $stats = $query->selectRaw('
                    COUNT(DISTINCT red_cross_units.id) as unit_count,
                    COUNT(users.id) as member_count
                ')
                ->first();

            if (!$stats || $stats->unit_count == 0) {
                return 0.0;
            }

            return round($stats->member_count / $stats->unit_count, 1);
        });
    }

    /**
     * % of active units that have at least one leader (team_leader or assistant).
     */
    public function getLeadershipCoveragePercent(?int $branchId = null): float
    {
        $cacheKey = 'leadership_coverage' . ($branchId ? "_branch_{$branchId}" : '_all');

        return $this->remember($cacheKey, function () use ($branchId) {
            $base = RedCrossUnit::query()
                ->active()
                ->join('divisions', 'divisions.id', '=', 'red_cross_units.division_id');

            if ($branchId) {
                $base->where('divisions.branch_id', $branchId);
            }

            $totalUnits = (int) $base->count('red_cross_units.id');

            if ($totalUnits === 0) {
                return 0.0;
            }

            $withLeadership = (int) (clone $base)
                ->where(function ($q) {
                    $q->whereNotNull('team_leader_user_id')
                        ->orWhereNotNull('assistant_team_leader_user_id');
                })
                ->count('red_cross_units.id');

            return round(($withLeadership / $totalUnits) * 100, 1);
        });
    }

    /**
     * Active units WITHOUT any leadership, for follow-up.
     */
    public function getUnitsWithoutLeadershipCount(?int $branchId = null): int
    {
        $cacheKey = 'units_without_leadership' . ($branchId ? "_branch_{$branchId}" : '_all');

        return $this->remember($cacheKey, function () use ($branchId) {
            $query = RedCrossUnit::query()
                ->active()
                ->join('divisions', 'divisions.id', '=', 'red_cross_units.division_id');

            if ($branchId) {
                $query->where('divisions.branch_id', $branchId);
            }

            return (int) $query
                ->whereNull('team_leader_user_id')
                ->whereNull('assistant_team_leader_user_id')
                ->count('red_cross_units.id');
        });
    }


    /**
     * Average age by gender among active users in units (female + male only).
     *
     * Returns:
     * [
     *   'female' => 29,
     *   'male'   => 31,
     * ]
     */
    public function getAverageAgeByGender(?int $branchId = null): array
    {
        $cacheKey = 'avg_age_by_gender' . ($branchId ? "_branch_{$branchId}" : '_all');

        return $this->remember($cacheKey, function () use ($branchId) {
            $currentYear = now()->year;

            $query = DB::table('users')
                ->whereNotNull('birth_year')
                ->where(function ($q) {
                    $q->whereNull('lifecycle_status')
                      ->orWhere('lifecycle_status', '!=', 'archived');
                })
                ->whereNotNull('red_cross_unit_id');

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            $rows = $query
                ->selectRaw("
                gender,
                AVG($currentYear - birth_year) as avg_age
            ")
                ->whereIn('gender', ['female', 'male'])
                ->groupBy('gender')
                ->pluck('avg_age', 'gender')
                ->toArray();

            return [
                'female' => isset($rows['female']) ? round($rows['female']) : null,
                'male'   => isset($rows['male'])   ? round($rows['male'])   : null,
            ];
        });
    }




}
