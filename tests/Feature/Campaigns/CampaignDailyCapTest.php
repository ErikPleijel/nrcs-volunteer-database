<?php

/**
 * The per-campaign daily cap (wizard Step 3, filter_json._throttling.daily_cap)
 * must hold across runs on the same day and reset on a new day. Previously
 * the runner compared the date-cast daily_sent_date to a string, so the
 * counter reset on every run and the cap never applied.
 */

use App\Campaigns\Delivery\CampaignDeliveryService;
use App\Campaigns\Delivery\LogEmailChannel;
use App\Campaigns\Sending\CampaignSendRunner;
use App\Models\MessagingCampaign;
use App\Models\MessagingRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-09-29 10:00:00'));

    $admin = User::factory()->create(['is_super_admin' => true]);

    $this->campaign = MessagingCampaign::create([
        'channel' => 'email',
        'audience_type' => 'member',
        'subject' => 'Hello',
        'body' => '<p>Hello</p>',
        'filter_json' => [
            '_content' => ['email_subject' => 'Hello', 'email_body' => '<p>Hello</p>'],
            '_throttling' => ['send_window_start' => null, 'send_window_end' => null, 'daily_cap' => 3],
        ],
        'status' => 'sending',
        'created_by' => $admin->id,
        'scope_level' => 'national',
    ]);

    User::factory()->count(5)->create()->each(fn (User $u) => MessagingRecipient::create([
        'messaging_campaign_id' => $this->campaign->id,
        'recipient_type' => User::class,
        'recipient_id' => $u->id,
        'email' => $u->email,
        'status' => 'pending',
    ]));

    $this->runner = new CampaignSendRunner(new CampaignDeliveryService([new LogEmailChannel]));
});

function sentCount(MessagingCampaign $campaign): int
{
    return MessagingRecipient::where('messaging_campaign_id', $campaign->id)->where('status', 'sent')->count();
}

test('the daily cap holds across runs on the same day', function () {
    $first = $this->runner->runOneBatch($this->campaign->fresh(), batch: 50);
    expect($first['sent'])->toBe(3);

    $this->travelTo(Carbon::parse('2026-09-29 15:00:00'));
    $second = $this->runner->runOneBatch($this->campaign->fresh(), batch: 50);

    expect($second['sent'])->toBe(0)
        ->and(sentCount($this->campaign))->toBe(3)
        ->and($this->campaign->fresh()->daily_sent_count)->toBe(3)
        ->and($this->campaign->fresh()->status)->toBe('sending');
});

test('the daily cap resets on a new day', function () {
    $this->runner->runOneBatch($this->campaign->fresh(), batch: 50);

    $this->travelTo(Carbon::parse('2026-09-30 09:00:00'));
    $nextDay = $this->runner->runOneBatch($this->campaign->fresh(), batch: 50);

    expect($nextDay['sent'])->toBe(2)
        ->and(sentCount($this->campaign))->toBe(5)
        ->and($this->campaign->fresh()->daily_sent_count)->toBe(2)
        ->and($this->campaign->fresh()->daily_sent_date->toDateString())->toBe('2026-09-30')
        ->and($this->campaign->fresh()->status)->toBe('sent');
});
