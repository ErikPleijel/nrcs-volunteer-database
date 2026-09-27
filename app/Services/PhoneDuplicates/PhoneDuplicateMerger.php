<?php

namespace App\Services\PhoneDuplicates;

use App\Models\Log as AuditLog;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies one PhoneDuplicateAnalyzer 'action' group: moves every planned
 * record from each loser to the winner, applies the winner's resulting RCU,
 * promotes a qualifying pending winner and archives the losers — all in one
 * transaction, so a failure leaves the group exactly as it was.
 *
 * Before writing, the group is re-checked under row locks against what the
 * analysis saw; anything that changed since (an email added, a record gone,
 * a different RCU) aborts the group rather than acting on stale facts.
 */
class PhoneDuplicateMerger
{
    /**
     * @return array audit entry: enough to reverse the group by hand
     */
    public function merge(array $group): array
    {
        return DB::transaction(function () use ($group) {
            $winnerFacts = $group['winner'];
            $ids = [$winnerFacts['id'], ...array_map(fn ($l) => $l['a']['id'], $group['losers'])];
            $users = User::whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

            $this->assertUnchanged($group, $users);

            $winner = $users[$winnerFacts['id']];
            $winnerBefore = $winner->only(['lifecycle_status', 'red_cross_unit_id', 'assigned_rcu_date', 'assigned_rcu_by_id']);
            $audit = [
                'phone' => '0'.$group['norm'],
                'winner_id' => $winner->id,
                'winner_before' => $winnerBefore,
                'losers' => [],
            ];

            foreach ($group['losers'] as $l) {
                $loser = $users[$l['a']['id']];
                $moved = $this->moveRecords($l['plan']['rows'], $loser->id, $winner->id);
                $audit['losers'][] = [
                    'loser_id' => $loser->id,
                    'lifecycle_before' => $loser->lifecycle_status,
                    'moved' => $moved,
                    'rcu' => $l['plan']['rcu'],
                ];
            }

            $final = $group['final'];
            $rcuAfter = [
                'red_cross_unit_id' => $final['rcu'],
                'assigned_rcu_date' => $final['rcu_date'],
                'assigned_rcu_by_id' => $final['rcu_by'],
            ];
            if ($rcuAfter !== $this->rcuFields($winner)) {
                $winner->forceFill($rcuAfter)->save();
            }

            $audit['promoted'] = $winner->refresh()->promoteFromPendingIfQualified();
            $audit['winner_after'] = $winner->only(['lifecycle_status', 'red_cross_unit_id', 'assigned_rcu_date', 'assigned_rcu_by_id']);

            foreach ($audit['losers'] as $entry) {
                $this->archiveLoser($users[$entry['loser_id']], $winner, $entry, $audit['phone']);
            }

            AuditLog::write(
                'user_phone_duplicate_merged',
                $winner,
                ['branch_id' => $winner->branch_id, 'division_id' => $winner->division_id],
                $winnerBefore,
                $audit['winner_after'] + [
                    'archived_user_ids' => array_column($audit['losers'], 'loser_id'),
                    'promoted' => $audit['promoted'],
                    'phone' => $audit['phone'],
                ],
                "DB-{$winner->id} kept as phone-duplicate winner for {$audit['phone']}; "
                    .count($audit['losers']).' duplicate account(s) archived into it.'
            );

            return $audit;
        });
    }

    /**
     * Reassign the planned rows. Each update is guarded by the loser's
     * user_id and must touch exactly the planned rows, or the group aborts.
     *
     * @return array<string, int[]> table => ids (or pivot keys) moved
     */
    protected function moveRecords(array $rows, int $loserId, int $winnerId): array
    {
        $moved = [];
        $tables = PhoneDuplicateAnalyzer::TABLES + PhoneDuplicateAnalyzer::EXTRA_TABLES;

        foreach ($rows as $type => $keys) {
            [$table, $key] = $tables[$type];
            $n = DB::table($table)
                ->where('user_id', $loserId)
                ->whereIn($key, $keys)
                ->update(['user_id' => $winnerId]);

            if ($n !== count($keys)) {
                throw new RuntimeException("{$table}: expected to move ".count($keys)." row(s) from DB-{$loserId}, moved {$n} — data changed since analysis.");
            }
            $moved[$table] = $keys;
        }

        return $moved;
    }

    /**
     * Archive the same way the admin edit and self-archive paths do:
     * lifecycle_status only (no legacy is_inactive/deactivated_* writes),
     * plus an audit log entry.
     */
    protected function archiveLoser(User $loser, User $winner, array $entry, string $phone): void
    {
        $loser->lifecycle_status = 'archived';
        $loser->save();

        AuditLog::write(
            'user_phone_duplicate_archived',
            $loser,
            ['branch_id' => $loser->branch_id, 'division_id' => $loser->division_id],
            ['lifecycle_status' => $entry['lifecycle_before']],
            [
                'lifecycle_status' => 'archived',
                'merged_into_user_id' => $winner->id,
                'moved' => $entry['moved'],
                'rcu' => $entry['rcu'],
                'phone' => $phone,
            ],
            "DB-{$loser->id} archived as a phone duplicate of DB-{$winner->id} ({$phone})."
        );
    }

    /** RCU columns normalised to the analyzer's types (int|null, Y-m-d|null, int|null). */
    private function rcuFields(User $user): array
    {
        $id = $user->getAttributes()['red_cross_unit_id'] ?? null;
        $date = $user->getAttributes()['assigned_rcu_date'] ?? null;
        $by = $user->getAttributes()['assigned_rcu_by_id'] ?? null;

        return [
            'red_cross_unit_id' => $id === null ? null : (int) $id,
            'assigned_rcu_date' => $date === null ? null : substr((string) $date, 0, 10),
            'assigned_rcu_by_id' => $by === null ? null : (int) $by,
        ];
    }

    private function assertUnchanged(array $group, $users): void
    {
        foreach ([$group['winner'], ...array_column($group['losers'], 'a')] as $facts) {
            $user = $users[$facts['id']] ?? null;

            if (! $user) {
                throw new RuntimeException("DB-{$facts['id']} no longer exists.");
            }
            if (trim((string) $user->email) !== '') {
                throw new RuntimeException("DB-{$user->id} now has an email; emailed accounts are never touched.");
            }
            if (PhoneNumber::normalize((string) $user->telephone1) !== (string) $group['norm']) {
                throw new RuntimeException("DB-{$user->id} Telephone1 changed since analysis.");
            }
            if ($this->rcuFields($user)['red_cross_unit_id'] !== $facts['rcu_now']) {
                throw new RuntimeException("DB-{$user->id} RCU changed since analysis.");
            }
        }

        foreach (array_column($group['losers'], 'a') as $facts) {
            $loser = $users[$facts['id']];
            if ($loser->lifecycle_status === 'archived') {
                throw new RuntimeException("DB-{$loser->id} was archived since analysis.");
            }
            if ($loser->is_super_admin || $loser->roles()->exists()) {
                throw new RuntimeException("DB-{$loser->id} holds an admin role; archive it by hand if intended.");
            }
        }
    }
}
