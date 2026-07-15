<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalculateFirstAidFreshness extends Command
{
    protected $signature = 'firstaid:recalculate {--dry-run : Compute and report without writing}';

    protected $description = 'Compute first-aid coverage count and average freshness (days since latest FA training) for branches and divisions';

    public function handle(): int
    {
        $startTime = microtime(true);
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[dry-run] No writes will be made.');
        } else {
            // Correct edit/delete drift first: recompute the denormalized per-user column for
            // ALL users in one set-based UPDATE (stale values are lowered or nulled). The
            // branch/division aggregates below then read this column instead of a trainings join.
            $this->recomputeUserColumn();
        }

        foreach (['division', 'branch'] as $level) {
            $this->processLevel($level, $dryRun);
        }

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->info("Elapsed: {$elapsed}s");

        return self::SUCCESS;
    }

    private function processLevel(string $level, bool $dryRun): void
    {
        $table = $level === 'division' ? 'divisions' : 'branches';
        $col = $level.'_id';
        $label = $level === 'division' ? 'Divisions' : 'Branches';

        $allAreaIds = DB::table($table)->pluck('id')->all();

        if (empty($allAreaIds)) {
            $this->line("{$label}: none found, skipping.");

            return;
        }

        // Aggregate per area: count of first-aiders and average days since their latest FA.
        // Real run reads the denormalized users.last_first_aid_at column (recomputed in handle());
        // dry-run reads the equivalent trainings subquery so it never depends on the column.
        $agg = $this->aggregate($col, ! $dryRun);

        // Build per-area values for ALL areas (count 0 / avg null when absent)
        $values = [];
        foreach ($allAreaIds as $id) {
            $cnt = (int) ($agg[$id]->cnt ?? 0);
            $avgDays = isset($agg[$id]) ? round((float) $agg[$id]->avg_days, 2) : null;
            $values[$id] = ['cnt' => $cnt, 'avg_days' => $avgDays];
        }

        $withFirstAiders = count(array_filter($values, fn ($v) => $v['cnt'] > 0));

        $this->info("{$label}: ".count($allAreaIds)." processed, {$withFirstAiders} with first-aiders");

        // Areas with at least one first-aider, ranked by avg_days
        $ranked = array_filter($values, fn ($v) => $v['avg_days'] !== null);
        uasort($ranked, fn ($a, $b) => $a['avg_days'] <=> $b['avg_days']);

        $names = DB::table($table)->whereIn('id', array_keys($ranked))->pluck('name', 'id');

        $freshest = array_slice($ranked, 0, 5, true);
        $stalest = array_slice(array_reverse($ranked, true), 0, 5, true);

        $this->line('  Freshest 5:');
        $this->renderRanking($freshest, $names);

        $this->line('  Stalest 5:');
        $this->renderRanking($stalest, $names);

        if ($dryRun) {
            return;
        }

        $now = now();
        DB::transaction(function () use ($table, $values, $now) {
            foreach ($values as $id => $v) {
                DB::table($table)->where('id', $id)->update([
                    'first_aid_count' => $v['cnt'],
                    'first_aid_avg_days' => $v['avg_days'],
                    'first_aid_computed_at' => $now,
                ]);
            }
        });
    }

    /**
     * Set-based recompute of users.last_first_aid_at for ALL users (no model hydration).
     * Canonical latest FA = MAX(training_date) of non-deleted trainings whose
     * training_type.is_first_aid = 1; users with none are set to NULL.
     */
    private function recomputeUserColumn(): void
    {
        DB::update('
            UPDATE users u
            LEFT JOIN (
                SELECT t.user_id, MAX(t.training_date) AS last_fa
                FROM trainings t
                JOIN training_types tt ON tt.id = t.training_type_id
                WHERE t.is_deleted = 0 AND tt.is_first_aid = 1 AND t.approval_status = \'approved\'
                GROUP BY t.user_id
            ) x ON x.user_id = u.id
            SET u.last_first_aid_at = x.last_fa
        ');
    }

    /**
     * Per-area first-aider count and average freshness, keyed by area id.
     *
     * The COUNT / AVG(GREATEST(DATEDIFF(...), 0)) formula lives here once; only the row source
     * and the latest-FA date column differ between modes:
     *   $useColumn = true  -> read users.last_first_aid_at (real run, after recomputeUserColumn())
     *   $useColumn = false -> read the equivalent trainings subquery (dry-run, no column dependency)
     */
    private function aggregate(string $col, bool $useColumn)
    {
        if ($useColumn) {
            $base = DB::table('users as u')->whereNotNull('u.last_first_aid_at');
            $dateCol = 'u.last_first_aid_at';
        } else {
            $latest = DB::table('trainings as t')
                ->join('training_types as tt', 'tt.id', '=', 't.training_type_id')
                ->where('t.is_deleted', false)
                ->where('t.approval_status', 'approved') // Phase 2: only approved records are real
                ->where('tt.is_first_aid', true)
                ->groupBy('t.user_id')
                ->selectRaw('t.user_id, MAX(t.training_date) as latest_fa');

            $base = DB::query()->fromSub($latest, 'lfa')
                ->join('users as u', 'u.id', '=', 'lfa.user_id');
            $dateCol = 'lfa.latest_fa';
        }

        return $base
            ->where('u.lifecycle_status', '!=', 'archived')
            ->whereNotNull("u.{$col}")
            ->groupBy("u.{$col}")
            ->selectRaw("u.{$col} as area_id, COUNT(*) as cnt,
                        AVG(GREATEST(DATEDIFF(CURDATE(), {$dateCol}), 0)) as avg_days")
            ->get()->keyBy('area_id');
    }

    private function renderRanking(array $ranking, $names): void
    {
        foreach ($ranking as $id => $v) {
            $name = $names[$id] ?? "ID {$id}";
            $days = $v['avg_days'];
            $months = round($days / 30.44, 1);
            $this->line("    [{$days}d ~{$months}mo] {$name}");
        }
    }
}
