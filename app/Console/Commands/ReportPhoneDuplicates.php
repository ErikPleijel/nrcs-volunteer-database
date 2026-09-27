<?php

namespace App\Console\Commands;

use App\Services\PhoneDuplicates\PhoneDuplicateAnalyzer;
use Illuminate\Console\Command;

/**
 * Dry-run report for de-duplicating accounts that share a Telephone1.
 * Read-only: runs SELECTs and writes one CSV — never touches the database.
 * The rules live in PhoneDuplicateAnalyzer, shared with the command that
 * performs the writes (users:archive-phone-duplicates).
 */
class ReportPhoneDuplicates extends Command
{
    protected $signature = 'users:report-phone-duplicates
                            {--output= : CSV path (default: storage/app/reports/phone-duplicates-<timestamp>.csv)}
                            {--samples=15 : Number of example groups to print}';

    protected $description = 'Dry-run: report which email-less duplicate-Telephone1 accounts would be archived and which records would move (read-only, writes a CSV only).';

    public function handle(PhoneDuplicateAnalyzer $analyzer): int
    {
        $this->info('[dry-run] Read-only report — nothing is archived, moved or modified.');

        $groups = $analyzer->groups();
        $accounts = $analyzer->accounts(array_merge(...array_values($groups)));

        $s = [
            'groups' => count($groups),
            'accounts' => count($accounts),
            'skipped' => 0,
            'skipped_accounts' => 0,
            'skip_reason' => ['names differ' => 0, 'genders differ' => 0, 'branches differ' => 0],
            'same_person' => 0,
            'no_action_0' => 0,
            'no_action_1' => 0,
            'skipped_role' => 0,
            'action' => 0,
            'emailed_left_alone' => 0,
            'already_archived' => 0,
            'basis' => ['records' => 0, 'record strength' => 0, 'recency (equal records)' => 0, 'recency (all empty)' => 0],
            'archive' => 0,
            'movers' => 0,
            'movers_winner_lacked' => 0,
            'moved_rows' => array_fill_keys(array_keys(PhoneDuplicateAnalyzer::TABLES + PhoneDuplicateAnalyzer::EXTRA_TABLES), 0),
            'rcu' => ['moved' => 0, 'took_newer' => 0, 'kept_winner' => 0],
            'duplicate_links_dropped' => 0,
            'winners_promotable' => 0,
        ];

        $path = $this->option('output') ?: storage_path('app/reports/phone-duplicates-'.now()->format('Ymd_His').'.csv');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        $csv = fopen($path, 'w');
        $this->writeCsv($csv, [
            'group_phone', 'group_size', 'no_email_in_group', 'group_verdict', 'winner_basis',
            'account_id', 'role', 'reason', 'would_move_to_winner', 'types_winner_lacked', 'rcu_conflict',
            'first_name', 'last_name', 'gender', 'branch_id', 'has_email', 'lifecycle_status',
            'created_at', 'last_login_at', 'telephone1_raw',
            'payments', 'current_personal_fee', 'donations', 'trainings', 'rcu_current', 'rcu_assigned_date',
            'rcu_activities', 'activities', 'id_cards', 'task_forces', 'certificates',
        ]);

        $samples = [];

        foreach ($groups as $norm => $ids) {
            $g = $analyzer->analyse((string) $norm, array_map(fn ($id) => $accounts[$id], $ids));
            $row = fn ($a, $verdict, $role, $reason = '', $basis = '', $move = '', $lacked = '', $conflict = '') => $this->writeCsv($csv, [
                '0'.$norm, $g['size'], $g['no_email'], $verdict, $basis, ...$this->accountColumns($a, $role, $reason, $move, $lacked, $conflict),
            ]);

            if ($g['verdict'] === 'skipped') {
                $s['skipped']++;
                $s['skipped_accounts'] += $g['size'];
                foreach ($g['skip'] as $r) {
                    $s['skip_reason'][$r]++;
                }
                foreach ($g['members'] as $a) {
                    $row($a, 'skipped: '.implode(', ', $g['skip']), 'skipped');
                }
                $samples[] = ['kind' => 'skipped'] + $g;

                continue;
            }

            $s['same_person']++;
            foreach ($g['members'] as $a) {
                if ($a['lifecycle_status'] === 'archived') {
                    $s['already_archived']++;
                } elseif ($a['has_email']) {
                    $s['emailed_left_alone']++;
                }
            }

            if ($g['verdict'] === 'skipped_role') {
                $s['skipped_role']++;
                foreach ($g['members'] as $a) {
                    $row($a, 'skipped: loser holds a role', in_array($a['id'], $g['role_holders'], true)
                        ? 'skipped (holds a role)' : ($this->leftAloneRole($a) ?? 'skipped'));
                }

                continue;
            }

            if ($g['verdict'] !== 'action') {
                $s[$g['verdict']]++;
                $verdict = $g['verdict'] === 'no_action_0' ? 'no action: no email-less accounts' : 'no action: only one email-less account';
                foreach ($g['members'] as $a) {
                    $row($a, $verdict, $this->leftAloneRole($a) ?? 'keep (only email-less)');
                }

                continue;
            }

            $s['action']++;
            $s['basis'][$g['basis']]++;
            $s['winners_promotable'] += $g['promotable'] ? 1 : 0;
            $conflicted = false;

            foreach ($g['losers'] as $l) {
                $a = $l['a'];
                $plan = $l['plan'];
                $s['archive']++;
                if ($plan['rows'] || ($plan['rcu'] && $plan['rcu']['action'] !== 'kept_winner')) {
                    $s['movers']++;
                }
                if ($l['lacked']) {
                    $s['movers_winner_lacked']++;
                }
                foreach ($plan['counts'] as $type => $n) {
                    $s['moved_rows'][$type] += $n;
                }
                if ($plan['rcu']) {
                    $s['rcu'][$plan['rcu']['action']]++;
                    $conflicted = $conflicted || $plan['rcu']['action'] !== 'moved';
                }
                $s['duplicate_links_dropped'] += $plan['dropped'];
            }

            foreach ($g['members'] as $a) {
                if ($role = $this->leftAloneRole($a)) {
                    $row($a, 'same person', $role, '', $g['basis']);
                }
            }
            $row($g['winner'], 'same person', 'keep', 'winner', $g['basis']);
            foreach ($g['losers'] as $l) {
                $rcu = $l['plan']['rcu'];
                $row($l['a'], 'same person', 'archive', $l['reason'], $g['basis'],
                    implode('; ', $l['plan']['moves']), implode(', ', $l['lacked']),
                    $rcu && $rcu['action'] !== 'moved' ? $rcu['action'] : '');
            }

            $kind = $conflicted ? 'rcu conflict'
                : (array_filter($g['losers'], fn ($l) => $l['lacked']) ? 'moves' : $g['basis']);
            $samples[] = ['kind' => $kind] + $g;
        }

        fclose($csv);

        $this->printSummary($s, $path);
        $this->printSamples($samples, (int) $this->option('samples'));

        return self::SUCCESS;
    }

    /** Accounts that are never candidates: already archived, or emailed. */
    private function leftAloneRole(array $a): ?string
    {
        return match (true) {
            $a['lifecycle_status'] === 'archived' => 'left alone (archived)',
            $a['has_email'] => 'left alone (has email)',
            default => null,
        };
    }

    private function accountColumns(array $a, string $role, string $reason, string $move, string $lacked, string $conflict): array
    {
        return [
            $a['id'], $role, $reason, $move, $lacked, $conflict,
            $a['first_name'], $a['last_name'], $a['gender'], $a['branch_id'], $a['has_email'] ? 'yes' : 'no', $a['lifecycle_status'],
            $a['created_at'], $a['last_login_at'], $a['telephone1'],
            $a['counts']['payment'], $a['current_fee'], $a['counts']['donation'], $a['counts']['training'],
            $a['rcu_now'] ?? '', $a['rcu_date'], $a['rcu_activities'], $a['counts']['activity'],
            $a['counts']['id_card'], $a['counts']['task_force'], $a['counts']['certificate'],
        ];
    }

    /** escape: '' — RFC 4180 output; a raw Telephone1 ending in a backslash otherwise breaks the row. */
    private function writeCsv($csv, array $fields): void
    {
        fputcsv($csv, $fields, escape: '');
    }

    private function printSummary(array $s, string $path): void
    {
        $this->newLine();
        $this->line("Duplicate groups: {$s['groups']}  ({$s['accounts']} accounts)");
        $this->line("  Not same person, skipped: {$s['skipped']}  ({$s['skipped_accounts']} accounts left alone)");
        foreach ($s['skip_reason'] as $r => $n) {
            $this->line("    {$r}: {$n}");
        }
        $this->line("  Same person: {$s['same_person']}");
        $this->line("    no action — no email-less accounts:   {$s['no_action_0']}");
        $this->line("    no action — only one email-less:      {$s['no_action_1']}");
        $this->line("    skipped — loser holds a role:         {$s['skipped_role']}");
        $this->line("    winner/loser split (2+ email-less):   {$s['action']}");
        $this->line("  In same-person groups:");
        $this->line("    emailed accounts, left alone (never touched): {$s['emailed_left_alone']}");
        $this->line("    already archived (e.g. by previous runs):     {$s['already_archived']}");

        $this->newLine();
        $this->line('Winner decided by:');
        foreach ($s['basis'] as $b => $n) {
            $this->line("  {$b}: {$n}");
        }

        $this->newLine();
        $this->line("Accounts that would be archived (all email-less): {$s['archive']}");

        $this->newLine();
        $this->line("Records that would move to the winner first: {$s['movers']} losing accounts");
        $this->line("  of which hold a record type the winner lacks: {$s['movers_winner_lacked']}");
        foreach ($s['moved_rows'] as $type => $n) {
            $this->line(sprintf('  %-20s %d rows', $type, $n));
        }
        $this->line("  current RCU moved to a winner without one: {$s['rcu']['moved']}");
        $this->line("  duplicate task force / organisation links left behind (winner already linked): {$s['duplicate_links_dropped']}");
        $this->line("  pending winners that would qualify for promotion after the move: {$s['winners_promotable']}");

        $this->newLine();
        $this->line('RCU conflicts (different current RCU; later assigned_rcu_date wins, tie keeps winner\'s):');
        $this->line("  winner takes loser's newer RCU: {$s['rcu']['took_newer']}");
        $this->line("  winner keeps its own RCU:       {$s['rcu']['kept_winner']}");

        $this->newLine();
        $this->info("CSV: {$path}");
    }

    private function printSamples(array $samples, int $limit): void
    {
        if ($limit <= 0) {
            return;
        }

        // A spread across kinds; larger groups first within a kind.
        $quota = ['rcu conflict' => 3, 'moves' => 5, 'record strength' => 2, 'records' => 2,
            'recency (equal records)' => 1, 'recency (all empty)' => 1, 'skipped' => 1];
        usort($samples, fn ($a, $b) => $b['size'] <=> $a['size']);
        $picked = [];
        foreach ($quota as $kind => $n) {
            foreach ($samples as $g) {
                if ($g['kind'] === $kind && $n-- > 0) {
                    $picked[] = $g;
                }
            }
        }

        $this->newLine();
        $this->line('Sample groups:');
        foreach (array_slice($picked, 0, $limit) as $g) {
            $this->newLine();
            $this->line("0{$g['norm']} ({$g['size']} accounts) — {$g['kind']}");
            if ($g['kind'] === 'skipped') {
                $this->line('  skipped: '.implode(', ', $g['skip']));

                continue;
            }
            if ($emailed = $g['size'] - $g['no_email']) {
                $this->line("  ({$emailed} emailed account(s) left alone)");
            }
            $this->line('  KEEP    '.$this->describe($g['winner']));
            foreach ($g['losers'] as $l) {
                $this->line('  ARCHIVE '.$this->describe($l['a']));
                $this->line('          '.$l['reason']);
                if ($l['plan']['moves']) {
                    $this->line('          would move: '.implode('; ', $l['plan']['moves']));
                }
            }
        }
    }

    private function describe(array $a): string
    {
        return sprintf('DB-%d %s %s | %s | branch %s | %s | login %s | records: %s',
            $a['id'], $a['first_name'], $a['last_name'], $a['gender'] ?? '-', $a['branch_id'] ?? '-',
            $a['lifecycle_status'], $a['last_login_at'] ?? 'never',
            $a['records'] ? implode(', ', array_map(fn ($c) => $c.'×'.$a['counts'][$c], $a['records'])) : 'none');
    }
}
