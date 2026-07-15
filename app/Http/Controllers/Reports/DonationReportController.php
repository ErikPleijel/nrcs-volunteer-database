<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Reports\DonationStatsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DonationReportController extends Controller
{
    private DonationStatsService $donationStatsService;

    public function __construct(DonationStatsService $donationStatsService)
    {
        $this->donationStatsService = $donationStatsService;
    }

    /**
     * National Donations Report
     *
     * - Trend graph (cash + in-kind) for all branches (national level)
     * - Quarterly summary table per branch (cash + in-kind)
     */
    public function national(Request $request)
    {
        // 🔢 Trend range selector: 2, 4, 6, 8 years (same pattern as trainings / financial)
        $trendOptions = [
            '2_years' => 2,
            '4_years' => 4,
            '6_years' => 6,
            '8_years' => 8,
        ];

        $selectedTrendKey = $request->input('trend_years', '2_years');
        $years            = $trendOptions[$selectedTrendKey] ?? 2;

        // 📅 Year selector for quarterly summary (simple last-5-years range)
        $currentYear   = Carbon::now()->year;
        $yearOptions   = range($currentYear, $currentYear - 4);
        $selectedYear  = (int) $request->input('year', $currentYear);

        // 📈 National donation trend (all branches) for Chart.js
        $trendDataset = $this->donationStatsService->getDonationTrendForChart(
            $years,
            null // null = all branches (national)
        );

        // 🧮 Quarterly summary per branch for the selected year
        $branchQuarterlySummaries = $this->donationStatsService
            ->getBranchDonationQuarterlySummary($selectedYear);

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($branchQuarterlySummaries, 'Branch', 'branch_name');
        }

        return view('reports.donations.national', [
            'trendOptions'               => $trendOptions,
            'selectedTrendKey'           => $selectedTrendKey,
            'years'                      => $yearOptions,
            'selectedYear'               => $selectedYear,
            'trendDataset'               => $trendDataset,
            'branchQuarterlySummaries'   => $branchQuarterlySummaries,
        ]);
    }

    /**
     * Streams $rows as a flat 11-column CSV (area name, Q1-Q4 Cash/In-kind,
     * Total Cash/In-kind) — same BOM + sep=, + fputcsv pattern as
     * MemberReportController::exportCsv(). $nameField is 'branch_name' at
     * national level, 'division_name' at branch level.
     */
    private function exportCsv($rows, string $areaLabel, string $nameField, ?string $scopeName = null): StreamedResponse
    {
        $date = now()->toDateString();
        $scopeSlug = $scopeName ? \Illuminate\Support\Str::slug($scopeName) : 'national';
        $filename = "donations-report-{$scopeSlug}-{$date}.csv";

        return response()->streamDownload(function () use ($rows, $areaLabel, $nameField) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, "sep=,\r\n");

            fputcsv($out, [
                $areaLabel,
                'Q1 Cash', 'Q1 In-kind',
                'Q2 Cash', 'Q2 In-kind',
                'Q3 Cash', 'Q3 In-kind',
                'Q4 Cash', 'Q4 In-kind',
                'Total Cash', 'Total In-kind',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->{$nameField},
                    $row->q1_cash, $row->q1_in_kind,
                    $row->q2_cash, $row->q2_in_kind,
                    $row->q3_cash, $row->q3_in_kind,
                    $row->q4_cash, $row->q4_in_kind,
                    $row->total_cash, $row->total_in_kind,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Branch Donations Report
     *
     * - Trend graph (cash + in-kind) for ONE branch
     * - Quarterly summary just for this branch (Q1–Q4, cash + in-kind)
     */
    public function branch(Request $request, int $branchId)
    {
        $branch = Branch::findOrFail($branchId);

        $trendOptions = [
            '2_years' => 2,
            '4_years' => 4,
            '6_years' => 6,
            '8_years' => 8,
        ];

        $selectedTrendKey = $request->input('trend_years', '2_years');
        $yearsToShow      = $trendOptions[$selectedTrendKey] ?? 2;

        $currentYear  = Carbon::now()->year;
        $yearOptions  = range($currentYear, $currentYear - 4);
        $selectedYear = (int) $request->input('year', $currentYear);

        // Branch trend
        $trendDataset = $this->donationStatsService->getDonationTrendForChart(
            $yearsToShow,
            $branch->id
        );

        // NEW: division-level quarterly summaries for this branch
        $divisionQuarterlySummaries = $this->donationStatsService
            ->getDivisionDonationQuarterlySummary($branch->id, $selectedYear);

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($divisionQuarterlySummaries, 'Division', 'division_name', $branch->name);
        }

        return view('reports.donations.branch', [
            'branch'                    => $branch,
            'trendOptions'              => $trendOptions,
            'selectedTrendKey'          => $selectedTrendKey,
            'years'                     => $yearOptions,
            'selectedYear'              => $selectedYear,
            'trendDataset'              => $trendDataset,
            'divisionQuarterlySummaries'=> $divisionQuarterlySummaries,
        ]);
    }

}
