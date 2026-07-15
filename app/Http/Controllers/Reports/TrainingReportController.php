<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Reports\TrainingStatsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrainingReportController extends Controller
{
    protected TrainingStatsService $trainingStatsService;

    public function __construct(TrainingStatsService $trainingStatsService)
    {
        $this->trainingStatsService = $trainingStatsService;
    }

    /**
     * National Training Report
     */
    public function national(Request $request)
    {
        // 🔢 Trend range selector in YEARS → will be converted to months
        $trendOptions = [
            '2_years' => 2,
            '4_years' => 4,
            '6_years' => 6,
            '8_years' => 8,
        ];

        $selectedTrendKey = $request->input('trend_years', '2_years');
        $years            = $trendOptions[$selectedTrendKey] ?? 2;
        $months           = $years * 12;

        // Year selector for quarterly table
        $selectedYear = (int) $request->input('year', now()->year);

        $availableYears = [];
        for ($y = now()->year; $y >= now()->year - 7; $y--) {
            $availableYears[] = $y;
        }

        // Existing national-level quarterly summary
        $quarterlySummary = $this->trainingStatsService
            ->getTrainingQuarterlySummary($selectedYear);

        // 🆕 Branch-level quarterly summary (for the big table)
        $branchQuarterlySummaries = $this->trainingStatsService
            ->getBranchTrainingQuarterlySummary($selectedYear);

        // Trend + headline stat (unchanged)
        $trendRaw = $this->trainingStatsService
            ->getTrainingTrendForChart($months);

        $trendDataset = [
            'labels' => $trendRaw['labels'],
            'series' => [
                [
                    'label'  => 'First Aid',
                    'values' => $trendRaw['first_aid'],
                ],
                [
                    'label'  => 'Other Trainings',
                    'values' => $trendRaw['other'],
                ],
            ],
        ];


        $firstAidLast12Months = $this->trainingStatsService
            ->getFirstAidTrainingsLast12Months();

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($branchQuarterlySummaries, 'Branch', 'branch_name');
        }

        return view('reports.trainings.national', [
            'trendOptions'              => $trendOptions,
            'selectedTrendKey'          => $selectedTrendKey,
            'trendYears'                => $years,
            'trendMonths'               => $months,
            'selectedYear'              => $selectedYear,
            'availableYears'            => $availableYears,
            'quarterlySummary'          => $quarterlySummary,
            'branchQuarterlySummaries'  => $branchQuarterlySummaries,
            'trendDataset'              => $trendDataset,
            'firstAidLast12Months'      => $firstAidLast12Months,
        ]);
    }

    /**
     * Streams $rows as a flattened 10-column CSV (area name, Q1-Q4 First
     * Aid/Other, Total) — mirrors the table's two-tier <thead> (rowspan
     * Branch/Total, colspan Q1-Q4 → FA/Other) flattened into one header
     * row, left to right, same order as on screen. Same BOM + sep=, +
     * fputcsv pattern as MemberReportController::exportCsv(). $nameField
     * is 'branch_name' at national level, 'division_name' at branch level.
     */
    private function exportCsv($rows, string $areaLabel, string $nameField, ?string $scopeName = null): StreamedResponse
    {
        $date = now()->toDateString();
        $scopeSlug = $scopeName ? \Illuminate\Support\Str::slug($scopeName) : 'national';
        $filename = "training-report-{$scopeSlug}-{$date}.csv";

        return response()->streamDownload(function () use ($rows, $areaLabel, $nameField) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, "sep=,\r\n");

            fputcsv($out, [
                $areaLabel,
                'Q1 First Aid', 'Q1 Other',
                'Q2 First Aid', 'Q2 Other',
                'Q3 First Aid', 'Q3 Other',
                'Q4 First Aid', 'Q4 Other',
                'Total',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->{$nameField},
                    $row->q1_first_aid, $row->q1_other,
                    $row->q2_first_aid, $row->q2_other,
                    $row->q3_first_aid, $row->q3_other,
                    $row->q4_first_aid, $row->q4_other,
                    $row->total_all,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Branch Training Report
     */
    public function branch(Request $request, Branch $branch)
    {
        // 🔢 Trend range selector in YEARS → will be converted to months
        $trendOptions = [
            '2_years' => 2,
            '4_years' => 4,
            '6_years' => 6,
            '8_years' => 8,
        ];

        $selectedTrendKey = $request->input('trend_years', '2_years');
        $years            = $trendOptions[$selectedTrendKey] ?? 2;
        $months           = $years * 12;

        // Year selector for quarterly table
        $selectedYear = (int) $request->input('year', now()->year);

        $availableYears = [];
        for ($y = now()->year; $y >= now()->year - 7; $y--) {
            $availableYears[] = $y;
        }

        // 📊 Quarterly summary (First Aid vs Other) – this branch
        $quarterlySummary = $this->trainingStatsService
            ->getTrainingQuarterlySummary($selectedYear, $branch->id);

        // 📈 Trend dataset for the chart – this branch
        $trendRaw = $this->trainingStatsService
            ->getTrainingTrendForChart($months, $branch->id);

        $divisionQuarterlySummaries = $this->trainingStatsService
            ->getDivisionTrainingQuarterlySummary($selectedYear, $branch->id);

        $trendDataset = [
            'labels' => $trendRaw['labels'],
            'series' => [
                [
                    'label'  => 'First Aid',
                    'values' => $trendRaw['first_aid'],
                ],
                [
                    'label'  => 'Other Trainings',
                    'values' => $trendRaw['other'],
                ],
            ],
        ];


        // 🔹 Headline stat – First Aid trainings last 12 months (this branch)
        $firstAidLast12Months = $this->trainingStatsService
            ->getFirstAidTrainingsLast12Months($branch->id);

        if ($request->input('export') === 'csv') {
            return $this->exportCsv($divisionQuarterlySummaries, 'Division', 'division_name', $branch->name);
        }

        return view('reports.trainings.branch', [
            'branch'                => $branch,
            'trendOptions'          => $trendOptions,
            'selectedTrendKey'      => $selectedTrendKey,
            'trendYears'            => $years,
            'trendMonths'           => $months,
            'selectedYear'          => $selectedYear,
            'availableYears'        => $availableYears,
            'quarterlySummary'      => $quarterlySummary,
            'quarterlySummary'             => $quarterlySummary,           // for totals
            'divisionQuarterlySummaries'   => $divisionQuarterlySummaries, // for table
            'trendDataset'          => $trendDataset,
            'firstAidLast12Months'  => $firstAidLast12Months,
        ]);
    }
}
