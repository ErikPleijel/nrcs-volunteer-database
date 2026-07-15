<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Reports\MembershipStatsService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberReportController extends Controller
{
    protected $membershipStatsService;

    public function __construct(MembershipStatsService $membershipStatsService)
    {
        $this->membershipStatsService = $membershipStatsService;
    }

    public function national(Request $request)
    {
        $trendOptions = [
            '2_years' => 24,
            '4_years' => 48,
            '6_years' => 72,
            '8_years' => 96,
        ];
        $selectedTrendKey = $request->input('trend_months', '2_years');
        $months = $trendOptions[$selectedTrendKey] ?? 24;

        $trendDataset = $this->snapshotTrendSeries($months);

        $currentYear = Carbon::now()->year;
        $years = range($currentYear, $currentYear - 5);
        $selectedYear = (int) $request->input('year', $currentYear);

        $selectedDateOrYearEnd = Carbon::create($selectedYear, 12, 31)->endOfDay();

        $demographics = $this->membershipStatsService->getDemographicsSnapshot(
            branchId: null,
            divisionId: null,
            unitId: null,
            atDate: $selectedDateOrYearEnd
        );

        $latestDate = \App\Models\StatsSnapshot::where('snapshot_date', '<=', now())->max('snapshot_date');

        $branchMemberCounts = $latestDate
            ? \App\Models\StatsSnapshot::where('stats_snapshots.snapshot_date', $latestDate)
                ->whereNotNull('stats_snapshots.branch_id')
                ->join('branches', 'stats_snapshots.branch_id', '=', 'branches.id')
                ->selectRaw('branches.id, branches.name,
                    SUM(members_men) as men, SUM(members_women) as women, SUM(members_total) as total')
                ->groupBy('branches.id', 'branches.name')
                ->orderBy('branches.name')
                ->get()
            : collect();

        // Fixed 4-column export (Branch, Men, Women, Total) — unlike the
        // Lifecycle Report there's no checkbox-driven column set here, so
        // this just streams $branchMemberCounts as-is; same
        // BOM + sep=, + fputcsv pattern established there.
        if ($request->input('export') === 'csv') {
            return $this->exportCsv($branchMemberCounts);
        }

        return view('reports.members.national', [
            'trendDataset'       => $trendDataset,
            'years'              => $years,
            'selectedYear'       => $selectedYear,
            'branchMemberCounts' => $branchMemberCounts,
            'trendOptions'       => $trendOptions,
            'selectedTrendKey'   => $selectedTrendKey,
            'demographics'       => $demographics,
            'latestDate'         => $latestDate,
        ]);
    }

    /**
     * Streams $branchMemberCounts as CSV — static header row, one row per
     * branch, same values as the on-screen table. BOM + sep=, override line
     * (raw fwrite, not fputcsv, so it isn't quoted/escaped as a data field)
     * established as the correct approach for Excel compatibility in the
     * Lifecycle Report's export.
     */
    private function exportCsv($branchMemberCounts): StreamedResponse
    {
        $date = now()->toDateString();
        $filename = "membership-report-national-{$date}.csv";

        return response()->streamDownload(function () use ($branchMemberCounts) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, "sep=,\r\n");

            fputcsv($out, ['Branch', 'Men', 'Women', 'Total']);

            foreach ($branchMemberCounts as $row) {
                fputcsv($out, [$row->name, $row->men, $row->women, $row->total]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function branch(Request $request, $branchId)
    {
        $branch = Branch::findOrFail($branchId);

        $trendOptions = [
            '2_years' => 24,
            '4_years' => 48,
            '6_years' => 72,
            '8_years' => 96,
        ];
        $selectedTrendKey = $request->input('trend_months', '2_years');
        $months = $trendOptions[$selectedTrendKey] ?? 24;

        $trendDataset = $this->snapshotTrendSeries($months, $branchId);

        $currentYear  = \Carbon\Carbon::now()->year;
        $years        = range($currentYear, $currentYear - 5);
        $selectedYear = (int) $request->input('year', $currentYear);
        $atDate       = \Carbon\Carbon::create($selectedYear, 12, 31)->endOfDay();

        $demographics = $this->membershipStatsService->getDemographicsSnapshot(
            branchId: $branchId,
            divisionId: null,
            unitId: null,
            atDate: $atDate
        );

        $latestDate = \App\Models\StatsSnapshot::max('snapshot_date');

        $divisionMemberCounts = $latestDate
            ? \App\Models\StatsSnapshot::where('stats_snapshots.snapshot_date', $latestDate)
                ->where('stats_snapshots.branch_id', $branchId)
                ->whereNotNull('stats_snapshots.division_id')
                ->join('divisions', 'stats_snapshots.division_id', '=', 'divisions.id')
                ->selectRaw('divisions.id, divisions.name,
                    SUM(members_men) as men, SUM(members_women) as women, SUM(members_total) as total')
                ->groupBy('divisions.id', 'divisions.name')
                ->orderBy('divisions.name')
                ->get()
            : collect();

        return view('reports.members.branch', [
            'branch'               => $branch,
            'trendDataset'         => $trendDataset,
            'divisionMemberCounts' => $divisionMemberCounts,
            'trendOptions'         => $trendOptions,
            'selectedTrendKey'     => $selectedTrendKey,
            'latestDate'           => $latestDate,
            'demographics'         => $demographics,
            'years'                => $years,
            'selectedYear'         => $selectedYear,
        ]);
    }

    public function division(Request $request, $divisionId)
    {
        $division = \App\Models\Division::findOrFail($divisionId);

        $trendOptions = [
            '2_years' => 24,
            '4_years' => 48,
            '6_years' => 72,
            '8_years' => 96,
        ];
        $selectedTrendKey = $request->input('trend_months', '2_years');
        $months = $trendOptions[$selectedTrendKey] ?? 24;

        $trendDataset = $this->snapshotTrendSeries($months, null, $divisionId);

        $currentYear  = \Carbon\Carbon::now()->year;
        $years        = range($currentYear, $currentYear - 5);
        $selectedYear = (int) $request->input('year', $currentYear);
        $atDate       = \Carbon\Carbon::create($selectedYear, 12, 31)->endOfDay();

        $demographics = $this->membershipStatsService->getDemographicsSnapshot(
            branchId: null,
            divisionId: $divisionId,
            unitId: null,
            atDate: $atDate
        );

        return view('reports.members.division', [
            'division'         => $division,
            'trendDataset'     => $trendDataset,
            'trendOptions'     => $trendOptions,
            'selectedTrendKey' => $selectedTrendKey,
            'demographics'     => $demographics,
            'years'            => $years,
            'selectedYear'     => $selectedYear,
        ]);
    }

    /**
     * Monthly trend series from stats_snapshots.
     * For each month in range: use the LATEST snapshot within that month.
     * Months without a snapshot produce null points (gaps).
     */
    private function snapshotTrendSeries(int $months, ?int $branchId = null, ?int $divisionId = null): array
    {
        $labels = [];
        $male   = [];
        $female = [];
        $total  = [];

        $start = now()->subMonths($months)->startOfMonth();

        for ($m = $start->copy(); $m <= now(); $m->addMonth()) {
            $labels[] = $m->format('M Y');

            $date = \App\Models\StatsSnapshot::whereBetween('snapshot_date', [
                    $m->toDateString(),
                    $m->copy()->endOfMonth()->toDateString(),
                ])
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->when($divisionId, fn ($q) => $q->where('division_id', $divisionId))
                ->max('snapshot_date');

            if (! $date) {
                $male[]   = null;
                $female[] = null;
                $total[]  = null;
                continue;
            }

            $row = \App\Models\StatsSnapshot::where('snapshot_date', $date)
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->when($divisionId, fn ($q) => $q->where('division_id', $divisionId))
                ->selectRaw('SUM(members_men) as men, SUM(members_women) as women, SUM(members_total) as total')
                ->first();

            $male[]   = (int) $row->men;
            $female[] = (int) $row->women;
            $total[]  = (int) $row->total;
        }

        return [
            'labels' => $labels,
            'series' => [
                'male'   => $male,
                'female' => $female,
                'total'  => $total,
            ],
        ];
    }
}
