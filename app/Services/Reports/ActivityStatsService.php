<?php

namespace App\Services\Reports;

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Branch;
use App\Models\Division;
use App\Models\RedCrossUnit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ActivityStatsService
{
    protected $cachePrefix = 'activity_stats_';

    protected $cacheTtl = 300; // 5 minutes

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
     * Get quick summary stats for dashboard display
     */
    public function getQuickStats()
    {
        return Cache::remember($this->cachePrefix.'quick_stats', $this->cacheTtl, function () {
            return [
                'total_activities' => $this->getTotalActivities(),
                'total_hours' => $this->getTotalHours(),
                'current_month_activities' => $this->getCurrentMonthActivities(),
                'current_month_hours' => $this->getCurrentMonthHours(),
                'active_activity_types' => $this->getActiveActivityTypes(),
                'top_types' => $this->getTopActivityTypes(3),
            ];
        });
    }

    /**
     * Get top activity types by count
     */
    private function getTopActivityTypes($limit = 3)
    {
        return DB::table('activities')
            ->join('activity_types', 'activities.activity_type_id', '=', 'activity_types.id')
            ->where('activities.is_deleted', false)
            ->where('activities.approval_status', 'approved') // Phase 2: only approved records are real
            ->groupBy('activity_types.id', 'activity_types.name')
            ->select([
                'activity_types.name as name',
                DB::raw('COUNT(activities.id) as count'),
            ])
            ->orderBy('count', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getActiveVolunteersTrendByGenderForChart(
        int $totalMonths,
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $unitId = null
    ): array {
        $cacheKey = "active_volunteers_trend_gender_{$totalMonths}m"
            .($branchId ? "_b_{$branchId}" : '')
            .($divisionId ? "_d_{$divisionId}" : '')
            .($unitId ? "_u_{$unitId}" : '');

        return $this->remember($cacheKey, function () use ($totalMonths, $branchId, $divisionId, $unitId) {
            $labels = [];
            $male = [];
            $female = [];

            $endDate = Carbon::now()->endOfMonth();

            for ($i = 0; $i < $totalMonths; $i++) {
                $date = $endDate->copy()->subMonths($i);

                $labels[] = $date->format('M Y');

                $base = DB::table('users')
                    ->where('users.created_at', '<=', $date)
                    ->where(function ($q) use ($date) {
                        $q->whereNull('users.deactivated_date')
                            ->orWhere('users.deactivated_date', '>', $date);
                    });

                if ($unitId) {
                    $base->where('users.red_cross_unit_id', $unitId);
                } else {
                    // Volunteer definition: assigned to an active unit
                    $base->whereExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('red_cross_units')
                            ->whereColumn('red_cross_units.id', 'users.red_cross_unit_id')
                            ->where('red_cross_units.is_active', true);
                    });

                    if ($divisionId) {
                        $base->whereExists(function ($q) use ($divisionId) {
                            $q->select(DB::raw(1))
                                ->from('red_cross_units')
                                ->whereColumn('red_cross_units.id', 'users.red_cross_unit_id')
                                ->where('red_cross_units.division_id', $divisionId);
                        });
                    }

                    if ($branchId) {
                        $base->whereExists(function ($q) use ($branchId) {
                            $q->select(DB::raw(1))
                                ->from('red_cross_units')
                                ->join('divisions', 'divisions.id', '=', 'red_cross_units.division_id')
                                ->whereColumn('red_cross_units.id', 'users.red_cross_unit_id')
                                ->where('divisions.branch_id', $branchId);
                        });
                    }
                }

                $counts = (clone $base)
                    ->selectRaw("
                        SUM(CASE WHEN users.gender = 'male' THEN 1 ELSE 0 END) AS male,
                        SUM(CASE WHEN users.gender = 'female' THEN 1 ELSE 0 END) AS female
                    ")
                    ->first();

                $male[] = (int) ($counts->male ?? 0);
                $female[] = (int) ($counts->female ?? 0);
            }

            return [
                'labels' => array_reverse($labels),
                'series' => [
                    'male' => array_reverse($male),
                    'female' => array_reverse($female),
                ],
            ];
        });
    }

    /**
     * Generic method to get total volunteers count grouped by any dimension
     *
     * @param  string  $groupBy  The dimension to group by ('branch', 'division', 'redCrossUnit')
     * @param  int|null  $branchId  Optional branch ID filter
     * @param  int|null  $divisionId  Optional division ID filter
     * @param  int|null  $redCrossUnitId  Optional Red Cross Unit ID filter
     */
    public function getTotalVolunteersCountGroupedBy(
        string $groupBy,
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $redCrossUnitId = null
    ): Collection {
        $cacheKey = "total_volunteers_by_{$groupBy}"
            .($branchId ? "_branch_{$branchId}" : '')
            .($divisionId ? "_division_{$divisionId}" : '')
            .($redCrossUnitId ? "_unit_{$redCrossUnitId}" : '');

        // Add versioning to cache key because the structure of the returned data is changing.
        $cacheKey .= '_v2_with_hours';

        return $this->remember($cacheKey, function () use ($groupBy, $branchId, $divisionId, $redCrossUnitId) {

            // A "volunteer" is a user associated with an active Red Cross Unit.
            $baseQuery = User::query()
                ->whereHas('redCrossUnit', function ($query) {
                    $query->where('is_active', true);
                });

            $hoursSubQuery = DB::table('activities')
                ->select('user_id', DB::raw('SUM(hours) as total_hours'))
                ->where('is_deleted', false)
                ->where('approval_status', 'approved') // Phase 2: only approved records are real
                ->groupBy('user_id');

            // Apply specific entity filters.
            if ($redCrossUnitId) {
                $baseQuery->where('users.red_cross_unit_id', $redCrossUnitId);
            }

            if ($divisionId) {
                // Filter users based on the division of their unit.
                $baseQuery->whereHas('redCrossUnit', function ($q) use ($divisionId) {
                    $q->where('division_id', $divisionId);
                });
            }

            if ($branchId) {
                // Filter users based on the branch of their unit's division.
                $baseQuery->whereHas('redCrossUnit.division', function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId);
                });
            }

            // Clone the base query for grouping.
            $query = clone $baseQuery;

            $query->leftJoinSub($hoursSubQuery, 'user_hours', 'user_hours.user_id', '=', 'users.id');

            $allEntities = collect([]);

            switch ($groupBy) {
                case 'branch':
                    // We join to aggregate. The base query has already filtered the relevant users.
                    $query->join('red_cross_units', 'users.red_cross_unit_id', '=', 'red_cross_units.id')
                        ->join('divisions', 'red_cross_units.division_id', '=', 'divisions.id')
                        ->join('branches', 'divisions.branch_id', '=', 'branches.id')
                        ->selectRaw('
                            branches.id,
                            branches.name,
                            COUNT(DISTINCT users.id) as volunteer_count,
                            SUM(COALESCE(user_hours.total_hours, 0)) as total_hours
                        ')
                        ->groupBy('branches.id', 'branches.name');

                    // Get all active branches to ensure all are listed, even if they have 0 volunteers.
                    $allEntities = Branch::active()->orderBy('name')->get();
                    break;

                case 'division':
                    $query->join('red_cross_units', 'users.red_cross_unit_id', '=', 'red_cross_units.id')
                        ->join('divisions', 'red_cross_units.division_id', '=', 'divisions.id')
                        ->selectRaw('
                            divisions.id,
                            divisions.name,
                            COUNT(DISTINCT users.id) as volunteer_count,
                            SUM(COALESCE(user_hours.total_hours, 0)) as total_hours
                        ')
                        ->groupBy('divisions.id', 'divisions.name');

                    $divisionQuery = Division::orderBy('name');
                    if ($branchId) {
                        $divisionQuery->where('branch_id', $branchId);
                    }
                    $allEntities = $divisionQuery->get();
                    break;

                case 'redCrossUnit':
                    $query->join('red_cross_units', 'users.red_cross_unit_id', '=', 'red_cross_units.id')
                        ->selectRaw('
                            red_cross_units.id,
                            red_cross_units.name,
                            COUNT(DISTINCT users.id) as volunteer_count,
                            SUM(COALESCE(user_hours.total_hours, 0)) as total_hours
                        ')
                        ->groupBy('red_cross_units.id', 'red_cross_units.name');

                    $unitQuery = RedCrossUnit::orderBy('name')->where('is_active', true);
                    if ($divisionId) {
                        $unitQuery->where('division_id', $divisionId);
                    } elseif ($branchId) {
                        $unitQuery->whereHas('division', function ($q) use ($branchId) {
                            $q->where('branch_id', $branchId);
                        });
                    }
                    $allEntities = $unitQuery->get();
                    break;

                default:
                    throw new \InvalidArgumentException("Invalid groupBy parameter: {$groupBy}.");
            }

            $stats = $query->get()->keyBy('id');

            // Map the counts to all available entities, showing 0 for those with no volunteers.
            return $allEntities->map(function ($entity) use ($stats) {
                $entityStats = $stats->get($entity->id);

                return [
                    'id' => $entity->id,
                    'name' => $entity->name,
                    'volunteer_count' => $entityStats->volunteer_count ?? 0,
                    'total_hours' => (int) ($entityStats->total_hours ?? 0),
                ];
            });
        });
    }

    /**
     * Get comprehensive activity statistics
     */
    public function getComprehensiveStats()
    {
        return Cache::remember($this->cachePrefix.'comprehensive', $this->cacheTtl, function () {
            return [
                'total_activities' => $this->getTotalActivities(),
                'total_hours' => $this->getTotalHours(),
                'current_month_activities' => $this->getCurrentMonthActivities(),
                'current_month_hours' => $this->getCurrentMonthHours(),
                'current_year_activities' => $this->getCurrentYearActivities(),
                'current_year_hours' => $this->getCurrentYearHours(),
                'active_activity_types' => $this->getActiveActivityTypes(),
                'total_activity_types' => $this->getTotalActivityTypes(),
                'activities_by_type' => $this->getActivitiesByType(),
                'activities_by_branch' => $this->getActivitiesByBranch(),
                'top_contributors' => $this->getTopContributors(),
                'red_cross_unit_activities' => $this->getRedCrossUnitActivities(),
            ];
        });
    }

    /**
     * Generates a dataset for a chart showing the trend of active volunteers over time.
     *
     * @param  int  $totalMonths  The total number of months to look back.
     * @param  int|null  $branchId  Optional branch ID to scope the query.
     * @param  int|null  $divisionId  Optional division ID to scope the query.
     * @param  int|null  $unitId  Optional Red Cross Unit ID to scope the query.
     * @return array An array containing 'labels' and 'values' for the chart.
     */
    public function getActiveVolunteersTrendForChart(
        int $totalMonths,
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $unitId = null
    ): array {
        $cacheKey = "active_volunteers_trend_{$totalMonths}m"
            .($branchId ? "_b_{$branchId}" : '')
            .($divisionId ? "_d_{$divisionId}" : '')
            .($unitId ? "_u_{$unitId}" : '');

        return $this->remember($cacheKey, function () use ($totalMonths, $branchId, $divisionId, $unitId) {
            $labels = [];
            $values = [];
            $endDate = Carbon::now()->endOfMonth();

            for ($i = 0; $i < $totalMonths; $i++) {
                $date = $endDate->copy()->subMonths($i);
                $labels[] = $date->format('M Y');

                $query = User::query()
                    ->where('created_at', '<=', $date) // User must have existed
                    ->where(function ($q) use ($date) {
                        $q->whereNull('deactivated_date')
                            ->orWhere('deactivated_date', '>', $date); // User must have been active
                    });

                if ($unitId) {
                    $query->where('red_cross_unit_id', $unitId);
                } else {
                    // The general definition of a volunteer requires being in an active unit
                    $query->whereHas('redCrossUnit', function ($q) {
                        $q->where('is_active', true);
                    });

                    if ($divisionId) {
                        $query->whereHas('redCrossUnit', function ($q) use ($divisionId) {
                            $q->where('division_id', $divisionId);
                        });
                    }
                    if ($branchId) {
                        $query->whereHas('redCrossUnit.division', function ($q) use ($branchId) {
                            $q->where('branch_id', $branchId);
                        });
                    }
                }

                $values[] = $query->count();
            }

            return [
                'labels' => array_reverse($labels),
                'values' => array_reverse($values),
            ];
        });
    }

    /**
     * Generates a dataset for a chart showing the trend of volunteer hours over time.
     *
     * @param  int  $totalMonths  The total number of months to look back.
     * @param  int|null  $branchId  Optional branch ID to scope the query.
     * @param  int|null  $divisionId  Optional division ID to scope the query.
     * @param  int|null  $unitId  Optional Red Cross Unit ID to scope the query.
     * @return array An array containing 'labels' and 'values' for the chart.
     */
    public function getVolunteerHoursTrendForChart(
        int $totalMonths,
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $unitId = null
    ): array {
        $cacheKey = "volunteer_hours_trend_{$totalMonths}m"
            .($branchId ? "_b_{$branchId}" : '')
            .($divisionId ? "_d_{$divisionId}" : '')
            .($unitId ? "_u_{$unitId}" : '');

        return $this->remember($cacheKey, function () use ($totalMonths, $branchId, $divisionId, $unitId) {
            $labels = [];
            $values = [];
            $endDate = Carbon::now()->endOfMonth();

            for ($i = 0; $i < $totalMonths; $i++) {
                $date = $endDate->copy()->subMonths($i);
                $labels[] = $date->format('M Y');

                $query = Activity::query()
                    ->where('is_deleted', false)
                    ->where('created_at', '<=', $date);

                if ($unitId) {
                    $query->where('assignable_type', \App\Models\RedCrossUnit::class)
                        ->where('assignable_id', $unitId);
                } elseif ($divisionId) {
                    $query->where('division_id', $divisionId);
                } elseif ($branchId) {
                    $query->where('branch_id', $branchId);
                }

                $values[] = $query->sum('hours') ?? 0;
            }

            return [
                'labels' => array_reverse($labels),
                'values' => array_reverse($values),
            ];
        });
    }

    /**
     * Get total number of active activities
     */
    public function getTotalActivities()
    {
        return Cache::remember($this->cachePrefix.'total_activities', $this->cacheTtl, function () {
            return Activity::active()->count();
        });
    }

    /**
     * Get total hours logged across all activities
     */
    public function getTotalHours()
    {
        return Cache::remember($this->cachePrefix.'total_hours', $this->cacheTtl, function () {
            return Activity::active()->sum('hours') ?? 0;
        });
    }

    /**
     * Get current month activities count
     */
    public function getCurrentMonthActivities()
    {
        return Cache::remember($this->cachePrefix.'current_month_activities', $this->cacheTtl, function () {
            return Activity::active()->currentMonth()->count();
        });
    }

    /**
     * Get current month hours
     */
    public function getCurrentMonthHours()
    {
        return Cache::remember($this->cachePrefix.'current_month_hours', $this->cacheTtl, function () {
            return Activity::active()->currentMonth()->sum('hours') ?? 0;
        });
    }

    /**
     * Get current year activities count
     */
    public function getCurrentYearActivities()
    {
        return Cache::remember($this->cachePrefix.'current_year_activities', $this->cacheTtl, function () {
            return Activity::active()->currentYear()->count();
        });
    }

    /**
     * Get current year hours
     */
    public function getCurrentYearHours()
    {
        return Cache::remember($this->cachePrefix.'current_year_hours', $this->cacheTtl, function () {
            return Activity::active()->currentYear()->sum('hours') ?? 0;
        });
    }

    /**
     * Get number of active activity types
     */
    public function getActiveActivityTypes()
    {
        return Cache::remember($this->cachePrefix.'active_activity_types', $this->cacheTtl, function () {
            return ActivityType::where('is_active', true)->count();
        });
    }

    /**
     * Get total number of activity types
     */
    public function getTotalActivityTypes()
    {
        return Cache::remember($this->cachePrefix.'total_activity_types', $this->cacheTtl, function () {
            return ActivityType::count();
        });
    }

    /**
     * Get activities grouped by type
     */
    public function getActivitiesByType()
    {
        return Cache::remember($this->cachePrefix.'activities_by_type', $this->cacheTtl, function () {
            return ActivityType::withCount(['activities' => function ($query) {
                $query->active();
            }])->get();
        });
    }

    /**
     * Get activities grouped by branch
     */
    public function getActivitiesByBranch()
    {
        return Cache::remember($this->cachePrefix.'activities_by_branch', $this->cacheTtl, function () {
            return Branch::withCount(['activities' => function ($query) {
                $query->active();
            }])->get();
        });
    }

    /**
     * Get top contributing volunteers by hours
     */
    public function getTopContributors($limit = 10)
    {
        return Cache::remember($this->cachePrefix.'top_contributors', $this->cacheTtl, function () use ($limit) {
            return User::withSum(['activities' => function ($query) {
                $query->active();
            }], 'hours')
                ->orderBy('activities_sum_hours', 'desc')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Point-in-time volunteer demographics snapshot (gender + age buckets + age-by-gender pyramid).
     * Mirrors MembershipStatsService::getDemographicsSnapshot() shape exactly
     * so the shared demographics Blade component works unchanged.
     */
    public function getVolunteerDemographicsSnapshot(
        ?int $branchId = null,
        ?int $divisionId = null,
        ?Carbon $atDate = null
    ): array {
        $atDate = $atDate?->copy()->endOfDay() ?? now()->endOfDay();

        $cacheKey = 'volunteer_demographics_'.
            $atDate->format('Y_m_d').
            '_b'.($branchId ?? 'all').
            '_d'.($divisionId ?? 'all');

        return $this->remember($cacheKey, function () use ($branchId, $divisionId, $atDate) {
            $query = User::volunteers();

            if ($branchId) {
                $query->whereHas('redCrossUnit.division', fn ($q) => $q->where('branch_id', $branchId));
            }
            if ($divisionId) {
                $query->whereHas('redCrossUnit', fn ($q) => $q->where('division_id', $divisionId));
            }

            $gender = $query->clone()
                ->selectRaw("
                    SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) AS female,
                    SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) AS male,
                    SUM(CASE WHEN gender NOT IN ('male','female') AND gender IS NOT NULL THEN 1 ELSE 0 END) AS other,
                    SUM(CASE WHEN gender IS NULL THEN 1 ELSE 0 END) AS unknown
                ")
                ->first()
                ->toArray();

            $ageBuckets = $query->clone()
                ->selectRaw('
                    SUM(CASE WHEN (YEAR(?) - users.birth_year) < 18 THEN 1 ELSE 0 END) AS age_0_17,
                    SUM(CASE WHEN (YEAR(?) - users.birth_year) BETWEEN 18 AND 25 THEN 1 ELSE 0 END) AS age_18_25,
                    SUM(CASE WHEN (YEAR(?) - users.birth_year) BETWEEN 26 AND 40 THEN 1 ELSE 0 END) AS age_26_40,
                    SUM(CASE WHEN (YEAR(?) - users.birth_year) BETWEEN 41 AND 60 THEN 1 ELSE 0 END) AS age_41_60,
                    SUM(CASE WHEN (YEAR(?) - users.birth_year) > 60 THEN 1 ELSE 0 END) AS age_60_plus,
                    SUM(CASE WHEN users.birth_year IS NULL THEN 1 ELSE 0 END) AS age_unknown
                ', [
                    $atDate->toDateString(),
                    $atDate->toDateString(),
                    $atDate->toDateString(),
                    $atDate->toDateString(),
                    $atDate->toDateString(),
                ])
                ->first()
                ->toArray();

            $agesByGenderRaw = $query->clone()
                ->selectRaw("
                    SUM(CASE WHEN (YEAR(?) - users.birth_year) < 15 AND users.gender = 'male' THEN 1 ELSE 0 END) AS under15_men,
                    SUM(CASE WHEN (YEAR(?) - users.birth_year) < 15 AND users.gender = 'female' THEN 1 ELSE 0 END) AS under15_women,
                    SUM(CASE WHEN (YEAR(?) - users.birth_year) BETWEEN 15 AND 24 AND users.gender = 'male' THEN 1 ELSE 0 END) AS age15_24_men,
                    SUM(CASE WHEN (YEAR(?) - users.birth_year) BETWEEN 15 AND 24 AND users.gender = 'female' THEN 1 ELSE 0 END) AS age15_24_women,
                    SUM(CASE WHEN (YEAR(?) - users.birth_year) BETWEEN 25 AND 34 AND users.gender = 'male' THEN 1 ELSE 0 END) AS age25_34_men,
                    SUM(CASE WHEN (YEAR(?) - users.birth_year) BETWEEN 25 AND 34 AND users.gender = 'female' THEN 1 ELSE 0 END) AS age25_34_women,
                    SUM(CASE WHEN (YEAR(?) - users.birth_year) BETWEEN 35 AND 44 AND users.gender = 'male' THEN 1 ELSE 0 END) AS age35_44_men,
                    SUM(CASE WHEN (YEAR(?) - users.birth_year) BETWEEN 35 AND 44 AND users.gender = 'female' THEN 1 ELSE 0 END) AS age35_44_women,
                    SUM(CASE WHEN (YEAR(?) - users.birth_year) BETWEEN 45 AND 54 AND users.gender = 'male' THEN 1 ELSE 0 END) AS age45_54_men,
                    SUM(CASE WHEN (YEAR(?) - users.birth_year) BETWEEN 45 AND 54 AND users.gender = 'female' THEN 1 ELSE 0 END) AS age45_54_women,
                    SUM(CASE WHEN (YEAR(?) - users.birth_year) BETWEEN 55 AND 64 AND users.gender = 'male' THEN 1 ELSE 0 END) AS age55_64_men,
                    SUM(CASE WHEN (YEAR(?) - users.birth_year) BETWEEN 55 AND 64 AND users.gender = 'female' THEN 1 ELSE 0 END) AS age55_64_women,
                    SUM(CASE WHEN (YEAR(?) - users.birth_year) >= 65 AND users.gender = 'male' THEN 1 ELSE 0 END) AS age65plus_men,
                    SUM(CASE WHEN (YEAR(?) - users.birth_year) >= 65 AND users.gender = 'female' THEN 1 ELSE 0 END) AS age65plus_women
                ", array_fill(0, 14, $atDate->toDateString()))
                ->first()
                ->toArray();

            $agesByGender = [
                'under15' => ['men' => (int) ($agesByGenderRaw['under15_men'] ?? 0), 'women' => (int) ($agesByGenderRaw['under15_women'] ?? 0)],
                'age15_24' => ['men' => (int) ($agesByGenderRaw['age15_24_men'] ?? 0), 'women' => (int) ($agesByGenderRaw['age15_24_women'] ?? 0)],
                'age25_34' => ['men' => (int) ($agesByGenderRaw['age25_34_men'] ?? 0), 'women' => (int) ($agesByGenderRaw['age25_34_women'] ?? 0)],
                'age35_44' => ['men' => (int) ($agesByGenderRaw['age35_44_men'] ?? 0), 'women' => (int) ($agesByGenderRaw['age35_44_women'] ?? 0)],
                'age45_54' => ['men' => (int) ($agesByGenderRaw['age45_54_men'] ?? 0), 'women' => (int) ($agesByGenderRaw['age45_54_women'] ?? 0)],
                'age55_64' => ['men' => (int) ($agesByGenderRaw['age55_64_men'] ?? 0), 'women' => (int) ($agesByGenderRaw['age55_64_women'] ?? 0)],
                'age65plus' => ['men' => (int) ($agesByGenderRaw['age65plus_men'] ?? 0), 'women' => (int) ($agesByGenderRaw['age65plus_women'] ?? 0)],
            ];

            return [
                'gender' => $gender,
                'ages' => $ageBuckets,
                'ages_by_gender' => $agesByGender,
            ];
        });
    }

    /**
     * Get activity count for Red Cross Units
     */
    public function getRedCrossUnitActivities()
    {
        return Cache::remember($this->cachePrefix.'red_cross_unit_activities', $this->cacheTtl, function () {
            return RedCrossUnit::withCount(['activities' => function ($query) {
                $query->active();
            }])->get();
        });
    }
}
