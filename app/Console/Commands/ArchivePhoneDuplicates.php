<?php

namespace App\Console\Commands;

use App\Services\PhoneDuplicates\PhoneDuplicateAnalyzer;
use App\Services\PhoneDuplicates\PhoneDuplicateMerger;
use App\Support\PhoneNumber;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Archives email-less duplicate-Telephone1 accounts, moving their records to
 * the kept account first. Groups, winners and moves come from
 * PhoneDuplicateAnalyzer — identical to users:report-phone-duplicates.
 *
 * Dry-run unless --commit. Each group is its own transaction
 * (PhoneDuplicateMerger); a failing group is rolled back, logged and
 * skipped, and the run continues. Every committed group is written to the
 * `logs` audit table and to a JSONL file with enough detail to reverse it.
 */
class ArchivePhoneDuplicates extends Command
{
    protected $signature = 'users:archive-phone-duplicates
                            {--commit : Actually write (default: dry-run preview, no writes)}
                            {--limit= : Process only the first N actionable groups}
                            {--phones= : Comma-separated phone numbers (any format) to process only those groups}';

    protected $description = 'Archive email-less duplicate-Telephone1 accounts after moving their records to the kept account (dry-run unless --commit).';

    public function handle(PhoneDuplicateAnalyzer $analyzer, PhoneDuplicateMerger $merger): int
    {
        $commit = (bool) $this->option('commit');
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;
        $phones = $this->option('phones') !== null
            ? array_values(array_filter(array_map(
                fn ($p) => PhoneNumber::normalize(trim($p)),
                explode(',', (string) $this->option('phones'))
            ), fn ($n) => strlen($n) >= 7))
            : null;

        $commit ? $this->warn('COMMIT mode — records WILL be moved and duplicate accounts archived.')
                : $this->info('[dry-run] No writes. Use --commit to write.');

        $groups = $analyzer->groups($phones);
        $accounts = $analyzer->accounts($groups ? array_merge(...array_values($groups)) : []);

        $auditPath = storage_path('logs/phone-duplicate-archive-'.now()->format('Ymd_His').'.jsonl');
        $stats = ['groups' => 0, 'losers' => 0, 'rows' => [], 'rcu' => ['moved' => 0, 'took_newer' => 0, 'kept_winner' => 0],
            'promotions' => 0, 'failed' => 0];
        $failures = [];
        $roleSkipped = [];
        $seen = [];

        foreach ($groups as $norm => $ids) {
            $g = $analyzer->analyse((string) $norm, array_map(fn ($id) => $accounts[$id], $ids));
            $seen[(string) $norm] = $g['verdict'];

            if ($g['verdict'] === 'skipped_role' && ($limit === null || $stats['groups'] + $stats['failed'] < $limit)) {
                // Never attempted: no transaction, no failure, no retry.
                $roleSkipped[] = "0{$norm} (".implode(', ', array_map(fn ($id) => "DB-{$id}", $g['role_holders'])).')';
            }
            if ($g['verdict'] !== 'action') {
                continue;
            }
            if ($limit !== null && $stats['groups'] + $stats['failed'] >= $limit) {
                break;
            }

            if (! $commit) {
                $this->tally($stats, $g, $g['promotable']);
                if ($limit !== null || $phones !== null || $stats['groups'] <= 15) {
                    $this->line($this->describe($g, 'would'));
                }

                continue;
            }

            try {
                $audit = $merger->merge($g);
                $this->tally($stats, $g, $audit['promoted']);
                $this->appendAudit($auditPath, ['status' => 'committed', 'at' => now()->toIso8601String()] + $audit);
                $this->line($this->describe($g, 'done'));
            } catch (Throwable $e) {
                $stats['failed']++;
                $failures[] = "0{$norm}: {$e->getMessage()}";
                $this->appendAudit($auditPath, ['status' => 'failed', 'at' => now()->toIso8601String(),
                    'phone' => "0{$norm}", 'winner_id' => $g['winner']['id'], 'error' => $e->getMessage()]);
                Log::channel('scheduler')->error('users:archive-phone-duplicates group failed, rolled back', [
                    'phone' => "0{$norm}", 'error' => $e->getMessage(),
                ]);
                $this->error("FAILED 0{$norm} (rolled back): {$e->getMessage()}");
            }
        }

        if ($phones !== null) {
            foreach ($phones as $n) {
                if (($seen[$n] ?? 'not a duplicate') !== 'action') {
                    $this->line("0{$n}: no action — ".match ($seen[$n] ?? null) {
                        null => 'not a duplicate group',
                        'skipped' => 'skipped: not the same person',
                        'skipped_role' => 'skipped: loser holds a role',
                        'no_action_0' => 'no email-less accounts',
                        'no_action_1' => 'only one email-less account left',
                    });
                }
            }
        }

        $this->printSummary($stats, $commit, $failures, $auditPath, $roleSkipped);

        Log::channel('scheduler')->info('users:archive-phone-duplicates completed', [
            'commit' => $commit, 'limit' => $limit, 'phones' => $phones,
            'groups' => $stats['groups'], 'archived' => $stats['losers'], 'failed' => $stats['failed'],
            'audit_file' => $commit && ($stats['groups'] + $stats['failed']) > 0 ? $auditPath : null,
        ]);

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function tally(array &$stats, array $g, bool $promoted): void
    {
        $stats['groups']++;
        $stats['promotions'] += $promoted ? 1 : 0;
        foreach ($g['losers'] as $l) {
            $stats['losers']++;
            foreach ($l['plan']['counts'] as $type => $n) {
                $stats['rows'][$type] = ($stats['rows'][$type] ?? 0) + $n;
            }
            if ($l['plan']['rcu']) {
                $stats['rcu'][$l['plan']['rcu']['action']]++;
            }
        }
    }

    private function describe(array $g, string $mode): string
    {
        $losers = array_map(fn ($l) => 'DB-'.$l['a']['id'], $g['losers']);
        $moves = array_merge(...array_map(fn ($l) => $l['plan']['moves'], $g['losers']));

        return sprintf('  [%s] 0%s keep DB-%d, archive %s%s',
            $mode, $g['norm'], $g['winner']['id'], implode(', ', $losers),
            $moves ? ' | move: '.implode('; ', $moves) : '');
    }

    private function appendAudit(string $path, array $entry): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, json_encode($entry, JSON_UNESCAPED_SLASHES).PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function printSummary(array $s, bool $commit, array $failures, string $auditPath, array $roleSkipped): void
    {
        $verb = $commit ? 'Processed' : 'Would process';

        $this->newLine();
        $this->line("{$verb}: {$s['groups']} group(s), ".($commit ? 'archived' : 'would archive')." {$s['losers']} account(s)");
        foreach ($s['rows'] as $type => $n) {
            $this->line(sprintf('  %-20s %d rows %s', $type, $n, $commit ? 'moved' : 'to move'));
        }
        $this->line("  RCU moved to winner without one: {$s['rcu']['moved']}; conflicts — took loser's newer: {$s['rcu']['took_newer']}, kept winner's: {$s['rcu']['kept_winner']}");
        $this->line('  winners '.($commit ? 'promoted' : 'that would be promoted').": {$s['promotions']}");

        if ($roleSkipped) {
            $this->newLine();
            $this->warn('Skipped, not attempted — loser holds a role (resolve by hand): '.count($roleSkipped).' group(s)');
            foreach ($roleSkipped as $line) {
                $this->line("  {$line}");
            }
        }

        if ($failures) {
            $this->newLine();
            $this->error("Failed and rolled back: {$s['failed']} group(s)");
            foreach ($failures as $f) {
                $this->line("  {$f}");
            }
            if ($s['groups'] === 0) {
                $this->line("  Failures recorded in {$auditPath}");
            }
        }

        // The JSONL file only exists once a group was committed or failed.
        if ($commit && $s['groups'] > 0) {
            $this->newLine();
            $this->info("Audit: `logs` table (actions user_phone_duplicate_merged / user_phone_duplicate_archived) and {$auditPath}");
        }
    }
}
