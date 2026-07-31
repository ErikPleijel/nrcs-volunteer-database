<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MaintenanceForceLogout extends Command
{
    protected $signature = 'maintenance:force-logout';
    protected $description = 'Delete all database sessions except those belonging to config(maintenance.allowed_user_ids) — run once when flipping the maintenance login gate on.';

    public function handle(): int
    {
        $allowedIds = array_map('intval', config('maintenance.allowed_user_ids', []));

        // Confirmed against the installed framework (Grammar::whereNotIn(),
        // vendor/laravel/framework/.../Query/Grammars/Grammar.php): an empty
        // $values array compiles the clause to '1 = 1', so whereNotIn('user_id', [])
        // already matches (and here, deletes) every row — the correct
        // behaviour when nobody is allowlisted. Branched explicitly anyway
        // so that outcome is a deliberate, visible decision in this command
        // rather than something relying on undocumented query-builder
        // internals, and so the empty-allowlist case gets its own warning.
        $query = DB::table('sessions');

        if ($allowedIds !== []) {
            $query->whereNotIn('user_id', $allowedIds);
        }

        $deleted = $query->delete();

        $this->info("Deleted {$deleted} session(s).");

        if ($allowedIds === []) {
            $this->warn('No allowed_user_ids configured — every session was deleted.');
        } else {
            $this->line('Preserved sessions for user IDs: '.implode(', ', $allowedIds));
        }

        return self::SUCCESS;
    }
}
