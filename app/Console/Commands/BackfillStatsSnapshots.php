<?php

namespace App\Console\Commands;

use App\Models\StatsSnapshot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillStatsSnapshots extends Command
{
    protected $signature = 'stats:backfill
        {--from= : Start date (Y-m-d), required — will be normalized to the first of that month}
        {--to= : End date (Y-m-d), optional — defaults to the first of the current month}
        {--dry-run : Report what would be written without writing}';

    protected $description = 'One-time historical backfill of monthly stats snapshots (members + volunteers only).';

    public function handle(): int
    {
        // --- Validate and normalise --from ---
        $fromRaw = $this->option('from');
        if (empty($fromRaw)) {
            $this->error('--from is required. Example: --from=2024-01-01');
            return self::FAILURE;
        }
        try {
            $from = Carbon::createFromFormat('Y-m-d', $fromRaw)->startOfMonth();
        } catch (\Throwable) {
            $this->error("Cannot parse --from \"{$fromRaw}\". Use Y-m-d format, e.g. 2024-01-01.");
            return self::FAILURE;
        }

        // --- Validate and normalise --to (defaults to first of current month) ---
        $toRaw = $this->option('to');
        if ($toRaw) {
            try {
                $to = Carbon::createFromFormat('Y-m-d', $toRaw)->startOfMonth();
            } catch (\Throwable) {
                $this->error("Cannot parse --to \"{$toRaw}\". Use Y-m-d format, e.g. 2024-12-01.");
                return self::FAILURE;
            }
        } else {
            $to = now()->startOfMonth();
        }

        if ($from->gt($to)) {
            $this->error("--from ({$from->toDateString()}) is after --to ({$to->toDateString()}).");
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('Dry-run: all queries will run and counts reported, nothing written.');
        }

        $startedAt       = microtime(true);
        $totalMonths     = 0;
        $totalWritten    = 0;
        $totalSkippedLive = 0;

        for ($month = $from->copy(); $month->lte($to); $month->addMonth()) {
            $snapshotDate = $month->toDateString();

            // Payment validity reference: last day of the month, mirroring MembershipStatsService
            // getActiveMembersTrend() / getActiveMembersTrendByGender() which uses LAST_DAY(rm.month_start).
            $lastDay = $month->copy()->endOfMonth()->toDateString();

            // --- (a) Members — point-in-time, conditions mirror MembershipStatsService trend methods ---
            // mp.payment_date <= LAST_DAY(month) AND (expiry_date IS NULL OR expiry_date >= LAST_DAY(month))
            // red_cross_unit_id IS NULL is the user's current state; historical unit membership cannot be
            // reconstructed. Accepted approximation, consistent with the existing trend chart definition.
            $membersResult = DB::table('membership_payments as mp')
                ->join('users as u', 'mp.user_id', '=', 'u.id')
                ->where('mp.is_deleted', 0)
                ->where('mp.approval_status', 'approved')
                ->where('mp.payment_date', '<=', $lastDay)
                ->where(function ($q) use ($lastDay) {
                    $q->whereNull('mp.expiry_date')
                        ->orWhere('mp.expiry_date', '>=', $lastDay);
                })
                ->whereNull('u.red_cross_unit_id')
                ->where('u.is_super_admin', false)
                ->selectRaw("
                    u.branch_id,
                    u.division_id,
                    COUNT(DISTINCT mp.user_id)                                         AS members_total,
                    COUNT(DISTINCT CASE WHEN u.gender = 'male'   THEN mp.user_id END) AS members_men,
                    COUNT(DISTINCT CASE WHEN u.gender = 'female' THEN mp.user_id END) AS members_women
                ")
                ->groupBy('u.branch_id', 'u.division_id')
                ->get()
                ->keyBy(fn ($r) => $r->branch_id . '|' . $r->division_id);

            // --- (b) Volunteers — current canonical definition filtered to joined-by date ---
            // Volunteers who have since left their unit are invisible historically; past figures
            // understated. Accepted approximation.
            $volunteersResult = User::volunteers()
                ->where('is_super_admin', false)
                ->where('assigned_rcu_date', '<=', $snapshotDate)
                ->selectRaw("
                    branch_id,
                    division_id,
                    COUNT(*)               AS volunteers_total,
                    SUM(gender = 'male')   AS volunteers_men,
                    SUM(gender = 'female') AS volunteers_women
                ")
                ->groupBy('branch_id', 'division_id')
                ->get()
                ->keyBy(fn ($r) => $r->branch_id . '|' . $r->division_id);

            $allKeys = collect($membersResult->keys())
                ->merge($volunteersResult->keys())
                ->unique();

            $monthWritten    = 0;
            $monthSkippedLive = 0;

            $processKeys = function () use (
                $allKeys, $snapshotDate, $membersResult, $volunteersResult, $dryRun,
                &$monthWritten, &$monthSkippedLive
            ) {
                foreach ($allKeys as $key) {
                    [$bId, $dId] = array_map(
                        fn ($v) => $v === '' ? null : (int) $v,
                        explode('|', $key)
                    );

                    $existing = StatsSnapshot::where('snapshot_date', $snapshotDate)
                        ->where('branch_id', $bId)
                        ->where('division_id', $dId)
                        ->first();

                    if ($existing && !$existing->is_backfilled) {
                        // Never overwrite a live snapshot with approximated backfill data
                        $monthSkippedLive++;
                        continue;
                    }

                    if (!$dryRun) {
                        $mem = $membersResult->get($key);
                        $vol = $volunteersResult->get($key);

                        StatsSnapshot::updateOrCreate(
                            ['snapshot_date' => $snapshotDate, 'branch_id' => $bId, 'division_id' => $dId],
                            [
                                'members_total'             => $mem?->members_total,
                                'members_men'               => $mem?->members_men,
                                'members_women'             => $mem?->members_women,
                                'volunteers_total'          => $vol?->volunteers_total,
                                'volunteers_men'            => $vol?->volunteers_men,
                                'volunteers_women'          => $vol?->volunteers_women,
                                // Not reconstructable from history — NULL means "unknown", never 0
                                'pending_engagement'        => null,
                                'active'                    => null,
                                'dormant'                   => null,
                                'archived'                  => null,
                                'dormant_avg_days_inactive' => null,
                                'is_backfilled'             => true,
                                'created_at'                => now(),
                            ]
                        );
                    }

                    $monthWritten++;
                }
            };

            if ($dryRun) {
                $processKeys();
            } else {
                DB::transaction($processKeys);
            }

            $totalMonths++;
            $totalWritten     += $monthWritten;
            $totalSkippedLive += $monthSkippedLive;

            $suffix = $monthSkippedLive > 0 ? ", {$monthSkippedLive} live rows skipped" : '';
            $suffix .= $dryRun ? ' (dry-run)' : '';
            $this->line("{$snapshotDate}: {$monthWritten} rows written{$suffix}");
        }

        $elapsed = round(microtime(true) - $startedAt, 2);

        $this->newLine();
        $this->table(
            ['Metric', 'Value'],
            [
                ['Months processed',  $totalMonths],
                ['Rows written',      $totalWritten],
                ['Live rows skipped', $totalSkippedLive],
                ['Elapsed',           "{$elapsed}s"],
            ]
        );

        if ($dryRun) {
            $this->info('Dry-run complete: no data was written.');
        }

        return self::SUCCESS;
    }
}
