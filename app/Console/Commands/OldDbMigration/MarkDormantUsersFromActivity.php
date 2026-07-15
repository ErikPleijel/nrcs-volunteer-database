<?php

namespace App\Console\Commands\OldDbMigration;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarkDormantUsersFromActivity extends Command
{
    protected $signature = 'users:mark-dormant-from-activity';

    protected $description = 'One-time migration: mark users as dormant based on last_activity_at and settings';

    public function handle(): int
    {
        // Get threshold from settings (or default 12)
        $dormantAfterMonths = Setting::getInt('membership.dormant_after_months', 12);

        $this->info("Marking users as dormant if no activity for {$dormantAfterMonths} months...");

        DB::table('users')
            ->whereIn('lifecycle_status', ['pending_engagement', 'active'])
            ->where(function ($q) use ($dormantAfterMonths) {
                $q->whereNull('last_activity_at')
                  ->orWhere('last_activity_at', '<', now()->subMonths($dormantAfterMonths));
            })
            ->update(['lifecycle_status' => 'dormant']);

        $this->info('Done. Users with old/no activity have been marked as dormant.');

        return self::SUCCESS;
    }
}
