<?php

namespace App\Services\Reports;

use App\Models\Donation;
use App\Models\MembershipPayment;
use App\Models\MembershipFee;
use App\Models\Branch;
use App\Models\Division;
use App\Models\RedCrossUnit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;


class FinancialStatsService
{
    protected string $cachePrefix = 'financial_stats_';

    /**
     * Simple cache wrapper so we can swap TTL centrally if needed.
     */
    protected function remember(string $key, \Closure $callback, ?int $minutes = null)
    {
        $minutes ??= 60; // or config('reports.cache_ttl', 60);

        return Cache::remember($this->cachePrefix . $key, $minutes, $callback);
    }

    /**
     * Membership fee revenue trend for charts.
     *
     * - Time range: 2, 4, 6 or 8 years (quarters as ticks)
     * - Scope: optional branch_id / division_id
     * - Returns: ['labels' => [...], 'values' => [...]]
     */
    public function getMembershipRevenueTrendForChart(
        int $years = 4,
        ?int $branchId = null,
        ?int $divisionId = null
    ): array {
        // Only allow 2, 4, 6, 8 years – default to 4 if something else is passed
        if (! in_array($years, [2, 4, 6, 8], true)) {
            $years = 4;
        }

        $scopeKey =
            ($branchId ? "_branch_{$branchId}" : '') .
            ($divisionId ? "_division_{$divisionId}" : '');

        $cacheKey = "membership_revenue_trend_{$years}{$scopeKey}";

        return $this->remember($cacheKey, function () use ($years, $branchId, $divisionId) {
            $quartersCount = $years * 4;

            // End at current quarter, start N-1 quarters back
            $endQuarter   = Carbon::now()->startOfQuarter();
            $startQuarter = $endQuarter->copy()->subQuarters($quartersCount - 1);

            $startDate = $startQuarter->copy()->startOfQuarter();
            $endDate   = $endQuarter->copy()->endOfQuarter();

            // One query to get all sums per year/quarter
            $query = MembershipPayment::query()
                ->where('is_deleted', false)
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->join('membership_fees', 'membership_payments.membership_fee_id', '=', 'membership_fees.id');

            if ($branchId) {
                $query->where('membership_payments.branch_id', $branchId);
            }

            if ($divisionId) {
                $query->where('membership_payments.division_id', $divisionId);
            }

            $rows = $query
                ->selectRaw('YEAR(payment_date) as year_num')
                ->selectRaw('QUARTER(payment_date) as quarter_num')
                ->selectRaw('SUM(membership_fees.amount) as total_amount')
                ->groupBy('year_num', 'quarter_num')
                ->orderBy('year_num')
                ->orderBy('quarter_num')
                ->get();

            // Index by "YYYY-Qn" for fast lookup
            $indexed = [];
            foreach ($rows as $row) {
                $key = sprintf('%d-Q%d', $row->year_num, $row->quarter_num);
                $indexed[$key] = (float) $row->total_amount;
            }

            $labels = [];
            $values = [];

            // Build continuous quarter timeline from start → end
            $cursor = $startQuarter->copy();
            for ($i = 0; $i < $quartersCount; $i++) {
                $year    = $cursor->year;
                $quarter = (int) ceil($cursor->month / 3);

                $key = sprintf('%d-Q%d', $year, $quarter);

                $labels[] = $year . ' Q' . $quarter;
                $values[] = $indexed[$key] ?? 0.0;

                $cursor->addQuarter();
            }

            return [
                'labels' => $labels,
                'values' => $values,
            ];
        });
    }


    /**
     * National → Branch membership revenue broken down per quarter.
     */


    public function getBranchMembershipSummariesByQuarter(
        int $year,
        ?int $branchId = null,
        ?int $divisionId = null
    ): Collection {
        // Normalise for cache key: 0 / null → "all"
        $branchKey   = ($branchId === null || $branchId === 0) ? 'all' : $branchId;
        $divisionKey = ($divisionId === null || $divisionId === 0) ? 'all' : $divisionId;

        $cacheKey = "membership_quarters_{$year}_branch_{$branchKey}_division_{$divisionKey}";

        return $this->remember($cacheKey, function () use ($year, $branchId, $divisionId) {

            // 🔹 CASE 1: National – group by BRANCH
            if ($branchId === null || $branchId === 0) {
                $query = MembershipPayment::query()
                    ->where('is_deleted', false)
                    ->whereYear('payment_date', $year)
                    ->join('membership_fees', 'membership_payments.membership_fee_id', '=', 'membership_fees.id')
                    ->join('branches', 'membership_payments.branch_id', '=', 'branches.id');

                // (Optional) you *could* still allow division filter here
                if (!is_null($divisionId) && $divisionId !== 0) {
                    $query->where('membership_payments.division_id', $divisionId);
                }

                $rows = $query
                    ->select([
                        'branches.id as branch_id',
                        'branches.name as branch_name',
                        DB::raw("QUARTER(payment_date) as quarter"),
                        DB::raw("SUM(membership_fees.amount) as total_amount"),
                        DB::raw("COUNT(membership_payments.id) as payment_count"),
                    ])
                    ->groupBy('branches.id', 'branches.name', DB::raw("QUARTER(payment_date)"))
                    ->orderBy('branches.name')
                    ->get();

            } else {
                // 🔹 CASE 2: Branch selected – group by DIVISIONS in that branch
                $query = MembershipPayment::query()
                    ->where('is_deleted', false)
                    ->whereYear('payment_date', $year)
                    ->where('membership_payments.branch_id', $branchId)
                    ->join('membership_fees', 'membership_payments.membership_fee_id', '=', 'membership_fees.id')
                    ->join('divisions', 'membership_payments.division_id', '=', 'divisions.id');

                // Optional single-division filter
                if (!is_null($divisionId) && $divisionId !== 0) {
                    $query->where('membership_payments.division_id', $divisionId);
                }

                // ⚠️ Here we alias division fields as branch_* to keep blades unchanged
                $rows = $query
                    ->select([
                        'divisions.id as branch_id',
                        'divisions.name as branch_name',
                        DB::raw("QUARTER(payment_date) as quarter"),
                        DB::raw("SUM(membership_fees.amount) as total_amount"),
                        DB::raw("COUNT(membership_payments.id) as payment_count"),
                    ])
                    ->groupBy('divisions.id', 'divisions.name', DB::raw("QUARTER(payment_date)"))
                    ->orderBy('divisions.name')
                    ->get();
            }

            // 👇 reshaping stays exactly as you had it
            $grouped = $rows->groupBy('branch_id');

            return $grouped->map(function ($items) {
                $branchName = $items->first()->branch_name;

                $quarters = [
                    'q1' => 0,
                    'q2' => 0,
                    'q3' => 0,
                    'q4' => 0,
                ];

                $total = 0;

                foreach ($items as $row) {
                    $key = 'q' . $row->quarter;
                    $quarters[$key] = (float) $row->total_amount;
                    $total += (float) $row->total_amount;
                }

                return (object)[
                    'branch_id'   => $items->first()->branch_id,
                    'branch_name' => $branchName, // at branch level this is actually division name
                    'q1'          => $quarters['q1'],
                    'q2'          => $quarters['q2'],
                    'q3'          => $quarters['q3'],
                    'q4'          => $quarters['q4'],
                    'total'       => $total,
                ];
            })->values();
        });
    }



    public function getDivisionMembershipSummariesByQuarter(
        int $year,
        int $branchId
    ): Collection {
        $cacheKey = "division_membership_quarters_{$year}_branch_{$branchId}";

        return $this->remember($cacheKey, function () use ($year, $branchId) {

            $rows = MembershipPayment::query()
                ->where('is_deleted', false)
                ->whereYear('payment_date', $year)
                ->where('membership_payments.branch_id', $branchId)
                ->join('membership_fees', 'membership_payments.membership_fee_id', '=', 'membership_fees.id')
                ->join('divisions', 'membership_payments.division_id', '=', 'divisions.id')
                ->select([
                    'divisions.id   as division_id',
                    'divisions.name as division_name',
                    DB::raw("QUARTER(payment_date) as quarter"),
                    DB::raw("SUM(membership_fees.amount) as total_amount"),
                    DB::raw("COUNT(membership_payments.id) as payment_count"),
                ])
                ->groupBy('divisions.id', 'divisions.name', DB::raw("QUARTER(payment_date)"))
                ->orderBy('divisions.name')
                ->get();

            $grouped = $rows->groupBy('division_id');

            return $grouped->map(function ($items) {
                $divisionName = $items->first()->division_name;

                $quarters = [
                    'q1' => 0,
                    'q2' => 0,
                    'q3' => 0,
                    'q4' => 0,
                ];

                $total = 0;

                foreach ($items as $row) {
                    $key = 'q' . $row->quarter;
                    $quarters[$key] = (float) $row->total_amount;
                    $total += (float) $row->total_amount;
                }

                return (object)[
                    'division_id'   => $items->first()->division_id,
                    'division_name' => $divisionName,
                    'q1'            => $quarters['q1'],
                    'q2'            => $quarters['q2'],
                    'q3'            => $quarters['q3'],
                    'q4'            => $quarters['q4'],
                    'total'         => $total,
                ];
            })->values();
        });
    }



    /**
     * Base query for donations, with optional branch/division filter.
     * ONLY cash donations are considered in higher-level methods.
     */
    private function baseDonationQuery(
        ?int $branchId = null,
        ?int $divisionId = null
    ) {
        $query = Donation::query()
            ->notDeleted()
            ->whereNotNull('date_donation');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($divisionId) {
            $query->where('division_id', $divisionId);
        }

        return $query;
    }

    /**
     * MEMBERSHIP REVENUE
     * -------------------
     * This is your original logic for membership fees (kept as-is).
     */
    public function getMembershipRevenue(
        Carbon $startDate,
        Carbon $endDate,
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $redCrossUnitId = null
    ): float {
        $cacheKey = "membership_revenue_{$startDate->toDateString()}_{$endDate->toDateString()}"
            . ($branchId ? "_branch_{$branchId}" : "")
            . ($divisionId ? "_division_{$divisionId}" : "")
            . ($redCrossUnitId ? "_unit_{$redCrossUnitId}" : "");

        return $this->remember($cacheKey, function () use ($startDate, $endDate, $branchId, $divisionId, $redCrossUnitId) {
            $query = MembershipPayment::where('is_deleted', false)
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->join('membership_fees', 'membership_payments.membership_fee_id', '=', 'membership_fees.id');

            if ($branchId) {
                $query->where('membership_payments.branch_id', $branchId);
            }

            if ($divisionId) {
                $query->where('membership_payments.division_id', $divisionId);
            }

            if ($redCrossUnitId) {
                $query->join('users', 'membership_payments.user_id', '=', 'users.id')
                    ->where('users.red_cross_unit_id', $redCrossUnitId);
            }

            return (float) $query->sum('membership_fees.amount');
        });
    }

    /**
     * CASH DONATION REVENUE (sum of donations.amount).
     */
    public function getCashDonationRevenue(
        Carbon $startDate,
        Carbon $endDate,
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $redCrossUnitId = null
    ): float {
        $cacheKey = "cash_donation_revenue_{$startDate->toDateString()}_{$endDate->toDateString()}"
            . ($branchId ? "_branch_{$branchId}" : "")
            . ($divisionId ? "_division_{$divisionId}" : "")
            . ($redCrossUnitId ? "_unit_{$redCrossUnitId}" : "");

        return $this->remember($cacheKey, function () use (
            $startDate,
            $endDate,
            $branchId,
            $divisionId,
            $redCrossUnitId
        ) {
            $query = $this->baseDonationQuery($branchId, $divisionId)
                ->whereBetween('date_donation', [$startDate, $endDate]);

            if ($redCrossUnitId) {
                $query->join('users', 'donations.user_id', '=', 'users.id')
                    ->where('users.red_cross_unit_id', $redCrossUnitId);
            }

            return (float) $query->sum('donations.amount');
        });
    }

    /**
     * National → Branch summaries (cash donations per branch).
     */
    public function getNationalBranchSummaries(
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): Collection {
        $keyPart = ($startDate && $endDate)
            ? "{$startDate->toDateString()}_{$endDate->toDateString()}"
            : 'all_time';

        $cacheKey = "national_branch_summaries_{$keyPart}";

        return $this->remember($cacheKey, function () use ($startDate, $endDate) {
            $query = $this->baseDonationQuery(null, null);

            if ($startDate && $endDate) {
                $query->whereBetween('date_donation', [$startDate, $endDate]);
            }

            $query->join('branches', 'donations.branch_id', '=', 'branches.id')
                ->select([
                    'branches.id as branch_id',
                    'branches.name as branch_name',
                    DB::raw("SUM(donations.amount) AS total_cash_donations"),
                    DB::raw("COUNT(donations.id) AS donation_count"),
                ])
                ->groupBy('branches.id', 'branches.name')
                ->orderBy('branches.name');

            return $query->get();
        });
    }

    /**
     * Branch → Division summaries (cash donations per division in a branch).
     */
    public function getBranchDivisionSummaries(
        Branch $branch,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): Collection {
        $keyPart = ($startDate && $endDate)
            ? "{$startDate->toDateString()}_{$endDate->toDateString()}"
            : 'all_time';

        $cacheKey = "branch_division_summaries_{$branch->id}_{$keyPart}";

        return $this->remember($cacheKey, function () use ($branch, $startDate, $endDate) {
            $query = $this->baseDonationQuery($branch->id, null);

            if ($startDate && $endDate) {
                $query->whereBetween('date_donation', [$startDate, $endDate]);
            }

            $query->join('divisions', 'donations.division_id', '=', 'divisions.id')
                ->select([
                    'divisions.id as division_id',
                    'divisions.name as division_name',
                    DB::raw("SUM(donations.amount) AS total_cash_donations"),
                    DB::raw("COUNT(donations.id) AS donation_count"),
                ])
                ->groupBy('divisions.id', 'divisions.name')
                ->orderBy('divisions.name');

            return $query->get();
        });
    }

    /**
     * Division → Unit summaries (cash donations per Red Cross unit).
     */
    public function getDivisionUnitSummaries(
        Division $division,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): Collection {
        $keyPart = ($startDate && $endDate)
            ? "{$startDate->toDateString()}_{$endDate->toDateString()}"
            : 'all_time';

        $cacheKey = "division_unit_summaries_{$division->id}_{$keyPart}";

        return $this->remember($cacheKey, function () use ($division, $startDate, $endDate) {
            $query = $this->baseDonationQuery(null, $division->id);

            if ($startDate && $endDate) {
                $query->whereBetween('date_donation', [$startDate, $endDate]);
            }

            $query->join('red_cross_units', 'donations.red_cross_unit_id', '=', 'red_cross_units.id')
                ->select([
                    'red_cross_units.id as unit_id',
                    'red_cross_units.name as unit_name',
                    DB::raw("SUM(donations.amount) AS total_cash_donations"),
                    DB::raw("COUNT(donations.id) AS donation_count"),
                ])
                ->groupBy('red_cross_units.id', 'red_cross_units.name')
                ->orderBy('red_cross_units.name');

            return $query->get();
        });
    }

    /**
     * Unit → Member summaries (cash donations per donor in a unit).
     */
    public function getUnitMemberSummaries(
        RedCrossUnit $unit,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null
    ): Collection {
        $keyPart = ($startDate && $endDate)
            ? "{$startDate->toDateString()}_{$endDate->toDateString()}"
            : 'all_time';

        $cacheKey = "unit_member_summaries_{$unit->id}_{$keyPart}";

        return $this->remember($cacheKey, function () use ($unit, $startDate, $endDate) {
            $query = $this->baseDonationQuery(null, null)
                ->where('donations.red_cross_unit_id', $unit->id);

            if ($startDate && $endDate) {
                $query->whereBetween('date_donation', [$startDate, $endDate]);
            }

            $query->join('users', 'donations.user_id', '=', 'users.id')
                ->select([
                    'users.id as user_id',
                    'users.name as user_name',
                    DB::raw("SUM(donations.amount) AS total_cash_donations"),
                    DB::raw("COUNT(donations.id) AS donation_count"),
                ])
                ->groupBy('users.id', 'users.name')
                ->orderBy('users.name');

            return $query->get();
        });
    }

    /**
     * Time-series data for charts (month-by-month cash donations).
     */
    public function getDonationTrendForChart(
        int $months = 24,
        ?int $branchId = null,
        ?int $divisionId = null,
        ?int $redCrossUnitId = null
    ): array {
        $scopeKey =
            ($branchId ? "_branch_{$branchId}" : '') .
            ($divisionId ? "_division_{$divisionId}" : '') .
            ($redCrossUnitId ? "_unit_{$redCrossUnitId}" : '');

        $cacheKey = "donation_trend_{$months}{$scopeKey}";

        return $this->remember($cacheKey, function () use ($months, $branchId, $divisionId, $redCrossUnitId) {
            $labels = [];
            $cash   = [];

            $end   = now()->startOfMonth();
            $start = $end->copy()->subMonths($months - 1);

            for ($i = 0; $i < $months; $i++) {
                $month = $start->copy()->addMonths($i);
                $labels[] = $month->format('Y-m');

                $monthQuery = $this->baseDonationQuery($branchId, $divisionId)
                    ->whereYear('date_donation', $month->year)
                    ->whereMonth('date_donation', $month->month);

                if ($redCrossUnitId) {
                    $monthQuery->join('users', 'donations.user_id', '=', 'users.id')
                        ->where('users.red_cross_unit_id', $redCrossUnitId);
                }

                $cash[] = (float) $monthQuery->sum('donations.amount');
            }

            return [
                'labels' => $labels,
                'values' => $cash,
            ];
        });
    }
}

