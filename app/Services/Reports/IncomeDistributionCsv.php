<?php

namespace App\Services\Reports;

use App\Support\Naira;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV exports of the Income Distribution report, in the same style as the
 * other report exports (streamDownload, fputcsv, UTF-8 BOM and a "sep=,"
 * line so Excel opens it correctly). Each file starts with what it covers:
 * the quarter, the in-progress warning when the quarter has not ended, any
 * integrity warning, the percentages used and the headline figures; then
 * the table and its TOTAL row. Amounts are plain naira decimals ("12345.67")
 * so spreadsheets read them as numbers.
 *
 * Both tabs: online (Paystack) and manual payments collected by branches.
 * Manual files also carry the go-live notes (before go-live / transition
 * quarter, with its two parts) and, for national admins, the invalid
 * MANUAL_GO_LIVE_DATE and missing-shares warnings. A branch user's file
 * holds their own branch only — $data is already scoped by the controller.
 */
class IncomeDistributionCsv
{
    /**
     * Branch level. $data is what the branch-level page receives; for a
     * branch user that is their own branch's row only.
     */
    public function branches(array $data): StreamedResponse
    {
        $isManual = ($data['activeTab'] ?? 'online') === 'manual';
        $branchLabel = $data['branchScopeLabel'] ?? null;
        $scope = $branchLabel === null ? '' : '-'.(Str::slug($branchLabel) ?: 'branch-'.$data['branchScope']);
        $filename = 'income-distribution-'.($isManual ? 'manual-' : '')."{$data['selectedQuarter']}-branches{$scope}.csv";

        return $this->download($filename, function ($out) use ($data, $branchLabel, $isManual) {
            $this->preamble($out, $data, 'Branch level', $isManual);
            if ($branchLabel !== null) {
                fputcsv($out, ['Branch', $this->text($branchLabel)]);
            }

            foreach ($data['percentageSets'] as $rule => $sets) {
                foreach ($sets as $set) {
                    fputcsv($out, [
                        'Percentages used',
                        IncomeDistributionStatsService::RULE_LABELS[$rule] ?? $rule,
                        IncomeDistributionStatsService::describePercentages($rule, $set),
                        $set['count'].' '.Str::plural('payment', $set['count']),
                    ]);
                }
            }
            if ($data['percentagesChanged']) {
                fputcsv($out, ['Note', 'Percentages changed during this quarter. Each payment keeps the percentages that applied when it was '.($isManual ? 'approved.' : 'paid.')]);
            }

            $headline = $data['headline'];
            fputcsv($out, ['Payments', $headline['count']]);

            if ($isManual) {
                fputcsv($out, ['Collected by '.($branchLabel === null ? 'branches' : 'the branch'), Naira::decimal($headline['net'])]);
                if ($data['transitionParts'] !== null) {
                    $date = IncomeDistributionStatsService::formatGoLiveDate($data['goLiveDate']);
                    foreach (['before' => "Before {$date} (estimates)", 'from' => "From {$date} (recorded)"] as $part => $label) {
                        $figures = $data['transitionParts'][$part];
                        fputcsv($out, [$label, Naira::decimal($figures['net']), $figures['count'].' '.Str::plural('payment', $figures['count'])]);
                    }
                }
                fputcsv($out, ['HQ share', Naira::decimal($headline['hq'])]);
                fputcsv($out, ['Branch share', Naira::decimal($headline['branch'])]);
                fputcsv($out, ['Division share', Naira::decimal($headline['division'])]);
                fputcsv($out, []);

                fputcsv($out, ['Branch', 'Payments', 'Collected', 'HQ share', 'Branch share', 'Division share', 'From Organisation']);
                foreach ($data['rows'] as $row) {
                    fputcsv($out, $this->manualBranchLine($this->text($row['label']), $row));
                }
                fputcsv($out, $this->manualBranchLine('TOTAL', $data['totals']));

                return;
            }

            fputcsv($out, ['Total paid by members', Naira::decimal($headline['paid'])]);
            fputcsv($out, ['Paystack charges', Naira::decimal($headline['fees'])]);
            fputcsv($out, ['Net received (distributed)', Naira::decimal($headline['net'])]);
            fputcsv($out, []);

            fputcsv($out, [
                'Branch', 'Payments', 'Paid by members', 'Paystack charges', 'Net received',
                'HQ share', 'Branch share', 'Division share', 'From Organisation',
            ]);
            foreach ($data['rows'] as $row) {
                fputcsv($out, $this->branchLine($this->text($row['label']), $row));
            }
            fputcsv($out, $this->branchLine('TOTAL', $data['totals']));
        });
    }

    /**
     * Division level, for the page's branch filter (all branches when none;
     * always the own branch for a branch user). The page's search box is
     * client-side only, so the file always holds every division for that
     * filter.
     */
    public function divisions(array $data): StreamedResponse
    {
        $isManual = ($data['channel'] ?? 'online') === 'manual';
        $scope = $data['branchFilter'] === null ? '' : '-'.(Str::slug($data['branchFilterLabel']) ?: 'branch-'.$data['branchFilter']);
        $filename = 'income-distribution-'.($isManual ? 'manual-' : '')."{$data['selectedQuarter']}-divisions{$scope}.csv";

        return $this->download($filename, function ($out) use ($data, $isManual) {
            $this->preamble($out, $data, 'Division level', $isManual);
            fputcsv($out, ['Branch', $this->text($data['branchFilterLabel'] ?? 'All branches')]);
            fputcsv($out, ['Divisions', $data['rows']->count()]);
            fputcsv($out, []);

            fputcsv($out, ['Division', 'Branch', 'Payments', $isManual ? 'Collected' : 'Net received', 'Division share']);
            foreach ($data['rows'] as $row) {
                fputcsv($out, [
                    $this->text($row['division']),
                    $this->text($row['branch']),
                    $row['count'],
                    Naira::decimal($row['net']),
                    Naira::decimal($row['division_share']),
                ]);
            }
            fputcsv($out, [
                'TOTAL',
                '',
                $data['totals']['count'],
                Naira::decimal($data['totals']['net']),
                Naira::decimal($data['totals']['division']),
            ]);
        });
    }

    /**
     * Title, quarter, the in-progress warning, any integrity warning, the
     * manual payments notes, and when the file was made.
     */
    private function preamble($out, array $data, string $level, bool $isManual): void
    {
        $tab = $isManual ? 'Manual payments (branches)' : 'Online payments (Paystack)';
        fputcsv($out, ["Income Distribution — {$tab} — {$level}"]);
        fputcsv($out, ['Quarter', $data['quarterLabel'].($data['quarterInProgress'] ? ' (in progress)' : '')]);

        if ($data['quarterInProgress']) {
            fputcsv($out, ['WARNING', IncomeDistributionStatsService::IN_PROGRESS_NOTE]);
        }
        foreach ($data['integrityProblems'] as $problem) {
            fputcsv($out, ['WARNING', 'The figures in this file do not add up. Do not use them for payouts. '.$problem]);
        }

        if ($isManual) {
            // National admins only (false / 0 for branch users).
            if ($data['goLiveDateInvalid']) {
                fputcsv($out, ['WARNING', IncomeDistributionStatsService::INVALID_GO_LIVE_NOTE]);
            }
            if (($data['unsplitPayments'] ?? 0) > 0) {
                fputcsv($out, ['WARNING', "{$data['unsplitPayments']} approved manual ".Str::plural('payment', $data['unsplitPayments']).' with no income shares yet, not counted in any quarter.']);
            }

            match ($data['goLivePhase']) {
                'before' => fputcsv($out, ['Note', IncomeDistributionStatsService::BEFORE_GO_LIVE_NOTE]),
                'transition' => fputcsv($out, ['Note', IncomeDistributionStatsService::transitionNote($data['goLiveDate'])]),
                default => null,
            };
        }

        fputcsv($out, ['Exported', now()->format('Y-m-d H:i')]);
        fputcsv($out, ['Amounts', $isManual
            ? 'Naira; by approval date (payments from the old system by payment date)'
            : 'Naira; based on Paystack payment time']);
    }

    private function branchLine(string $label, array $figures): array
    {
        return [
            $label,
            $figures['count'],
            Naira::decimal($figures['paid']),
            Naira::decimal($figures['fees']),
            Naira::decimal($figures['net']),
            Naira::decimal($figures['hq']),
            Naira::decimal($figures['branch']),
            Naira::decimal($figures['division']),
            Naira::decimal($figures['organisation_net']),
        ];
    }

    private function manualBranchLine(string $label, array $figures): array
    {
        return [
            $label,
            $figures['count'],
            Naira::decimal($figures['net']),
            Naira::decimal($figures['hq']),
            Naira::decimal($figures['branch']),
            Naira::decimal($figures['division']),
            Naira::decimal($figures['organisation_net']),
        ];
    }

    /**
     * A name cell that a spreadsheet must never run as a formula: one
     * starting with =, +, -, @, tab or carriage return gets a leading
     * apostrophe.
     */
    private function text(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    private function download(string $filename, callable $write): StreamedResponse
    {
        return response()->streamDownload(function () use ($write) {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");
            fwrite($out, "sep=,\r\n");

            $write($out);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
