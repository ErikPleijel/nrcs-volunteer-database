<?php

namespace App\Services\Reports;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RegistrationStatsService
{
    /**
     * Global TTL for all registration stats cache (in seconds).
     *
     * During dev, you can set this to 1 (or even 0 to disable caching).
     * In production, 3600 (1 hour) is a reasonable default.
     */
    private int $cacheTtl = 3600;

    /**
     * Cache key prefix so we keep things grouped.
     */
    private string $cachePrefix = 'registration_stats_';

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
     * Registrations (user profiles created) in the last 12 months.
     */
    public function getRegistrationsLast12Months(?int $branchId = null): int
    {
        $cacheKey = $this->cachePrefix . 'last12' . ($branchId ? '_branch_' . $branchId : '_all');

        return $this->remember($cacheKey, function () use ($branchId) {
            $query = User::whereBetween('created_at', [now()->subYear(), now()]);

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            return $query->count();
        });
    }

    /**
     * Registrations (user profiles created) 12–24 months ago.
     */
    public function getRegistrationsPrev12Months(?int $branchId = null): int
    {
        $cacheKey = $this->cachePrefix . 'prev12' . ($branchId ? '_branch_' . $branchId : '_all');

        return $this->remember($cacheKey, function () use ($branchId) {
            $start = now()->subYears(2);
            $end   = now()->subYear();

            $query = User::whereBetween('created_at', [$start, $end]);

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            return $query->count();
        });
    }

    public function getDormantProfiles(?int $branchId = null): int
    {
        $cacheKey = $this->cachePrefix . 'dormant' . ($branchId ? '_branch_' . $branchId : '_all');

        return $this->remember($cacheKey, function () use ($branchId) {

            $cutoff = now()->subMonths(48);

            $query = User::query();

            // Filter by branch if provided
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            // No activity since cutoff
            $query->whereDoesntHave('activities', function ($q) use ($cutoff) {
                $q->where('date', '>=', $cutoff);
            });

            // No donations since cutoff
            $query->whereDoesntHave('donations', function ($q) use ($cutoff) {
                $q->where(function ($don) use ($cutoff) {
                    $don->where('date_donation', '>=', $cutoff);
                });
            });

            // No trainings since cutoff
            $query->whereDoesntHave('trainings', function ($q) use ($cutoff) {
                $q->where('training_date', '>=', $cutoff);
            });

            // No membership payments since cutoff
            $query->whereDoesntHave('membershipPayments', function ($q) use ($cutoff) {
                $q->where('payment_date', '>=', $cutoff);
            });

            return $query->count();
        });
    }

    /**
     * Dormant log in profiles:
     * Profiles with no or very old last_login, filtered by branch if given.
     */
    public function getLoginDormantProfiles(?int $branchId = null): int
    {
        // 🔧 Fix cache key so it doesn't collide with getDormantProfiles()
        $cacheKey = $this->cachePrefix . 'login_dormant' . ($branchId ? '_branch_' . $branchId : '_all');

        return $this->remember($cacheKey, function () use ($branchId) {
            $cutoff = now()->subMonths(36);

            $query = User::where(function ($q) use ($cutoff) {
                $q->whereNull('last_login')
                    ->orWhere('last_login', '<', $cutoff);
            });

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            return $query->count();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | NEW: Trend + Branch/Division summaries for registrations
    |--------------------------------------------------------------------------
    */

    /**
     * Monthly registration trend for Chart.js.
     *
     * Returns:
     * [
     *   'labels' => ['Jan 2022', 'Feb 2022', ...],
     *   'values' => [12, 8, 19, ...],
     *   'meta'   => [...],
     * ]
     *
     * $years: 2,4,6,8 (default 4 in your controller/UI).
     * $branchId: null for national, or specific branch for branch chart.
     */
    public function getRegistrationTrendForChart(int $years = 2, ?int $branchId = null): array
    {
        // Clamp years between 1 and 10, just to be safe
        $years = max(1, min($years, 10));

        $cacheKey = $this->cachePrefix
            . 'trend_' . $years
            . ($branchId ? '_branch_' . $branchId : '_all');

        return $this->remember($cacheKey, function () use ($years, $branchId) {
            // End = current month, start = N years back
            $end   = now()->startOfMonth();
            $start = (clone $end)->subYears($years)->startOfMonth();

            $query = User::query()
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as registrations_count")
                ->whereBetween('created_at', [$start, (clone $end)->endOfMonth()]);

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            $rows = $query
                ->groupBy('ym')
                ->orderBy('ym')
                ->get();

            $byMonth = $rows->keyBy('ym');

            $labels = [];
            $values = [];

            $cursor = $start->copy();
            while ($cursor <= $end) {
                $ym = $cursor->format('Y-m');

                $labels[] = $cursor->format('M Y'); // e.g. "Jan 2024"
                $values[] = optional($byMonth->get($ym))->registrations_count ?? 0;

                $cursor->addMonth();
            }

            return [
                'labels' => $labels,
                'values' => array_map('intval', $values),
                'meta'   => [
                    'from'     => $start->toDateString(),
                    'to'       => $end->toDateString(),
                    'years'    => $years,
                    'branchId' => $branchId,
                ],
            ];
        });
    }

    /**
     * National blade: list of branches with registration counts
     * for a given calendar year.
     *
     * Returns a Collection of:
     *  - branch_id
     *  - branch_name
     *  - registrations_count
     */
    public function getBranchRegistrationSummary(int $year): Collection
    {
        [$start, $end] = $this->getCalendarYearRange($year);

        $cacheKey = $this->cachePrefix
            . 'branch_summary_year_' . $year;

        return $this->remember($cacheKey, function () use ($start, $end) {
            return DB::table('users as u')
                ->join('branches as b', 'b.id', '=', 'u.branch_id')
                ->selectRaw('
                    u.branch_id,
                    b.name as branch_name,
                    COUNT(*) as registrations_count
                ')
                ->whereBetween('u.created_at', [$start, $end])
                ->groupBy('u.branch_id', 'b.name')
                ->orderBy('b.name')
                ->get();
        });
    }

    /**
     * Branch blade: list of divisions with registration counts
     * for a given branch and calendar year.
     *
     * Returns a Collection of:
     *  - division_id
     *  - division_name
     *  - registrations_count
     */
    public function getDivisionRegistrationSummary(int $branchId, int $year): Collection
    {
        [$start, $end] = $this->getCalendarYearRange($year);

        $cacheKey = $this->cachePrefix
            . 'division_summary_branch_' . $branchId
            . '_year_' . $year;

        return $this->remember($cacheKey, function () use ($branchId, $start, $end) {
            return DB::table('users as u')
                ->join('divisions as d', 'd.id', '=', 'u.division_id')
                ->selectRaw('
                    u.division_id,
                    d.name as division_name,
                    COUNT(*) as registrations_count
                ')
                ->where('u.branch_id', $branchId)
                ->whereBetween('u.created_at', [$start, $end])
                ->groupBy('u.division_id', 'd.name')
                ->orderBy('d.name')
                ->get();
        });
    }

    /**
     * Helper: calendar-year range (Jan 1 – Dec 31), clamped to "now" for the current year.
     *
     * Assumes $year <= current year in normal usage.
     */
    protected function getCalendarYearRange(int $year): array
    {
        // Start at Jan 1 of given year
        $start = Carbon::create($year, 1, 1)->startOfDay();

        // End at Dec 31 of given year, but never beyond "now"
        $end = Carbon::create($year, 12, 31)->endOfDay();
        if ($end->greaterThan(now())) {
            $end = now();
        }

        // If someone passes a future year, avoid start > end weirdness:
        if ($start->greaterThan($end)) {
            $start = $end->copy()->startOfYear();
        }

        return [$start, $end];
    }
}
