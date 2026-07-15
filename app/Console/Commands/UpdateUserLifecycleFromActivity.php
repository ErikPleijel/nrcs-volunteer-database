<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateUserLifecycleFromActivity extends Command
{
    protected $signature = 'users:update-lifecycle-from-activity';

    protected $description = 'Update lifecycle_status based on last_activity_at and settings';

    public function handle(): int
    {
        $start     = microtime(true);
        $toDormant = [];

        User::where('lifecycle_status', 'active')
            ->whereDoesntHave('organisations')
            ->orderBy('id')
            ->chunkById(500, function ($users) use (&$toDormant) {
                foreach ($users as $u) {
                    if ($u->isDormantByPolicy()) {
                        $toDormant[] = $u->id;
                    }
                }
            });

        if (! empty($toDormant)) {
            DB::transaction(function () use ($toDormant) {
                foreach (array_chunk($toDormant, 1000) as $ids) {
                    User::whereIn('id', $ids)->update(['lifecycle_status' => 'dormant']);
                }
            });
        }

        $elapsed = round(microtime(true) - $start, 2);
        $this->info('Lifecycle policy: demoted ' . count($toDormant) . " active → dormant. ({$elapsed}s)");

        return self::SUCCESS;
    }
}
