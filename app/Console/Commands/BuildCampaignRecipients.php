<?php

namespace App\Console\Commands;

use App\Campaigns\Recipients\CampaignRecipientBuilder;
use App\Models\MessagingCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * CLI twin of the admin "Build" button — both use CampaignRecipientBuilder, so opt-outs,
 * E.164 numbers, shared-number deduplication and the never-reset-attempted-rows rule are
 * identical. Safe to rerun on a campaign that has already sent.
 */
class BuildCampaignRecipients extends Command
{
    protected $signature = 'campaigns:build-recipients
                            {campaignId : The messaging_campaigns.id}
                            {--fresh : Delete this campaign\'s pending/skipped recipients before rebuilding (attempted rows are kept)}
                            {--only-contactable : Only include recipients that have contact info for the campaign channel}
                            {--chunk=500 : Chunk size for processing users}';

    protected $description = 'Materialize recipients for a campaign based on filter_json, into messaging_recipients.';

    public function handle(CampaignRecipientBuilder $builder): int
    {
        $campaignId = (int) $this->argument('campaignId');

        /** @var MessagingCampaign|null $campaign */
        $campaign = MessagingCampaign::query()->find($campaignId);

        if (! $campaign) {
            $this->error("Campaign #{$campaignId} not found.");

            return self::FAILURE;
        }

        if (! in_array($campaign->status, ['approved', 'queued'], true)) {
            $this->warn("Campaign status is '{$campaign->status}'. Typically you build recipients when approved/queued.");
        }

        $this->info("Building recipients for campaign #{$campaign->id} ({$campaign->title})");
        $this->line("Channel: {$campaign->channel}, Audience: {$campaign->audience_type}, Scope: {$campaign->scope_level} / {$campaign->scope_id}");

        $counts = DB::transaction(fn () => $builder->build(
            $campaign,
            fresh: (bool) $this->option('fresh'),
            onlyContactable: (bool) $this->option('only-contactable'),
            chunk: max(1, (int) $this->option('chunk')),
        ));

        $this->info(CampaignRecipientBuilder::summary($counts));

        return self::SUCCESS;
    }
}
