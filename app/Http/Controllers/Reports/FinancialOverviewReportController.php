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
                    ->whereHas('membershipFee', fn($q) => $q->where('is_volunteer_fee', false))
                    ->join('membership_fees', 'membership_payments.membership_fee_id', '=', 'membership_fees.id')
                    ->sum('membership_fees.amount');

                $volunteerAmount = (clone $base)
                    ->whereHas('membershipFee', fn($q) => $q->where('is_volunteer_fee', true))
                    ->join('membership_fees', 'membership_payments.membership_fee_id', '=', 'membership_fees.id')
                    ->sum('membership_fees.amount');

                $orgAmount = (clone $base)
                    ->whereNotNull('organisation_id')
                    ->join('membership_fees', 'membership_payments.membership_fee_id', '=', 'membership_fees.id')
                    ->sum('membership_fees.amount');

                $total = $memberAmount + $volunteerAmount + $orgAmount;

                $paymentsData[] = [
                    'label'            => $row->name,
                    'member_amount'    => $memberAmount,
                    'volunteer_amount' => $volunteerAmount,
                    'org_amount'       => $orgAmount,
                    'total'            => $total,
                ];
            }
        }

        // Tab 2 — Fee Breakdown
        $feeBreakdownData = collect();
        if ($activeTab === 'breakdown') {
            $breakdownBase = MembershipPayment::query()
                ->where('is_deleted', false)
                ->whereBetween('payment_date', [$qStart, $qEnd]);

            if (!$isNational && $scopeBranchId) {
                $breakdownBase->where('branch_id', $scopeBranchId);
            }

            $feeBreakdownData = MembershipFee::query()
                ->orderBy('is_volunteer_fee', 'asc')
                ->orderBy('name', 'asc')
                ->orderBy('validity_years', 'asc')
                ->get()
                ->map(function ($fee) use ($breakdownBase) {
                    $total = (clone $breakdownBase)
                        ->where('membership_fee_id', $fee->id)
                        ->join('membership_fees', 'membership_payments.membership_fee_id', '=', 'membership_fees.id')
                        ->sum('membership_fees.amount');
                    return [
                        'fee_name'         => $fee->name . ($fee->validity_years ? ' ' . $fee->validity_years . ' Years' : ''),
                        'is_volunteer_fee' => $fee->is_volunteer_fee,
                        'total'            => $total,
                    ];
                })
                ->filter(fn($row) => $row['total'] > 0)
                ->values();
        }

        // Export reflects whichever tab is active — the 'tab' query param is
        // already carried by the existing tab-switch links, so array_merge
        // of request()->query() with ['export' => 'csv'] naturally targets
        // the currently-displayed dataset, not both tabs at once.
        if ($request->input('export') === 'csv') {
            $scopeName = !$isNational ? $selectedBranchName : null;

            return $activeTab === 'breakdown'
                ? $this->exportFeeBreakdownCsv($feeBreakdownData, $isNational, $scopeName, $selectedQuarter)
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
        ));
    }

    /**
     * Streams the Payments tab as CSV — same row shape/order as the
     * on-screen table (one row per branch/division, then a Total row
     * matching the table's <tfoot>). Same BOM + sep=, + fputcsv pattern as
     * MemberReportController::exportCsv().
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
                fputcsv($out, [$row['label'], $row['member_amount'], $row['volunteer_amount'], $row['org_amount'], $row['total']]);
                $totalMembers   += $row['member_amount'];
                $totalVolunteer += $row['volunteer_amount'];
                $totalOrg       += $row['org_amount'];
                $grandTotal     += $row['total'];
            }

            if (! empty($paymentsData)) {
                fputcsv($out, ['Total', $totalMembers, $totalVolunteer, $totalOrg, $grandTotal]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Streams the Fee Breakdown tab as CSV — preserves the on-screen
     * table's grouped structure (Member fees section + subtotal, Volunteer
     * fees section + subtotal, Grand Total) rather than flattening it into
     * a plain row list. The on-screen "Member"/"Volunteer" badge (which has
     * no dedicated column on screen) is folded into the fee name so the
     * flat CSV still conveys the same grouping unambiguously.
     */
    private function exportFeeBreakdownCsv($feeBreakdownData, bool $isNational, ?string $scopeName, string $quarter): StreamedResponse
    {
        $scopeSlug = $isNational ? 'national' : \Illuminate\Support\Str::slug($scopeName);
        $filename = "financial-breakdown-fees-{$scopeSlug}-{$quarter}.csv";

        $memberFees    = $feeBreakdownData->where('is_volunteer_fee', false)->values();
        $volunteerFees = $feeBreakdownData->where('is_volunteer_fee', true)->values();
        $memberSubtotal    = $memberFees->sum('total');
        $volunteerSubtotal = $volunteerFees->sum('total');
        $grandTotal        = $memberSubtotal + $volunteerSubtotal;

        return response()->streamDownload(function () use ($memberFees, $volunteerFees, $memberSubtotal, $volunteerSubtotal, $grandTotal) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, "sep=,\r\n");

            fputcsv($out, ['Fee Type', 'Total Amount']);

            foreach ($memberFees as $row) {
                fputcsv($out, [$row['fee_name'].' (Member)', $row['total']]);
            }
            if ($memberFees->isNotEmpty()) {
                fputcsv($out, ['Member fees subtotal', $memberSubtotal]);
            }

            foreach ($volunteerFees as $row) {
                fputcsv($out, [$row['fee_name'].' (Volunteer)', $row['total']]);
            }
            if ($volunteerFees->isNotEmpty()) {
                fputcsv($out, ['Volunteer fees subtotal', $volunteerSubtotal]);
            }

            if ($memberFees->isNotEmpty() || $volunteerFees->isNotEmpty()) {
                fputcsv($out, ['Grand Total', $grandTotal]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
