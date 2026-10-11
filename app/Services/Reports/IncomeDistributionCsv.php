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
 */
class IncomeDistributionCsv
{
    /**
     * Branch level. $data is what the branch-level page receives.
     */
    public function branches(array $data): StreamedResponse
    {
        $filename = "income-distribution-{$data['selectedQuarter']}-branches.csv";

        return $this->download($filename, function ($out) use ($data) {
            $this->preamble($out, $data, 'Branch level');

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
                fputcsv($out, ['Note', 'Percentages changed during this quarter. Each payment keeps the percentages that applied when it was paid.']);
            }

            $headline = $data['headline'];
            fputcsv($out, ['Payments', $headline['count']]);
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
     * Division level, for the page's branch filter (all branches when none).
     * The page's search box is client-side only, so the file always holds
     * every division for that filter.
     */
    public function divisions(array $data): StreamedResponse
    {
        $scope = $data['branchFilter'] === null ? '' : '-'.(Str::slug($data['branchFilterLabel']) ?: 'branch-'.$data['branchFilter']);
        $filename = "income-distribution-{$data['selectedQuarter']}-divisions{$scope}.csv";

        return $this->download($filename, function ($out) use ($data) {
            $this->preamble($out, $data, 'Division level');
            fputcsv($out, ['Branch', $this->text($data['branchFilterLabel'] ?? 'All branches')]);
            fputcsv($out, ['Divisions', $data['rows']->count()]);
            fputcsv($out, []);

            fputcsv($out, ['Division', 'Branch', 'Payments', 'Net received', 'Division share']);
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
     * Title, quarter, the in-progress warning, any integrity warning, and
     * when the file was made.
     */
    private function preamble($out, array $data, string $level): void
    {
        fputcsv($out, ['Income Distribution — Online payments (Paystack) — '.$level]);
        fputcsv($out, ['Quarter', $data['quarterLabel'].($data['quarterInProgress'] ? ' (in progress)' : '')]);

        if ($data['quarterInProgress']) {
            fputcsv($out, ['WARNING', IncomeDistributionStatsService::IN_PROGRESS_NOTE]);
        }
        foreach ($data['integrityProblems'] as $problem) {
            fputcsv($out, ['WARNING', 'The figures in this file do not add up. Do not use them for payouts. '.$problem]);
        }

        fputcsv($out, ['Exported', now()->format('Y-m-d H:i')]);
        fputcsv($out, ['Amounts', 'Naira; based on Paystack payment time']);
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
