<?php

namespace App\Console\Commands;

use App\Campaigns\Sending\CampaignSendRunner;
use App\Models\MessagingCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SendCampaigns extends Command
{
    protected $signature = 'campaigns:send
                            {campaignId? : Optional campaign id to process only one}
                            {--batch=50 : Max recipients per run per campaign}
                            {--dry-run : Do not actually send, just mark as sent}
                            {--force : Ignore send window (but still respects daily cap unless you change it)}';
    protected $description = 'Process sending for campaigns with status=sending.';

    public function handle(CampaignSendRunner $runner): int
    {
        $campaignId = $this->argument('campaignId');
        $batch = (int)$this->option('batch');
        $dryRun = (bool)$this->option('dry-run');
        $force = (bool)$this->option('force');

        $lock = \Illuminate\Support\Facades\Cache::lock('campaigns:send', 55);

        if (!$lock->get()) {
            \Illuminate\Support\Facades\Log::channel('scheduler')->warning('campaigns:send skipped — already running');
            return self::SUCCESS;
        }

        try {
            \Illuminate\Support\Facades\Log::channel('campaign_deliveries')->info('campaigns:send tick', [
                'campaignId' => $campaignId ? (int) $campaignId : null,
                'batch' => $batch,
                'dry_run' => $dryRun,
                'force' => $force,
            ]);

            $query = MessagingCampaign::query()
                ->where('status', 'sending');

            if ($campaignId) {
                $query->where('id', (int)$campaignId);
            }

            $campaigns = $query->orderBy('id')->get();

            if ($campaigns->isEmpty()) {
                \Illuminate\Support\Facades\Log::channel('campaign_deliveries')->info('campaigns:send: no sending campaigns');
                return self::SUCCESS;
            }

            foreach ($campaigns as $campaign) {
                $result = $runner->runOneBatch(
                    campaign: $campaign,
                    batch: $batch,
                    dryRun: $dryRun,
                    force: $force,
                );

                \Illuminate\Support\Facades\Log::channel('campaign_deliveries')->info('campaigns:send runOneBatch', [
                    'campaign_id' => $campaign->id,
                    'campaign_title' => $campaign->title,
                    'campaign_channel' => $campaign->channel,
                    'processed' => $result['processed'] ?? 0,
                    'sent' => $result['sent'] ?? 0,
                    'failed' => $result['failed'] ?? 0,
                    'dry_run' => $dryRun,
                    'force' => $force,
                    'batch' => $batch,
                ]);
            }

            return self::SUCCESS;
        } finally {
            optional($lock)->release();
        }
    }
}
