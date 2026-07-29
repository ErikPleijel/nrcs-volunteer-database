<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinancialOverviewReportController extends Controller
{
    public function index(Request $request)
    {
        $activeTab   = $request->input('tab', 'payments');
        $currentYear = now()->year;

        // Quarter options — current quarter back 5 years, desc
        $quarterOptions = [];
        for ($year = $currentYear; $year >= $currentYear - 5; $year--) {
            for ($q = 4; $q >= 1; $q--) {
                $qStart = Carbon::create($year, ($q - 1) * 3 + 1, 1)->startOfMonth();
                if ($qStart->isFuture()) continue;
                $quarterOptions[] = ['value' => "{$year}-Q{$q}", 'label' => "{$year} Q{$q}"];
            }
        }

        $currentQ        = 'Q' . ceil(now()->month / 3);
        $defaultQuarter  = "{$currentYear}-{$currentQ}";
        $selectedQuarter = $request->input('quarter', $defaultQuarter);

        [$qYear, $qLabel] = explode('-', $selectedQuarter);
        $qNum   = (int) str_replace('Q', '', $qLabel);
        $qStart = Carbon::create($qYear, ($qNum - 1) * 3 + 1, 1)->startOfDay();
        $qEnd   = (clone $qStart)->addMonths(3)->subSecond();

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

        $basePayments = function ($rowItem) use ($rowType, $qStart, $qEnd) {
            $q = MembershipPayment::query()
                ->where('is_deleted', false)
                ->whereBetween('payment_date', [$qStart, $qEnd]);
            if ($rowType === 'branch') {
                $q->where('branch_id', $rowItem->id);
            } else {
                $q->where('division_id', $rowItem->id);
            }
            return $q;
        };

        // Tab 1 — Payments
        $paymentsData = [];
        if ($activeTab === 'payments') {
            foreach ($rows as $row) {
                $base = $basePayments($row);

                $memberAmount = (clone $base)
                    ->whereNull('organisation_id')
                    ->whereHas('membershipFee', fn($q) => $q->where('is_volunteer_fee', false))
                    ->join('membership_fees', 'membership_payments.membership_fee_id', '=', 'membership_fees.id')
                    ->sum('membership_fees.amount');

                $volunteerAmount = (clone $base)
                    ->whereNull('organisation_id')
                    ->whereHas('membershipFee', fn($q) => $q->where('is_volunteer_fee', true))
                    ->join('membership_fees', 'membership_payments.membership_fee_id', '=', 'membership_fees.id')
                    ->sum('membership_fees.amount');

                $orgAmount = (clone $base)
                    ->whereNotNull('organisation_id')
                    ->join('membership_fees', 'membership_payments.membership_fee_id', '=', 'membership_fees.id')
                    ->sum('membership_fees.amount');

                $total = $memberAmount + $volunteerAmount + $orgAmount;

                $paymentsData[] = [
                    'id'               => $row->id,
                    'level'            => $rowType, // 'branch' or 'division'
                    'label'            => $row->name,
                    'member_amount'    => $memberAmount,
                    'volunteer_amount' => $volunteerAmount,
                    'org_amount'       => $orgAmount,
                    'total'            => $total,
                ];
            }
        }

        // Tab 2 — Fee Breakdown: three mutually exclusive sections by
        // contributor type. Organisation is scoped by organisation_id
        // regardless of is_volunteer_fee (an org-attributed payment can use
        // either fee flavour — see MembershipPaymentController::store()'s
        // fee-eligibility check, which keys off the linked user's own RC-unit
        // status, not organisation_id), so Member/Volunteer here are always
        // personal-only (organisation_id IS NULL) to keep the three sections
        // non-overlapping.
        $memberFeeBreakdown = collect();
        $volunteerFeeBreakdown = collect();
        $organisationFeeBreakdown = collect();
        $feeBreakdownGrandTotal = 0;
        $feeBreakdownData = collect();
        if ($activeTab === 'breakdown') {
            $breakdownBase = MembershipPayment::query()
                ->where('is_deleted', false)
                ->whereBetween('payment_date', [$qStart, $qEnd]);

            if (!$isNational && $scopeBranchId) {
                $breakdownBase->where('branch_id', $scopeBranchId);
            }

            // Reuses $paymentsData's org_amount convention exactly:
            // whereNotNull('organisation_id') for organisation, whereNull for
            // personal (member/volunteer).
            $buildFeeRows = function ($feesQuery, $paymentsBase) {
                return $feesQuery->get()
                    ->map(function ($fee) use ($paymentsBase) {
                        $total = (clone $paymentsBase)
                            ->where('membership_fee_id', $fee->id)
                            ->join('membership_fees', 'membership_payments.membership_fee_id', '=', 'membership_fees.id')
                            ->sum('membership_fees.amount');
                        return [
                            'fee_name'         => $fee->name . ($fee->validity_years ? ' ' . $fee->validity_years . ' Years' : ''),
                            'is_volunteer_fee' => $fee->is_volunteer_fee,
                            'total'            => $total,
                        ];
                    })
                    ->filter(fn ($row) => $row['total'] > 0)
                    ->values();
            };

            $memberFeeBreakdown = $buildFeeRows(
                MembershipFee::query()->where('is_volunteer_fee', false)->orderBy('name')->orderBy('validity_years'),
                (clone $breakdownBase)->whereNull('organisation_id')
            );

            $volunteerFeeBreakdown = $buildFeeRows(
                MembershipFee::query()->where('is_volunteer_fee', true)->orderBy('name')->orderBy('validity_years'),
                (clone $breakdownBase)->whereNull('organisation_id')
            );

            $organisationFeeBreakdown = $buildFeeRows(
                MembershipFee::query()->orderBy('is_volunteer_fee')->orderBy('name')->orderBy('validity_years'),
                (clone $breakdownBase)->whereNotNull('organisation_id')
            );

            $feeBreakdownGrandTotal = $memberFeeBreakdown->sum('total')
                + $volunteerFeeBreakdown->sum('total')
                + $organisationFeeBreakdown->sum('total');

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
                ? $this->exportFeeBreakdownCsv($memberFeeBreakdown, $volunteerFeeBreakdown, $organisationFeeBreakdown, $feeBreakdownGrandTotal, $isNational, $scopeName, $selectedQuarter)
                : $this->exportPaymentsCsv($paymentsData, $rowType, $isNational, $scopeName, $selectedQuarter);
        }

        return view('reports.financial.index', compact(
            'activeTab',
            'quarterOptions',
            'selectedQuarter',
            'defaultQuarter',
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

        $payments = MembershipPayment::query()
            ->where('is_deleted', false)
            ->whereBetween('payment_date', [$qStart, $qEnd])
            ->when($level === 'division', fn ($q) => $q->where('division_id', $id))
            ->when($level === 'branch', fn ($q) => $q->where('branch_id', $id))
            ->when($category === 'member', fn ($q) => $q->whereNull('organisation_id')
                ->whereHas('membershipFee', fn ($fq) => $fq->where('is_volunteer_fee', false)))
            ->when($category === 'volunteer', fn ($q) => $q->whereNull('organisation_id')
                ->whereHas('membershipFee', fn ($fq) => $fq->where('is_volunteer_fee', true)))
            ->when($category === 'organisation', fn ($q) => $q->whereNotNull('organisation_id'))
            ->with(['user', 'organisation', 'membershipFee'])
            ->orderBy('payment_date', 'desc')
            ->get();

        // MembershipPayment has no amount column of its own — amount lives
        // on MembershipFee (fixed per fee type), same convention used
        // throughout this controller.
        $total = $payments->sum(fn ($payment) => $payment->membershipFee->amount ?? 0);

        return view('reports.financial.breakdown', [
            'area'     => $area,
            'level'    => $level,
            'quarter'  => $quarter,
            'category' => $category,
            'payments' => $payments,
            'total'    => $total,
        ]);
    }

    /**
     * Streams the Payments tab as CSV — same row shape/order as the
     * on-screen table (one row per branch/division, plus a Total row).
     * Same BOM + sep=, + fputcsv pattern as MemberReportController::exportCsv().
     * Every numeric value (detail rows and the Total row alike) is passed
     * through number_format($x, 0, '.', '') so the whole export is
     * uniformly decimal-free — detail amounts otherwise come back as raw
     * DB decimal strings (e.g. "322000.00") while the accumulated totals
     * are plain PHP floats (e.g. 322000), which read inconsistently
     * side-by-side without this.
     */
    private function exportPaymentsCsv(array $paymentsData, string $rowType, bool $isNational, ?string $scopeName, string $quarter): StreamedResponse
    {
        $scopeSlug = $isNational ? 'national' : \Illuminate\Support\Str::slug($scopeName);
        $filename = "financial-breakdown-payments-{$scopeSlug}-{$quarter}.csv";
        $areaLabel = $rowType === 'branch' ? 'Branch' : 'Division';

        return response()->streamDownload(function () use ($paymentsData, $areaLabel) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, "sep=,\r\n");

            fputcsv($out, [$areaLabel, 'Members', 'Volunteers', 'Organisations', 'Total']);

            $totalMembers = $totalVolunteer = $totalOrg = $grandTotal = 0;

            foreach ($paymentsData as $row) {
                fputcsv($out, [
                    $row['label'],
                    number_format($row['member_amount'], 0, '.', ''),
                    number_format($row['volunteer_amount'], 0, '.', ''),
                    number_format($row['org_amount'], 0, '.', ''),
                    number_format($row['total'], 0, '.', ''),
                ]);
                $totalMembers   += $row['member_amount'];
                $totalVolunteer += $row['volunteer_amount'];
                $totalOrg       += $row['org_amount'];
                $grandTotal     += $row['total'];
            }

            if (! empty($paymentsData)) {
                fputcsv($out, [
                    'Total',
                    number_format($totalMembers, 0, '.', ''),
                    number_format($totalVolunteer, 0, '.', ''),
                    number_format($totalOrg, 0, '.', ''),
                    number_format($grandTotal, 0, '.', ''),
                ]);
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
     * list. Each row's category (which has no dedicated column on screen)
     * is folded into the fee name via a "(Member)"/"(Volunteer)"/
     * "(Organisation)" suffix so the flat CSV still conveys the same
     * grouping unambiguously. Built directly from the three source
     * collections (not a re-split of a concatenated list by
     * is_volunteer_fee), so an organisation-attributed payment always gets
     * its own "(Organisation)" row instead of being silently folded into
     * Member/Volunteer based on the fee's own is_volunteer_fee flag.
     */
    private function exportFeeBreakdownCsv(
        $memberFeeBreakdown,
        $volunteerFeeBreakdown,
        $organisationFeeBreakdown,
        float $grandTotal,
        bool $isNational,
        ?string $scopeName,
        string $quarter
    ): StreamedResponse {
        $scopeSlug = $isNational ? 'national' : \Illuminate\Support\Str::slug($scopeName);
        $filename = "financial-breakdown-fees-{$scopeSlug}-{$quarter}.csv";

        $memberSubtotal       = $memberFeeBreakdown->sum('total');
        $volunteerSubtotal    = $volunteerFeeBreakdown->sum('total');
        $organisationSubtotal = $organisationFeeBreakdown->sum('total');

        return response()->streamDownload(function () use (
            $memberFeeBreakdown,
            $volunteerFeeBreakdown,
            $organisationFeeBreakdown,
            $memberSubtotal,
            $volunteerSubtotal,
            $organisationSubtotal,
            $grandTotal
        ) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, "sep=,\r\n");

            fputcsv($out, ['Fee Type', 'Total Amount']);

            foreach ($memberFeeBreakdown as $row) {
                fputcsv($out, [$row['fee_name'].' (Member)', number_format($row['total'], 0, '.', '')]);
            }
            if ($memberFeeBreakdown->isNotEmpty()) {
                fputcsv($out, ['Member fees subtotal', number_format($memberSubtotal, 0, '.', '')]);
            }

            foreach ($volunteerFeeBreakdown as $row) {
                fputcsv($out, [$row['fee_name'].' (Volunteer)', number_format($row['total'], 0, '.', '')]);
            }
            if ($volunteerFeeBreakdown->isNotEmpty()) {
                fputcsv($out, ['Volunteer fees subtotal', number_format($volunteerSubtotal, 0, '.', '')]);
            }

            foreach ($organisationFeeBreakdown as $row) {
                fputcsv($out, [$row['fee_name'].' (Organisation)', number_format($row['total'], 0, '.', '')]);
            }
            if ($organisationFeeBreakdown->isNotEmpty()) {
                fputcsv($out, ['Organisation fees subtotal', number_format($organisationSubtotal, 0, '.', '')]);
            }

            if ($memberFeeBreakdown->isNotEmpty() || $volunteerFeeBreakdown->isNotEmpty() || $organisationFeeBreakdown->isNotEmpty()) {
                fputcsv($out, ['Grand Total', number_format($grandTotal, 0, '.', '')]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
