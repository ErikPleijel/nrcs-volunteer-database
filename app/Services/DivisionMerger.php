<?php

namespace App\Services;

use App\Models\Log as AuditLog;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Merges one division into another in an already-populated database: every
 * record that points at $fromId is moved to $toId, then $fromId is
 * deleted. Used by the data migration for DivisionMerges; a fresh legacy
 * import maps the ids itself and never creates the merged-away division.
 *
 * Covers every place a division id is stored:
 *   users, red_cross_units, membership_payments, donations, activities,
 *   trainings, logs (plain division_id columns);
 *   messaging_campaigns (scope_id when scope_level = 'division', and the
 *   saved filter's division_id);
 *   stats_snapshots (one row per date/branch/division — rows are added
 *   into the target's row for the same date, not just relabelled).
 *
 * Payments keep their stored amounts and shares; only the division they
 * are counted under changes (same branch).
 */
class DivisionMerger
{
    /** Tables with a plain division_id column. */
    public const DIVISION_ID_TABLES = [
        'users',
        'red_cross_units',
        'membership_payments',
        'donations',
        'activities',
        'trainings',
        'logs',
    ];

    /** stats_snapshots count columns, added together when two rows meet. */
    private const SNAPSHOT_COUNTS = [
        'pending_engagement', 'active', 'dormant', 'archived',
        'members_total', 'members_men', 'members_women',
        'volunteers_total', 'volunteers_men', 'volunteers_women',
    ];

    /**
     * @return array<string, int> rows changed per table; empty when $fromId
     *                            no longer exists (already merged)
     *
     * @throws InvalidArgumentException when $toId does not exist or is in another branch
     */
    public function merge(int $fromId, int $toId): array
    {
        return DB::transaction(function () use ($fromId, $toId) {
            $from = DB::table('divisions')->where('id', $fromId)->lockForUpdate()->first();
            if ($from === null) {
                return [];
            }

            $to = DB::table('divisions')->where('id', $toId)->first();
            if ($to === null) {
                throw new InvalidArgumentException("Cannot merge division {$fromId}: target division {$toId} does not exist.");
            }
            if ((int) $from->branch_id !== (int) $to->branch_id) {
                throw new InvalidArgumentException("Cannot merge division {$fromId} into {$toId}: they are in different branches.");
            }

            $counts = [];
            foreach (self::DIVISION_ID_TABLES as $table) {
                $counts[$table] = DB::table($table)->where('division_id', $fromId)->update(['division_id' => $toId]);
            }

            $counts['messaging_campaigns.scope_id'] = DB::table('messaging_campaigns')
                ->where('scope_level', 'division')
                ->where('scope_id', $fromId)
                ->update(['scope_id' => $toId]);
            $counts['messaging_campaigns.filter_json'] = $this->mergeCampaignFilters($fromId, $toId);
            $counts['stats_snapshots'] = $this->mergeSnapshots($fromId, $toId);

            DB::table('divisions')->where('id', $fromId)->delete();

            AuditLog::write(
                'division_merged',
                null,
                null,
                ['division_id' => $fromId, 'name' => $from->name],
                ['division_id' => $toId, 'name' => $to->name, 'moved' => $counts],
                sprintf('Division "%s" (#%d) merged into "%s" (#%d).', $from->name, $fromId, $to->name, $toId)
            );

            return $counts;
        });
    }

    /**
     * A saved campaign filter stores the division as "division_id" (a
     * string from the form). Only exact matches are changed.
     */
    private function mergeCampaignFilters(int $fromId, int $toId): int
    {
        $changed = 0;

        $campaigns = DB::table('messaging_campaigns')
            ->where('filter_json', 'like', '%division_id%')
            ->get(['id', 'filter_json']);

        foreach ($campaigns as $campaign) {
            $filter = json_decode((string) $campaign->filter_json, true);
            if (! is_array($filter) || ! array_key_exists('division_id', $filter) || (string) $filter['division_id'] !== (string) $fromId) {
                continue;
            }

            $filter['division_id'] = is_int($filter['division_id']) ? $toId : (string) $toId;
            DB::table('messaging_campaigns')->where('id', $campaign->id)->update(['filter_json' => json_encode($filter)]);
            $changed++;
        }

        return $changed;
    }

    /**
     * Moves $fromId's snapshot rows to $toId. Where $toId already has a row
     * for the same date and branch, the counts are added together (the
     * average days inactive weighted by each row's dormant count) and the
     * $fromId row is deleted.
     */
    private function mergeSnapshots(int $fromId, int $toId): int
    {
        $rows = DB::table('stats_snapshots')->where('division_id', $fromId)->get();

        foreach ($rows as $row) {
            $target = DB::table('stats_snapshots')
                ->where('snapshot_date', $row->snapshot_date)
                ->where('branch_id', $row->branch_id)
                ->where('division_id', $toId)
                ->first();

            if ($target === null) {
                DB::table('stats_snapshots')->where('id', $row->id)->update(['division_id' => $toId]);

                continue;
            }

            $merged = [];
            foreach (self::SNAPSHOT_COUNTS as $column) {
                $merged[$column] = (int) $target->{$column} + (int) $row->{$column};
            }

            $merged['dormant_avg_days_inactive'] = $this->weightedAverage([
                [$target->dormant_avg_days_inactive, (int) $target->dormant],
                [$row->dormant_avg_days_inactive, (int) $row->dormant],
            ]);
            // Backfilled if either half was.
            $merged['is_backfilled'] = (bool) $target->is_backfilled || (bool) $row->is_backfilled;

            DB::table('stats_snapshots')->where('id', $target->id)->update($merged);
            DB::table('stats_snapshots')->where('id', $row->id)->delete();
        }

        return $rows->count();
    }

    /**
     * Average of [value, weight] pairs, ignoring missing values; null when
     * nothing is left to average. One decimal, like the column.
     */
    private function weightedAverage(array $pairs): ?float
    {
        $sum = 0.0;
        $weight = 0;
        foreach ($pairs as [$value, $count]) {
            if ($value !== null && $count > 0) {
                $sum += (float) $value * $count;
                $weight += $count;
            }
        }

        return $weight === 0 ? null : round($sum / $weight, 1);
    }
}
