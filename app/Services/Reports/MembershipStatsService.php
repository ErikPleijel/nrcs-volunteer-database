<?php

namespace App\Services\Reports;

use App\Models\Branch;
use App\Models\MembershipPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MembershipStatsService
{
    /**
     * Global TTL for all membership stats cache (in seconds).
     *
     * During dev, you can set this to 1 (or even 0 to disable caching).
     * In production, 3600 (1 hour) is a reasonable default.
     */
    private int $cacheTtl = 1;

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

    protected function ensureReportMonthsRange(Carbon $start, Carbon $end): void
    {
        $start = $start->copy()->startOfMonth();
        $end = $end->copy()->startOfMonth();

        // Fetch existing month_start values in range
        $existing = DB::table('report_months')
            ->whereBetween('month_start', [$start->toDateString(), $end->toDateString()])
            ->pluck('month_start')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-01'))
            ->all();

        $existingSet = array_flip($existing);

        $rows = [];
        $now = now();

        for ($date = $start->copy(); $date <= $end; $date->addMonth()) {
            $key = $date->format('Y-m-01');

            if (! isset($existingSet[$key])) {
                $rows[] = [
                    'month_start' => $date->toDateString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (! empty($rows)) {
            DB::table('report_months')->insert($rows);
        }
    }

    /**
     * Get the total count of active members (valid membership payments)
     *
     * @param  int|null  $branchId  Filter by branch ID (null for all branches)
     * @param  int|null  $divisionId  Filter by division ID
     * @param  int|null  $redCrossUnitId  Filter by Red Cross Unit ID
     * @param  int|null  $userId  Filter by user ID
     */
    public function getTotalMembersCount(
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $redCrossUnitId = null,
        ?int $userId = null
    ): int {
        $cacheKey = 'membership_total_members'
            .($branchId ? '_branch_'.$branchId : '')
            .($divisionId ? '_division_'.$divisionId : '')
            .($redCrossUnitId ? '_unit_'.$redCrossUnitId : '')
            .($userId ? '_user_'.$userId : '');

        return $this->remember($cacheKey, function () use ($branchId, $divisionId, $redCrossUnitId, $userId) {
            $query = \App\Models\User::members();

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }
            if ($divisionId) {
                $query->where('division_id', $divisionId);
            }
            if ($redCrossUnitId) {
                $query->where('red_cross_unit_id', $redCrossUnitId);
            } // returns 0 by definition; kept for signature compatibility
            if ($userId) {
                $query->where('id', $userId);
            }

            return $query->count();
        });
    }

    /**
     * Generic method to get total members count grouped by any dimension
     *
     * @param  string  $groupBy  The dimension to group by ('branch', 'division', 'redCrossUnit', 'user')
     * @param  int|null  $branchId  Optional branch ID filter
     * @param  int|null  $divisionId  Optional division ID filter
     * @param  int|null  $redCrossUnitId  Optional Red Cross Unit ID filter
     */
    public function getTotalMembersCountGroupedBy(
        string $groupBy,
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $redCrossUnitId = null
    ): Collection {
        $cacheKey = "membership_members_by_{$groupBy}"
            .($branchId ? "_branch_{$branchId}" : '')
            .($divisionId ? "_division_{$divisionId}" : '')
            .($redCrossUnitId ? "_unit_{$redCrossUnitId}" : '');

        return $this->remember($cacheKey, function () use ($groupBy, $branchId, $divisionId) {
            $query = \App\Models\User::members();

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            if ($divisionId) {
                $query->where('division_id', $divisionId);
            }

            // Configure group by dimension
            switch ($groupBy) {
                case 'branch':
                    $query->leftJoin('branches', 'users.branch_id', '=', 'branches.id')
                        ->selectRaw('
                            branches.id,
                            COALESCE(branches.name, "No Branch") as name,
                            COUNT(users.id) as member_count
                        ')
                        ->groupBy('branches.id', 'branches.name');

                    $allEntities = Branch::active()->orderBy('name')->get();
                    $idField = 'branch_id';
                    break;

                case 'division':
                    $query->leftJoin('divisions', 'users.division_id', '=', 'divisions.id')
                        ->selectRaw('
                            divisions.id,
                            COALESCE(divisions.name, "No Division") as name,
                            COUNT(users.id) as member_count
                        ')
                        ->groupBy('divisions.id', 'divisions.name');

                    $divisionQuery = \App\Models\Division::orderBy('name');
                    if ($branchId) {
                        $divisionQuery->where('branch_id', $branchId);
                    }
                    $allEntities = $divisionQuery->get();
                    $idField = 'division_id';
                    break;

                case 'redCrossUnit':
                    // scopeMembers() requires red_cross_unit_id IS NULL, so all unit counts are 0.
                    $query->leftJoin('red_cross_units', 'users.red_cross_unit_id', '=', 'red_cross_units.id')
                        ->selectRaw('
                            red_cross_units.id,
                            COALESCE(red_cross_units.name, "No Unit") as name,
                            COUNT(users.id) as member_count
                        ')
                        ->groupBy('red_cross_units.id', 'red_cross_units.name');

                    $unitQuery = \App\Models\RedCrossUnit::orderBy('name');
                    if ($divisionId) {
                        $unitQuery->where('division_id', $divisionId);
                    } elseif ($branchId) {
                        $unitQuery->whereHas('division', function ($q) use ($branchId) {
                            $q->where('branch_id', $branchId);
                        });
                    }
                    $allEntities = $unitQuery->get();
                    $idField = 'red_cross_unit_id';
                    break;

                case 'user':
                    $query->selectRaw('
                            users.id,
                            CONCAT(users.first_name, " ", users.last_name) as full_name,
                            users.id as member_id,
                            users.created_at as member_since,
                            COUNT(users.id) as member_count
                        ')
                        ->groupBy('users.id', 'users.first_name', 'users.last_name', 'users.created_at')
                        ->orderBy('users.first_name')
                        ->orderBy('users.last_name');

                    return $query->get();

                default:
                    throw new \InvalidArgumentException("Invalid groupBy parameter: {$groupBy}");
            }

            $counts = $query->pluck('member_count', 'id');

            return $allEntities->map(function ($entity) use ($counts) {
                return [
                    'id' => $entity->id,
                    'name' => $entity->name,
                    'member_count' => $counts->get($entity->id, 0),
                ];
            });
        });
    }

    /**
     * Get total members count grouped by branch
     * This is now a wrapper around the generic method
     */
    public function getTotalMembersCountByBranch(): Collection
    {
        return $this->getTotalMembersCountGroupedBy('branch');
    }

    /**
     * Get new members in the last 12 months
     *
     * @param  int|null  $branchId  Filter by branch ID
     * @param  int|null  $divisionId  Filter by division ID
     * @param  int|null  $redCrossUnitId  Filter by Red Cross Unit ID
     * @param  int|null  $userId  Filter by user ID
     */
    public function getNewMembersLast12Months(
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $redCrossUnitId = null,
        ?int $userId = null
    ): int {
        $cacheKey = 'membership_new_last_12_months'
            .($branchId ? '_branch_'.$branchId : '')
            .($divisionId ? '_division_'.$divisionId : '')
            .($redCrossUnitId ? '_unit_'.$redCrossUnitId : '')
            .($userId ? '_user_'.$userId : '');

        return $this->remember($cacheKey, function () use ($branchId, $divisionId, $redCrossUnitId, $userId) {
            $oneYearAgo = now()->subYear()->startOfDay();
            $today = now()->endOfDay();

            // Subquery: first payment per user
            $sub = DB::table('membership_payments')
                ->select('membership_payments.user_id', DB::raw('MIN(membership_payments.payment_date) as first_payment_date'))
                ->where('membership_payments.is_deleted', 0)
                ->where('membership_payments.approval_status', 'approved') // Phase 2: only approved records are real
                ->join('users', 'membership_payments.user_id', '=', 'users.id')
                ->whereNull('users.red_cross_unit_id');

            if ($branchId) {
                $sub->where('membership_payments.branch_id', $branchId);
            }

            if ($divisionId) {
                $sub->where('membership_payments.division_id', $divisionId);
            }

            if ($redCrossUnitId) {
                $sub->where('users.red_cross_unit_id', $redCrossUnitId);
            }

            if ($userId) {
                $sub->where('membership_payments.user_id', $userId);
            }

            $sub->groupBy('membership_payments.user_id');

            // Wrap subquery to filter on first_payment_date
            return DB::table(DB::raw("({$sub->toSql()}) as t"))
                ->mergeBindings($sub)
                ->whereBetween('first_payment_date', [$oneYearAgo, $today])
                ->count();
        });
    }

    /**
     * Get active members per month (snapshot at month-end) split by gender for the last N months.
     * Intentionally uses point-in-time expiry logic and includes all lifecycle statuses —
     * historical month-end snapshots cannot be filtered by current lifecycle state.
     *
     * Returns:
     * [
     *   'labels' => ['Jan 2024', ...],
     *   'series' => [
     *      'male' => [..],
     *      'female' => [..],
     *   ],
     * ]
     */
    public function getActiveMembersTrendByGenderForChart(
        int $months = 36,
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $redCrossUnitId = null,
        ?int $userId = null
    ): array {
        $rows = $this->getActiveMembersTrendByGender($months, $branchId, $divisionId, $redCrossUnitId, $userId);

        $labels = [];
        $male = [];
        $female = [];

        foreach ($rows as $row) {
            $monthStart = Carbon::parse($row['date']);
            $labels[] = $monthStart->format('M Y');
            $male[] = (int) ($row['male'] ?? 0);
            $female[] = (int) ($row['female'] ?? 0);
        }

        return [
            'labels' => $labels,
            'series' => [
                'male' => $male,
                'female' => $female,
            ],
        ];
    }

    /**
     * Same as getActiveMembersTrend(), but split counts by users.gender.
     *
     * NOTE: counts are DISTINCT mp.user_id per month, classified by the user's gender.
     */
    public function getActiveMembersTrendByGender(
        int $months = 36,
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $redCrossUnitId = null,
        ?int $userId = null
    ): array {
        $cacheKey = "membership_active_trend_gender_{$months}"
            .($branchId ? "_branch_{$branchId}" : '')
            .($divisionId ? "_division_{$divisionId}" : '')
            .($redCrossUnitId ? "_unit_{$redCrossUnitId}" : '')
            .($userId ? "_user_{$userId}" : '');

        return $this->remember($cacheKey, function () use ($months, $branchId, $divisionId, $redCrossUnitId, $userId) {
            $end = now()->startOfMonth();
            $start = $end->copy()->subMonths($months - 1);

            $startDate = $start->toDateString();
            $endDate = $end->toDateString();

            $this->ensureReportMonthsRange($start, $end);

            $query = DB::table('report_months as rm')
                ->leftJoin('membership_payments as mp', function ($join) use ($branchId, $divisionId) {
                    $join->on('mp.is_deleted', '=', DB::raw('0'))
                        ->on('mp.payment_date', '<=', DB::raw('LAST_DAY(rm.month_start)'))
                        ->where(function ($q) {
                            $q->whereNull('mp.expiry_date')
                                ->orWhere('mp.expiry_date', '>=', DB::raw('LAST_DAY(rm.month_start)'));
                        });

                    if ($branchId) {
                        $join->where('mp.branch_id', $branchId);
                    }

                    if ($divisionId) {
                        $join->where('mp.division_id', $divisionId);
                    }
                })
                ->leftJoin('users as u', 'mp.user_id', '=', 'u.id')
                ->where(function ($q) {
                    $q->whereNull('u.red_cross_unit_id')
                        ->orWhereNull('mp.user_id'); // preserve left join nulls for months with no data
                });

            if ($redCrossUnitId) {
                $query->where('u.red_cross_unit_id', $redCrossUnitId);
            }

            if ($userId) {
                $query->where('mp.user_id', $userId);
            }

            $query->whereBetween('rm.month_start', [$startDate, $endDate])
                ->groupBy('rm.month_start')
                ->orderBy('rm.month_start')
                ->selectRaw('
                    rm.month_start,
                    COUNT(DISTINCT CASE WHEN u.gender = "male" THEN mp.user_id END) AS male,
                    COUNT(DISTINCT CASE WHEN u.gender = "female" THEN mp.user_id END) AS female
                ');

            $rows = $query->get();

            return $rows->map(function ($row) {
                return [
                    'date' => $row->month_start,
                    'label' => Carbon::parse($row->month_start)->format('Y-m'),
                    'male' => (int) $row->male,
                    'female' => (int) $row->female,
                ];
            })->all();
        });
    }

    /**
     * Get active members per month (snapshot at month-end) for the last N months.
     * Default: 36 months (3 years), for graphing.
     *
     * @param  int  $months  Number of months to include
     * @param  int|null  $branchId  Filter by branch ID
     * @param  int|null  $divisionId  Filter by division ID
     * @param  int|null  $redCrossUnitId  Filter by Red Cross Unit ID
     * @param  int|null  $userId  Filter by user ID
     */
    public function getActiveMembersTrendForChart(
        int $months = 36,
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $redCrossUnitId = null,
        ?int $userId = null
    ): array {
        // Reuse your existing function that does the SQL
        $rows = $this->getActiveMembersTrend($months, $branchId, $divisionId, $redCrossUnitId, $userId);

        $labels = [];
        $values = [];

        foreach ($rows as $row) {
            // Adjust this depending on how $row is structured (array vs object)
            $monthStart = \Carbon\Carbon::parse($row['date']);

            $labels[] = $monthStart->format('M Y');   // e.g. "Jan 2024"
            $values[] = (int) $row['active_members'];
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    /**
     * Get active members per month (snapshot at month-end) for the last N months.
     * Default: 36 months (3 years).
     * Intentionally uses point-in-time expiry logic and includes all lifecycle statuses —
     * historical month-end snapshots cannot be filtered by current lifecycle state.
     *
     * @param  int  $months  Number of months to include
     * @param  int|null  $branchId  Filter by branch ID
     * @param  int|null  $divisionId  Filter by division ID
     * @param  int|null  $redCrossUnitId  Filter by Red Cross Unit ID
     * @param  int|null  $userId  Filter by user ID
     */
    public function getActiveMembersTrend(
        int $months = 36,
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $redCrossUnitId = null,
        ?int $userId = null
    ): array {
        $cacheKey = "membership_active_trend_{$months}"
            .($branchId ? "_branch_{$branchId}" : '')
            .($divisionId ? "_division_{$divisionId}" : '')
            .($redCrossUnitId ? "_unit_{$redCrossUnitId}" : '')
            .($userId ? "_user_{$userId}" : '');

        return $this->remember($cacheKey, function () use ($months, $branchId, $divisionId, $redCrossUnitId, $userId) {
            $end = now()->startOfMonth();             // e.g. 2025-11-01
            $start = $end->copy()->subMonths($months - 1); // N-1 months back

            $startDate = $start->toDateString();
            $endDate = $end->toDateString();

            // 🔹 Make sure all needed months exist in report_months
            $this->ensureReportMonthsRange($start, $end);

            $query = DB::table('report_months as rm')
                ->leftJoin('membership_payments as mp', function ($join) use ($branchId, $divisionId) {
                    $join->on('mp.is_deleted', '=', DB::raw('0'))
                        ->on('mp.payment_date', '<=', DB::raw('LAST_DAY(rm.month_start)'))
                        ->where(function ($q) {
                            $q->whereNull('mp.expiry_date')
                                ->orWhere('mp.expiry_date', '>=', DB::raw('LAST_DAY(rm.month_start)'));
                        });

                    // Apply scope filters
                    if ($branchId) {
                        $join->where('mp.branch_id', $branchId);
                    }

                    if ($divisionId) {
                        $join->where('mp.division_id', $divisionId);
                    }
                });

            // Always join users to enforce member definition (no RC unit)
            $query->leftJoin('users', 'mp.user_id', '=', 'users.id')
                ->where(function ($q) {
                    $q->whereNull('users.red_cross_unit_id')
                        ->orWhereNull('mp.user_id'); // preserve left join nulls
                });

            if ($redCrossUnitId) {
                $query->where('users.red_cross_unit_id', $redCrossUnitId);
            }

            if ($userId) {
                $query->where('mp.user_id', $userId);
            }

            $query->whereBetween('rm.month_start', [$startDate, $endDate])
                ->groupBy('rm.month_start')
                ->orderBy('rm.month_start')
                ->selectRaw('rm.month_start, COUNT(DISTINCT mp.user_id) AS active_members');

            $rows = $query->get();

            return $rows->map(function ($row) {
                return [
                    'date' => $row->month_start,
                    'label' => Carbon::parse($row->month_start)->format('Y-m'),
                    'active_members' => (int) $row->active_members,
                ];
            })->all();
        });
    }

    /**
     * Get active members N months ago (snapshot at month-end).
     *
     * @param  int  $monthsAgo  Number of months to look back
     * @param  int|null  $branchId  Filter by branch ID
     * @param  int|null  $divisionId  Filter by division ID
     * @param  int|null  $redCrossUnitId  Filter by Red Cross Unit ID
     * @param  int|null  $userId  Filter by user ID
     */
    protected function getActiveMembersMonthsAgo(
        int $monthsAgo,
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $redCrossUnitId = null,
        ?int $userId = null
    ): int {
        if ($monthsAgo < 0) {
            throw new \InvalidArgumentException('monthsAgo must be >= 0');
        }

        // We need a trend that includes "monthsAgo" and the current month.
        $monthsNeeded = $monthsAgo + 1;

        $trend = $this->getActiveMembersTrend($monthsNeeded, $branchId, $divisionId, $redCrossUnitId, $userId);

        if (empty($trend)) {
            return 0;
        }

        // getActiveMembersTrend() returns an array from oldest → newest.
        // When we ask for ($monthsAgo + 1) months, the first element
        // is exactly "monthsAgo" months ago.
        $first = reset($trend);

        return (int) ($first['active_members'] ?? 0);
    }

    /**
     * Active members 1 month ago (snapshot at last month end).
     *
     * @param  int|null  $branchId  Filter by branch ID
     * @param  int|null  $divisionId  Filter by division ID
     * @param  int|null  $redCrossUnitId  Filter by Red Cross Unit ID
     * @param  int|null  $userId  Filter by user ID
     */
    public function getActiveMembersOneMonthAgo(
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $redCrossUnitId = null,
        ?int $userId = null
    ): int {
        return $this->getActiveMembersMonthsAgo(1, $branchId, $divisionId, $redCrossUnitId, $userId);
    }

    /**
     * Active members 12 months ago (snapshot at that month end).
     *
     * @param  int|null  $branchId  Filter by branch ID
     * @param  int|null  $divisionId  Filter by division ID
     * @param  int|null  $redCrossUnitId  Filter by Red Cross Unit ID
     * @param  int|null  $userId  Filter by user ID
     */
    public function getActiveMembersTwelveMonthsAgo(
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $redCrossUnitId = null,
        ?int $userId = null
    ): int {
        return $this->getActiveMembersMonthsAgo(12, $branchId, $divisionId, $redCrossUnitId, $userId);
    }

    /**
     * Get expired members in the last 12 months who have not renewed
     *
     * @param  int|null  $branchId  Filter by branch ID
     * @param  int|null  $divisionId  Filter by division ID
     * @param  int|null  $redCrossUnitId  Filter by Red Cross Unit ID
     * @param  int|null  $userId  Filter by user ID
     */
    public function getExpiredMembersLast12MonthsNotRenewed(
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $redCrossUnitId = null,
        ?int $userId = null
    ): int {
        $cacheKey = 'expired_last_12_months_not_renewed'
            .($branchId ? '_branch_'.$branchId : '')
            .($divisionId ? '_division_'.$divisionId : '')
            .($redCrossUnitId ? '_unit_'.$redCrossUnitId : '')
            .($userId ? '_user_'.$userId : '');

        return $this->remember($cacheKey, function () use ($branchId, $divisionId, $redCrossUnitId, $userId) {
            $oneYearAgo = now()->subYear()->startOfDay();
            $today = now()->toDateString();

            // Latest expiry per user
            $sub = DB::table('membership_payments')
                ->select('membership_payments.user_id', DB::raw('MIN(membership_payments.payment_date) as first_payment_date'))
                ->where('membership_payments.is_deleted', 0)
                ->where('membership_payments.approval_status', 'approved') // Phase 2: only approved records are real
                ->join('users', 'membership_payments.user_id', '=', 'users.id')
                ->whereNull('users.red_cross_unit_id');

            if ($branchId) {
                $sub->where('membership_payments.branch_id', $branchId);
            }

            if ($divisionId) {
                $sub->where('membership_payments.division_id', $divisionId);
            }

            if ($redCrossUnitId) {
                $sub->where('users.red_cross_unit_id', $redCrossUnitId);
            }

            if ($userId) {
                $sub->where('membership_payments.user_id', $userId);
            }

            $sub->groupBy('membership_payments.user_id');

            // Wrap subquery to filter on first_payment_date
            return DB::table(DB::raw("({$sub->toSql()}) as t"))
                ->mergeBindings($sub)
                ->whereBetween('first_payment_date', [$oneYearAgo, $today])
                ->count();
        });
    }

    /**
     * Get renewal rate for memberships that expired in the last 12 months
     *
     * @param  int|null  $branchId  Filter by branch ID
     * @param  int|null  $divisionId  Filter by division ID
     * @param  int|null  $redCrossUnitId  Filter by Red Cross Unit ID
     */
    public function getRenewalRateLast12Months(
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $redCrossUnitId = null
    ): float {
        $cacheKey = 'renewal_rate_last_12_months'
            .($branchId ? '_branch_'.$branchId : '')
            .($divisionId ? '_division_'.$divisionId : '')
            .($redCrossUnitId ? '_unit_'.$redCrossUnitId : '');

        return $this->remember($cacheKey, function () use ($branchId, $divisionId, $redCrossUnitId) {
            $oneYearAgo = now()->subYear()->startOfDay();
            $today = now()->endOfDay();

            // 1) All expiry events in the last 12 months
            $expiringSub = DB::table('membership_payments as mp1')
                ->select('mp1.user_id', 'mp1.expiry_date')
                ->where('mp1.is_deleted', 0)
                ->where('mp1.approval_status', 'approved') // Phase 2: only approved records are real
                ->whereBetween('mp1.expiry_date', [$oneYearAgo, $today])
                ->join('users', 'mp1.user_id', '=', 'users.id')
                ->whereNull('users.red_cross_unit_id');

            if ($branchId) {
                $expiringSub->where('mp1.branch_id', $branchId);
            }

            if ($divisionId) {
                $expiringSub->where('mp1.division_id', $divisionId);
            }

            if ($redCrossUnitId) {
                $expiringSub->where('users.red_cross_unit_id', $redCrossUnitId);
            }

            // We'll wrap this subquery so we can join against it
            $expiringSql = $expiringSub->toSql();

            // 2) Total distinct users who had an expiry in that window
            $totalExpired = DB::table(DB::raw("({$expiringSql}) as e"))
                ->mergeBindings($expiringSub)
                ->distinct('e.user_id')
                ->count('e.user_id');

            if ($totalExpired === 0) {
                return 0.0;
            }

            // 3) Renewed = users from that set who have ANY later expiry_date
            $renewedCount = DB::table(DB::raw("({$expiringSql}) as e"))
                ->mergeBindings($expiringSub)
                ->join('membership_payments as mp2', function ($join) {
                    $join->on('mp2.user_id', '=', 'e.user_id')
                        ->on('mp2.expiry_date', '>', 'e.expiry_date');
                })
                ->where('mp2.is_deleted', 0)
                ->where('mp2.approval_status', 'approved') // Phase 2: self-join — both mp1 and mp2 must be approved
                ->when($branchId, function ($query) use ($branchId) {
                    $query->where('mp2.branch_id', $branchId);
                })
                ->when($divisionId, function ($query) use ($divisionId) {
                    $query->where('mp2.division_id', $divisionId);
                })
                ->when($redCrossUnitId, function ($query) use ($redCrossUnitId) {
                    $query->join('users', 'mp2.user_id', '=', 'users.id')
                        ->where('users.red_cross_unit_id', $redCrossUnitId);
                })
                ->distinct('e.user_id')
                ->count('e.user_id');

            return round(($renewedCount / $totalExpired) * 100, 1);
        });
    }

    /**
     * Get the count of members with multi-year memberships (validity_years > 1)
     *
     * @param  int|null  $branchId  Filter by branch ID
     * @param  int|null  $divisionId  Filter by division ID
     * @param  int|null  $redCrossUnitId  Filter by Red Cross Unit ID
     * @param  int|null  $userId  Filter by user ID
     */
    public function getMultiYearMembersCount(
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $redCrossUnitId = null,
        ?int $userId = null
    ): int {
        $cacheKey = 'membership_multi_year_members'
            .($branchId ? '_branch_'.$branchId : '')
            .($divisionId ? '_division_'.$divisionId : '')
            .($redCrossUnitId ? '_unit_'.$redCrossUnitId : '')
            .($userId ? '_user_'.$userId : '');

        return $this->remember($cacheKey, function () use ($branchId, $divisionId, $redCrossUnitId, $userId) {
            $query = MembershipPayment::valid()
                ->join('membership_fees', 'membership_payments.membership_fee_id', '=', 'membership_fees.id')
                ->where('membership_fees.validity_years', '>', 1)
                ->join('users', 'membership_payments.user_id', '=', 'users.id')
                ->whereNull('users.red_cross_unit_id')
                ->distinct('membership_payments.user_id');

            if ($branchId) {
                $query->where('membership_payments.branch_id', $branchId);
            }

            if ($divisionId) {
                $query->where('membership_payments.division_id', $divisionId);
            }

            if ($redCrossUnitId) {
                $query->where('users.red_cross_unit_id', $redCrossUnitId);
            }

            if ($userId) {
                $query->where('membership_payments.user_id', $userId);
            }

            return $query->count('membership_payments.user_id');
        });
    }

    /**
     * Get the count of members with single-year memberships (validity_years = 1)
     *
     * @param  int|null  $branchId  Filter by branch ID
     * @param  int|null  $divisionId  Filter by division ID
     * @param  int|null  $redCrossUnitId  Filter by Red Cross Unit ID
     * @param  int|null  $userId  Filter by user ID
     */
    public function getSingleYearMembersCount(
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $redCrossUnitId = null,
        ?int $userId = null
    ): int {
        $cacheKey = 'membership_single_year_members'
            .($branchId ? '_branch_'.$branchId : '')
            .($divisionId ? '_division_'.$divisionId : '')
            .($redCrossUnitId ? '_unit_'.$redCrossUnitId : '')
            .($userId ? '_user_'.$userId : '');

        return $this->remember($cacheKey, function () use ($branchId, $divisionId, $redCrossUnitId, $userId) {
            $query = MembershipPayment::valid()
                ->join('membership_fees', 'membership_payments.membership_fee_id', '=', 'membership_fees.id')
                ->where('membership_fees.validity_years', '=', 1)
                ->join('users', 'membership_payments.user_id', '=', 'users.id')
                ->whereNull('users.red_cross_unit_id')
                ->distinct('membership_payments.user_id');

            if ($branchId) {
                $query->where('membership_payments.branch_id', $branchId);
            }

            if ($divisionId) {
                $query->where('membership_payments.division_id', $divisionId);
            }

            if ($redCrossUnitId) {
                $query->where('users.red_cross_unit_id', $redCrossUnitId);
            }

            if ($userId) {
                $query->where('membership_payments.user_id', $userId);
            }

            return $query->count('membership_payments.user_id');
        });
    }

    /**
     * Get count of members expiring in the next 30 days
     *
     * @param  int|null  $branchId  Filter by branch ID
     * @param  int|null  $divisionId  Filter by division ID
     * @param  int|null  $redCrossUnitId  Filter by Red Cross Unit ID
     * @param  int|null  $userId  Filter by user ID
     */
    public function getMembersExpiringNext30Days(
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $redCrossUnitId = null,
        ?int $userId = null
    ): int {
        $cacheKey = 'membership_expiring_next_30_days_count'
            .($branchId ? '_branch_'.$branchId : '')
            .($divisionId ? '_division_'.$divisionId : '')
            .($redCrossUnitId ? '_unit_'.$redCrossUnitId : '')
            .($userId ? '_user_'.$userId : '');

        return $this->remember($cacheKey, function () use ($branchId, $divisionId, $redCrossUnitId, $userId) {
            $now = now();
            $in30Days = $now->copy()->addDays(30);

            $query = MembershipPayment::where('is_deleted', false)
                ->whereBetween('expiry_date', [$now, $in30Days])
                ->join('users', 'membership_payments.user_id', '=', 'users.id')
                ->whereNull('users.red_cross_unit_id')
                ->distinct('membership_payments.user_id');

            if ($branchId) {
                $query->where('membership_payments.branch_id', $branchId);
            }

            if ($divisionId) {
                $query->where('membership_payments.division_id', $divisionId);
            }

            if ($redCrossUnitId) {
                $query->where('users.red_cross_unit_id', $redCrossUnitId);
            }

            if ($userId) {
                $query->where('membership_payments.user_id', $userId);
            }

            return $query->count('membership_payments.user_id');
        });
    }

    /**
     * Get count of members expired in the last 90 days
     *
     * @param  int|null  $branchId  Filter by branch ID
     * @param  int|null  $divisionId  Filter by division ID
     * @param  int|null  $redCrossUnitId  Filter by Red Cross Unit ID
     * @param  int|null  $userId  Filter by user ID
     */
    public function getMembersExpiredLast90Days(
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $redCrossUnitId = null,
        ?int $userId = null
    ): int {
        $cacheKey = 'membership_expired_last_90_days_count'
            .($branchId ? '_branch_'.$branchId : '')
            .($divisionId ? '_division_'.$divisionId : '')
            .($redCrossUnitId ? '_unit_'.$redCrossUnitId : '')
            .($userId ? '_user_'.$userId : '');

        return $this->remember($cacheKey, function () use ($branchId, $divisionId, $redCrossUnitId, $userId) {
            $now = now();
            $last90Days = $now->copy()->subDays(90);

            $query = MembershipPayment::where('is_deleted', false)
                ->whereBetween('expiry_date', [$last90Days, $now])
                ->join('users', 'membership_payments.user_id', '=', 'users.id')
                ->whereNull('users.red_cross_unit_id')
                ->distinct('membership_payments.user_id');

            if ($branchId) {
                $query->where('membership_payments.branch_id', $branchId);
            }

            if ($divisionId) {
                $query->where('membership_payments.division_id', $divisionId);
            }

            if ($redCrossUnitId) {
                $query->where('users.red_cross_unit_id', $redCrossUnitId);
            }

            if ($userId) {
                $query->where('membership_payments.user_id', $userId);
            }

            return $query->count('membership_payments.user_id');
        });
    }

    /**
     * Get the count of expired members
     *
     * @param  int|null  $branchId  Filter by branch ID
     * @param  int|null  $divisionId  Filter by division ID
     * @param  int|null  $redCrossUnitId  Filter by Red Cross Unit ID
     * @param  int|null  $userId  Filter by user ID
     */
    public function getExpiredMembersCount(
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $redCrossUnitId = null,
        ?int $userId = null
    ): int {
        $cacheKey = 'membership_expired_members'
            .($branchId ? '_branch_'.$branchId : '')
            .($divisionId ? '_division_'.$divisionId : '')
            .($redCrossUnitId ? '_unit_'.$redCrossUnitId : '')
            .($userId ? '_user_'.$userId : '');

        return $this->remember($cacheKey, function () use ($branchId, $divisionId, $redCrossUnitId, $userId) {
            $query = MembershipPayment::expired()
                ->distinct('user_id');

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            if ($divisionId) {
                $query->where('division_id', $divisionId);
            }

            if ($redCrossUnitId || $userId) {
                $query->join('users', 'membership_payments.user_id', '=', 'users.id');

                if ($redCrossUnitId) {
                    $query->where('users.red_cross_unit_id', $redCrossUnitId);
                }

                if ($userId) {
                    $query->where('membership_payments.user_id', $userId);
                }
            }

            return $query->count('user_id');
        });
    }

    /**
     * Get membership revenue for a specific time period
     *
     * @param  Carbon\Carbon  $startDate  The start date for the period
     * @param  Carbon\Carbon  $endDate  The end date for the period
     * @param  int|null  $branchId  Filter by branch ID
     * @param  int|null  $divisionId  Filter by division ID
     * @param  int|null  $redCrossUnitId  Filter by Red Cross Unit ID
     */
    public function getMembershipRevenue(
        Carbon $startDate,
        Carbon $endDate,
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $redCrossUnitId = null
    ): float {
        $cacheKey = "membership_revenue_{$startDate->toDateString()}_{$endDate->toDateString()}"
            .($branchId ? "_branch_{$branchId}" : '')
            .($divisionId ? "_division_{$divisionId}" : '')
            .($redCrossUnitId ? "_unit_{$redCrossUnitId}" : '');

        return $this->remember($cacheKey, function () use ($startDate, $endDate, $branchId, $divisionId, $redCrossUnitId) {
            $query = MembershipPayment::where('is_deleted', false)
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->join('membership_fees', 'membership_payments.membership_fee_id', '=', 'membership_fees.id')
                ->join('users', 'membership_payments.user_id', '=', 'users.id')
                ->whereNull('users.red_cross_unit_id');

            if ($branchId) {
                $query->where('membership_payments.branch_id', $branchId);
            }

            if ($divisionId) {
                $query->where('membership_payments.division_id', $divisionId);
            }

            if ($redCrossUnitId) {
                $query->where('users.red_cross_unit_id', $redCrossUnitId);
            }

            return (float) $query->sum('membership_fees.amount');
        });
    }

    public function getDemographicsSnapshot(
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $unitId = null,
        ?Carbon $atDate = null
    ): array {
        $atDate = $atDate?->copy()->endOfDay() ?? now()->endOfDay();

        $cacheKey = 'membership_demographics_'.
            $atDate->format('Y_m_d').
            '_b'.($branchId ?? 'all').
            '_d'.($divisionId ?? 'all').
            '_u'.($unitId ?? 'all');

        return $this->remember($cacheKey, function () use ($branchId, $divisionId, $unitId, $atDate) {

            $query = User::query()
                ->whereIn('lifecycle_status', ['active', 'dormant'])
                ->whereNull('red_cross_unit_id')
                ->whereHas('membershipPayments', function ($q) use ($atDate) {
                    $q->where('is_deleted', 0)
                        ->where('payment_date', '<=', $atDate)
                        ->where(function ($q2) use ($atDate) {
                            $q2->whereNull('expiry_date')
                                ->orWhere('expiry_date', '>=', $atDate);
                        });
                });

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }
            if ($divisionId) {
                $query->where('division_id', $divisionId);
            }
            if ($unitId) {
                $query->where('red_cross_unit_id', $unitId);
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

            // Corrected ageBuckets calculation to use 'birth_year' from the User model
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
                'under15' => ['men' => (int) ($agesByGenderRaw['under15_men'] ?? 0),  'women' => (int) ($agesByGenderRaw['under15_women'] ?? 0)],
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
}
