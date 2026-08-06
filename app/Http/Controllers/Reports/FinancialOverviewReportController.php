<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialOverviewReportController extends Controller
{
    public function index(Request $request)
    {
        $activeTab   = $request->input('tab', 'payments');
        $currentYear = now()->year;

        // Year options — both tabs are full-year (Q1-Q4) views, sharing one
        // year selector. (Fee Breakdown used to have its own separate
        // quarter selector; that's gone now — see breakdown()/
        // parseQuarterRange() for the unrelated per-drill-down quarter param
        // those still use, which this page-level selector never fed.)
        // 2016 is a fixed historical floor (earliest data this report
        // covers); the upper bound is always now()->year, never a literal,
        // so the list grows on its own every January. Descending
        // (newest-first) to match the convention used by every other
        // year selector in this codebase (e.g. TrainingReportController).
        $yearOptions  = range($currentYear, 2016);
        $defaultYear  = $currentYear;
        $selectedYear = (int) $request->input('year', $defaultYear);
        $yearStart    = Carbon::create($selectedYear, 1, 1)->startOfDay();
        $yearEnd      = Carbon::create($selectedYear, 12, 31)->endOfDay();

        $selectedScope     = $request->input('scope', 'national');
        $isNational        = $selectedScope === 'national';
        $scopeBranchId     = !$isNational ? (int) $selectedScope : null;

        $branches = Branch::orderBy('name')->get();

        $rowType = $isNational ? 'branch' : 'division';
        $rows    = $isNational
            ? $branches
            : Division::where('branch_id', $scopeBranchId)->orderBy('name')->get();

        $selectedBranchName = !$isNational
            ? $branches->firstWhere('id', $scopeBranchId)?->name
            : null;

        // Tab 1 — Payments: full-year (Q1-Q4) breakdown per branch/division,
        // each quarter split into member/volunteer/organisation amounts.
        //
        // Single grouped query (GROUP BY row + QUARTER(payment_date)) rather
        // than the old per-row × per-category × per-quarter loop — mirrors
        // DonationStatsService::getBranchDonationQuarterlySummary()'s exact
        // shape (conditional SUM(CASE WHEN...) columns, grouped by row +
        // QUARTER(date), reshaped in PHP via groupBy()->map()). Built on
        // DB::table() rather than MembershipPayment::query(), so — unlike
        // the old per-row Eloquent queries — it does NOT get
        // approval_status='approved' for free from the ApprovedScope global
        // scope; that filter is added explicitly below instead (same
        // reasoning as getBranchDonationQuarterlySummary()'s own explicit
        // approval_status filter).
        $paymentsData = [];
        if ($activeTab === 'payments') {
            $joinTable = $rowType === 'branch' ? 'branches' : 'divisions';
            $idColumn  = $rowType === 'branch' ? 'branch_id' : 'division_id';

            $query = DB::table('membership_payments')
                ->join($joinTable, "membership_payments.{$idColumn}", '=', "{$joinTable}.id")
                ->join('membership_fees', 'membership_payments.membership_fee_id', '=', 'membership_fees.id')
                ->where('membership_payments.is_deleted', false)
                ->where('membership_payments.approval_status', 'approved') // Phase 2: only approved records are real — explicit because DB::table() bypasses ApprovedScope
                ->whereBetween('membership_payments.payment_date', [$yearStart, $yearEnd]);

            if (!$isNational && $scopeBranchId) {
                $query->where('membership_payments.branch_id', $scopeBranchId);
            }

            $groupedRows = $query
                ->select(
                    "{$joinTable}.id as row_id",
                    "{$joinTable}.name as row_name",
                    DB::raw('QUARTER(membership_payments.payment_date) as quarter'),
                    DB::raw('
                        SUM(CASE WHEN membership_payments.organisation_id IS NULL AND membership_fees.is_volunteer_fee = 0 THEN membership_fees.amount ELSE 0 END) as member_amount
                    '),
                    DB::raw('
                        SUM(CASE WHEN membership_payments.organisation_id IS NULL AND membership_fees.is_volunteer_fee = 1 THEN membership_fees.amount ELSE 0 END) as volunteer_amount
                    '),
                    DB::raw('
                        SUM(CASE WHEN membership_payments.organisation_id IS NOT NULL THEN membership_fees.amount ELSE 0 END) as org_amount
                    ')
                )
                ->groupBy("{$joinTable}.id", "{$joinTable}.name", DB::raw('QUARTER(membership_payments.payment_date)'))
                ->get()
                ->groupBy('row_id');

            // Iterate $rows (not $groupedRows) so every branch/division still
            // appears with zero-filled quarters even with no payments this
            // year — matches the old loop's behaviour of always showing every
            // row regardless of data.
            $paymentsData = $rows->map(function ($rowItem) use ($groupedRows, $rowType) {
                $items = $groupedRows->get($rowItem->id, collect());

                $quarters = [
                    1 => ['member' => 0.0, 'volunteer' => 0.0, 'org' => 0.0],
                    2 => ['member' => 0.0, 'volunteer' => 0.0, 'org' => 0.0],
                    3 => ['member' => 0.0, 'volunteer' => 0.0, 'org' => 0.0],
                    4 => ['member' => 0.0, 'volunteer' => 0.0, 'org' => 0.0],
                ];

                foreach ($items as $item) {
                    $q = (int) $item->quarter;
                    if (! isset($quarters[$q])) {
                        continue;
                    }
                    $quarters[$q]['member']    += (float) $item->member_amount;
                    $quarters[$q]['volunteer'] += (float) $item->volunteer_amount;
                    $quarters[$q]['org']       += (float) $item->org_amount;
                }

                $yearTotal = 0.0;
                foreach ($quarters as $q) {
                    $yearTotal += $q['member'] + $q['volunteer'] + $q['org'];
                }

                return [
                    'id'         => $rowItem->id,
                    'level'      => $rowType, // 'branch' or 'division'
                    'label'      => $rowItem->name,
                    'q1_member'    => $quarters[1]['member'],
                    'q1_volunteer' => $quarters[1]['volunteer'],
                    'q1_org'       => $quarters[1]['org'],
                    'q2_member'    => $quarters[2]['member'],
                    'q2_volunteer' => $quarters[2]['volunteer'],
                    'q2_org'       => $quarters[2]['org'],
                    'q3_member'    => $quarters[3]['member'],
                    'q3_volunteer' => $quarters[3]['volunteer'],
                    'q3_org'       => $quarters[3]['org'],
                    'q4_member'    => $quarters[4]['member'],
                    'q4_volunteer' => $quarters[4]['volunteer'],
                    'q4_org'       => $quarters[4]['org'],
                    'year_total'   => $yearTotal,
                ];
            })->values()->all();
        }

        // Tab 2 — Fee Breakdown: full-year (Q1-Q4 + Year Total) breakdown per
        // fee, in three mutually exclusive sections by contributor type.
        // Organisation is scoped by organisation_id regardless of
        // is_volunteer_fee (an org-attributed payment can use either fee
        // flavour — see MembershipPaymentController::store()'s
        // fee-eligibility check, which keys off the linked user's own RC-unit
        // status, not organisation_id), so Member/Volunteer here are always
        // personal-only (organisation_id IS NULL) to keep the three sections
        // non-overlapping.
        //
        // Single grouped query (GROUP BY fee + organisation-flag +
        // QUARTER(payment_date)) rather than the old per-fee sum() loop (66
        // queries on a national dev load: 24 member + 9 volunteer + 33
        // organisation-eligible fees). Grouping on
        // (organisation_id IS NOT NULL) as its own dimension — not just
        // fee_id — matters: organisation_id lives on the payment, not the
        // fee, so the same fee_id can carry both personal and
        // organisation-sponsored payments; these must stay as two separate
        // rows in two separate sections below, never merged into one total.
        // Built on MembershipPayment::query() (Eloquent), same as the old
        // per-fee loop, so approval_status='approved' is still applied
        // automatically via the ApprovedScope global scope even with this
        // raw SELECT/GROUP BY — global scopes apply to the WHERE clause
        // regardless of SELECT shape, so no explicit approval_status filter
        // is needed here (unlike the Payments tab's DB::table() query,
        // which bypasses ApprovedScope entirely and must filter explicitly).
        $memberFeeBreakdown = collect();
        $volunteerFeeBreakdown = collect();
        $organisationFeeBreakdown = collect();
        $feeBreakdownGrandTotal = 0;
        $feeBreakdownData = collect();
        if ($activeTab === 'breakdown') {
            $feeBreakdownBase = MembershipPayment::query()
                ->where('is_deleted', false)
                ->whereBetween('payment_date', [$yearStart, $yearEnd]);

            if (!$isNational && $scopeBranchId) {
                $feeBreakdownBase->where('branch_id', $scopeBranchId);
            }

            $groupedFeeRows = $feeBreakdownBase
                ->join('membership_fees', 'membership_payments.membership_fee_id', '=', 'membership_fees.id')
                ->select(
                    'membership_payments.membership_fee_id',
                    'membership_fees.name as fee_name',
                    'membership_fees.validity_years',
                    'membership_fees.is_volunteer_fee',
                    DB::raw('(membership_payments.organisation_id IS NOT NULL) as is_organisation'),
                    DB::raw('QUARTER(membership_payments.payment_date) as quarter'),
                    DB::raw('SUM(membership_fees.amount) as amount')
                )
                ->groupBy(
                    'membership_payments.membership_fee_id',
                    'membership_fees.name',
                    'membership_fees.validity_years',
                    'membership_fees.is_volunteer_fee',
                    DB::raw('(membership_payments.organisation_id IS NOT NULL)'),
                    DB::raw('QUARTER(membership_payments.payment_date)')
                )
                ->get();

            // Reshape: one logical row per (fee, organisation-flag)
            // combination, folding its per-quarter amounts together —
            // mirrors the Payments tab's groupBy()->map() reshape technique.
            $buildYearRow = function ($rowsForOneFee) {
                $first = $rowsForOneFee->first();
                $quarters = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0];
                foreach ($rowsForOneFee as $r) {
                    $q = (int) $r->quarter;
                    if (isset($quarters[$q])) {
                        $quarters[$q] += (float) $r->amount;
                    }
                }
                $yearTotal = array_sum($quarters);

                return [
                    'fee_id'           => $first->membership_fee_id,
                    'fee_name'         => $first->fee_name . ($first->validity_years ? ' ' . $first->validity_years . ' Years' : ''),
                    'is_volunteer_fee' => (bool) $first->is_volunteer_fee,
                    'q1'               => $quarters[1],
                    'q2'               => $quarters[2],
                    'q3'               => $quarters[3],
                    'q4'               => $quarters[4],
                    'year_total'       => $yearTotal,
                ];
            };

            $personalRows = $groupedFeeRows->where('is_organisation', 0)
                ->groupBy('membership_fee_id')
                ->map($buildYearRow)
                ->values();

            $organisationRows = $groupedFeeRows->where('is_organisation', 1)
                ->groupBy('membership_fee_id')
                ->map($buildYearRow)
                ->values();

            $memberFeeBreakdown = $personalRows
                ->filter(fn ($row) => !$row['is_volunteer_fee'] && $row['year_total'] > 0)
                ->sortBy('fee_name')
                ->values();

            $volunteerFeeBreakdown = $personalRows
                ->filter(fn ($row) => $row['is_volunteer_fee'] && $row['year_total'] > 0)
                ->sortBy('fee_name')
                ->values();

            // Sorted fee_name first, then is_volunteer_fee last, so the
            // (stable) final sort's primary key is is_volunteer_fee —
            // matches the old query's orderBy('is_volunteer_fee')->orderBy('name').
            $organisationFeeBreakdown = $organisationRows
                ->filter(fn ($row) => $row['year_total'] > 0)
                ->sortBy('fee_name')
                ->sortBy(fn ($row) => $row['is_volunteer_fee'] ? 1 : 0)
                ->values();

            $feeBreakdownGrandTotal = $memberFeeBreakdown->sum('year_total')
                + $volunteerFeeBreakdown->sum('year_total')
                + $organisationFeeBreakdown->sum('year_total');

            // Kept for exportFeeBreakdownCsv()/the empty-state check below —
            // a flat concat of the three (mutually exclusive, so no
            // double-counting). The CSV's own Member/Volunteer split still
            // keys off is_volunteer_fee only, so an organisation-attributed
            // volunteer-fee row would fall into the CSV's Volunteer section
            // without a distinguishing label — a pre-existing limitation of
            // that exporter, not addressed here (out of scope for this pass).
            $feeBreakdownData = $memberFeeBreakdown
                ->concat($volunteerFeeBreakdown)
                ->concat($organisationFeeBreakdown)
                ->values();
        }

        // Export reflects whichever tab is active — the 'tab' query param is
        // already carried by the existing tab-switch links, so array_merge
        // of request()->query() with ['export' => 'csv'] naturally targets
        // the currently-displayed dataset, not both tabs at once.
        if ($request->input('export') === 'csv') {
            $scopeName = !$isNational ? $selectedBranchName : null;

            return $activeTab === 'breakdown'
                ? $this->exportFeeBreakdownCsv($memberFeeBreakdown, $volunteerFeeBreakdown, $organisationFeeBreakdown, $feeBreakdownGrandTotal, $isNational, $scopeName, $selectedYear)
                : $this->exportPaymentsCsv($paymentsData, $rowType, $isNational, $scopeName, $selectedYear);
        }

        return view('reports.financial.index', compact(
            'activeTab',
            'yearOptions',
            'selectedYear',
            'defaultYear',
            'branches',
            'selectedScope',
            'isNational',
            'selectedBranchName',
            'rowType',
            'paymentsData',
            'feeBreakdownData',
            'memberFeeBreakdown',
            'volunteerFeeBreakdown',
            'organisationFeeBreakdown',
            'feeBreakdownGrandTotal',
        ));
    }

    /**
     * Parses "{year}-Q{n}" into [start, end] Carbon instances for that
     * quarter. Same algorithm as index()'s inline quarter parsing,
     * extracted here so breakdown() doesn't reimplement it inline
     * (index() itself is left untouched/unrefactored for this change).
     */
    private function parseQuarterRange(string $quarter): array
    {
        [$qYear, $qLabel] = explode('-', $quarter);
        $qNum = (int) str_replace('Q', '', $qLabel);
        $qStart = Carbon::create($qYear, ($qNum - 1) * 3 + 1, 1)->startOfDay();
        $qEnd = (clone $qStart)->addMonths(3)->subSecond();

        return [$qStart, $qEnd];
    }

    /**
     * Breakdown: the individual payments behind one Payments/Fee Breakdown
     * figure (branch OR division + quarter + category). Deliberately built
     * on MembershipPayment::query() rather than DB::table() — this is what
     * gives the approval_status = 'approved' filter for free via the
     * Approvable trait's ApprovedScope global scope (confirmed in this
     * session's investigation: neither tab in index() ever filters
     * approval_status explicitly; it's enforced entirely by that scope,
     * which only applies to Eloquent queries). Dropping to a raw query here
     * would silently include pending/rejected payments.
     *
     * $level distinguishes a branch-level row (national Payments tab, one
     * row per branch) from a division-level row (branch-scoped Payments
     * tab, one row per division) — the pre-flight check on this feature
     * caught that a division row's total does NOT equal its enclosing
     * branch's total, so linking a division row to a branch-only query
     * would silently show the wrong figure. The 'branch_id' param name is
     * kept (not renamed) for signature stability; its value is interpreted
     * as a division_id when $level === 'division'.
     */
    public function breakdown(Request $request)
    {
        $id       = (int) $request->input('branch_id');
        $level    = $request->input('level', 'branch') === 'division' ? 'division' : 'branch';
        $quarter  = $request->input('quarter');
        $category = $request->input('category');

        $area = $level === 'division'
            ? Division::findOrFail($id)
            : Branch::findOrFail($id);

        // Branch-level viewers may drill into their own branch (branch-level
        // request) OR any division within their own branch (division-level
        // request, checked via that division's own branch_id — a division
        // id can never equal a branch id, so this can't be collapsed into a
        // single scopedId === $id comparison). Division-level viewers may
        // only drill into their own division — never a branch-level
        // breakdown, even for their own enclosing branch. National-level
        // viewers (including observer_national_level, per
        // User::NATIONAL_ROLES) are unrestricted.
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

        [$qStart, $qEnd] = $this->parseQuarterRange($quarter);

        $baseQuery = MembershipPayment::query()
            ->where('is_deleted', false)
            ->whereBetween('payment_date', [$qStart, $qEnd])
            ->when($level === 'division', fn ($q) => $q->where('division_id', $id))
            ->when($level === 'branch', fn ($q) => $q->where('branch_id', $id))
            ->when($category === 'member', fn ($q) => $q->whereNull('organisation_id')
                ->whereHas('membershipFee', fn ($fq) => $fq->where('is_volunteer_fee', false)))
            ->when($category === 'volunteer', fn ($q) => $q->whereNull('organisation_id')
                ->whereHas('membershipFee', fn ($fq) => $fq->where('is_volunteer_fee', true)))
            ->when($category === 'organisation', fn ($q) => $q->whereNotNull('organisation_id'));

        // Computed on clones BEFORE ->paginate() — paginate() only returns the
        // current page's 200 rows, but the "Total (N payments)" row must
        // reflect every matching payment across all pages, not just what's on
        // screen. MembershipPayment has no amount column of its own — amount
        // lives on MembershipFee (fixed per fee type) — so the sum needs its
        // own join, same convention used throughout this controller.
        $totalCount = (clone $baseQuery)->count();
        $total = (float) (clone $baseQuery)
            ->join('membership_fees', 'membership_payments.membership_fee_id', '=', 'membership_fees.id')
            ->sum('membership_fees.amount');

        // Secondary orderBy('id') added alongside the existing payment_date
        // sort so pagination is fully deterministic — payment_date alone
        // (a date, not datetime, column) can have many ties, which without a
        // tiebreaker can shuffle rows between pages across requests.
        $payments = $baseQuery
            ->with(['user', 'organisation', 'membershipFee'])
            ->orderBy('payment_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(200);

        return view('reports.financial.breakdown', [
            'area'       => $area,
            'level'      => $level,
            'quarter'    => $quarter,
            'category'   => $category,
            'payments'   => $payments,
            'total'      => $total,
            'totalCount' => $totalCount,
        ]);
    }

    /**
     * Breakdown: the individual payments behind one Fee Breakdown Q1-Q4 cell
     * (fee + quarter + category, scoped to the page's own national-or-
     * single-branch $selectedScope). Fee Breakdown has no division-row
     * concept — unlike breakdown()'s branch/division $level, this only ever
     * scopes nationally or to a single branch, matching exactly what
     * index() itself offers on this tab (confirmed before writing this:
     * index()'s own $selectedScope is always 'national' or a branch id,
     * never a division id, for the Fee Breakdown tab specifically).
     *
     * Access check deliberately mirrors breakdown()'s existing pattern
     * (independently re-derived from the authenticated session, not
     * trusted from the request) rather than index()'s own scope handling —
     * confirmed index() applies NO server-side restriction on its `scope`
     * query param at all (any viewer with the coarse view_reports
     * permission can view any branch's aggregate numbers by editing the
     * URL). That's fine for aggregate figures, but this route exposes
     * individual payer-identifying records, so — like breakdown() before
     * it — it requires the narrower view_payments permission (see
     * routes/web.php) plus this additional scope check, rather than
     * inheriting index()'s permissiveness.
     *
     * Division-level viewers have no division-scoped view on this tab, so
     * their access resolves to their own enclosing branch via
     * getScopedBranchId() (the same division-to-branch mapping used
     * elsewhere in this app) — confirmed this doesn't under- or
     * over-restrict them relative to what index() already lets them see:
     * index() itself has no restriction to match, so this method's check
     * is modeled on breakdown()'s existing restriction for the same class
     * of individual-payment-listing operation, applied to the branch-only
     * (no division) scope this tab actually has.
     */
    public function breakdownByFee(Request $request)
    {
        $feeId    = (int) $request->input('fee_id');
        $quarter  = $request->input('quarter');
        $category = $request->input('category');
        $scope    = $request->input('scope', 'national');

        $fee = MembershipFee::findOrFail($feeId);

        $isNational    = $scope === 'national';
        $scopeBranchId = !$isNational ? (int) $scope : null;
        $branch        = $scopeBranchId ? Branch::findOrFail($scopeBranchId) : null;

        // Branch- and division-level viewers alike are checked against
        // getScopedBranchId() — for a branch-level viewer this is just
        // their own branch_id (identical to getScopedId()); for a
        // division-level viewer it's their enclosing branch (there being no
        // division-scoped mode on this tab to check against instead).
        $accessLevel = auth()->user()->getAccessLevel();
        $viewerScopedBranchId = auth()->user()->getScopedBranchId();

        $inScope = match ($accessLevel) {
            'national' => true,
            'branch', 'division' => !$isNational && $scopeBranchId === $viewerScopedBranchId,
            default => false,
        };

        if (! $inScope) {
            abort(403);
        }

        [$qStart, $qEnd] = $this->parseQuarterRange($quarter);

        $baseQuery = MembershipPayment::query()
            ->where('is_deleted', false)
            ->whereBetween('payment_date', [$qStart, $qEnd])
            ->where('membership_fee_id', $feeId)
            ->when($scopeBranchId, fn ($q) => $q->where('branch_id', $scopeBranchId))
            ->when($category === 'member', fn ($q) => $q->whereNull('organisation_id')
                ->whereHas('membershipFee', fn ($fq) => $fq->where('is_volunteer_fee', false)))
            ->when($category === 'volunteer', fn ($q) => $q->whereNull('organisation_id')
                ->whereHas('membershipFee', fn ($fq) => $fq->where('is_volunteer_fee', true)))
            ->when($category === 'organisation', fn ($q) => $q->whereNotNull('organisation_id'));

        // Computed on clones BEFORE ->paginate() — paginate() only returns the
        // current page's 200 rows, but the "Total (N payments)" row must
        // reflect every matching payment across all pages, not just what's on
        // screen. MembershipPayment has no amount column of its own — amount
        // lives on MembershipFee (fixed per fee type) — so the sum needs its
        // own join, same convention used throughout this controller.
        $totalCount = (clone $baseQuery)->count();
        $total = (float) (clone $baseQuery)
            ->join('membership_fees', 'membership_payments.membership_fee_id', '=', 'membership_fees.id')
            ->sum('membership_fees.amount');

        // Secondary orderBy('id') added alongside the existing payment_date
        // sort so pagination is fully deterministic — payment_date alone
        // (a date, not datetime, column) can have many ties, which without a
        // tiebreaker can shuffle rows between pages across requests.
        $payments = $baseQuery
            ->with(['user', 'organisation', 'membershipFee', 'branch'])
            ->orderBy('payment_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(200);

        return view('reports.financial.breakdown-by-fee', [
            'fee'        => $fee,
            'scope'      => $scope,
            'isNational' => $isNational,
            'branch'     => $branch,
            'quarter'    => $quarter,
            'category'   => $category,
            'payments'   => $payments,
            'total'      => $total,
            'totalCount' => $totalCount,
        ]);
    }

    /**
     * Streams the Payments tab as CSV — same row shape/order as the
     * on-screen table (one row per branch/division, plus a Total row),
     * now the full-year Q1-Q4 × Member/Volunteer/Organisation breakdown
     * instead of a single quarter. Same BOM + sep=, + fputcsv pattern as
     * MemberReportController::exportCsv(). Every numeric value (detail rows
     * and the Total row alike) is passed through number_format($x, 0, '.', '')
     * so the whole export is uniformly decimal-free — detail amounts
     * otherwise come back as raw DB decimal strings (e.g. "322000.00")
     * while the accumulated totals are plain PHP floats (e.g. 322000),
     * which read inconsistently side-by-side without this.
     */
    private function exportPaymentsCsv(array $paymentsData, string $rowType, bool $isNational, ?string $scopeName, int $year): StreamedResponse
    {
        $scopeSlug = $isNational ? 'national' : \Illuminate\Support\Str::slug($scopeName);
        $filename = "financial-breakdown-payments-{$scopeSlug}-{$year}.csv";
        $areaLabel = $rowType === 'branch' ? 'Branch' : 'Division';

        $columns = [
            'q1_member', 'q1_volunteer', 'q1_org',
            'q2_member', 'q2_volunteer', 'q2_org',
            'q3_member', 'q3_volunteer', 'q3_org',
            'q4_member', 'q4_volunteer', 'q4_org',
        ];

        return response()->streamDownload(function () use ($paymentsData, $areaLabel, $columns) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, "sep=,\r\n");

            fputcsv($out, [
                $areaLabel,
                'Q1 Members', 'Q1 Volunteers', 'Q1 Organisations',
                'Q2 Members', 'Q2 Volunteers', 'Q2 Organisations',
                'Q3 Members', 'Q3 Volunteers', 'Q3 Organisations',
                'Q4 Members', 'Q4 Volunteers', 'Q4 Organisations',
                'Year Total',
            ]);

            $columnTotals = array_fill_keys($columns, 0.0);
            $grandTotal = 0.0;

            foreach ($paymentsData as $row) {
                $line = [$row['label']];
                foreach ($columns as $column) {
                    $line[] = number_format($row[$column], 0, '.', '');
                    $columnTotals[$column] += $row[$column];
                }
                $line[] = number_format($row['year_total'], 0, '.', '');

                fputcsv($out, $line);
                $grandTotal += $row['year_total'];
            }

            if (! empty($paymentsData)) {
                $totalLine = ['Total'];
                foreach ($columns as $column) {
                    $totalLine[] = number_format($columnTotals[$column], 0, '.', '');
                }
                $totalLine[] = number_format($grandTotal, 0, '.', '');

                fputcsv($out, $totalLine);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Streams the Fee Breakdown tab as CSV — preserves the on-screen
     * table's three-way grouped structure (Member fees section + subtotal,
     * Volunteer fees section + subtotal, Organisation fees section +
     * subtotal, Grand Total) rather than flattening it into a plain row
     * list, now with Q1-Q4 + Year Total columns per fee row instead of a
     * single Total Amount column. Each row's category (which has no
     * dedicated column on screen) is folded into the fee name via a
     * "(Member)"/"(Volunteer)"/"(Organisation)" suffix so the flat CSV
     * still conveys the same grouping unambiguously. Built directly from
     * the three source collections (not a re-split of a concatenated list
     * by is_volunteer_fee), so an organisation-attributed payment always
     * gets its own "(Organisation)" row instead of being silently folded
     * into Member/Volunteer based on the fee's own is_volunteer_fee flag.
     */
    private function exportFeeBreakdownCsv(
        $memberFeeBreakdown,
        $volunteerFeeBreakdown,
        $organisationFeeBreakdown,
        float $grandTotal,
        bool $isNational,
        ?string $scopeName,
        int $year
    ): StreamedResponse {
        $scopeSlug = $isNational ? 'national' : \Illuminate\Support\Str::slug($scopeName);
        $filename = "financial-breakdown-fees-{$scopeSlug}-{$year}.csv";

        $memberSubtotals       = $this->sumFeeQuarterColumns($memberFeeBreakdown);
        $volunteerSubtotals    = $this->sumFeeQuarterColumns($volunteerFeeBreakdown);
        $organisationSubtotals = $this->sumFeeQuarterColumns($organisationFeeBreakdown);

        return response()->streamDownload(function () use (
            $memberFeeBreakdown,
            $volunteerFeeBreakdown,
            $organisationFeeBreakdown,
            $memberSubtotals,
            $volunteerSubtotals,
            $organisationSubtotals,
            $grandTotal
        ) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, "sep=,\r\n");

            fputcsv($out, ['Fee Type', 'Q1', 'Q2', 'Q3', 'Q4', 'Year Total']);

            $writeRow = function ($out, string $label, array $row) {
                fputcsv($out, [
                    $label,
                    number_format($row['q1'], 0, '.', ''),
                    number_format($row['q2'], 0, '.', ''),
                    number_format($row['q3'], 0, '.', ''),
                    number_format($row['q4'], 0, '.', ''),
                    number_format($row['year_total'], 0, '.', ''),
                ]);
            };

            foreach ($memberFeeBreakdown as $row) {
                $writeRow($out, $row['fee_name'].' (Member)', $row);
            }
            if ($memberFeeBreakdown->isNotEmpty()) {
                $writeRow($out, 'Member fees subtotal', $memberSubtotals);
            }

            foreach ($volunteerFeeBreakdown as $row) {
                $writeRow($out, $row['fee_name'].' (Volunteer)', $row);
            }
            if ($volunteerFeeBreakdown->isNotEmpty()) {
                $writeRow($out, 'Volunteer fees subtotal', $volunteerSubtotals);
            }

            foreach ($organisationFeeBreakdown as $row) {
                $writeRow($out, $row['fee_name'].' (Organisation)', $row);
            }
            if ($organisationFeeBreakdown->isNotEmpty()) {
                $writeRow($out, 'Organisation fees subtotal', $organisationSubtotals);
            }

            if ($memberFeeBreakdown->isNotEmpty() || $volunteerFeeBreakdown->isNotEmpty() || $organisationFeeBreakdown->isNotEmpty()) {
                fputcsv($out, ['Grand Total', '', '', '', '', number_format($grandTotal, 0, '.', '')]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Sums q1/q2/q3/q4/year_total across a Fee Breakdown section's rows, for
     * that section's subtotal row in exportFeeBreakdownCsv().
     */
    private function sumFeeQuarterColumns($rows): array
    {
        return [
            'q1'         => $rows->sum('q1'),
            'q2'         => $rows->sum('q2'),
            'q3'         => $rows->sum('q3'),
            'q4'         => $rows->sum('q4'),
            'year_total' => $rows->sum('year_total'),
        ];
    }
}
