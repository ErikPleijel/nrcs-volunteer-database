<?php

namespace App\Services\Reports;

use App\Models\Training;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TrainingStatsService
{
    /**
     * Global TTL for all training stats cache (in seconds).
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

    protected $cachePrefix = 'training_stats_';

    public function getTrainingQuarterlySummary(
        int $year,
        ?int $branchId = null
    ): array {
        $branchKey = $branchId ? "branch_{$branchId}" : 'national';

        $cacheKey = $this->cachePrefix
            ."training_quarterly_summary_{$year}_{$branchKey}";

        return $this->remember($cacheKey, function () use ($year, $branchId) {

            $start = "{$year}-01-01";
            $end = "{$year}-12-31";

            // Get ALL trainings in the year for this scope
            $trainings = DB::table('trainings')
                ->join('training_types', 'trainings.training_type_id', '=', 'training_types.id')
                ->where('trainings.is_deleted', 0)
                ->where('trainings.approval_status', 'approved') // Phase 2: only approved records are real
                ->whereBetween('trainings.training_date', [$start, $end])
                ->when($branchId, function ($q) use ($branchId) {
                    $q->where('trainings.branch_id', $branchId);
                })
                ->select(
                    'trainings.id',
                    'trainings.training_date',
                    'training_types.is_first_aid'
                )
                ->get();

            // Initialize quarters
            $quarters = [
                'q1' => ['first_aid' => 0, 'other' => 0, 'total' => 0],
                'q2' => ['first_aid' => 0, 'other' => 0, 'total' => 0],
                'q3' => ['first_aid' => 0, 'other' => 0, 'total' => 0],
                'q4' => ['first_aid' => 0, 'other' => 0, 'total' => 0],
            ];

            foreach ($trainings as $row) {
                $month = (int) substr($row->training_date, 5, 2);

                if ($month >= 1 && $month <= 3) {
                    $qKey = 'q1';
                } elseif ($month >= 4 && $month <= 6) {
                    $qKey = 'q2';
                } elseif ($month >= 7 && $month <= 9) {
                    $qKey = 'q3';
                } else {
                    $qKey = 'q4';
                }

                $isFirstAid = (bool) $row->is_first_aid;

                if ($isFirstAid) {
                    $quarters[$qKey]['first_aid']++;
                } else {
                    $quarters[$qKey]['other']++;
                }

                $quarters[$qKey]['total']++;
            }

            return $quarters;
        });
    }

    public function getTrainingTrendForChart(
        int $months = 24,
        ?int $branchId = null
    ): array {
        $branchKey = $branchId ? "branch_{$branchId}" : 'national';

        $cacheKey = $this->cachePrefix
            ."training_trend_{$months}_{$branchKey}";

        return $this->remember($cacheKey, function () use ($months, $branchId) {

            $end = now()->startOfMonth();
            $start = $end->copy()->subMonths($months - 1);

            $startDate = $start->toDateString();
            $endDate = $end->copy()->endOfMonth()->toDateString();

            $rows = DB::table('trainings')
                ->join('training_types', 'trainings.training_type_id', '=', 'training_types.id')
                ->where('trainings.is_deleted', 0)
                ->where('trainings.approval_status', 'approved') // Phase 2: only approved records are real
                ->whereBetween('trainings.training_date', [$startDate, $endDate])
                ->when($branchId, function ($q) use ($branchId) {
                    $q->where('trainings.branch_id', $branchId);
                })
                ->select(
                    DB::raw("DATE_FORMAT(trainings.training_date, '%Y-%m-01') as month_start"),
                    'training_types.is_first_aid',
                    DB::raw('COUNT(DISTINCT trainings.id) as training_count')
                )
                ->groupBy('month_start', 'training_types.is_first_aid')
                ->orderBy('month_start')
                ->get();

            // Initialize months
            $labels = [];
            $firstAid = [];
            $other = [];

            $cursor = $start->copy();
            $monthlyMap = [];

            while ($cursor <= $end) {
                $key = $cursor->format('Y-m-01');
                $labels[] = $cursor->format('M Y'); // e.g. "Jan 2024"
                $monthlyMap[$key] = [
                    'first_aid' => 0,
                    'other' => 0,
                ];
                $cursor->addMonth();
            }

            // Fill counts
            foreach ($rows as $row) {
                $key = $row->month_start;

                if (! isset($monthlyMap[$key])) {
                    continue;
                }

                $isFirstAid = (bool) $row->is_first_aid;

                if ($isFirstAid) {
                    $monthlyMap[$key]['first_aid'] += (int) $row->training_count;
                } else {
                    $monthlyMap[$key]['other'] += (int) $row->training_count;
                }
            }

            foreach ($monthlyMap as $data) {
                $firstAid[] = $data['first_aid'];
                $other[] = $data['other'];
            }

            return [
                'labels' => $labels,
                'first_aid' => $firstAid,
                'other' => $other,
            ];
        });
    }

    public function getBranchTrainingQuarterlySummary(int $year): Collection
    {
        $cacheKey = $this->cachePrefix."training_quarterly_by_branch_{$year}";

        return $this->remember($cacheKey, function () use ($year) {
            $rows = DB::table('trainings')
                ->join('branches', 'trainings.branch_id', '=', 'branches.id')
                ->join('training_types', 'trainings.training_type_id', '=', 'training_types.id')
                ->where('trainings.is_deleted', 0)
                ->where('trainings.approval_status', 'approved') // Phase 2: only approved records are real
                ->whereYear('trainings.training_date', $year)
                ->select(
                    'branches.id as branch_id',
                    'branches.name as branch_name',
                    DB::raw('QUARTER(trainings.training_date) as quarter'),
                    DB::raw("
                    CASE
                        WHEN training_types.is_first_aid = 1 THEN 'first_aid'
                        ELSE 'other'
                    END as training_kind
                "),
                    DB::raw('COUNT(DISTINCT trainings.id) as training_count')
                )
                ->groupBy(
                    'branches.id',
                    'branches.name',
                    DB::raw('QUARTER(trainings.training_date)'),
                    DB::raw("
                    CASE
                        WHEN training_types.is_first_aid = 1 THEN 'first_aid'
                        ELSE 'other'
                    END
                ")
                )
                ->orderBy('branches.name')
                ->get();

            $grouped = $rows->groupBy('branch_id');

            return $grouped->map(function ($items) {
                $branchName = $items->first()->branch_name;

                $quarters = [
                    'q1' => ['first_aid' => 0, 'other' => 0],
                    'q2' => ['first_aid' => 0, 'other' => 0],
                    'q3' => ['first_aid' => 0, 'other' => 0],
                    'q4' => ['first_aid' => 0, 'other' => 0],
                ];

                foreach ($items as $row) {
                    $qKey = 'q'.$row->quarter;
                    if (! isset($quarters[$qKey])) {
                        continue;
                    }

                    if ($row->training_kind === 'first_aid') {
                        $quarters[$qKey]['first_aid'] += (int) $row->training_count;
                    } else {
                        $quarters[$qKey]['other'] += (int) $row->training_count;
                    }
                }

                $totalFirstAid = 0;
                $totalOther = 0;

                foreach ($quarters as $q) {
                    $totalFirstAid += $q['first_aid'];
                    $totalOther += $q['other'];
                }

                return (object) [
                    'branch_id' => $items->first()->branch_id,
                    'branch_name' => $branchName,

                    'q1_first_aid' => $quarters['q1']['first_aid'],
                    'q1_other' => $quarters['q1']['other'],
                    'q2_first_aid' => $quarters['q2']['first_aid'],
                    'q2_other' => $quarters['q2']['other'],
                    'q3_first_aid' => $quarters['q3']['first_aid'],
                    'q3_other' => $quarters['q3']['other'],
                    'q4_first_aid' => $quarters['q4']['first_aid'],
                    'q4_other' => $quarters['q4']['other'],

                    'total_first_aid' => $totalFirstAid,
                    'total_other' => $totalOther,
                    'total_all' => $totalFirstAid + $totalOther,
                ];
            })->values();
        });
    }

    public function getDivisionTrainingQuarterlySummary(int $year, int $branchId): Collection
    {
        $cacheKey = $this->cachePrefix."training_quarterly_by_division_{$year}_branch_{$branchId}";

        return $this->remember($cacheKey, function () use ($year, $branchId) {
            $rows = DB::table('trainings')
                ->join('divisions', 'trainings.division_id', '=', 'divisions.id')
                ->join('training_types', 'trainings.training_type_id', '=', 'training_types.id')
                ->where('trainings.is_deleted', 0)
                ->where('trainings.approval_status', 'approved') // Phase 2: only approved records are real
                ->whereYear('trainings.training_date', $year)
                ->where('trainings.branch_id', $branchId)
                ->select(
                    'divisions.id as division_id',
                    'divisions.name as division_name',
                    DB::raw('QUARTER(trainings.training_date) as quarter'),
                    DB::raw("
                    CASE
                        WHEN training_types.is_first_aid = 1 THEN 'first_aid'
                        ELSE 'other'
                    END as training_kind
                "),
                    DB::raw('COUNT(DISTINCT trainings.id) as training_count')
                )
                ->groupBy(
                    'divisions.id',
                    'divisions.name',
                    DB::raw('QUARTER(trainings.training_date)'),
                    DB::raw("
                    CASE
                        WHEN training_types.is_first_aid = 1 THEN 'first_aid'
                        ELSE 'other'
                    END
                ")
                )
                ->orderBy('divisions.name')
                ->get();

            // Group by division
            $grouped = $rows->groupBy('division_id');

            return $grouped->map(function ($items) {
                $divisionName = $items->first()->division_name;

                $quarters = [
                    'q1' => ['first_aid' => 0, 'other' => 0],
                    'q2' => ['first_aid' => 0, 'other' => 0],
                    'q3' => ['first_aid' => 0, 'other' => 0],
                    'q4' => ['first_aid' => 0, 'other' => 0],
                ];

                foreach ($items as $row) {
                    $qKey = 'q'.$row->quarter;
                    if (! isset($quarters[$qKey])) {
                        continue;
                    }

                    if ($row->training_kind === 'first_aid') {
                        $quarters[$qKey]['first_aid'] += (int) $row->training_count;
                    } else {
                        $quarters[$qKey]['other'] += (int) $row->training_count;
                    }
                }

                $totalFirstAid = 0;
                $totalOther = 0;

                foreach ($quarters as $q) {
                    $totalFirstAid += $q['first_aid'];
                    $totalOther += $q['other'];
                }

                return (object) [
                    'division_id' => $items->first()->division_id,
                    'division_name' => $divisionName,

                    'q1_first_aid' => $quarters['q1']['first_aid'],
                    'q1_other' => $quarters['q1']['other'],
                    'q2_first_aid' => $quarters['q2']['first_aid'],
                    'q2_other' => $quarters['q2']['other'],
                    'q3_first_aid' => $quarters['q3']['first_aid'],
                    'q3_other' => $quarters['q3']['other'],
                    'q4_first_aid' => $quarters['q4']['first_aid'],
                    'q4_other' => $quarters['q4']['other'],

                    'total_first_aid' => $totalFirstAid,
                    'total_other' => $totalOther,
                    'total_all' => $totalFirstAid + $totalOther,
                ];
            })->values();
        });
    }

    /**
     * Generic training counter for any date range.
     *
     * @param  string  $startDate  e.g. '2024-01-01'
     * @param  string  $endDate  e.g. '2024-12-31'
     */
    private function countTrainingsBetween(string $startDate, string $endDate, ?int $branchId = null): int
    {
        $query = Training::query()
            ->where('is_deleted', 0)
            ->whereNotNull('training_date')
            ->whereBetween('training_date', [$startDate, $endDate]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query->count();
    }

    /**
     * Trainings in the last 12 months.
     */
    public function getTotalTrainingsLast12Months(?int $branchId = null): int
    {
        $end = Carbon::now()->toDateString();
        $start = Carbon::now()->subYear()->toDateString();

        $cacheKey = $this->cachePrefix
            .'total_trainings_last_12_months'
            .($branchId ? '_branch_'.$branchId : '');

        return $this->remember($cacheKey, function () use ($start, $end, $branchId) {
            return $this->countTrainingsBetween($start, $end, $branchId);
        });
    }

    /**
     * Trainings from 12–24 months ago.
     */
    public function getTotalTrainings12to24MonthsAgo(?int $branchId = null): int
    {
        $end = Carbon::now()->subYear()->toDateString();   // 12 months ago
        $start = Carbon::now()->subYears(2)->toDateString(); // 24 months ago

        $cacheKey = $this->cachePrefix
            .'total_trainings_12_to_24_months_ago'
            .($branchId ? '_branch_'.$branchId : '');

        return $this->remember($cacheKey, function () use ($start, $end, $branchId) {
            return $this->countTrainingsBetween($start, $end, $branchId);
        });
    }

    /**
     * Get trainings filtered by training group name, optional branch and date range.
     *
     * @param  int|null  $branchId  Optional branch filter
     * @param  string|null  $startDate  e.g. '2024-01-01' (based on training_date)
     * @param  string|null  $endDate  e.g. '2024-12-31'
     * @return \Illuminate\Support\Collection
     */
    public function getTrainingsByTrainingGroupName(
        string $trainingGroupName,
        ?int $branchId = null,
        ?string $startDate = null,
        ?string $endDate = null
    ) {
        $cacheKey = $this->cachePrefix
            .'group_'
            .md5(
                $trainingGroupName
                .'|'.($branchId ?? 'all')
                .'|'.($startDate ?? 'null')
                .'|'.($endDate ?? 'null')
            );

        return $this->remember($cacheKey, function () use ($trainingGroupName, $branchId, $startDate, $endDate) {
            $query = DB::table('trainings')
                ->join('training_types', 'trainings.training_type_id', '=', 'training_types.id')
                ->join('training_groups', 'training_types.group_id', '=', 'training_groups.id')
                ->where('trainings.is_deleted', 0)
                ->where('trainings.approval_status', 'approved') // Phase 2: only approved records are real
                ->where('training_groups.group_name', 'LIKE', '%'.$trainingGroupName.'%')
                ->select(
                    'trainings.*',
                    'training_types.name as training_type_name',
                    'training_groups.group_name'
                );

            // Optional branch filter
            if (! is_null($branchId)) {
                $query->where('trainings.branch_id', $branchId);
            }

            // Optional date filters on training_date
            if ($startDate && $endDate) {
                $query->whereBetween('trainings.training_date', [$startDate, $endDate]);
            } elseif ($startDate) {
                $query->where('trainings.training_date', '>=', $startDate);
            } elseif ($endDate) {
                $query->where('trainings.training_date', '<=', $endDate);
            }

            return $query->get();
        });
    }

    /**
     * First Aid trainings in the last 12 months.
     */
    public function getFirstAidTrainingsLast12Months(?int $branchId = null): int
    {
        $end = now()->toDateString();
        $start = now()->subYear()->toDateString();

        $cacheKey = $this->cachePrefix
            .'first_aid_last_12_months'
            .($branchId ? '_branch_'.$branchId : '');

        return $this->remember($cacheKey, function () use ($branchId, $start, $end) {
            return DB::table('trainings')
                ->join('training_types', 'trainings.training_type_id', '=', 'training_types.id')
                ->where('trainings.is_deleted', 0)
                ->where('trainings.approval_status', 'approved') // Phase 2: only approved records are real
                ->where('training_types.is_first_aid', 1)
                ->whereBetween('trainings.training_date', [$start, $end])
                ->when($branchId, fn ($q) => $q->where('trainings.branch_id', $branchId))
                ->count();
        });
    }

    /**
     * First Aid trainings 12–24 months ago.
     */
    public function getFirstAidTrainings12to24MonthsAgo(?int $branchId = null): int
    {
        $end = now()->subYear()->toDateString();   // 12 months ago
        $start = now()->subYears(2)->toDateString(); // 24 months ago

        $cacheKey = $this->cachePrefix
            .'first_aid_12_to_24_months_ago'
            .($branchId ? '_branch_'.$branchId : '');

        return $this->remember($cacheKey, function () use ($branchId, $start, $end) {
            return DB::table('trainings')
                ->join('training_types', 'trainings.training_type_id', '=', 'training_types.id')
                ->where('trainings.is_deleted', 0)
                ->where('trainings.approval_status', 'approved') // Phase 2: only approved records are real
                ->where('training_types.is_first_aid', 1)
                ->whereBetween('trainings.training_date', [$start, $end])
                ->when($branchId, fn ($q) => $q->where('trainings.branch_id', $branchId))
                ->count();
        });
    }
}
