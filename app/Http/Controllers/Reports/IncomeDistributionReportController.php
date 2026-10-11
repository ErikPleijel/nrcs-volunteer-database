<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\FeeDistributionSettings;
use App\Services\Reports\IncomeDistributionCsv;
use App\Services\Reports\IncomeDistributionStatsService;
use App\Support\Naira;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Income Distribution report: how a quarter's membership income is shared
 * between HQ, branches and divisions. Tab 1 covers online (Paystack)
 * payments; Tab 2 manual payments, collected by the branches.
 *
 * Who sees what: view_income_distribution (national DB administrators)
 * sees both tabs for every branch; view_income_distribution_branch (branch
 * secretary, branch DB administrator) sees Tab 2 only, for their own branch.
 *
 * Every figure is a sum of shares stored on the payments when they were
 * fulfilled or approved, so a closed quarter never changes. The page checks
 * that the branch rows add up to the headline and the three shares to the
 * net, and shows a red warning if they ever do not. ?export=csv downloads
 * Tab 1 at either level as CSV (IncomeDistributionCsv).
 */
class IncomeDistributionReportController extends Controller
{
    public const TABS = ['online', 'manual'];

    public function index(Request $request, IncomeDistributionStatsService $stats, IncomeDistributionCsv $csv)
    {
        $user = $request->user();
        $isNational = $user->can('view_income_distribution');

        // Branch users: Tab 2, own branch only.
        $branchScope = null;
        if (! $isNational) {
            abort_unless($user->can('view_income_distribution_branch'), 403);
            $branchScope = $user->getScopedBranchId();
            abort_if($branchScope === null, 403);
        }

        $activeTab = $isNational
            ? (in_array($request->input('tab'), self::TABS, true) ? $request->input('tab') : 'online')
            : 'manual';

        if ($activeTab === 'manual') {
            return $this->manualTab($request, $stats->manual(), $isNational, $branchScope);
        }

        $data = ['activeTab' => $activeTab, 'isNational' => true] + $this->quarterData($request, $stats);
        $selectedQuarter = $data['selectedQuarter'];

        [$start, $end] = IncomeDistributionStatsService::quarterRange($selectedQuarter);

        $headline = $stats->headline($start, $end);
        $rows = $stats->branchRows($start, $end);
        $percentageSets = $stats->percentageSets($start, $end);
        $totals = $this->totals($rows);

        $data += [
            'headline' => $headline,
            'rows' => $rows,
            'totals' => $totals,
            'percentageSets' => $percentageSets,
            'percentagesChanged' => collect($percentageSets)->contains(fn (array $sets) => count($sets) > 1),
            'integrityProblems' => $this->integrityProblems($headline, $rows, $totals, [
                'count' => 'Payments',
                'paid' => 'Paid by members',
                'fees' => 'Paystack charges',
                'net' => 'Net received',
            ]),
        ];

        if ($request->input('export') === 'csv') {
            return $csv->branches($data);
        }

        return view('reports.income-distribution.index', $data);
    }

    /**
     * Tab 2: manual payments, which the branches collect themselves. Same
     * layout as Tab 1, without Paystack charges (net = amount collected),
     * plus the go-live notes: quarters before MANUAL_GO_LIVE_DATE are
     * estimates; the quarter it falls in shows both parts separately.
     */
    private function manualTab(Request $request, IncomeDistributionStatsService $stats, bool $isNational, ?int $branchScope)
    {
        $data = [
            'activeTab' => 'manual',
            'isNational' => $isNational,
            'branchScope' => $branchScope,
            'branchScopeLabel' => $branchScope === null ? null : $stats->branchLabel($branchScope),
        ] + $this->quarterData($request, $stats);

        [$start, $end] = IncomeDistributionStatsService::quarterRange($data['selectedQuarter']);

        $headline = $stats->headline($start, $end, $branchScope);
        $rows = $stats->branchRows($start, $end, $branchScope);
        $percentageSets = $stats->percentageSets($start, $end, $branchScope);
        $totals = $this->totals($rows);

        $goLive = FeeDistributionSettings::manualGoLiveDate();
        $goLivePhase = IncomeDistributionStatsService::goLivePhase($start, $end, $goLive);

        $transitionParts = null;
        if ($goLivePhase === 'transition') {
            $goLiveMoment = Carbon::instance($goLive);
            $transitionParts = [
                'before' => $stats->headline($start, $goLiveMoment, $branchScope),
                'from' => $stats->headline($goLiveMoment, $end, $branchScope),
            ];
        }

        $integrityProblems = $this->integrityProblems($headline, $rows, $totals, [
            'count' => 'Payments',
            'net' => 'Collected',
        ]);
        if ($transitionParts !== null && $transitionParts['before']['net'] + $transitionParts['from']['net'] !== $headline['net']) {
            $integrityProblems[] = 'The amounts before and from go-live do not add up to the total collected.';
        }

        $data += [
            'headline' => $headline,
            'rows' => $rows,
            'totals' => $totals,
            'percentageSets' => $percentageSets,
            'percentagesChanged' => collect($percentageSets)->contains(fn (array $sets) => count($sets) > 1),
            'integrityProblems' => $integrityProblems,
            'goLiveDate' => $goLive,
            'goLivePhase' => $goLivePhase,
            'transitionParts' => $transitionParts,
            // .env mistakes and missing shares are for national admins to fix.
            'goLiveDateInvalid' => $isNational && FeeDistributionSettings::manualGoLiveDateIsInvalid(),
            'unsplitPayments' => $isNational ? $stats->unsplitManualPayments() : 0,
        ];

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

    /** Column totals over the branch rows. */
    private function totals(Collection $rows): array
    {
        $totals = [];
        foreach (['count', 'paid', 'fees', 'net', 'hq', 'branch', 'division', 'organisation_net'] as $column) {
            $totals[$column] = $rows->sum($column);
        }

        return $totals;
    }

    /**
     * Should never happen: every figure comes from the same payments.
     *
     * @param  array<string, string>  $checked  headline column => label
     * @return list<string>
     */
    private function integrityProblems(array $headline, Collection $rows, array $totals, array $checked): array
    {
        $problems = [];
        foreach ($checked as $column => $label) {
            if ($totals[$column] !== $headline[$column]) {
                $problems[] = "{$label}: the table total does not match the headline.";
            }
        }
        if ($totals['net'] !== $totals['hq'] + $totals['branch'] + $totals['division']) {
            $problems[] = 'The HQ, branch and division shares do not add up to the net received.';
        }
        foreach ($rows as $row) {
            if ($row['net'] !== $row['hq'] + $row['branch'] + $row['division']) {
                $problems[] = "{$row['label']}: the shares do not add up to the net received.";
            }
        }

        return $problems;
    }
}
