<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Division;
use App\Services\Reports\ActivityStatsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VolunteerReportController extends Controller
{
    protected ActivityStatsService $activityStatsService;

    public function __construct(ActivityStatsService $activityStatsService)
    {
        $this->activityStatsService = $activityStatsService;
    }

    public function index()
    {
        return view('reports.volunteers.index');
    }

    public function demographics()
    {
        $data = [
            'ageGroups'           => $this->getAgeDistribution(),
            'genderDistribution'  => $this->getGenderDistribution(),
            'locationDistribution'=> $this->getLocationDistribution(),
        ];

        return view('reports.volunteers.demographics', compact('data'));
    }

    public function activity()
    {
        $data = [
            'activeVolunteers'      => $this->getActiveVolunteers(),
            'volunteersByActivity'  => $this->getVolunteersByActivity(),
            'volunteerTrends'       => $this->getVolunteerTrends(),
        ];

        return view('reports.volunteers.activity', compact('data'));
    }

    public function export($type)
    {
        return response()->download('/path/to/generated/file');
    }

    public function getStats(Request $request)
    {
        $totalVolunteers = $this->activityStatsService->getTotalVolunteersCountGroupedBy('branch')->sum('volunteer_count');

        return response()->json(['total_volunteers' => $totalVolunteers]);
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

        $trendDataset = $this->snapshotVolunteerTrend($months);

        $currentYear  = Carbon::now()->year;
        $years        = range($currentYear, $currentYear - 5);
        $selectedYear = (int) $request->input('year', $currentYear);
        $atDate       = Carbon::create($selectedYear, 12, 31)->endOfDay();

        $demographics = $this->activityStatsService->getVolunteerDemographicsSnapshot(
            branchId: null,
            divisionId: null,
            atDate: $atDate
        );

        $latestDate = \App\Models\StatsSnapshot::max('snapshot_date');

        $branchVolunteerCounts = $latestDate
            ? \App\Models\StatsSnapshot::where('stats_snapshots.snapshot_date', $latestDate)
                ->whereNotNull('stats_snapshots.branch_id')
                ->join('branches', 'stats_snapshots.branch_id', '=', 'branches.id')
                ->selectRaw('branches.id, branches.name,
                    SUM(volunteers_men) as men, SUM(volunteers_women) as women, SUM(volunteers_total) as total')
                ->groupBy('branches.id', 'branches.name')
                ->orderBy('branches.name')
                ->get()
            : collect();

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($branchVolunteerCounts, 'Branch');
        }

        return view('reports.volunteers.national', [
            'trendDataset'         => $trendDataset,
            'trendOptions'         => $trendOptions,
            'selectedTrendKey'     => $selectedTrendKey,
            'branchVolunteerCounts'=> $branchVolunteerCounts,
            'latestDate'           => $latestDate,
            'demographics'         => $demographics,
            'years'                => $years,
            'selectedYear'         => $selectedYear,
        ]);
    }

    /**
     * Streams $counts as CSV — same BOM + sep=, + fputcsv pattern as
     * MemberReportController::exportCsv(). $areaLabel is the header for the
     * first column ("Branch" at national level, "Division" at branch level);
     * $scopeName (branch name) is only used to make the branch-level
     * filename distinguishable from the national one.
     */
    private function exportCsv($counts, string $areaLabel, ?string $scopeName = null): StreamedResponse
    {
        $date = now()->toDateString();
        $scopeSlug = $scopeName ? \Illuminate\Support\Str::slug($scopeName) : 'national';
        $filename = "volunteer-report-{$scopeSlug}-{$date}.csv";

        return response()->streamDownload(function () use ($counts, $areaLabel) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, "sep=,\r\n");

            fputcsv($out, [$areaLabel, 'Men', 'Women', 'Total']);

            foreach ($counts as $row) {
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

        $trendDataset = $this->snapshotVolunteerTrend($months, $branchId);

        $currentYear  = Carbon::now()->year;
        $years        = range($currentYear, $currentYear - 5);
        $selectedYear = (int) $request->input('year', $currentYear);
        $atDate       = Carbon::create($selectedYear, 12, 31)->endOfDay();

        $demographics = $this->activityStatsService->getVolunteerDemographicsSnapshot(
            branchId: $branchId,
            divisionId: null,
            atDate: $atDate
        );

        $latestDate = \App\Models\StatsSnapshot::max('snapshot_date');

        $divisionVolunteerCounts = $latestDate
            ? \App\Models\StatsSnapshot::where('stats_snapshots.snapshot_date', $latestDate)
                ->where('stats_snapshots.branch_id', $branchId)
                ->whereNotNull('stats_snapshots.division_id')
                ->join('divisions', 'stats_snapshots.division_id', '=', 'divisions.id')
                ->selectRaw('divisions.id, divisions.name,
                    SUM(volunteers_men) as men, SUM(volunteers_women) as women, SUM(volunteers_total) as total')
                ->groupBy('divisions.id', 'divisions.name')
                ->orderBy('divisions.name')
                ->get()
            : collect();

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($divisionVolunteerCounts, 'Division', $branch->name);
        }

        return view('reports.volunteers.branch', [
            'branch'                  => $branch,
            'trendDataset'            => $trendDataset,
            'trendOptions'            => $trendOptions,
            'selectedTrendKey'        => $selectedTrendKey,
            'divisionVolunteerCounts' => $divisionVolunteerCounts,
            'latestDate'              => $latestDate,
            'demographics'            => $demographics,
            'years'                   => $years,
            'selectedYear'            => $selectedYear,
        ]);
    }

    public function division(Request $request, $divisionId)
    {
        $division = Division::findOrFail($divisionId);

        $trendOptions = [
            '2_years' => 24,
            '4_years' => 48,
            '6_years' => 72,
            '8_years' => 96,
        ];
        $selectedTrendKey = $request->input('trend_months', '2_years');
        $months = $trendOptions[$selectedTrendKey] ?? 24;

        $trendDataset = $this->snapshotVolunteerTrend($months, null, $divisionId);

        $currentYear  = Carbon::now()->year;
        $years        = range($currentYear, $currentYear - 5);
        $selectedYear = (int) $request->input('year', $currentYear);
        $atDate       = Carbon::create($selectedYear, 12, 31)->endOfDay();

        $demographics = $this->activityStatsService->getVolunteerDemographicsSnapshot(
            branchId: null,
            divisionId: $divisionId,
            atDate: $atDate
        );

        return view('reports.volunteers.division', [
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
     * Monthly volunteer-count trend from stats_snapshots.
     * Latest snapshot within each month; null points for gap months.
     */
    private function snapshotVolunteerTrend(int $months, ?int $branchId = null, ?int $divisionId = null): array
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
                ->selectRaw('SUM(volunteers_men) as men, SUM(volunteers_women) as women, SUM(volunteers_total) as total')
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

    // ─── Placeholder helpers used by demographics() and activity() ───────────

    private function getAgeDistribution()
    {
        return ['under_18' => 15, '18_to_24' => 125, '25_to_34' => 230, '35_to_44' => 180, '45_to_54' => 120, '55_to_64' => 85, '65_plus' => 45];
    }

    private function getGenderDistribution()
    {
        return ['male' => 410, 'female' => 380, 'other' => 10];
    }

    private function getLocationDistribution()
    {
        return ['north' => 210, 'south' => 190, 'east' => 180, 'west' => 220];
    }

    private function getActiveVolunteers()
    {
        return 450;
    }

    private function getVolunteersByActivity()
    {
        return ['first_aid' => 180, 'disaster_relief' => 120, 'blood_donation' => 90, 'community_service' => 140, 'training' => 70];
    }

    private function getVolunteerTrends()
    {
        return [
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            'data'   => [410, 420, 430, 450, 460, 480, 490, 500, 520, 540, 560, 580],
        ];
    }
}
