<?php

namespace App\Services\Reports;

use App\Models\Donation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DonationStatsService
{
    /**
     * Global TTL for all donation stats cache (in seconds).
     *
     * During dev, you can set this to 1 (or even 0 to disable caching).
     * In production, 3600 (1 hour) is a reasonable default.
     */
    private int $cacheTtl = 1;

    /**
     * Cache key prefix so we keep things grouped.
     */
    private string $cachePrefix = 'donation_stats_';

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
     * Build a monthly donation trend dataset for Chart.js.
     *
     * Returns an array:
     * [
     *     'labels'        => ['Jan 2024', 'Feb 2024', ...],
     *     'cash_values'   => [100000.0, 75000.0, ...],   // ₦ per month
     *     'in_kind_values'=> [3, 5, ...],                // count of in-kind donations per month
     * ]
     *
     * @param  int  $years  Number of years back from now (e.g. 2, 4, 6...)
     * @param  int|null  $branchId  Null = all branches (national); otherwise specific branch
     */
    public function getDonationTrendForChart(int $years = 2, ?int $branchId = null): array
    {
        $years = max(1, $years); // just to be safe

        $cacheKey = $this->cachePrefix
            ."donation_trend_{$years}_years_branch_".($branchId ?? 'all');

        return $this->remember($cacheKey, function () use ($years, $branchId) {
            // We go from "years ago, start of month" up to "this month"
            $end = Carbon::now()->startOfMonth();
            $start = (clone $end)->subYears($years)->startOfMonth();

            // Aggregate by Year + Month, separating cash and in-kind
            $rows = $this->baseDonationQuery($branchId, null)
                ->whereBetween('date_donation', [
                    $start->toDateString(),
                    $end->copy()->endOfMonth()->toDateString(),
                ])
                ->selectRaw('
                    YEAR(date_donation)  as year,
                    MONTH(date_donation) as month,
                    SUM(CASE WHEN in_kind_donation = 0 THEN amount ELSE 0 END) as cash_amount,
                    SUM(CASE WHEN in_kind_donation = 1 THEN 1      ELSE 0 END) as in_kind_count
                ')
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get();

            // Index rows by YYYY-MM so we can fill in gaps with zeros
            $indexed = [];
            foreach ($rows as $row) {
                $key = sprintf('%04d-%02d', $row->year, $row->month);
                $indexed[$key] = [
                    'cash_amount' => (float) $row->cash_amount,
                    'in_kind_count' => (int) $row->in_kind_count,
                ];
            }

            $labels = [];
            $cashValues = [];
            $inKindValues = [];

            $cursor = $start->copy();
            $lastMonth = $end->copy()->startOfMonth();

            while ($cursor <= $lastMonth) {
                $key = $cursor->format('Y-m');

                $labels[] = $cursor->format('M Y');
                $cashValues[] = $indexed[$key]['cash_amount'] ?? 0.0;
                $inKindValues[] = $indexed[$key]['in_kind_count'] ?? 0;

                $cursor->addMonth();
            }

            return [
                'labels' => $labels,
                'cash_values' => $cashValues,
                'in_kind_values' => $inKindValues,
            ];
        });
    }

    /**
     * Quarterly donation summary by branch for a given year.
     *
     * For each branch, returns an object:
     * [
     *   branch_id,
     *   branch_name,
     *
     *   q1_cash,      q1_in_kind,
     *   q2_cash,      q2_in_kind,
     *   q3_cash,      q3_in_kind,
     *   q4_cash,      q4_in_kind,
     *
     *   total_cash,   // sum of all quarters (₦)
     *   total_in_kind // sum of all quarters (count of in-kind donations)
     * ]
     */
    public function getBranchDonationQuarterlySummary(int $year): Collection
    {
        $cacheKey = $this->cachePrefix."donation_quarterly_by_branch_{$year}";

        return $this->remember($cacheKey, function () use ($year) {
            $rows = DB::table('donations')
                ->join('branches', 'donations.branch_id', '=', 'branches.id')
                ->where('donations.is_deleted', 0)
                ->where('donations.approval_status', 'approved') // Phase 2: only approved records are real
                ->whereYear('donations.date_donation', $year)
                ->select(
                    'branches.id as branch_id',
                    'branches.name as branch_name',
                    DB::raw('QUARTER(donations.date_donation) as quarter'),
                    // Sum of cash amounts (only where in_kind_donation = 0)
                    DB::raw('
                        SUM(
                            CASE
                                WHEN donations.in_kind_donation = 0
                                THEN donations.amount
                                ELSE 0
                            END
                        ) as cash_amount
                    '),
                    // Count of in-kind donations (only where in_kind_donation = 1)
                    DB::raw('
                        SUM(
                            CASE
                                WHEN donations.in_kind_donation = 1
                                THEN 1
                                ELSE 0
                            END
                        ) as in_kind_count
                    ')
                )
                ->groupBy(
                    'branches.id',
                    'branches.name',
                    DB::raw('QUARTER(donations.date_donation)')
                )
                ->orderBy('branches.name')
                ->get();

            $grouped = $rows->groupBy('branch_id');

            return $grouped->map(function ($items) {
                $branchName = $items->first()->branch_name;

                $quarters = [
                    'q1' => ['cash' => 0.0, 'in_kind' => 0],
                    'q2' => ['cash' => 0.0, 'in_kind' => 0],
                    'q3' => ['cash' => 0.0, 'in_kind' => 0],
                    'q4' => ['cash' => 0.0, 'in_kind' => 0],
                ];

                foreach ($items as $row) {
                    $qKey = 'q'.$row->quarter;
                    if (! isset($quarters[$qKey])) {
                        continue;
                    }

                    $quarters[$qKey]['cash'] += (float) $row->cash_amount;
                    $quarters[$qKey]['in_kind'] += (int) $row->in_kind_count;
                }

                $totalCash = 0.0;
                $totalInKind = 0;

                foreach ($quarters as $q) {
                    $totalCash += $q['cash'];
                    $totalInKind += $q['in_kind'];
                }

                return (object) [
                    'branch_id' => $items->first()->branch_id,
                    'branch_name' => $branchName,

                    'q1_cash' => $quarters['q1']['cash'],
                    'q1_in_kind' => $quarters['q1']['in_kind'],
                    'q2_cash' => $quarters['q2']['cash'],
                    'q2_in_kind' => $quarters['q2']['in_kind'],
                    'q3_cash' => $quarters['q3']['cash'],
                    'q3_in_kind' => $quarters['q3']['in_kind'],
                    'q4_cash' => $quarters['q4']['cash'],
                    'q4_in_kind' => $quarters['q4']['in_kind'],

                    'total_cash' => $totalCash,
                    'total_in_kind' => $totalInKind,
                ];
            })->values();
        });
    }

    public function getDivisionDonationQuarterlySummary(int $branchId, int $year): Collection
    {
        $cacheKey = $this->cachePrefix."donation_quarterly_by_division_branch_{$branchId}_{$year}";

        return $this->remember($cacheKey, function () use ($branchId, $year) {
            $rows = DB::table('donations')
                ->join('divisions', 'donations.division_id', '=', 'divisions.id')
                ->where('donations.is_deleted', 0)
                ->where('donations.approval_status', 'approved') // Phase 2: only approved records are real
                ->where('donations.branch_id', $branchId)
                ->whereYear('donations.date_donation', $year)
                ->select(
                    'divisions.id as division_id',
                    'divisions.name as division_name',
                    DB::raw('QUARTER(donations.date_donation) as quarter'),
                    DB::raw('
                    SUM(
                        CASE
                            WHEN donations.in_kind_donation = 0
                            THEN donations.amount
                            ELSE 0
                        END
                    ) as cash_amount
                '),
                    DB::raw('
                    SUM(
                        CASE
                            WHEN donations.in_kind_donation = 1
                            THEN 1
                            ELSE 0
                        END
                    ) as in_kind_count
                ')
                )
                ->groupBy(
                    'divisions.id',
                    'divisions.name',
                    DB::raw('QUARTER(donations.date_donation)')
                )
                ->orderBy('divisions.name')
                ->get();

            $grouped = $rows->groupBy('division_id');

            return $grouped->map(function ($items) {
                $divisionName = $items->first()->division_name;

                $quarters = [
                    'q1' => ['cash' => 0.0, 'in_kind' => 0],
                    'q2' => ['cash' => 0.0, 'in_kind' => 0],
                    'q3' => ['cash' => 0.0, 'in_kind' => 0],
                    'q4' => ['cash' => 0.0, 'in_kind' => 0],
                ];

                foreach ($items as $row) {
                    $qKey = 'q'.$row->quarter;
                    if (! isset($quarters[$qKey])) {
                        continue;
                    }

                    $quarters[$qKey]['cash'] += (float) $row->cash_amount;
                    $quarters[$qKey]['in_kind'] += (int) $row->in_kind_count;
                }

                $totalCash = 0.0;
                $totalInKind = 0;

                foreach ($quarters as $q) {
                    $totalCash += $q['cash'];
                    $totalInKind += $q['in_kind'];
                }

                return (object) [
                    'division_id' => $items->first()->division_id,
                    'division_name' => $divisionName,

                    'q1_cash' => $quarters['q1']['cash'],
                    'q1_in_kind' => $quarters['q1']['in_kind'],
                    'q2_cash' => $quarters['q2']['cash'],
                    'q2_in_kind' => $quarters['q2']['in_kind'],
                    'q3_cash' => $quarters['q3']['cash'],
                    'q3_in_kind' => $quarters['q3']['in_kind'],
                    'q4_cash' => $quarters['q4']['cash'],
                    'q4_in_kind' => $quarters['q4']['in_kind'],

                    'total_cash' => $totalCash,
                    'total_in_kind' => $totalInKind,
                ];
            })->values();
        });
    }

    /**
     * Base query for donations, respecting soft delete and optional filters.
     *
     * @param  bool|null  $inKind  true = in-kind, false = cash, null = both
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function baseDonationQuery(?int $branchId = null, ?bool $inKind = null)
    {
        $query = Donation::query()
            ->notDeleted()
            ->whereNotNull('date_donation');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if (! is_null($inKind)) {
            $query->where('in_kind_donation', $inKind);
        }

        return $query;
    }

    /**
     * SUM cash donations between dates (in_kind_donation = false).
     */
    private function sumCashDonationsBetween(
        string $startDate,
        string $endDate,
        ?int $branchId = null
    ): float {
        return (float) $this->baseDonationQuery($branchId, false)
            ->whereBetween('date_donation', [$startDate, $endDate])
            ->sum('amount');
    }

    /**
     * COUNT in-kind donations between dates (in_kind_donation = true).
     */
    private function countInKindDonationsBetween(
        string $startDate,
        string $endDate,
        ?int $branchId = null
    ): int {
        return $this->baseDonationQuery($branchId, true)
            ->whereBetween('date_donation', [$startDate, $endDate])
            ->count();
    }

    /**
     * COUNT all donations (cash + in-kind) between dates.
     * (If you don't need this, you can drop it.)
     */
    private function countAllDonationsBetween(
        string $startDate,
        string $endDate,
        ?int $branchId = null
    ): int {
        return $this->baseDonationQuery($branchId, null)
            ->whereBetween('date_donation', [$startDate, $endDate])
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | Generic between-date helpers (can be reused by controllers)
    |--------------------------------------------------------------------------
    */

    /**
     * Cash donation amount between two dates (sum of amount, in_kind = false).
     */
    public function getCashDonationAmountBetween(
        string $startDate,
        string $endDate,
        ?int $branchId = null
    ): float {
        $cacheKey = $this->cachePrefix.'cash_amount_between_'.
            $startDate.'_'.$endDate.
            '_branch_'.($branchId ?? 'all');

        return $this->remember($cacheKey, function () use ($startDate, $endDate, $branchId) {
            return $this->sumCashDonationsBetween($startDate, $endDate, $branchId);
        });
    }

    /**
     * In-kind donation count between two dates (number of records, in_kind = true).
     */
    public function getInKindDonationCountBetween(
        string $startDate,
        string $endDate,
        ?int $branchId = null
    ): int {
        $cacheKey = $this->cachePrefix.'inkind_count_between_'.
            $startDate.'_'.$endDate.
            '_branch_'.($branchId ?? 'all');

        return $this->remember($cacheKey, function () use ($startDate, $endDate, $branchId) {
            return $this->countInKindDonationsBetween($startDate, $endDate, $branchId);
        });
    }

    /**
     * Total number of donations (cash + in-kind) between two dates.
     */
    public function getDonationCountBetween(
        string $startDate,
        string $endDate,
        ?int $branchId = null
    ): int {
        $cacheKey = $this->cachePrefix.'total_count_between_'.
            $startDate.'_'.$endDate.
            '_branch_'.($branchId ?? 'all');

        return $this->remember($cacheKey, function () use ($startDate, $endDate, $branchId) {
            return $this->countAllDonationsBetween($startDate, $endDate, $branchId);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Predefined periods – last 12 months & 12–24 months ago
    |--------------------------------------------------------------------------
    */

    /**
     * Cash donation amount in the last 12 months (sum of amount, in_kind = false).
     */
    public function getCashDonationAmountLast12Months(?int $branchId = null): float
    {
        $end = Carbon::now()->toDateString();
        $start = Carbon::now()->subYear()->toDateString();

        $cacheKey = $this->cachePrefix.'cash_amount_last_12_months'.
            '_branch_'.($branchId ?? 'all');

        return $this->remember($cacheKey, function () use ($start, $end, $branchId) {
            return $this->sumCashDonationsBetween($start, $end, $branchId);
        });
    }

    /**
     * Cash donation amount 12–24 months ago.
     */
    public function getCashDonationAmount12to24MonthsAgo(?int $branchId = null): float
    {
        $end = Carbon::now()->subYear()->toDateString();   // 12 months ago
        $start = Carbon::now()->subYears(2)->toDateString(); // 24 months ago

        $cacheKey = $this->cachePrefix.'cash_amount_12_to_24_months_ago'.
            '_branch_'.($branchId ?? 'all');

        return $this->remember($cacheKey, function () use ($start, $end, $branchId) {
            return $this->sumCashDonationsBetween($start, $end, $branchId);
        });
    }

    /**
     * In-kind donation count in the last 12 months (number of records).
     */
    public function getInKindDonationCountLast12Months(?int $branchId = null): int
    {
        $end = Carbon::now()->toDateString();
        $start = Carbon::now()->subYear()->toDateString();

        $cacheKey = $this->cachePrefix.'inkind_count_last_12_months'.
            '_branch_'.($branchId ?? 'all');

        return $this->remember($cacheKey, function () use ($start, $end, $branchId) {
            return $this->countInKindDonationsBetween($start, $end, $branchId);
        });
    }

    /**
     * In-kind donation count 12–24 months ago (number of records).
     */
    public function getInKindDonationCount12to24MonthsAgo(?int $branchId = null): int
    {
        $end = Carbon::now()->subYear()->toDateString();
        $start = Carbon::now()->subYears(2)->toDateString();

        $cacheKey = $this->cachePrefix.'inkind_count_12_to_24_months_ago'.
            '_branch_'.($branchId ?? 'all');

        return $this->remember($cacheKey, function () use ($start, $end, $branchId) {
            return $this->countInKindDonationsBetween($start, $end, $branchId);
        });
    }

    /**
     * Total number of donations (cash + in-kind) in the last 12 months.
     * Optional, but often useful for dashboards.
     */
    public function getDonationCountLast12Months(?int $branchId = null): int
    {
        $end = Carbon::now()->toDateString();
        $start = Carbon::now()->subYear()->toDateString();

        $cacheKey = $this->cachePrefix.'total_count_last_12_months'.
            '_branch_'.($branchId ?? 'all');

        return $this->remember($cacheKey, function () use ($start, $end, $branchId) {
            return $this->countAllDonationsBetween($start, $end, $branchId);
        });
    }

    /**
     * Total number of donations 12–24 months ago (cash + in-kind).
     */
    public function getDonationCount12to24MonthsAgo(?int $branchId = null): int
    {
        $end = Carbon::now()->subYear()->toDateString();
        $start = Carbon::now()->subYears(2)->toDateString();

        $cacheKey = $this->cachePrefix.'total_count_12_to_24_months_ago'.
            '_branch_'.($branchId ?? 'all');

        return $this->remember($cacheKey, function () use ($start, $end, $branchId) {
            return $this->countAllDonationsBetween($start, $end, $branchId);
        });
    }
}
