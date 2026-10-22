<?php

/**
 * Campaign email goes through Laravel's mail system. A failed send is never counted as
 * sent, and a production server without a real mailer fails clearly.
 */

use App\Campaigns\Delivery\CampaignDeliveryService;
use App\Campaigns\Delivery\DeliveryMessage;
use App\Campaigns\Delivery\LogSmsChannel;
use App\Campaigns\Delivery\MailEmailChannel;
use App\Campaigns\Sending\CampaignSendRunner;
use App\Mail\CampaignEmail;
use App\Models\MessagingCampaign;
use App\Models\MessagingRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_super_admin' => true]);

    $this->makeCampaign = fn (string $channel) => MessagingCampaign::create([
        'channel' => $channel,
        'audience_type' => 'member',
        'subject' => 'Hello',
        'body' => 'Hello',
        'filter_json' => ['_content' => ['email_subject' => 'Renewal', 'email_body' => '<p>Please renew</p>', 'sms_body' => 'Please renew']],
        'status' => 'sending',
        'created_by' => $this->admin->id,
        'scope_level' => 'national',
    ]);

    $this->addRecipient = function (MessagingCampaign $campaign, ?string $email, ?string $phone) {
        $u = User::factory()->create(['email' => $email]);

        return MessagingRecipient::create([
            'messaging_campaign_id' => $campaign->id,
            'recipient_type' => User::class,
            'recipient_id' => $u->id,
            'email' => $email,
            'phone' => $phone,
            'status' => 'pending',
        ]);
    };

    $this->runner = new CampaignSendRunner(new CampaignDeliveryService([new MailEmailChannel, new LogSmsChannel]));
});

test('the configured campaign email channel is the mail channel', function () {
    expect(config('campaigns.delivery.channels'))->toContain(MailEmailChannel::class)
        ->and(class_exists('App\Campaigns\Delivery\LogEmailChannel'))->toBeFalse();
});

test('an email campaign is sent through the mailer with the rendered subject and body', function () {
    Mail::fake();
    $campaign = ($this->makeCampaign)('email');
    $r = ($this->addRecipient)($campaign, 'ada@example.org', null);

    $this->runner->runOneBatch($campaign->fresh(), batch: 50, force: true);

    Mail::assertSent(CampaignEmail::class, fn (CampaignEmail $mail) => $mail->hasTo('ada@example.org')
        && $mail->subjectLine === 'Hello'
        && str_contains($mail->htmlBody, 'Please renew'));
    expect($r->fresh()->status)->toBe('sent')
        ->and($r->fresh()->channel_used)->toBe('email');
});

test('a mail error marks the recipient failed, not sent', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP connection refused'));
    $campaign = ($this->makeCampaign)('email');
    $r = ($this->addRecipient)($campaign, 'ada@example.org', null);

    $this->runner->runOneBatch($campaign->fresh(), batch: 50, force: true);

    $r->refresh();
    expect($r->status)->toBe('failed')
        ->and($r->last_error)->toContain('SMTP connection refused');
});

test('email_fallback_sms falls back to SMS when the email fails', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP connection refused'));
    $campaign = ($this->makeCampaign)('email_fallback_sms');
    $r = ($this->addRecipient)($campaign, 'ada@example.org', '+2348031234567');

    $this->runner->runOneBatch($campaign->fresh(), batch: 50, force: true);

    $r->refresh();
    expect($r->status)->toBe('sent')
        ->and($r->channel_used)->toBe('sms');
});

test('in production a log mailer fails clearly and nothing counts as sent', function () {
    Mail::fake();
    app()['env'] = 'production';
    config(['mail.default' => 'log']);
    $campaign = ($this->makeCampaign)('email');
    $r = ($this->addRecipient)($campaign, 'ada@example.org', null);

    $this->runner->runOneBatch($campaign->fresh(), batch: 50, force: true);

    Mail::assertNothingSent();
    $r->refresh();
    expect($r->status)->toBe('failed')
        ->and($r->last_error)->toContain('No real mailer is configured');
});

test('in production a log mailer still lets email_fallback_sms reach SMS', function () {
    Mail::fake();
    app()['env'] = 'production';
    config(['mail.default' => 'log']);
    $campaign = ($this->makeCampaign)('email_fallback_sms');
    $r = ($this->addRecipient)($campaign, 'ada@example.org', '+2348031234567');

    $this->runner->runOneBatch($campaign->fresh(), batch: 50, force: true);

    expect($r->fresh()->channel_used)->toBe('sms');
});

test('in production a real mailer sends', function () {
    Mail::fake();
    app()['env'] = 'production';
    config(['mail.default' => 'smtp']);

    $recipient = new MessagingRecipient(['messaging_campaign_id' => 1, 'email' => 'ada@example.org']);
    $recipient->id = 5;
    $attempt = (new MailEmailChannel)->deliver($recipient, new DeliveryMessage(subject: 'Hi', body: 'x', emailBody: '<p>x</p>'));

    expect($attempt->ok)->toBeTrue()
        ->and($attempt->provider)->toBe('mail:smtp');
    Mail::assertSent(CampaignEmail::class);
});

test('a dry run never sends an email', function () {
    Mail::fake();

    $recipient = new MessagingRecipient(['messaging_campaign_id' => 1, 'email' => 'ada@example.org']);
    $recipient->id = 5;
    $attempt = (new MailEmailChannel)->deliver($recipient, new DeliveryMessage(subject: 'Hi', body: 'x', meta: ['dry_run' => true]));

    expect($attempt->ok)->toBeTrue()
        ->and($attempt->provider)->toBe('mail-dryrun');
    Mail::assertNothingSent();
});
