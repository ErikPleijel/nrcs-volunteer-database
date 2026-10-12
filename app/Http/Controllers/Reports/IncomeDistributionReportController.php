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
 * payments; Tab 2 manual payments, collected by the branches. Both have a
 * branch level (index) and a division level (divisions, ?channel=manual
 * for Tab 2).
 *
 * Who sees what: view_income_distribution (national DB administrators)
 * sees every branch; view_income_distribution_branch (branch secretary,
 * branch DB administrator) sees both tabs and both levels for their own
 * branch only — any other branch is refused.
 *
 * Every figure is a sum of shares stored on the payments when they were
 * fulfilled or approved, so a closed quarter never changes. The page checks
 * that the branch rows add up to the headline and the three shares to the
 * net, and shows a red warning if they ever do not. ?export=csv downloads
 * either tab at either level as CSV (IncomeDistributionCsv).
 */
class IncomeDistributionReportController extends Controller
{
    public const TABS = ['online', 'manual'];

    public function index(Request $request, IncomeDistributionStatsService $stats, IncomeDistributionCsv $csv)
    {
        [$isNational, $branchScope] = $this->scope($request);

        $activeTab = in_array($request->input('tab'), self::TABS, true) ? $request->input('tab') : 'online';
        $isManual = $activeTab === 'manual';
        if ($isManual) {
            $stats = $stats->manual();
        }

        $data = ['activeTab' => $activeTab] + $this->commonData($request, $stats, $isNational, $branchScope);
        [$start, $end] = IncomeDistributionStatsService::quarterRange($data['selectedQuarter']);

        $headline = $stats->headline($start, $end, $branchScope);
        $rows = $stats->branchRows($start, $end, $branchScope);
        $percentageSets = $stats->percentageSets($start, $end, $branchScope);
        $totals = $this->totals($rows);

        $integrityProblems = $this->integrityProblems($headline, $rows, $totals, $isManual
            ? ['count' => 'Payments', 'net' => 'Collected']
            : ['count' => 'Payments', 'paid' => 'Paid by members', 'fees' => 'Paystack charges', 'net' => 'Net received']);

        $data += [
            'headline' => $headline,
            'rows' => $rows,
            'totals' => $totals,
            'percentageSets' => $percentageSets,
            'percentagesChanged' => collect($percentageSets)->contains(fn (array $sets) => count($sets) > 1),
        ];

        if ($isManual) {
            $data += $this->goLiveData($start, $end, $isNational);

            // The quarter the go-live date falls in: its two parts, shown
            // under the headline total.
            $data['transitionParts'] = null;
            if ($data['goLivePhase'] === 'transition') {
                $goLive = Carbon::instance($data['goLiveDate']);
                $data['transitionParts'] = [
                    'before' => $stats->headline($start, $goLive, $branchScope),
                    'from' => $stats->headline($goLive, $end, $branchScope),
                ];
                if ($data['transitionParts']['before']['net'] + $data['transitionParts']['from']['net'] !== $headline['net']) {
                    $integrityProblems[] = 'The amounts before and from go-live do not add up to the total collected.';
                }
            }

            // Missing shares are for national admins to have fixed.
            $data['unsplitPayments'] = $isNational ? $stats->unsplitManualPayments() : 0;
        }

        $data['integrityProblems'] = $integrityProblems;

        if ($request->input('export') === 'csv') {
            return $csv->branches($data);
        }

        return view('reports.income-distribution.index', $data);
    }

    /**
     * Division level: every division that receives a share in the quarter
     * (optionally only those paid through one branch; always only the own
     * branch for branch users), for payouts. Bank details per division are
     * planned as an extra column here later.
     */
    public function divisions(Request $request, IncomeDistributionStatsService $stats, IncomeDistributionCsv $csv)
    {
        [$isNational, $branchScope] = $this->scope($request);

        $channel = $request->query('channel') === 'manual' ? 'manual' : 'online';
        $isManual = $channel === 'manual';
        if ($isManual) {
            $stats = $stats->manual();
        }

        // ?branch= is a payment's stored branch id, so a branch that no
        // longer exists can still be filtered on. Anything that is not a
        // positive whole number means "all branches".
        $branchFilter = filter_var($request->query('branch'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        if ($branchScope !== null) {
            abort_if($branchFilter !== null && $branchFilter !== $branchScope, 403);
            $branchFilter = $branchScope;
        }

        $data = ['channel' => $channel] + $this->commonData($request, $stats, $isNational, $branchScope);
        [$start, $end] = IncomeDistributionStatsService::quarterRange($data['selectedQuarter']);

        $rows = $stats->divisionRows($start, $end, $branchFilter);

        $totals = [
            'count' => $rows->sum('count'),
            'net' => $rows->sum('net'),
            'division' => $rows->sum('division_share'),
        ];

        // Should never happen: every division share belongs to a division
        // row. Compared with the same figure the branch level shows (the
        // quarter's, or the branch row's, division share total).
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

        if ($isManual) {
            $data += $this->goLiveData($start, $end, $isNational);
        }

        if ($request->input('export') === 'csv') {
            return $csv->divisions($data);
        }

        return view('reports.income-distribution.divisions', $data);
    }

    /**
     * [is national, own branch id or null]. National admins see every
     * branch; branch users their own; anyone else, or a branch user with
     * no branch, is refused.
     *
     * @return array{0: bool, 1: int|null}
     */
    private function scope(Request $request): array
    {
        $user = $request->user();

        if ($user->can('view_income_distribution')) {
            return [true, null];
        }

        abort_unless($user->can('view_income_distribution_branch'), 403);
        $branchId = $user->getScopedBranchId();
        abort_if($branchId === null, 403);

        return [false, (int) $branchId];
    }

    /**
     * What both levels of both tabs need: who is looking, the quarter
     * selector (an unknown or malformed ?quarter= falls back to the default
     * rather than erroring — it is a query param, not validated user input)
     * and, for national admins, the payments dated before the first quarter.
     */
    private function commonData(Request $request, IncomeDistributionStatsService $stats, bool $isNational, ?int $branchScope): array
    {
        $quarterOptions = $stats->quarterOptions();
        $selectedQuarter = (string) $request->input('quarter', '');

        if (! array_key_exists($selectedQuarter, $quarterOptions)) {
            $selectedQuarter = $stats->defaultQuarter();
        }

        return [
            'isNational' => $isNational,
            'branchScope' => $branchScope,
            'branchScopeLabel' => $branchScope === null ? null : $stats->branchLabel($branchScope),
            'quarterOptions' => $quarterOptions,
            'selectedQuarter' => $selectedQuarter,
            'quarterLabel' => IncomeDistributionStatsService::quarterLabel($selectedQuarter),
            'quarterInProgress' => IncomeDistributionStatsService::isInProgress($selectedQuarter),
            'paymentsBeforeFirstQuarter' => $isNational ? $stats->countBeforeFirstQuarter() : 0,
        ];
    }

    /**
     * Manual payments: where the quarter stands against MANUAL_GO_LIVE_DATE
     * (before / transition / live), and — for national admins only — whether
     * that .env value is set but invalid.
     */
    private function goLiveData(Carbon $start, Carbon $end, bool $isNational): array
    {
        $goLive = FeeDistributionSettings::manualGoLiveDate();

        return [
            'goLiveDate' => $goLive,
            'goLivePhase' => IncomeDistributionStatsService::goLivePhase($start, $end, $goLive),
            'goLiveDateInvalid' => $isNational && FeeDistributionSettings::manualGoLiveDateIsInvalid(),
        ];
    }

    /** Column totals over the branch rows. */
    private function totals(Collection $rows): array
    {
        $totals = [];
        foreach (['count', 'paid', 'fees', 'net', 'hq', 'branch', 'division'] as $column) {
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
