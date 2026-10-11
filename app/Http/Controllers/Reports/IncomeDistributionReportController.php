<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\IncomeDistributionStatsService;
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
 * shows a red warning if they ever do not.
 */
class IncomeDistributionReportController extends Controller
{
    public const TABS = ['online', 'manual'];

    public function index(Request $request, IncomeDistributionStatsService $stats)
    {
        $activeTab = in_array($request->input('tab'), self::TABS, true) ? $request->input('tab') : 'online';

        $quarterOptions = $stats->quarterOptions();
        $defaultQuarter = $stats->defaultQuarter();
        $selectedQuarter = (string) $request->input('quarter', $defaultQuarter);

        // An unknown or malformed quarter falls back to the default rather
        // than erroring — this is a query param, not validated user input.
        if (! array_key_exists($selectedQuarter, $quarterOptions)) {
            $selectedQuarter = $defaultQuarter;
        }

        $data = [
            'activeTab' => $activeTab,
            'quarterOptions' => $quarterOptions,
            'selectedQuarter' => $selectedQuarter,
            'quarterLabel' => $quarterOptions[$selectedQuarter] ?? str_replace('-', ' ', $selectedQuarter),
        ];

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

        return view('reports.income-distribution.index', $data + [
            'headline' => $headline,
            'rows' => $rows,
            'totals' => $totals,
            'percentageSets' => $percentageSets,
            'percentagesChanged' => collect($percentageSets)->contains(fn (array $sets) => count($sets) > 1),
            'integrityProblems' => $integrityProblems,
        ]);
    }
}
