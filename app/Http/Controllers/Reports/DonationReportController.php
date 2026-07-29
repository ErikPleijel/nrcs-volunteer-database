<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Division;
use App\Models\Donation;
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

    /**
     * Breakdown: the individual donations behind one Quarterly Donations
     * Summary cell (branch OR division + year + quarter + cash/in-kind).
     * Reproduces the exact same filter predicate as
     * DonationStatsService::getBranchDonationQuarterlySummary() /
     * getDivisionDonationQuarterlySummary() so this list's total always
     * matches the summary cell that was clicked.
     *
     * $level distinguishes a branch-level row (national summary, one row
     * per branch) from a division-level row (branch view, one row per
     * division) — mirrors FinancialOverviewReportController::breakdown()'s
     * level param exactly. The 'branch_id' param name is kept (not
     * renamed) for signature stability; its value is interpreted as a
     * division_id when $level === 'division'.
     */
    public function breakdown(Request $request)
    {
        $id       = (int) $request->input('branch_id');
        $level    = $request->input('level', 'branch') === 'division' ? 'division' : 'branch';
        $year     = (int) $request->input('year');
        $quarter  = (int) $request->input('quarter');
        $type     = $request->input('type') === 'in-kind' ? 'in-kind' : 'cash';

        $area = $level === 'division'
            ? Division::findOrFail($id)
            : Branch::findOrFail($id);

        // Hierarchy-aware scope check — matches
        // FinancialOverviewReportController::breakdown() exactly. Branch-level
        // viewers may drill into their own branch OR any division within it
        // (checked via that division's own branch_id); division-level viewers
        // may only drill into their own division, never a branch-level
        // breakdown even for their own enclosing branch; national-level
        // viewers (including observer_national_level, per
        // User::NATIONAL_ROLES) are unrestricted. getScopedId() (not
        // getScopedBranchId()) is used deliberately, per the same correction
        // applied to Financial's breakdown().
        $accessLevel = auth()->user()->getAccessLevel();
        $scopedId = auth()->user()->getScopedId();

        $inScope = match ($accessLevel) {
            'national' => true,
            'branch'   => ($level === 'branch' && $id === $scopedId)
                || ($level === 'division' && $area->branch_id === $scopedId),
            'division' => $level === 'division' && $id === $scopedId,
            default    => false,
        };

        if (! $inScope) {
            abort(403);
        }

        $donations = Donation::query()
            ->where('is_deleted', 0)
            ->where('approval_status', 'approved')
            ->whereYear('date_donation', $year)
            ->whereRaw('QUARTER(date_donation) = ?', [$quarter])
            ->where('in_kind_donation', $type === 'in-kind' ? 1 : 0)
            ->when($level === 'division', fn ($q) => $q->where('division_id', $id))
            ->when($level === 'branch', fn ($q) => $q->where('branch_id', $id))
            ->with(['user', 'organisation'])
            ->orderBy('date_donation', 'desc')
            ->get();

        // Matches the summary cell itself: cash is a currency sum, in-kind is
        // a plain count of donations (never a summed "amount" — in-kind items
        // aren't fungible, so the report only ever counts them).
        $total = $type === 'in-kind'
            ? $donations->count()
            : $donations->sum('amount');

        return view('reports.donations.breakdown', [
            'area'       => $area,
            'level'      => $level,
            'year'       => $year,
            'quarter'    => $quarter,
            'type'       => $type,
            'donations'  => $donations,
            'total'      => $total,
        ]);
    }

}
