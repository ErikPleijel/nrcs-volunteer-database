<?php

namespace App\Console\Commands;

use App\Services\IncomeDistributionCalculator;
use App\Support\Naira;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Stores the amount, income shares and accounting_date on approved manual
 * membership payments that do not have them yet — in practice the legacy
 * imports, which were never approved in this system. Payments approved
 * here get theirs at approval (MembershipPayment::afterApproved()).
 *
 * - Amount: the payment's own fee row (fee + ID card fee when included).
 *   For legacy payments that is today's price — the old system kept no
 *   price history — so the report labels those quarters as estimates.
 * - Shares: the current Fee distribution percentages, one snapshot for the
 *   whole run.
 * - accounting_date: the payment date for legacy payments (no approver;
 *   their decided_at is unreliable), the approval date otherwise.
 *
 * Safe to run again: only rows without a split are touched. Pending and
 * rejected payments are left for approval; deleted ones are left out.
 * A row whose amount cannot be worked out is listed, never guessed, and
 * the command then exits with failure.
 */
class BackfillManualPaymentShares extends Command
{
    protected $signature = 'payments:backfill-manual-shares
                            {--chunk=1000 : Rows per batch}
                            {--dry-run : Work everything out and report, but write nothing}';

    protected $description = 'Store amount, income shares and accounting date on approved manual payments that have none yet (e.g. legacy imports)';

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — nothing will be written.');
        }

        $skipped = [
            'already have shares' => $this->manual()->where('approval_status', 'approved')->where($this->notDeleted())->whereNotNull('distribution_rule')->count(),
            'pending approval (shares come with approval)' => $this->manual()->where('approval_status', 'pending')->count(),
            'rejected' => $this->manual()->where('approval_status', 'rejected')->count(),
            'deleted' => $this->manual()->where('approval_status', 'approved')->where('is_deleted', true)->count(),
        ];

        $candidates = $this->candidates();
        $total = (clone $candidates)->count();

        $percentages = IncomeDistributionCalculator::currentPercentages();
        $this->line('Percentages used: payments with a division '.implode(' / ', $this->describe($percentages['standard']))
            .'; with a branch but no division '.implode(' / ', $this->describe($percentages['branch_level'])).'; no branch: HQ 100%.');

        if ($total === 0) {
            $this->info('Nothing to fill: every approved manual payment already has its shares.');
            $this->report(0, [], [], $skipped);

            return self::SUCCESS;
        }

        $fees = DB::table('membership_fees')->get(['id', 'amount', 'id_card_fee'])->keyBy('id');

        $filled = 0;
        $byRule = [];
        $unresolved = [];

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $candidates->orderBy('id')->chunkById($chunk, function ($rows) use ($fees, $percentages, $dryRun, $bar, &$filled, &$byRule, &$unresolved) {
            $updates = [];

            foreach ($rows as $row) {
                $bar->advance();

                $fee = $fees[$row->membership_fee_id] ?? null;
                if ($fee === null) {
                    $unresolved[$row->id] = "fee #{$row->membership_fee_id} does not exist";

                    continue;
                }
                if ($fee->amount === null) {
                    $unresolved[$row->id] = "fee #{$fee->id} has no amount";

                    continue;
                }

                $accountingDate = $row->decided_by_user_id === null
                    ? $row->payment_date
                    : ($row->decided_at === null ? null : Carbon::parse($row->decided_at)->toDateString());
                if ($accountingDate === null) {
                    $unresolved[$row->id] = 'no payment or approval date';

                    continue;
                }

                $columns = IncomeDistributionCalculator::manualPaymentColumns(
                    $fee->amount,
                    $fee->id_card_fee ?? 0,
                    (bool) $row->id_card_included,
                    $row->branch_id === null ? null : (int) $row->branch_id,
                    $row->division_id === null ? null : (int) $row->division_id,
                    percentages: $percentages,
                ) + ['accounting_date' => substr((string) $accountingDate, 0, 10)];

                $updates[$row->id] = $columns;

                $rule = $columns['distribution_rule'];
                $byRule[$rule]['count'] = ($byRule[$rule]['count'] ?? 0) + 1;
                $byRule[$rule]['net'] = ($byRule[$rule]['net'] ?? 0) + $columns['net_kobo'];
            }

            if (! $dryRun && $updates !== []) {
                // updated_at is left alone: this adds figures, it does not
                // change what was recorded about the payment.
                DB::transaction(function () use ($updates) {
                    foreach ($updates as $id => $columns) {
                        DB::table('membership_payments')
                            ->where('id', $id)
                            ->whereNull('distribution_rule')
                            ->update($columns);
                    }
                });
            }

            $filled += count($updates);
        });

        $bar->finish();
        $this->newLine(2);

        $this->report($filled, $byRule, $unresolved, $skipped, $dryRun);

        return $unresolved === [] ? self::SUCCESS : self::FAILURE;
    }

    /** Manual payments (legacy rows may predate the channel column's default). */
    private function manual(): Builder
    {
        return DB::table('membership_payments')
            ->where(fn (Builder $q) => $q->where('payment_channel', 'manual')->orWhereNull('payment_channel'));
    }

    private function notDeleted(): \Closure
    {
        return fn (Builder $q) => $q->where('is_deleted', false)->orWhereNull('is_deleted');
    }

    private function candidates(): Builder
    {
        return $this->manual()
            ->where('approval_status', 'approved')
            ->where($this->notDeleted())
            ->whereNull('distribution_rule')
            ->select(['id', 'membership_fee_id', 'id_card_included', 'organisation_id', 'branch_id', 'division_id', 'payment_date', 'decided_at', 'decided_by_user_id']);
    }

    /** @return list<string> */
    private function describe(array $parties): array
    {
        $labels = ['hq' => 'HQ', 'branch' => 'Branch', 'division' => 'Division'];

        return array_map(fn ($party, $value) => "{$labels[$party]} {$value}%", array_keys($parties), $parties);
    }

    private function report(int $filled, array $byRule, array $unresolved, array $skipped, bool $dryRun = false): void
    {
        $this->info(sprintf('%s %s approved manual %s.', $dryRun ? 'Would fill' : 'Filled', number_format($filled), $filled === 1 ? 'payment' : 'payments'));

        if ($byRule !== []) {
            ksort($byRule);
            $this->table(['Rule', 'Payments', 'Net amount'], collect($byRule)->map(fn ($row, $rule) => [
                $rule, number_format($row['count']), Naira::format($row['net']),
            ])->values()->all());
        }

        $this->table(['Skipped', 'Payments'], collect($skipped)->map(fn ($count, $reason) => [$reason, number_format($count)])->values()->all());

        if ($unresolved !== []) {
            $this->error(sprintf('%d %s could not be filled because the amount could not be worked out (left untouched):', count($unresolved), count($unresolved) === 1 ? 'payment' : 'payments'));
            $this->table(['Payment id', 'Why'], collect($unresolved)->map(fn ($why, $id) => [$id, $why])->values()->all());
        }
    }
}
