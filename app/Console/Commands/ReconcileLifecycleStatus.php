<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcileLifecycleStatus extends Command
{
    protected $signature = 'lifecycle:reconcile {--apply : Write changes (default: dry-run report only)}';
    protected $description = 'Re-evaluate active/dormant users under the volunteer/member dormancy policy; report, or --apply to write.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $apply ? $this->warn('APPLY mode — lifecycle_status changes WILL be written.')
               : $this->info('[dry-run] No writes. Use --apply to write.');

        $stats   = ['active->dormant' => ['volunteer'=>0,'member'=>0,'unassigned'=>0,'neither'=>0],
                    'dormant->active' => ['volunteer'=>0,'member'=>0,'unassigned'=>0,'neither'=>0]];
        $samples = ['active->dormant' => [], 'dormant->active' => []];
        $changes = [];   // id => target status
        $scanned = 0;

        User::query()
            ->whereIn('lifecycle_status', ['active','dormant'])
            ->withCount('organisations')
            ->orderBy('id')
            ->chunkById(500, function ($users) use (&$stats,&$samples,&$changes,&$scanned) {
                foreach ($users as $u) {
                    $type = $u->lifecyclePolicyType();

                    // Genuine organisation-only contacts (no RCU, no personal
                    // membership — type 'neither') are exempt from the sweep. A
                    // user who ALSO has an RCU assignment or a personal membership
                    // payment is a real volunteer/member and must not be skipped
                    // just for carrying an organisation link too — see Decisions.md
                    // "Organisation-linked persons" entry.
                    if ($u->organisations_count > 0 && $type === 'neither') {
                        continue;
                    }

                    $scanned++;
                    $current = $u->lifecycle_status;
                    $target  = $u->isDormantByPolicy() ? 'dormant' : 'active';
                    if ($target === $current) continue;

                    $key  = $current === 'active' ? 'active->dormant' : 'dormant->active';
                    $stats[$key][$type]++;
                    if (count($samples[$key]) < 15) {
                        $samples[$key][] = sprintf('  DB-%d %s [%s] — %s',
                            $u->id, $u->full_name, $type, $this->reason($u, $type, $target));
                    }
                    $changes[$u->id] = $target;
                }
            });

        $this->line("Scanned (active + dormant): {$scanned}");
        $this->newLine();
        foreach (['active->dormant','dormant->active'] as $key) {
            $by = $stats[$key]; $total = array_sum($by);
            $this->info(sprintf('%s: %d   (volunteer %d, member %d, unassigned %d, neither %d)',
                $key, $total, $by['volunteer'], $by['member'], $by['unassigned'], $by['neither']));
            foreach ($samples[$key] as $line) $this->line($line);
            if ($total > count($samples[$key])) $this->line('  … ('.($total - count($samples[$key])).' more)');
            $this->newLine();
        }

        if (! $apply) {
            $this->info('Dry-run complete. Re-run with --apply to write these changes.');
            Log::channel('scheduler')->info('lifecycle:reconcile completed', [
                'apply' => false,
                'scanned' => $scanned,
                'active_to_dormant' => array_sum($stats['active->dormant']),
                'dormant_to_active' => array_sum($stats['dormant->active']),
            ]);
            return self::SUCCESS;
        }

        $toDormant = array_keys(array_filter($changes, fn($t) => $t === 'dormant'));
        $toActive  = array_keys(array_filter($changes, fn($t) => $t === 'active'));
        DB::transaction(function () use ($toDormant, $toActive) {
            foreach (array_chunk($toDormant, 1000) as $ids) User::whereIn('id',$ids)->update(['lifecycle_status'=>'dormant']);
            foreach (array_chunk($toActive,  1000) as $ids) User::whereIn('id',$ids)->update(['lifecycle_status'=>'active']);
        });
        $this->info('Applied: '.count($toDormant).' → dormant, '.count($toActive).' → active.');
        Log::channel('scheduler')->info('lifecycle:reconcile completed', [
            'apply' => true,
            'scanned' => $scanned,
            'to_dormant' => count($toDormant),
            'to_active' => count($toActive),
        ]);
        return self::SUCCESS;
    }

    private function reason(User $u, string $type, string $target): string
    {
        if ($type === 'member') {
            if ($target === 'dormant') {
                $exp = optional($u->latestMembershipPayment)->expiry_date;
                return 'membership expired'.($exp ? ' '.(string)$exp : '');
            }
            $exp = optional($u->currentMembershipPayment)->expiry_date;
            return 'valid membership'.($exp ? ' until '.(string)$exp : '');
        }
        $la = $u->last_activity_at;
        return $target === 'dormant'
            ? 'inactive since '.($la ? (string)$la : 'never')
            : 'recent activity '.($la ? (string)$la : '?');
    }
}
