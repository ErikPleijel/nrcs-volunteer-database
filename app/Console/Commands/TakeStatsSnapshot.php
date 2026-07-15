<?php

namespace App\Console\Commands;

use App\Models\StatsSnapshot;
use App\Models\User;
use Illuminate\Console\Command;

class TakeStatsSnapshot extends Command
{
    protected $signature = 'stats:snapshot {--date= : Snapshot date (Y-m-d), defaults to today}';

    protected $description = 'Record a daily statistics snapshot grouped by branch/division.';

    public function handle(): int
    {
        $date = $this->option('date')
            ? \Carbon\Carbon::createFromFormat('Y-m-d', $this->option('date'))->toDateString()
            : now()->toDateString();

        $startedAt = microtime(true);

        // (a) Lifecycle counts — one query over all non-super-admin users
        $lifecycle = User::query()
            ->where('is_super_admin', false)
            ->selectRaw("
                branch_id, division_id,
                SUM(lifecycle_status = 'pending_engagement') as pending_engagement,
                SUM(lifecycle_status = 'active')             as active,
                SUM(lifecycle_status = 'dormant')            as dormant,
                SUM(lifecycle_status = 'archived')           as archived
            ")
            ->groupBy('branch_id', 'division_id')
            ->get()
            ->keyBy(fn ($r) => $r->branch_id . '|' . $r->division_id);

        // (b) Members — one query using the canonical scope
        $members = User::members()
            ->where('is_super_admin', false)
            ->selectRaw("
                branch_id, division_id,
                COUNT(*)               as members_total,
                SUM(gender = 'male')   as members_men,
                SUM(gender = 'female') as members_women
            ")
            ->groupBy('branch_id', 'division_id')
            ->get()
            ->keyBy(fn ($r) => $r->branch_id . '|' . $r->division_id);

        // (c) Volunteers — same pattern
        $volunteers = User::volunteers()
            ->where('is_super_admin', false)
            ->selectRaw("
                branch_id, division_id,
                COUNT(*)               as volunteers_total,
                SUM(gender = 'male')   as volunteers_men,
                SUM(gender = 'female') as volunteers_women
            ")
            ->groupBy('branch_id', 'division_id')
            ->get()
            ->keyBy(fn ($r) => $r->branch_id . '|' . $r->division_id);

        // (d) Dormant inactivity average
        $dormantInactivity = User::dormant()
            ->where('is_super_admin', false)
            ->whereNotNull('last_activity_at')
            ->selectRaw("branch_id, division_id, AVG(DATEDIFF(NOW(), last_activity_at)) as avg_days")
            ->groupBy('branch_id', 'division_id')
            ->get()
            ->keyBy(fn ($r) => $r->branch_id . '|' . $r->division_id);

        // Merge all keys from the four result sets
        $allKeys = collect()
            ->merge($lifecycle->keys())
            ->merge($members->keys())
            ->merge($volunteers->keys())
            ->merge($dormantInactivity->keys())
            ->unique();

        $written = 0;

        foreach ($allKeys as $key) {
            [$bId, $dId] = array_map(
                fn ($v) => $v === '' ? null : (int) $v,
                explode('|', $key)
            );

            $lc  = $lifecycle->get($key);
            $mem = $members->get($key);
            $vol = $volunteers->get($key);
            $di  = $dormantInactivity->get($key);

            StatsSnapshot::updateOrCreate(
                ['snapshot_date' => $date, 'branch_id' => $bId, 'division_id' => $dId],
                [
                    'pending_engagement'        => $lc?->pending_engagement,
                    'active'                    => $lc?->active,
                    'dormant'                   => $lc?->dormant,
                    'archived'                  => $lc?->archived,
                    'members_total'             => $mem?->members_total,
                    'members_men'               => $mem?->members_men,
                    'members_women'             => $mem?->members_women,
                    'volunteers_total'          => $vol?->volunteers_total,
                    'volunteers_men'            => $vol?->volunteers_men,
                    'volunteers_women'          => $vol?->volunteers_women,
                    'dormant_avg_days_inactive' => $di?->avg_days,
                    'is_backfilled'             => false,
                    'created_at'                => now(),
                ]
            );

            $written++;
        }

        $elapsed = round(microtime(true) - $startedAt, 2);

        $this->info("Snapshot {$date}: {$written} rows written in {$elapsed}s.");

        return self::SUCCESS;
    }
}
