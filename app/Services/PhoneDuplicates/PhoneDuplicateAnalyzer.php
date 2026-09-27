<?php

namespace App\Services\PhoneDuplicates;

use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;

/**
 * Read-only analysis of accounts that share a Telephone1, shared by
 * users:report-phone-duplicates (dry-run CSV) and users:archive-phone-duplicates
 * (the writes), so both always agree on groups, winners and moves.
 *
 * Groups: users whose Telephone1 normalises (PhoneNumber::normalize(), the
 * same rule as phone login) to the same number of 7+ digits.
 *
 * Same person: every account has the same soundex(first)+soundex(last) pair
 * (order-insensitive), at most one distinct gender and at most one distinct
 * branch. Anything else is skipped — shared household/registrar/placeholder
 * numbers, not one person registering twice.
 *
 * Only EMAIL-LESS, non-archived accounts are ever candidates: an account with an email logs
 * in by email (see LoginController), so it neither collides on phone login
 * nor gets archived. With 2+ email-less accounts in a same-person group, the
 * strongest record profile wins (more record types, then payment > RCU >
 * training > activity > ID card > task force > donation > certificate), then
 * latest last_login_at, then highest id (users:handle-duplicates precedent).
 *
 * A group where an account holding an admin role (or super admin) would be
 * archived is skipped outright — like self-archive, which refuses role
 * holders — and is left for a person to resolve. A role holder that WINS is
 * fine: it keeps its account and role and only gains the losers' records.
 *
 * Every live record of a losing account moves to the winner; soft-deleted
 * rows stay behind. When winner and loser sit in different current RCUs, the
 * winner ends up with whichever assignment has the later assigned_rcu_date
 * (a tie, or no dates, keeps the winner's own).
 */
class PhoneDuplicateAnalyzer
{
    /** Record types, strongest first — the ranking priority. */
    public const RECORDS = ['payment', 'rcu', 'training', 'activity', 'id_card', 'task_force', 'donation', 'certificate'];

    /** Record type => [table, key the move is expressed in, live-row filter]; RCU lives on users. */
    public const TABLES = [
        'payment' => ['membership_payments', 'id', 'is_deleted'],
        'donation' => ['donations', 'id', 'is_deleted'],
        'training' => ['trainings', 'id', 'is_deleted'],
        'activity' => ['activities', 'id', 'is_deleted'],
        'id_card' => ['id_card_prints', 'id', 'deleted_at'],
        'certificate' => ['certificates_print', 'id', 'deleted_at'],
        'task_force' => ['task_force_members', 'task_force_id', null],
    ];

    /** Moved alongside, but not a ranking signal. */
    public const EXTRA_TABLES = [
        'payment_transaction' => ['payment_transactions', 'id', null],
        'organisation' => ['organisation_user', 'organisation_id', null],
    ];

    /** Unique (key, user_id) pivots: a link the winner already has is not moved. */
    public const PIVOTS = ['task_force', 'organisation'];

    /**
     * Normalised Telephone1 => [user ids], duplicates only, in first-id order.
     *
     * @param  string[]|null  $onlyNorms  restrict to these normalised numbers
     * @return array<string, int[]>
     */
    public function groups(?array $onlyNorms = null): array
    {
        $byNorm = [];
        $only = $onlyNorms === null ? null : array_flip($onlyNorms);

        DB::table('users')
            ->select('id', 'telephone1')
            ->whereNotNull('telephone1')
            ->where('telephone1', '<>', '')
            ->chunkById(5000, function ($rows) use (&$byNorm, $only) {
                foreach ($rows as $r) {
                    $norm = PhoneNumber::normalize((string) $r->telephone1);
                    if (strlen($norm) >= 7 && ($only === null || isset($only[$norm]))) {
                        $byNorm[$norm][] = (int) $r->id;
                    }
                }
            });

        return array_filter($byNorm, fn ($ids) => count($ids) > 1);
    }

    /**
     * Account facts plus the live rows each account owns per table.
     *
     * @param  int[]  $ids
     * @return array<int, array>
     */
    public function accounts(array $ids): array
    {
        $accounts = [];
        $today = now()->toDateString();

        foreach (array_chunk($ids, 2000) as $chunk) {
            $rows = [];
            foreach (self::TABLES + self::EXTRA_TABLES as $type => [$table, $key, $live]) {
                $rows[$type] = DB::table($table)
                    ->whereIn('user_id', $chunk)
                    ->when($live === 'is_deleted', fn ($q) => $q->whereRaw('COALESCE(is_deleted, 0) = 0'))
                    ->when($live === 'deleted_at', fn ($q) => $q->whereNull('deleted_at'))
                    ->orderBy($key)
                    ->get(['user_id', $key.' as k'])
                    ->groupBy('user_id')
                    ->map(fn ($g) => $g->pluck('k')->map(fn ($k) => (int) $k)->all());
            }

            $currentFee = DB::table('membership_payments')
                ->whereIn('user_id', $chunk)
                ->whereRaw('COALESCE(is_deleted, 0) = 0')
                ->where('expiry_date', '>=', $today)
                ->whereNull('organisation_id')
                ->whereNull('red_cross_unit_id')
                ->groupBy('user_id')
                ->pluck(DB::raw('COUNT(*)'), 'user_id');
            $rcuActivities = DB::table('activities')
                ->whereIn('user_id', $chunk)
                ->whereRaw('COALESCE(is_deleted, 0) = 0')
                ->where('assignable_type', 'like', '%RedCrossUnit')
                ->groupBy('user_id')
                ->pluck(DB::raw('COUNT(*)'), 'user_id');
            $roleHolders = DB::table('model_has_roles')
                ->where('model_type', User::class)
                ->whereIn('model_id', $chunk)
                ->distinct()
                ->pluck('model_id')
                ->flip();

            $users = DB::table('users')->whereIn('id', $chunk)->get([
                'id', 'first_name', 'last_name', 'gender', 'branch_id', 'email', 'telephone1', 'lifecycle_status',
                'created_at', 'last_login_at', 'red_cross_unit_id', 'assigned_rcu_date', 'assigned_rcu_by_id', 'is_super_admin',
            ]);

            foreach ($users as $u) {
                $id = (int) $u->id;
                $own = [];
                foreach (array_keys(self::TABLES + self::EXTRA_TABLES) as $type) {
                    $own[$type] = $rows[$type][$id] ?? [];
                }
                $rcuActs = (int) ($rcuActivities[$id] ?? 0);

                $counts = array_map('count', array_intersect_key($own, self::TABLES));
                // Current or historical: assigned now, ever stamped with an
                // assignment date, or logged activity against an RCU.
                $counts['rcu'] = ($u->red_cross_unit_id !== null || $u->assigned_rcu_date !== null || $rcuActs > 0)
                    ? max(1, $rcuActs) : 0;

                $accounts[$id] = [
                    'id' => $id,
                    'first_name' => (string) $u->first_name,
                    'last_name' => (string) $u->last_name,
                    'gender' => $u->gender,
                    'branch_id' => $u->branch_id,
                    'has_email' => trim((string) $u->email) !== '',
                    'telephone1' => $u->telephone1,
                    'lifecycle_status' => $u->lifecycle_status,
                    'has_role' => isset($roleHolders[$id]) || (bool) $u->is_super_admin,
                    'created_at' => $u->created_at,
                    'last_login_at' => $u->last_login_at,
                    'rcu_now' => $u->red_cross_unit_id !== null ? (int) $u->red_cross_unit_id : null,
                    'rcu_date' => $u->assigned_rcu_date,
                    'rcu_by' => $u->assigned_rcu_by_id !== null ? (int) $u->assigned_rcu_by_id : null,
                    'rcu_activities' => $rcuActs,
                    'current_fee' => (int) ($currentFee[$id] ?? 0),
                    'rows' => $own,
                    'counts' => $counts,
                    'records' => array_values(array_filter(self::RECORDS, fn ($t) => $counts[$t] > 0)),
                ];
            }
        }

        return $accounts;
    }

    /**
     * Decide one group. 'verdict' is skipped | no_action_0 | no_action_1 |
     * skipped_role | action;
     * for 'action' the result carries the winner, basis, each loser's reason
     * and move plan, and the winner's resulting RCU/fee state.
     */
    public function analyse(string $norm, array $members): array
    {
        // Archived accounts are already dealt with (e.g. a previous --commit run)
        // and no longer collide on phone login, so they are never candidates.
        $phoneOnly = array_values(array_filter($members, fn ($a) => ! $a['has_email'] && $a['lifecycle_status'] !== 'archived'));
        $result = ['norm' => $norm, 'members' => $members, 'size' => count($members), 'no_email' => count($phoneOnly)];

        if ($skip = $this->notSamePersonReasons($members)) {
            return $result + ['verdict' => 'skipped', 'skip' => $skip];
        }
        if (count($phoneOnly) <= 1) {
            return $result + ['verdict' => count($phoneOnly) === 0 ? 'no_action_0' : 'no_action_1'];
        }
        usort($phoneOnly, fn ($a, $b) => $this->compare($a, $b));
        $winner = $phoneOnly[0];

        if ($holders = array_values(array_filter(array_slice($phoneOnly, 1), fn ($a) => $a['has_role']))) {
            return $result + ['verdict' => 'skipped_role', 'role_holders' => array_column($holders, 'id')];
        }

        // Plan moves in order so each loser sees the winner's state after
        // earlier losers' records (RCU, task forces, organisations) moved.
        $state = [
            'rcu' => $winner['rcu_now'],
            'rcu_date' => $winner['rcu_date'],
            'rcu_by' => $winner['rcu_by'],
            'links' => ['task_force' => $winner['rows']['task_force'], 'organisation' => $winner['rows']['organisation']],
            'fee' => $winner['current_fee'] > 0,
        ];
        $losers = [];
        foreach (array_slice($phoneOnly, 1) as $a) {
            $losers[] = [
                'a' => $a,
                'reason' => $this->loserReason($a, $winner),
                'plan' => $this->planMoves($a, $state),
                'lacked' => array_values(array_diff($a['records'], $winner['records'])),
            ];
        }

        return $result + [
            'verdict' => 'action',
            'winner' => $winner,
            'basis' => $this->winnerBasis($phoneOnly[0], $phoneOnly[1]),
            'losers' => $losers,
            'final' => $state,
            'promotable' => $winner['lifecycle_status'] === 'pending_engagement'
                && $winner['rcu_now'] === null && $winner['current_fee'] === 0
                && ($state['rcu'] !== null || $state['fee']),
        ];
    }

    /** Reasons a group is NOT one person; empty when it is. */
    private function notSamePersonReasons(array $members): array
    {
        $names = $genders = $branches = [];
        foreach ($members as $a) {
            $pair = [soundex($a['first_name']), soundex($a['last_name'])];
            sort($pair);
            $names[implode('|', $pair)] = true;
            if ($a['gender'] !== null) {
                $genders[$a['gender']] = true;
            }
            if ($a['branch_id'] !== null) {
                $branches[$a['branch_id']] = true;
            }
        }

        return array_keys(array_filter([
            'names differ' => count($names) > 1,
            'genders differ' => count($genders) > 1,
            'branches differ' => count($branches) > 1,
        ]));
    }

    /** Record profile, comparable: number of record types, then presence in priority order. */
    private function strength(array $a): array
    {
        return [count($a['records']), ...array_map(fn ($t) => $a['counts'][$t] > 0, self::RECORDS)];
    }

    /** Sort comparator: strongest account first. */
    private function compare(array $a, array $b): int
    {
        return [$this->strength($b), $b['last_login_at'] !== null, (string) $b['last_login_at'], $b['id']]
            <=> [$this->strength($a), $a['last_login_at'] !== null, (string) $a['last_login_at'], $a['id']];
    }

    /** Which rule actually separated the winner from the runner-up. */
    private function winnerBasis(array $w, array $r): string
    {
        if ($w['records'] && ! $r['records']) {
            return 'records';
        }
        if ($this->strength($w) !== $this->strength($r)) {
            return 'record strength';
        }

        return $w['records'] ? 'recency (equal records)' : 'recency (all empty)';
    }

    private function loserReason(array $a, array $w): string
    {
        if ($this->strength($w) !== $this->strength($a)) {
            return ($a['records'] ? 'weaker records ('.implode(', ', $a['records']).')' : 'no records')
                .' vs winner ('.implode(', ', $w['records']).')';
        }

        $prefix = $a['records'] ? 'same record types; ' : 'neither has records; ';

        if ($w['last_login_at'] !== null && $a['last_login_at'] === null) {
            return $prefix.'never logged in (winner last login '.$w['last_login_at'].')';
        }
        if ($a['last_login_at'] !== null && $a['last_login_at'] !== $w['last_login_at']) {
            return $prefix.'older last login ('.$a['last_login_at'].' < '.$w['last_login_at'].')';
        }

        return $prefix.'older account (lower id, no login to compare)';
    }

    /**
     * What moves from loser $a to the winner. $state is the winner's state
     * so far and is updated in place (earlier losers' moves count).
     *
     * 'rows' is type => keys to reassign (ids, or task_force_id/organisation_id
     * for pivots). 'rcu' is null, or ['action' => moved|took_newer|kept_winner,
     * 'from' => [...], 'to' => [...]] where from/to are the winner's RCU
     * fields before/after this loser.
     *
     * @return array{rows: array<string,int[]>, moves: string[], counts: array<string,int>, rcu: ?array, dropped: int}
     */
    private function planMoves(array $a, array &$state): array
    {
        $rows = [];
        $moves = [];
        $counts = [];
        $dropped = 0;

        foreach (self::TABLES + self::EXTRA_TABLES as $type => [$table, $key]) {
            $keys = $a['rows'][$type];
            if (in_array($type, self::PIVOTS, true)) {
                $new = array_values(array_diff($keys, $state['links'][$type]));
                $dropped += count($keys) - count($new);
                $state['links'][$type] = array_merge($state['links'][$type], $new);
                $keys = $new;
            }
            if (! $keys) {
                continue;
            }
            $rows[$type] = $keys;
            $counts[$type] = count($keys);
            $moves[] = in_array($type, self::PIVOTS, true)
                ? "{$table}.{$key} ".implode(',', $keys)
                : "{$table} #".implode(',#', $keys);
        }

        if ($a['current_fee'] > 0) {
            $state['fee'] = true;
        }

        $rcu = null;
        if ($a['rcu_now'] !== null && $a['rcu_now'] !== $state['rcu']) {
            $from = ['red_cross_unit_id' => $state['rcu'], 'assigned_rcu_date' => $state['rcu_date'], 'assigned_rcu_by_id' => $state['rcu_by']];
            $loserFields = ['red_cross_unit_id' => $a['rcu_now'], 'assigned_rcu_date' => $a['rcu_date'], 'assigned_rcu_by_id' => $a['rcu_by']];

            if ($state['rcu'] === null) {
                $action = 'moved';
            } else {
                // Later assignment wins; null dates sort oldest; a tie keeps the winner's.
                $action = (string) $a['rcu_date'] > (string) $state['rcu_date'] ? 'took_newer' : 'kept_winner';
            }
            $to = $action === 'kept_winner' ? $from : $loserFields;

            $rcu = ['action' => $action, 'from' => $from, 'to' => $to, 'loser' => $loserFields];
            [$state['rcu'], $state['rcu_date'], $state['rcu_by']] = [$to['red_cross_unit_id'], $to['assigned_rcu_date'], $to['assigned_rcu_by_id']];

            $moves[] = match ($action) {
                'moved' => "users.red_cross_unit_id {$a['rcu_now']} (assigned ".($a['rcu_date'] ?? '?').')',
                'took_newer' => "RCU conflict: winner takes newer RCU {$a['rcu_now']} (".($a['rcu_date'] ?? '?').") over {$from['red_cross_unit_id']} (".($from['assigned_rcu_date'] ?? '?').')',
                'kept_winner' => "RCU conflict: winner keeps RCU {$from['red_cross_unit_id']} (".($from['assigned_rcu_date'] ?? '?').") over {$a['rcu_now']} (".($a['rcu_date'] ?? '?').', not newer)',
            };
        }

        return ['rows' => $rows, 'moves' => $moves, 'counts' => $counts, 'rcu' => $rcu, 'dropped' => $dropped];
    }
}
