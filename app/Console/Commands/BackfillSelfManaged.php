<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fills users.self_managed_at (see User::isSelfManaged()) for accounts the
 * person has already used themselves:
 *  - a recorded login (this system, or the old system's Lastlogin) → last_login_at;
 *  - consent given by the person themselves (self-registration, or the
 *    confirmation at login) → consent_obtained_at, else created_at.
 *
 * Only empty values are filled, so it is safe to run again. Called by the
 * migration that added the column and by migrate:old-db right after the
 * users import (on a fresh database the migration finds no users yet).
 * Query-builder writes: no model events, audit entries or updated_at changes.
 */
class BackfillSelfManaged extends Command
{
    protected $signature = 'users:backfill-self-managed';

    protected $description = 'Set self_managed_at for users who have logged in or registered themselves (only where empty).';

    public function handle(): int
    {
        $fromLogin = DB::update('UPDATE users SET self_managed_at = last_login_at
            WHERE self_managed_at IS NULL AND last_login_at IS NOT NULL');

        $fromConsent = DB::update('UPDATE users SET self_managed_at = COALESCE(consent_obtained_at, created_at)
            WHERE self_managed_at IS NULL AND consent_obtained_by_id = id');

        $this->info("self_managed_at set: {$fromLogin} from last login, {$fromConsent} from own consent.");

        return self::SUCCESS;
    }
}
