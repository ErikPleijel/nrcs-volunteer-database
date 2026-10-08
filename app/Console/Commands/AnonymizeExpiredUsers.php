<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserAnonymizer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Anonymizes accounts archived longer than data_protection.anonymize_after_years
 * (default 7) ago, via UserAnonymizer. Dry run by default; --apply to act.
 * Role holders are never anonymized: they are skipped and reported. Scheduled
 * daily at 03:30 (routes/console.php). See Decisions.md 2026-10-08.
 */
class AnonymizeExpiredUsers extends Command
{
    protected $signature = 'users:anonymize-expired {--apply : Anonymize (default: dry-run report only)}';

    protected $description = 'Anonymize accounts archived more than data_protection.anonymize_after_years ago; report, or --apply to act.';

    public function handle(UserAnonymizer $anonymizer): int
    {
        $apply = (bool) $this->option('apply');
        $years = (int) config('data_protection.anonymize_after_years', 7);
        $cutoff = now()->subYears($years);

        $apply ? $this->warn('APPLY mode — accounts WILL be anonymized. This cannot be undone.')
               : $this->info('[dry-run] Nothing is changed. Use --apply to anonymize.');
        $this->line("Archived on or before {$cutoff->toDateTimeString()} ({$years} years).");

        $expired = fn () => User::query()
            ->where('lifecycle_status', 'archived')
            ->whereNull('anonymized_at')
            ->whereNotNull('archived_at')
            ->where('archived_at', '<=', $cutoff);

        $skipped = $expired()
            ->where(fn ($q) => $q->whereHas('roles')->orWhere('is_super_admin', true))
            ->pluck('id');

        $anonymized = 0;
        $eligible = 0;
        $failed = [];

        $expired()
            ->whereDoesntHave('roles')
            ->where('is_super_admin', false)
            ->chunkById(200, function ($users) use ($apply, $anonymizer, &$anonymized, &$eligible, &$failed) {
                foreach ($users as $user) {
                    $eligible++;

                    if (! $apply) {
                        continue;
                    }

                    try {
                        $anonymizer->anonymize($user, null, 'scheduled');
                        $anonymized++;
                    } catch (\Throwable $e) {
                        $failed[] = $user->id;
                        Log::channel('scheduler')->error('users:anonymize-expired failed for one account', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info(($apply ? 'Anonymized' : 'Would anonymize').': '.($apply ? $anonymized : $eligible));
        if ($skipped->isNotEmpty()) {
            $this->warn("Skipped (hold an administrative role): {$skipped->count()} — "
                .$skipped->map(fn ($id) => "DB-{$id}")->implode(', '));
        }
        if ($failed) {
            $this->error('Failed: '.count($failed).' — '.implode(', ', array_map(fn ($id) => "DB-{$id}", $failed)));
        }

        Log::channel('scheduler')->info('users:anonymize-expired completed', [
            'apply' => $apply,
            'years' => $years,
            'eligible' => $eligible,
            'anonymized' => $anonymized,
            'skipped_role_holders' => $skipped->all(),
            'failed' => $failed,
        ]);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
