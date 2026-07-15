<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearStatsCache extends Command
{
    protected $signature = 'stats:clear-cache';

    protected $description = 'Clear cached statistics values';

    public function handle(): int
    {
        $keys = [
            'membership_total_members',
            'red_cross_volunteers_total',

            // Add more cache keys used in StatsService here:
            // 'membership_active_count',
            // 'donations_total',
            // 'activities_last_30_days',
        ];

        foreach ($keys as $key) {
            Cache::forget($key);
        }

        $this->info('🧹 Stats cache cleared successfully.');
        return self::SUCCESS;
    }
}
