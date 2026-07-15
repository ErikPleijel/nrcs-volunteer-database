<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Division;
use App\Services\Reports\FinancialStatsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialReportController extends Controller
{
    public function __construct(
        protected FinancialStatsService $financialStatsService
    ) {}

    /**
     * National – overview by branch
     */
    public function national(Request $request)
    {
        // 🔢 Trend range selector: 2, 4, 6, 8 years
        $trendOptions = [
            '2_years' => 2,
            '4_years' => 4,
            '6_years' => 6,
            '8_years' => 8,
        ];

        // We’ll use "trend_years" as the query param
        $selectedTrendKey = $request->input('trend_years', '2_years');
        $years            = $trendOptions[$selectedTrendKey] ?? 2;

        // 🌍 Optional filters (for both total + trend)
        $branchId   = $request->filled('branch_id')   ? (int) $request->input('branch_id')   : null;
        $divisionId = $request->filled('division_id') ? (int) $request->input('division_id') : null;

        // ✅ Year selector (for the annual total + quarterly table)
        $selectedYear = (int) $request->input('year', now()->year);

        $availableYears = [];
        for ($y = now()->year; $y >= now()->year - 7; $y--) {
            $availableYears[] = $y;
        }

        // ✅ Membership revenue (total for selected year, scoped by optional branch/division)
        $start = Carbon::create($selectedYear, 1, 1)->startOfDay();
        $end   = Carbon::create($selectedYear, 12, 31)->endOfDay();


        // ✅ Quarterly summaries (still national → branch; no branch filter here)
        $branchMembershipSummaries = $this->financialStatsService
            ->getBranchMembershipSummariesByQuarter($selectedYear);

        // 📈 NEW: trend dataset (quarters over trailing N years)
        $trendDataset = $this->financialStatsService
            ->getMembershipRevenueTrendForChart($years, $branchId, $divisionId);

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($branchMembershipSummaries, 'Branch', 'branch_name');
        }

        return view('reports.financial.national', [
            'trendOptions'              => $trendOptions,
            'selectedTrendKey'          => $selectedTrendKey,
            'trendYears'                => $years, // 👈 add this if you want
            'selectedYear'              => $selectedYear,
            'availableYears'            => $availableYears,
            'branchMembershipSummaries' => $branchMembershipSummaries,
            'trendDataset'              => $trendDataset,
            'branchId'                  => $branchId,
            'divisionId'                => $divisionId,
        ]);

    }

    /**
     * Streams $rows as a flat CSV (area name, Q1-Q4, Total) — same
     * BOM + sep=, + fputcsv pattern as MemberReportController::exportCsv().
     * $nameField is 'branch_name' at national level, 'division_name' at
     * branch level.
     */
    private function exportCsv($rows, string $areaLabel, string $nameField, ?string $scopeName = null): StreamedResponse
    {
        $date = now()->toDateString();
        $scopeSlug = $scopeName ? \Illuminate\Support\Str::slug($scopeName) : 'national';
        $filename = "financial-trends-report-{$scopeSlug}-{$date}.csv";

        return response()->streamDownload(function () use ($rows, $areaLabel, $nameField) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, "sep=,\r\n");

            fputcsv($out, [$areaLabel, 'Q1', 'Q2', 'Q3', 'Q4', 'Total']);

            foreach ($rows as $row) {
                fputcsv($out, [$row->{$nameField}, $row->q1, $row->q2, $row->q3, $row->q4, $row->total]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }


    /**
     * Branch – overview by division
     */
    public function branch(Request $request, Branch $branch)
    {
        // 🔢 Trend range selector: 2, 4, 6, 8 years
        $trendOptions = [
            '2_years' => 2,
            '4_years' => 4,
            '6_years' => 6,
            '8_years' => 8,
        ];

        $selectedTrendKey = $request->input('trend_years', '2_years');
        $years            = $trendOptions[$selectedTrendKey] ?? 2;

        // For branch level we allow optional division filter (for future division drilldown)
        $divisionId = $request->filled('division_id')
            ? (int) $request->input('division_id')
            : null;

        // Year selector for totals + quarterly table
        $selectedYear = (int) $request->input('year', now()->year);

        $availableYears = [];
        for ($y = now()->year; $y >= now()->year - 7; $y--) {
            $availableYears[] = $y;
        }

        $start = Carbon::create($selectedYear, 1, 1)->startOfDay();
        $end   = Carbon::create($selectedYear, 12, 31)->endOfDay();



        // Quarterly summaries: here per division within the branch
        // (You’ll create this in the service if it doesn’t exist yet)
        $divisionMembershipSummaries = $this->financialStatsService
            ->getDivisionMembershipSummariesByQuarter($selectedYear, $branch->id);

        // Trend dataset for the chart for this branch
        $trendDataset = $this->financialStatsService
            ->getMembershipRevenueTrendForChart($years, $branch->id, $divisionId);

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($divisionMembershipSummaries, 'Division', 'division_name', $branch->name);
        }

        return view('reports.financial.branch', [
            'branch'                     => $branch,
            'trendOptions'               => $trendOptions,
            'selectedTrendKey'           => $selectedTrendKey,
            'trendYears'                 => $years,
            'selectedYear'               => $selectedYear,
            'availableYears'             => $availableYears,
            'divisionMembershipSummaries'=> $divisionMembershipSummaries,
            'trendDataset'               => $trendDataset,
            'divisionId'                 => $divisionId,
        ]);
    }

    /**
     * Division – overview by Red Cross unit
     */

}
