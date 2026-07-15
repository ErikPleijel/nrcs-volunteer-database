<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Reports\RegistrationStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationReportController extends Controller
{
    protected RegistrationStatsService $stats;

    public function __construct(RegistrationStatsService $stats)
    {
        $this->stats = $stats;
    }

    /**
     * National registrations report.
     *
     * - Trend chart (2/4/6/8 years, default 4)
     * - Table: branches with registration counts for selected year
     */
    public function national(Request $request)
    {
        // Dropdown for trend range (years)
        $trendOptions = [
            '2_years' => 2,
            '4_years' => 4,
            '6_years' => 6,
            '8_years' => 8,
        ];

        $selectedTrendKey = $request->input('trend_years', '4_years');
        $trendYears       = $trendOptions[$selectedTrendKey] ?? 4;

        // Dataset for registrations chart (national level)
        $trendDataset = $this->stats->getRegistrationTrendForChart($trendYears, null);

        // Year selector for the branch summary table
        $currentYear = Carbon::now()->year;
        $selectedYear = (int) $request->input('year', $currentYear);

        // Simple year options: last 5 years (including current)
        $yearOptions = collect(range($currentYear, $currentYear - 4))->sortDesc()->values();

        // Table under the chart: branches with registration count for the selected year
        $branchRegistrationSummaries = $this->stats->getBranchRegistrationSummary($selectedYear);

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($branchRegistrationSummaries, 'Branch', 'branch_name');
        }

        return view('reports.registrations.national', [
            'trendOptions'               => $trendOptions,
            'selectedTrendKey'           => $selectedTrendKey,
            'trendDataset'               => $trendDataset,
            'selectedYear'               => $selectedYear,
            'yearOptions'                => $yearOptions,
            'branchRegistrationSummaries'=> $branchRegistrationSummaries,
        ]);
    }

    /**
     * Streams $rows as a 2-column CSV (area name, Registrations) — same
     * BOM + sep=, + fputcsv pattern as MemberReportController::exportCsv().
     * $nameField is 'branch_name' at national level, 'division_name' at
     * branch level (RegistrationStatsService's summary rows don't share a
     * common 'name' property the way branch/division models do).
     */
    private function exportCsv($rows, string $areaLabel, string $nameField, ?string $scopeName = null): StreamedResponse
    {
        $date = now()->toDateString();
        $scopeSlug = $scopeName ? \Illuminate\Support\Str::slug($scopeName) : 'national';
        $filename = "registrations-report-{$scopeSlug}-{$date}.csv";

        return response()->streamDownload(function () use ($rows, $areaLabel, $nameField) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, "sep=,\r\n");

            fputcsv($out, [$areaLabel, 'Registrations']);

            foreach ($rows as $row) {
                fputcsv($out, [$row->{$nameField}, $row->registrations_count]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Branch-level registrations report.
     *
     * - Trend chart (2/4/6/8 years, default 4) for this branch only
     * - Table: divisions within this branch with registration counts for selected year
     */
    public function branch(Request $request, int $branchId)
    {
        $branch = Branch::findOrFail($branchId);

        // Dropdown for trend range (years)
        $trendOptions = [
            '2_years' => 2,
            '4_years' => 4,
            '6_years' => 6,
            '8_years' => 8,
        ];

        $selectedTrendKey = $request->input('trend_years', '4_years');
        $trendYears       = $trendOptions[$selectedTrendKey] ?? 4;

        // Dataset for registrations chart for this branch
        $trendDataset = $this->stats->getRegistrationTrendForChart($trendYears, $branchId);

        // Year selector for the division summary table
        $currentYear  = Carbon::now()->year;
        $selectedYear = (int) $request->input('year', $currentYear);

        $yearOptions = collect(range($currentYear, $currentYear - 4))->sortDesc()->values();

        // Table under the chart: divisions in this branch
        $divisionRegistrationSummaries = $this->stats->getDivisionRegistrationSummary($branchId, $selectedYear);

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($divisionRegistrationSummaries, 'Division', 'division_name', $branch->name);
        }

        return view('reports.registrations.branch', [
            'branch'                        => $branch,
            'trendOptions'                  => $trendOptions,
            'selectedTrendKey'              => $selectedTrendKey,
            'trendDataset'                  => $trendDataset,
            'selectedYear'                  => $selectedYear,
            'yearOptions'                   => $yearOptions,
            'divisionRegistrationSummaries' => $divisionRegistrationSummaries,
        ]);
    }
}
