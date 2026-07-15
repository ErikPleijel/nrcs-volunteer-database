<?php

namespace App\Console\Commands;

use App\Models\MessagingCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillCampaignOrigin extends Command
{
    protected $signature = 'campaigns:backfill-origin {--apply : Write changes (default: dry-run report only)}';
    protected $description = 'Backfill origin_level/origin_branch_id on existing messaging_campaigns from each creator\'s CURRENT scope; report, or --apply to write.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $apply ? $this->warn('APPLY mode — origin_level/origin_branch_id changes WILL be written.')
               : $this->info('[dry-run] No writes. Use --apply to write.');

        $scanned = 0;
        $skippedNoCreator = 0;
        $changes = []; // id => ['origin_level' => ..., 'origin_branch_id' => ...]
        $samples = [];

        MessagingCampaign::query()
            ->whereNull('origin_level')
            ->orderBy('id')
            ->with('creator')
            ->chunkById(500, function ($campaigns) use (&$scanned, &$skippedNoCreator, &$changes, &$samples) {
                foreach ($campaigns as $campaign) {
                    $scanned++;
                    $creator = $campaign->creator;

                    if (! $creator) {
                        $skippedNoCreator++;
                        continue;
                    }

                    $originLevel = $creator->getAccessLevel() === 'national' ? 'national' : 'branch';
                    $originBranchId = $creator->getScopedBranchId();

                    $changes[$campaign->id] = [
                        'origin_level' => $originLevel,
                        'origin_branch_id' => $originBranchId,
                    ];

                    if (count($samples) < 15) {
                        $samples[] = sprintf(
                            '  campaign #%d created_by=%d → origin_level=%s origin_branch_id=%s',
                            $campaign->id,
                            $creator->id,
                            $originLevel,
                            $originBranchId ?? 'null'
                        );
                    }
                }
            });

        $this->line("Scanned (origin_level currently null): {$scanned}");
        $this->info('Would set origin on: '.count($changes).' campaign(s).');
        foreach ($samples as $line) {
            $this->line($line);
        }
        if (count($changes) > count($samples)) {
            $this->line('  … ('.(count($changes) - count($samples)).' more)');
        }
        $this->info("Skipped (creator no longer exists): {$skippedNoCreator}");
        $this->newLine();

        if (! $apply) {
            $this->info('Dry-run complete. Re-run with --apply to write these changes.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes) {
            foreach ($changes as $id => $attrs) {
                MessagingCampaign::whereKey($id)->update($attrs);
            }
        });

        $this->info('Applied origin backfill to '.count($changes).' campaign(s).');

        return self::SUCCESS;
    }
}
