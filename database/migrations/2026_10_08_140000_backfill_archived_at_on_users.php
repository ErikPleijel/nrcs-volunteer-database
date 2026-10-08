<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Backfill archived_at (and archived_by_id) for accounts archived before the
 * columns existed. archived_at starts the 7-year anonymization clock, so the
 * rule only ever errs late (Decisions.md 2026-10-08):
 *  - the created_at of the latest archive audit row for the user
 *    (user_self_archived, user_phone_duplicate_archived, user_archived),
 *    with that row's user_id as archived_by_id;
 *  - no such row: the moment this migration runs, archived_by_id null.
 * Only rows still NULL are touched, so re-running changes nothing.
 */
return new class extends Migration
{
    private const ARCHIVE_ACTIONS = ['user_self_archived', 'user_phone_duplicate_archived', 'user_archived'];

    public function up(): void
    {
        $now = now();
        $fromAudit = 0;
        $fromNow = 0;

        DB::table('users')
            ->where('lifecycle_status', 'archived')
            ->whereNull('archived_at')
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($now, &$fromAudit, &$fromNow) {
                $ids = $rows->pluck('id')->all();

                // Latest archive audit row per user (highest id on equal timestamps).
                $latest = DB::table('logs')
                    ->where('subject_type', User::class)
                    ->whereIn('subject_id', $ids)
                    ->whereIn('action', self::ARCHIVE_ACTIONS)
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->get(['subject_id', 'user_id', 'created_at'])
                    ->keyBy('subject_id');

                foreach ($ids as $id) {
                    $row = $latest->get($id);

                    DB::table('users')->where('id', $id)->update([
                        'archived_at' => $row ? $row->created_at : $now,
                        'archived_by_id' => $row?->user_id,
                    ]);

                    $row ? $fromAudit++ : $fromNow++;
                }
            });

        $message = "archived_at backfill: {$fromAudit} from an audit entry, {$fromNow} set to now ({$now}).";
        Log::info($message);

        if (! app()->runningUnitTests()) {
            (new ConsoleOutput)->writeln("  {$message}");
        }
    }

    public function down(): void
    {
        // Irreversible: backfilled dates cannot be told apart from recorded ones.
    }
};
