<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PromoteQualifiedPending extends Command
{
    protected $signature = 'lifecycle:promote-qualified-pending {--apply : Write changes (default: dry-run report only)}';
    protected $description = 'Promote pending_engagement users who qualify (RCU assigned OR current personal fee) to active; report, or --apply to write.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $apply ? $this->warn('APPLY mode — qualifying pending_engagement users WILL be promoted to active.')
               : $this->info('[dry-run] No writes. Use --apply to write.');

        $stats   = ['rcu' => 0, 'fee' => 0, 'rcu+fee' => 0];
        $samples = [];
        $total   = 0;

        // Pre-filter in SQL; promoteFromPendingIfQualified() re-checks per user,
        // so the rule itself lives in one place (User).
        User::query()
            ->awaitingEngagement()
            ->where(fn ($q) => $q->whereNotNull('red_cross_unit_id')
                ->orWhereHas('currentMembershipPayment', fn ($p) => $p->personal()))
            ->withContributorFacts()
            ->orderBy('id')
            ->chunkById(500, function ($users) use ($apply, &$stats, &$samples, &$total) {
                foreach ($users as $u) {
                    $basis = match ($u->contributor_type) {
                        User::CONTRIBUTOR_VOLUNTEER_MEMBER => 'rcu+fee',
                        User::CONTRIBUTOR_VOLUNTEER => 'rcu',
                        User::CONTRIBUTOR_MEMBER => 'fee',
                        default => null,
                    };
                    if ($basis === null) {
                        continue;
                    }

                    if ($apply && ! $u->promoteFromPendingIfQualified()) {
                        continue;
                    }

                    $total++;
                    $stats[$basis]++;
                    if (count($samples) < 15) {
                        $samples[] = sprintf('  DB-%d %s [%s]', $u->id, $u->full_name, $basis);
                    }
                }
            });

        $this->line(sprintf('%s: %d   (RCU only %d, fee only %d, RCU + fee %d)',
            $apply ? 'Promoted' : 'Would promote', $total, $stats['rcu'], $stats['fee'], $stats['rcu+fee']));
        foreach ($samples as $line) $this->line($line);
        if ($total > count($samples)) $this->line('  … ('.($total - count($samples)).' more)');

        if ($apply && $total > 0) {
            $this->newLine();
            $this->info('Promoted users go to active; the next lifecycle:reconcile run applies the dormancy policy to them.');
        }

        Log::channel('scheduler')->info('lifecycle:promote-qualified-pending completed', [
            'apply' => $apply,
            'promoted' => $total,
            'by_basis' => $stats,
        ]);

        return self::SUCCESS;
    }
}
