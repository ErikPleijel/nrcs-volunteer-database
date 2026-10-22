<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fills users.optout_token (the token in campaign unsubscribe links, see
 * User::OPTOUT_TOKEN_LENGTH) for users that have none. Anonymised users are
 * skipped: their tokens are cleared on purpose.
 *
 * Only empty values are filled, so it is safe to run again. Called by the
 * migration that added the column and by migrate:old-db right after the
 * users import (on a fresh database the migration finds no users yet).
 * Query-builder writes in chunks: no model events, audit entries or
 * updated_at changes.
 */
class BackfillOptoutTokens extends Command
{
    protected $signature = 'users:backfill-optout-tokens {--chunk=2000}';

    protected $description = 'Give every user without one an optout_token for campaign unsubscribe links.';

    public function handle(): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $filled = 0;

        DB::table('users')
            ->whereNull('optout_token')
            ->whereNull('anonymized_at')
            ->select('id')
            ->chunkById($chunk, function ($rows) use (&$filled) {
                $cases = [];
                $bindings = [];
                foreach ($rows as $row) {
                    $cases[] = 'WHEN ? THEN ?';
                    $bindings[] = $row->id;
                    $bindings[] = Str::random(User::OPTOUT_TOKEN_LENGTH);
                }
                $ids = $rows->pluck('id')->all();

                $filled += DB::update(
                    'UPDATE users SET optout_token = CASE id '.implode(' ', $cases).' END
                     WHERE id IN ('.implode(',', array_fill(0, count($ids), '?')).')',
                    [...$bindings, ...$ids]
                );
            });

        $this->info("optout_token set for {$filled} users.");

        return self::SUCCESS;
    }
}
