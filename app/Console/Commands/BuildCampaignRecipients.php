<?php

namespace App\Console\Commands;

use App\Models\MessagingCampaign;
use App\Models\MessagingRecipient;
use App\Models\User;
use App\Services\UserFilterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BuildCampaignRecipients extends Command
{
    protected $signature = 'campaigns:build-recipients
                            {campaignId : The messaging_campaigns.id}
                            {--fresh : Delete existing recipients for this campaign before rebuilding}
                            {--only-contactable : Only include recipients that have contact info for the campaign channel}
                            {--chunk=500 : Chunk size for processing users}';

    protected $description = 'Materialize recipients for a campaign based on filter_json, into messaging_recipients.';

    public function handle(UserFilterService $userFilterService): int
    {
        $campaignId = (int) $this->argument('campaignId');
        $fresh = (bool) $this->option('fresh');
        $onlyContactable = (bool) $this->option('only-contactable');
        $chunk = (int) $this->option('chunk');

        /** @var MessagingCampaign|null $campaign */
        $campaign = MessagingCampaign::query()->find($campaignId);

        if (!$campaign) {
            $this->error("Campaign #{$campaignId} not found.");
            return self::FAILURE;
        }

        if (!in_array($campaign->status, ['approved', 'queued'], true)) {
            $this->warn("Campaign status is '{$campaign->status}'. Typically you build recipients when approved/queued.");
        }

        $filters = is_array($campaign->filter_json) ? $campaign->filter_json : [];

        $this->info("Building recipients for campaign #{$campaign->id} ({$campaign->title})");
        $this->line("Channel: {$campaign->channel}, Audience: {$campaign->audience_type}, Scope: {$campaign->scope_level} / {$campaign->scope_id}");

        if ($fresh) {
            $this->warn("Deleting existing recipients for campaign #{$campaign->id}...");
            MessagingRecipient::query()
                ->where('messaging_campaign_id', $campaign->id)
                ->delete();
            $this->info("✔ Existing recipients deleted.");
        }

        // Base query (same as wizard step2 base)
        $baseQuery = User::query()
            ->where('is_super_admin', false);

        // Apply the SAME filter rules using the scope stored on the campaign
        $filteredQuery = $userFilterService->apply(
            $baseQuery,
            $filters,
            $campaign->scope_level,
            $campaign->scope_id
        );

        $totalMatched = (clone $filteredQuery)->count();
        $this->info("Matched users: {$totalMatched}");

        $created = 0;
        $updated = 0;
        $skipped = 0;

        $bar = $this->output->createProgressBar($totalMatched);
        $bar->start();

        (clone $filteredQuery)
            ->select(['id', 'email', 'telephone1', 'telephone2', 'first_name', 'last_name'])
            ->orderBy('id')
            ->chunkById($chunk, function ($users) use ($campaign, $onlyContactable, &$created, &$updated, &$skipped, $bar) {

                DB::beginTransaction();
                try {
                    foreach ($users as $user) {
                        $email = $this->cleanEmail($user->email ?? null);
                        $phone = $this->pickPhone($user->telephone1 ?? null, $user->telephone2 ?? null);

                        // If campaign channel is email, require email.
                        // If sms, require phone.
                        // If both, allow either.
                        if ($onlyContactable) {
                            if ($campaign->channel === 'email' && !$email) {
                                $skipped++;
                                $bar->advance();
                                continue;
                            }
                            if ($campaign->channel === 'sms' && !$phone) {
                                $skipped++;
                                $bar->advance();
                                continue;
                            }
                            if ($campaign->channel === 'both' && !$email && !$phone) {
                                $skipped++;
                                $bar->advance();
                                continue;
                            }
                        }

                        $payload = [
                            'first_name' => $user->first_name,
                            'last_name' => $user->last_name,
                            'full_name' => trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                        ];

                        // Use updateOrCreate so reruns are safe
                        $recipient = MessagingRecipient::query()->updateOrCreate(
                            [
                                'messaging_campaign_id' => $campaign->id,
                                'recipient_type' => User::class,
                                'recipient_id' => $user->id,
                            ],
                            [
                                'email' => $email,
                                'phone' => $phone,
                                'payload_json' => $payload,
                                'status' => 'pending',
                                'last_error' => null,
                                'sent_at' => null,
                            ]
                        );

                        // updateOrCreate doesn’t tell us created/updated directly,
                        // but we can infer via wasRecentlyCreated.
                        if ($recipient->wasRecentlyCreated) {
                            $created++;
                        } else {
                            $updated++;
                        }

                        $bar->advance();
                    }

                    DB::commit();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    throw $e;
                }
            });

        $bar->finish();
        $this->newLine(2);

        // Update campaign stats_total based on pending recipients count (all statuses)
        $statsTotal = MessagingRecipient::query()
            ->where('messaging_campaign_id', $campaign->id)
            ->count();

        $campaign->update([
            'stats_total' => $statsTotal,
        ]);

        $this->info("Done.");
        $this->line("Created: {$created}, Updated: {$updated}, Skipped: {$skipped}");
        $this->line("Campaign stats_total updated to: {$statsTotal}");

        return self::SUCCESS;
    }

    private function cleanEmail(?string $email): ?string
    {
        $email = trim((string) $email);
        return $email !== '' ? $email : null;
    }

    private function pickPhone(?string $t1, ?string $t2): ?string
    {
        $t1 = trim((string) $t1);
        $t2 = trim((string) $t2);

        if ($t1 !== '') return $t1;
        if ($t2 !== '') return $t2;

        return null;
    }
}
