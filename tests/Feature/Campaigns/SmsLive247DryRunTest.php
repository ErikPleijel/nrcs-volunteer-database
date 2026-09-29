<?php

/**
 * End to end with the SMSLive247 channel in its default dry run: the runner
 * marks the recipient sent and stores the fake provider id — and no HTTP
 * request leaves the app.
 */

use App\Campaigns\Delivery\CampaignDeliveryService;
use App\Campaigns\Delivery\SmsLive247Channel;
use App\Campaigns\Sending\CampaignSendRunner;
use App\Models\MessagingCampaign;
use App\Models\MessagingRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('a campaign SMS through SMSLive247 in dry run is recorded but never sent', function () {
    Http::fake();
    config(['smslive247.dry_run' => true]);

    $admin = User::factory()->create(['is_super_admin' => true]); // campaign creator only
    $member = User::factory()->create(['email' => null]);

    $campaign = MessagingCampaign::create([
        'channel' => 'sms', 'audience_type' => 'member', 'body' => 'Hello',
        'filter_json' => ['_content' => ['sms_body' => 'Hello']],
        'status' => 'sending', 'created_by' => $admin->id, 'scope_level' => 'national',
    ]);
    $row = MessagingRecipient::create([
        'messaging_campaign_id' => $campaign->id, 'recipient_type' => User::class,
        'recipient_id' => $member->id, 'phone' => '+2348031234567', 'status' => 'pending',
    ]);

    (new CampaignSendRunner(new CampaignDeliveryService([app(SmsLive247Channel::class)])))
        ->runOneBatch($campaign->fresh(), batch: 50, force: true);

    $row->refresh();
    expect($row->status)->toBe('sent')
        ->and($row->channel_used)->toBe('sms')
        ->and($row->provider)->toBe('smslive247-dryrun')
        ->and($row->provider_message_id)->toStartWith('dryrun-');
    Http::assertNothingSent();
});

test('the default campaign SMS channel is still the log-only one', function () {
    expect(config('campaigns.delivery.channels'))->toContain(\App\Campaigns\Delivery\LogSmsChannel::class)
        ->not->toContain(SmsLive247Channel::class);
});
