<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\IncomeDistributionCsv;
use App\Services\Reports\IncomeDistributionStatsService;
use App\Support\Naira;
use Illuminate\Http\Request;

/**
 * Income Distribution report (national DB administrators, permission
 * view_income_distribution): how a quarter's online (Paystack) membership
 * income is shared between HQ, branches and divisions. Tab 1 covers
 * Paystack; Tab 2 (manual payments at branch level) is a placeholder.
 *
 * Every figure is a sum of shares stored on the payments when they were
 * fulfilled, so a closed quarter never changes. The page checks that the
 * branch rows add up to the headline and the three shares to the net, and
 * shows a red warning if they ever do not. ?export=csv downloads either
 * level as CSV (IncomeDistributionCsv).
 */
class IncomeDistributionReportController extends Controller
{
    public const TABS = ['online', 'manual'];

    public function index(Request $request, IncomeDistributionStatsService $stats, IncomeDistributionCsv $csv)
    {
        $activeTab = in_array($request->input('tab'), self::TABS, true) ? $request->input('tab') : 'online';

        $data = ['activeTab' => $activeTab] + $this->quarterData($request, $stats);
        $selectedQuarter = $data['selectedQuarter'];

        if ($activeTab !== 'online') {
            return view('reports.income-distribution.index', $data);
        }

        [$start, $end] = IncomeDistributionStatsService::quarterRange($selectedQuarter);

        $headline = $stats->headline($start, $end);
        $rows = $stats->branchRows($start, $end);
        $percentageSets = $stats->percentageSets($start, $end);

        $totals = [];
        foreach (['count', 'paid', 'fees', 'net', 'hq', 'branch', 'division', 'organisation_net'] as $column) {
            $totals[$column] = $rows->sum($column);
        }

        // Should never happen: every figure comes from the same payments.
        $integrityProblems = [];
        foreach (['count' => 'Payments', 'paid' => 'Paid by members', 'fees' => 'Paystack charges', 'net' => 'Net received'] as $column => $label) {
            if ($totals[$column] !== $headline[$column]) {
                $integrityProblems[] = "{$label}: the table total does not match the headline.";
            }
        }
        if ($totals['net'] !== $totals['hq'] + $totals['branch'] + $totals['division']) {
            $integrityProblems[] = 'The HQ, branch and division shares do not add up to the net received.';
        }
        foreach ($rows as $row) {
            if ($row['net'] !== $row['hq'] + $row['branch'] + $row['division']) {
                $integrityProblems[] = "{$row['label']}: the shares do not add up to the net received.";
            }
        }

        $data += [
            'headline' => $headline,
            'rows' => $rows,
            'totals' => $totals,
            'percentageSets' => $percentageSets,
            'percentagesChanged' => collect($percentageSets)->contains(fn (array $sets) => count($sets) > 1),
            'integrityProblems' => $integrityProblems,
        ];

        if ($request->input('export') === 'csv') {
            return $csv->branches($data);
        }

        return view('reports.income-distribution.index', $data);
    }

    /**
     * Division level: every division that receives a share in the quarter
     * (optionally only those paid through one branch), for payouts. Bank
     * details per division are planned as an extra column here later.
     */
    public function divisions(Request $request, IncomeDistributionStatsService $stats, IncomeDistributionCsv $csv)
    {
        $data = $this->quarterData($request, $stats);
        [$start, $end] = IncomeDistributionStatsService::quarterRange($data['selectedQuarter']);

        // ?branch= is a payment's stored branch id, so a branch that no
        // longer exists can still be filtered on. Anything that is not a
        // positive whole number means "all branches".
        $branchFilter = filter_var($request->query('branch'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;

        $rows = $stats->divisionRows($start, $end, $branchFilter);

        $totals = [
            'count' => $rows->sum('count'),
            'net' => $rows->sum('net'),
            'division' => $rows->sum('division_share'),
        ];

        // Should never happen: every division share belongs to a division
        // row. Compared with the same figure Tab 1 shows (the quarter's, or
        // the branch row's, division share total).
        $expectedDivisionShare = $stats->headline($start, $end, $branchFilter)['division'];
        $integrityProblems = $totals['division'] === $expectedDivisionShare ? [] : [
            sprintf(
                'The division shares here (%s) do not match the division share total on the branch level (%s).',
                Naira::format($totals['division']),
                Naira::format($expectedDivisionShare)
            ),
        ];

        $data += [
            'branchFilter' => $branchFilter,
            'branchFilterLabel' => $branchFilter === null ? null : $stats->branchLabel($branchFilter),
            'rows' => $rows,
            'totals' => $totals,
            'integrityProblems' => $integrityProblems,
        ];

        if ($request->input('export') === 'csv') {
            return $csv->divisions($data);
        }

        return view('reports.income-distribution.divisions', $data);
    }

    /**
     * The quarter selector shared by both levels: options, the selected
     * quarter (an unknown or malformed ?quarter= falls back to the default
     * rather than erroring — it is a query param, not validated user input)
     * and whether it is still in progress.
     */
    private function quarterData(Request $request, IncomeDistributionStatsService $stats): array
    {
        $quarterOptions = $stats->quarterOptions();
        $selectedQuarter = (string) $request->input('quarter', '');

        if (! array_key_exists($selectedQuarter, $quarterOptions)) {
            $selectedQuarter = $stats->defaultQuarter();
        }

        return [
            'quarterOptions' => $quarterOptions,
            'selectedQuarter' => $selectedQuarter,
            'quarterLabel' => IncomeDistributionStatsService::quarterLabel($selectedQuarter),
            'quarterInProgress' => IncomeDistributionStatsService::isInProgress($selectedQuarter),
        ];
    }
}
