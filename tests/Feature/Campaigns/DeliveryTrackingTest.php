<?php

/**
 * Per-recipient delivery tracking: the send runner records which channel
 * delivered, which provider, and the provider's message id; campaign stats
 * and the monitor understand the SMS-era statuses.
 */

use App\Campaigns\Delivery\CampaignDeliveryService;
use App\Campaigns\Delivery\DeliveryAttempt;
use App\Campaigns\Delivery\DeliveryChannel;
use App\Campaigns\Delivery\DeliveryMessage;
use App\Campaigns\Delivery\LogEmailChannel;
use App\Campaigns\Delivery\LogSmsChannel;
use App\Campaigns\Sending\CampaignSendRunner;
use App\Models\MessagingCampaign;
use App\Models\MessagingRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $permissions = ['manage-admin-panel', 'campaign_request_approve'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create(['is_super_admin' => true]); // excluded from audiences
    $this->approver = User::factory()->create();
    $this->approver->assignRole('national_db_administrator');

    $this->makeCampaign = fn (string $channel, string $status = 'sending') => MessagingCampaign::create([
        'channel' => $channel,
        'audience_type' => 'member',
        'subject' => 'Hello',
        'body' => 'Hello',
        'filter_json' => ['_content' => ['email_subject' => 'Hello', 'email_body' => '<p>Hello</p>', 'sms_body' => 'Hello there']],
        'status' => $status,
        'created_by' => $this->admin->id,
        'scope_level' => 'national',
    ]);

    $this->addRecipient = function (MessagingCampaign $campaign, ?string $email, ?string $phone, string $status = 'pending') {
        $u = User::factory()->create(['email' => $email]);

        return MessagingRecipient::create([
            'messaging_campaign_id' => $campaign->id,
            'recipient_type' => User::class,
            'recipient_id' => $u->id,
            'email' => $email,
            'phone' => $phone,
            'status' => $status,
        ]);
    };

    $this->logRunner = new CampaignSendRunner(new CampaignDeliveryService([new LogEmailChannel, new LogSmsChannel]));
});

test('a sent recipient records channel, provider and provider message id', function () {
    $campaign = ($this->makeCampaign)('email');
    $r = ($this->addRecipient)($campaign, 'a@example.com', null);

    $this->logRunner->runOneBatch($campaign->fresh(), batch: 50, force: true);

    $r->refresh();
    expect($r->status)->toBe('sent')
        ->and($r->channel_used)->toBe('email')
        ->and($r->provider)->toBe('log-only')
        ->and($r->provider_message_id)->toStartWith('log-email-');
});

test('email_fallback_sms records the channel that actually delivered', function () {
    $campaign = ($this->makeCampaign)('email_fallback_sms');
    $withEmail = ($this->addRecipient)($campaign, 'b@example.com', '+2348031234567');
    $smsOnly = ($this->addRecipient)($campaign, null, '+2348031234568');

    $this->logRunner->runOneBatch($campaign->fresh(), batch: 50, force: true);

    expect($withEmail->fresh()->channel_used)->toBe('email')
        ->and($smsOnly->fresh()->channel_used)->toBe('sms')
        ->and($smsOnly->fresh()->provider_message_id)->toStartWith('log-sms-');
});

test('a failed attempt records the provider and channel it failed on', function () {
    $campaign = ($this->makeCampaign)('sms');
    $r = ($this->addRecipient)($campaign, null, '+2348031234567');

    $failing = new class implements DeliveryChannel
    {
        public function name(): string { return 'sms'; }

        public function supports(MessagingRecipient $recipient): bool { return true; }

        public function deliver(MessagingRecipient $recipient, DeliveryMessage $message): DeliveryAttempt
        {
            return DeliveryAttempt::failed('sms', 'Insufficient balance', '402', provider: 'smslive247');
        }
    };

    (new CampaignSendRunner(new CampaignDeliveryService([$failing])))
        ->runOneBatch($campaign->fresh(), batch: 50, force: true);

    $r->refresh();
    expect($r->status)->toBe('failed')
        ->and($r->last_error)->toBe('Insufficient balance')
        ->and($r->channel_used)->toBe('sms')
        ->and($r->provider)->toBe('smslive247')
        ->and($r->provider_message_id)->toBeNull();
});

test('campaign stats count delivered as sent, expired as failed, and skipped as neither', function () {
    $campaign = ($this->makeCampaign)('sms', 'sent');
    foreach (['sent', 'delivered', 'failed', 'expired', 'skipped_shared_number', 'skipped_invalid_number', 'queued'] as $status) {
        ($this->addRecipient)($campaign, null, '+2348031234567', $status);
    }

    $campaign->refreshRecipientStats();

    expect($campaign->fresh()->only(['stats_total', 'stats_sent', 'stats_failed']))
        ->toBe(['stats_total' => 7, 'stats_sent' => 2, 'stats_failed' => 2]);
});

test('reset failed clears the previous attempt provider details', function () {
    $campaign = ($this->makeCampaign)('sms', 'sent');
    $r = ($this->addRecipient)($campaign, null, '+2348031234567', 'failed');
    $r->update(['provider' => 'smslive247', 'provider_message_id' => 'abc', 'channel_used' => 'sms']);

    $this->actingAs($this->approver)->post(route('campaigns.admin.recipients.resetFailed', $campaign));

    $r->refresh();
    expect($r->status)->toBe('pending')
        ->and($r->provider)->toBeNull()
        ->and($r->provider_message_id)->toBeNull()
        ->and($r->channel_used)->toBeNull()
        ->and($campaign->fresh()->status)->toBe('queued');
});

test('the monitor has queued and skipped tabs and counts', function () {
    $campaign = ($this->makeCampaign)('sms', 'sent');
    ($this->addRecipient)($campaign, null, '+2348031234567', 'skipped_shared_number');
    ($this->addRecipient)($campaign, null, '+2348031234568', 'delivered');
    ($this->addRecipient)($campaign, null, '+2348031234569', 'queued');

    $this->actingAs($this->approver)
        ->get(route('campaigns.admin.monitor', ['campaign' => $campaign, 'tab' => 'skipped']))
        ->assertOk()
        ->assertViewHas('skippedCount', 1)
        ->assertViewHas('queuedCount', 1)
        ->assertViewHas('sentCount', 1)
        ->assertViewHas('recipients', fn ($page) => $page->total() === 1)
        ->assertSee('Skipped shared number');
});

test('a both campaign keeps the SMS record when email and SMS both succeed', function () {
    $campaign = ($this->makeCampaign)('both');
    $both = ($this->addRecipient)($campaign, 'c@example.com', '+2348031234567');
    $emailOnly = ($this->addRecipient)($campaign, 'd@example.com', null);

    $this->logRunner->runOneBatch($campaign->fresh(), batch: 50, force: true);

    expect($both->fresh()->status)->toBe('sent')
        ->and($both->fresh()->channel_used)->toBe('sms')
        ->and($both->fresh()->provider_message_id)->toStartWith('log-sms-')
        ->and($emailOnly->fresh()->status)->toBe('sent')
        ->and($emailOnly->fresh()->channel_used)->toBe('email');

    // The post-send summary for "both" still counts by contact details, as before.
    $summary = app(\App\Services\CampaignAudienceSummaryService::class)->summarizeFromRecipients($campaign->fresh());
    expect($summary['willEmail'])->toBe(2)
        ->and($summary['willSms'])->toBe(0);
});
