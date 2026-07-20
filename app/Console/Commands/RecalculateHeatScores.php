<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecalculateHeatScores extends Command
{
    protected $signature = 'heat:recalculate {--dry-run : Compute and report without writing}';

    protected $description = 'Compute and store 0–1 heat scores for divisions and branches';

    public function handle(): int
    {
        $startTime = microtime(true);
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[dry-run] No writes will be made.');
        }

        foreach (['division', 'branch'] as $level) {
            $this->processLevel($level, $dryRun);
        }

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->info("Elapsed: {$elapsed}s");

        Log::channel('scheduler')->info('heat:recalculate completed', [
            'dry_run' => $dryRun,
            'elapsed_seconds' => $elapsed,
        ]);

        return self::SUCCESS;
    }

    private function processLevel(string $level, bool $dryRun): void
    {
        $table = $level === 'division' ? 'divisions' : 'branches';
        $col = $level.'_id';
        $windowStart = now()->subMonths(config('heat.window_months'));
        $weights = config('heat.weights');

        $allAreaIds = DB::table($table)->pluck('id')->all();

        $label = $level === 'division' ? 'Divisions' : 'Branches';

        if (empty($allAreaIds)) {
            $this->line("{$label}: none found, skipping.");

            return;
        }

        // Volunteer count per area (canonical scope; super-admins have no area col set)
        $volunteers = \App\Models\User::volunteers()
            ->selectRaw("{$col} as area_id, COUNT(*) as cnt")
            ->whereNotNull($col)
            ->groupBy($col)
            ->pluck('cnt', 'area_id');

        // Hours per area within trailing window
        $hours = DB::table('activities')
            ->join('users', 'activities.user_id', '=', 'users.id')
            ->where('activities.is_deleted', false)
            ->where('activities.approval_status', 'approved') // Phase 2: only approved records are real
            ->where('activities.date', '>=', $windowStart)
            ->selectRaw("users.{$col} as area_id, SUM(activities.hours) as total_hours")
            ->whereNotNull("users.{$col}")
            ->groupBy("users.{$col}")
            ->pluck('total_hours', 'area_id');

        // Training count per area within trailing window
        $trainings = DB::table('trainings')
            ->join('users', 'trainings.user_id', '=', 'users.id')
            ->where('trainings.is_deleted', false)
            ->where('trainings.approval_status', 'approved') // Phase 2: only approved records are real
            ->where('trainings.training_date', '>=', $windowStart)
            ->selectRaw("users.{$col} as area_id, COUNT(*) as cnt")
            ->whereNotNull("users.{$col}")
            ->groupBy("users.{$col}")
            ->pluck('cnt', 'area_id');

        // Raw per-volunteer factors
        $rawHpv = [];
        $rawTpv = [];
        foreach ($allAreaIds as $id) {
            $v = (int) ($volunteers[$id] ?? 0);
            $rawHpv[$id] = $v > 0 ? ((float) ($hours[$id] ?? 0)) / $v : 0.0;
            $rawTpv[$id] = $v > 0 ? ((int) ($trainings[$id] ?? 0)) / $v : 0.0;
        }

        $maxHpv = max($rawHpv ?: [0]) ?: 1;
        $maxTpv = max($rawTpv ?: [0]) ?: 1;

        // Normalize and weight
        $scores = [];
        foreach ($allAreaIds as $id) {
            $nHpv = $rawHpv[$id] / $maxHpv;
            $nTpv = $rawTpv[$id] / $maxTpv;
            $heat = $weights['hours_per_volunteer'] * $nHpv
                        + $weights['trainings_per_volunteer'] * $nTpv;
            $scores[$id] = round($heat, 4);
        }

        // Top 5 for output
        arsort($scores);
        $top5 = array_slice($scores, 0, 5, true);
        $topIds = array_keys($top5);
        $names = DB::table($table)->whereIn('id', $topIds)->pluck('name', 'id');

        $this->info("{$label}: ".count($allAreaIds).' processed');
        $this->line('  Max hours/volunteer:     '.round($maxHpv, 2));
        $this->line('  Max trainings/volunteer: '.round($maxTpv, 4));
        $this->line('  Top 5:');
        foreach ($top5 as $id => $score) {
            $name = $names[$id] ?? "ID {$id}";
            $this->line("    [{$score}] {$name}");
        }

        Log::channel('scheduler')->info('heat:recalculate level processed', [
            'level' => $level,
            'processed' => count($allAreaIds),
            'max_hours_per_volunteer' => round($maxHpv, 2),
            'max_trainings_per_volunteer' => round($maxTpv, 4),
            'dry_run' => $dryRun,
        ]);

        if ($dryRun) {
            return;
        }

        $now = now();
        DB::transaction(function () use ($table, $scores, $now) {
            foreach ($scores as $id => $heat) {
                DB::table($table)->where('id', $id)->update([
                    'heat_score' => $heat,
                    'heat_computed_at' => $now,
                ]);
            }
        });
    }
}
