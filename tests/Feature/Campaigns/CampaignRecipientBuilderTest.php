<?php

/**
 * The shared recipient builder (admin Build button + campaigns:build-recipients):
 * never resets attempted rows, applies opt-outs, and gives each shared SMS number
 * to one recipient (active → telephone1 → lowest id).
 */

use App\Campaigns\Delivery\CampaignDeliveryService;
use App\Campaigns\Delivery\DeliveryAttempt;
use App\Campaigns\Delivery\DeliveryChannel;
use App\Campaigns\Delivery\DeliveryMessage;
use App\Campaigns\Recipients\CampaignRecipientBuilder;
use App\Campaigns\Sending\CampaignSendRunner;
use App\Models\MessagingCampaign;
use App\Models\MessagingRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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

    $this->approver = User::factory()->create(); // no phone: never an SMS row
    $this->approver->assignRole('national_db_administrator');

    $this->campaignFor = fn (string $channel, string $status = 'queued') => MessagingCampaign::create([
        'channel' => $channel,
        'audience_type' => 'member',
        'subject' => 'Hello',
        'body' => 'Hello',
        'filter_json' => ['_content' => ['email_subject' => 'Hello', 'email_body' => '<p>Hello</p>', 'sms_body' => 'Hello there']],
        'status' => $status,
        'created_by' => $this->approver->id,
        'scope_level' => 'national',
    ]);
});

function row(MessagingCampaign $campaign, User $user): ?MessagingRecipient
{
    return MessagingRecipient::where('messaging_campaign_id', $campaign->id)->where('recipient_id', $user->id)->first();
}

function buildRecipients(MessagingCampaign $campaign, array $options = []): array
{
    return app(CampaignRecipientBuilder::class)->build($campaign->fresh(), ...$options);
}

/** Records every deliver() call per channel, always succeeds. */
function recordingChannel(string $name): DeliveryChannel
{
    return new class($name) implements DeliveryChannel
    {
        public array $delivered = [];

        public function __construct(private string $channelName) {}

        public function name(): string { return $this->channelName; }

        public function supports(MessagingRecipient $recipient): bool
        {
            return $this->channelName === 'email' ? ! empty($recipient->email) : ! empty($recipient->phone);
        }

        public function deliver(MessagingRecipient $recipient, DeliveryMessage $message): DeliveryAttempt
        {
            $this->delivered[] = $recipient->id;

            return DeliveryAttempt::success($this->channelName, 'fake-'.$recipient->id, provider: 'fake');
        }
    };
}

/*
|--------------------------------------------------------------------------
| Rebuilds never reset attempted rows
|--------------------------------------------------------------------------
*/

test('rerunning the builder on a sent campaign sends nothing again (both builders)', function () {
    User::factory()->count(3)->sequence(
        ['email' => null, 'telephone1' => '08031111111'],
        ['email' => null, 'telephone1' => '08032222222'],
        ['email' => null, 'telephone1' => '08033333333'],
    )->create();
    $campaign = ($this->campaignFor)('sms');

    buildRecipients($campaign, ['onlyContactable' => true]);
    $campaign->update(['status' => 'sending']);

    $sms = recordingChannel('sms');
    $runner = new CampaignSendRunner(new CampaignDeliveryService([$sms]));
    $runner->runOneBatch($campaign->fresh(), batch: 50, force: true);
    expect($sms->delivered)->toHaveCount(3)
        ->and($campaign->fresh()->status)->toBe('sent');

    // Rebuild through the service, the CLI (incl. --fresh) and the admin button's rules.
    buildRecipients($campaign, ['onlyContactable' => true]);
    Artisan::call('campaigns:build-recipients', ['campaignId' => $campaign->id, '--only-contactable' => true]);
    Artisan::call('campaigns:build-recipients', ['campaignId' => $campaign->id, '--fresh' => true]);

    expect(MessagingRecipient::where('messaging_campaign_id', $campaign->id)->where('status', 'sent')->count())->toBe(3);

    $campaign->update(['status' => 'sending']);
    $runner->runOneBatch($campaign->fresh(), batch: 50, force: true);

    expect($sms->delivered)->toHaveCount(3); // nothing sent twice
});

test('a rebuild leaves queued, failed and expired rows untouched and adds new people', function () {
    $users = User::factory()->count(3)->sequence(
        ['email' => null, 'telephone1' => '08031111111'],
        ['email' => null, 'telephone1' => '08032222222'],
        ['email' => null, 'telephone1' => '08033333333'],
    )->create();
    $campaign = ($this->campaignFor)('sms');
    buildRecipients($campaign);

    foreach (['queued', 'failed', 'expired'] as $i => $status) {
        row($campaign, $users[$i])->update(['status' => $status, 'phone' => '+2340000000000']);
    }
    $newcomer = User::factory()->create(['email' => null, 'telephone1' => '08034444444']);

    $counts = buildRecipients($campaign);

    foreach (['queued', 'failed', 'expired'] as $i => $status) {
        expect(row($campaign, $users[$i])->status)->toBe($status)
            ->and(row($campaign, $users[$i])->phone)->toBe('+2340000000000');
    }
    expect(row($campaign, $newcomer)->status)->toBe('pending')
        ->and($counts['kept'])->toBe(3)
        ->and($counts['created'])->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Opt-outs (now also on the CLI)
|--------------------------------------------------------------------------
*/

test('the CLI respects SMS and email opt-outs', function () {
    $smsOptedOut = User::factory()->create(['email' => null, 'telephone1' => '08031111111', 'sms_opt_out' => true]);
    $emailOptedOut = User::factory()->create(['email' => 'e@example.com', 'telephone1' => '08032222222', 'email_opt_out' => true]);

    $sms = ($this->campaignFor)('sms');
    Artisan::call('campaigns:build-recipients', ['campaignId' => $sms->id]);
    expect(row($sms, $smsOptedOut))->toBeNull();

    $fallback = ($this->campaignFor)('email_fallback_sms');
    Artisan::call('campaigns:build-recipients', ['campaignId' => $fallback->id]);
    expect(row($fallback, $emailOptedOut)->email)->toBeNull()        // email masked
        ->and(row($fallback, $emailOptedOut)->phone)->toBe('+2348032222222'); // still reachable by SMS
});

/*
|--------------------------------------------------------------------------
| Shared-number deduplication
|--------------------------------------------------------------------------
*/

test('an active account wins a shared number over an older dormant one', function () {
    $olderDormant = User::factory()->create(['email' => null, 'telephone1' => '08031234567', 'lifecycle_status' => 'dormant']);
    $active = User::factory()->create(['email' => null, 'telephone1' => '0803 123 4567', 'lifecycle_status' => 'active']);
    $campaign = ($this->campaignFor)('sms');

    $counts = buildRecipients($campaign);

    expect(row($campaign, $active)->status)->toBe('pending')
        ->and(row($campaign, $olderDormant)->status)->toBe('skipped_shared_number')
        ->and(row($campaign, $olderDormant)->last_error)->toBe(CampaignRecipientBuilder::SHARED_NUMBER_ERROR)
        ->and($counts['shared_numbers'])->toBe(1);
});

test('a number matched via telephone1 wins over the same number in someone\'s telephone2', function () {
    $viaTelephone2 = User::factory()->create(['email' => null, 'telephone1' => 'nil', 'telephone2' => '08031234567']);
    $viaTelephone1 = User::factory()->create(['email' => null, 'telephone1' => '08031234567']);
    $campaign = ($this->campaignFor)('sms');

    buildRecipients($campaign);

    expect(row($campaign, $viaTelephone1)->status)->toBe('pending')
        ->and(row($campaign, $viaTelephone2)->status)->toBe('skipped_shared_number');
});

test('with everything else equal the lowest user id wins', function () {
    $first = User::factory()->create(['email' => null, 'telephone1' => '08031234567']);
    $second = User::factory()->create(['email' => null, 'telephone1' => '+2348031234567']);
    $campaign = ($this->campaignFor)('sms');

    buildRecipients($campaign);

    expect(row($campaign, $first)->status)->toBe('pending')
        ->and(row($campaign, $second)->status)->toBe('skipped_shared_number');
});

test('both: a shared-number loser with email still gets email, without SMS', function () {
    $winner = User::factory()->create(['email' => 'a@example.com', 'telephone1' => '08031234567']);
    $loser = User::factory()->create(['email' => 'b@example.com', 'telephone1' => '08031234567']);
    $campaign = ($this->campaignFor)('both');

    buildRecipients($campaign);

    expect(row($campaign, $winner)->phone)->toBe('+2348031234567')
        ->and(row($campaign, $loser)->status)->toBe('pending')
        ->and(row($campaign, $loser)->email)->toBe('b@example.com')
        ->and(row($campaign, $loser)->phone)->toBeNull();
});

test('email_fallback_sms: people with email are not in the SMS pool; email-less people share one SMS', function () {
    $withEmail = User::factory()->create(['email' => 'a@example.com', 'telephone1' => '08031234567']);
    $smsA = User::factory()->create(['email' => null, 'telephone1' => '08031234567']);
    $smsB = User::factory()->create(['email' => null, 'telephone1' => '08031234567']);
    $campaign = ($this->campaignFor)('email_fallback_sms');

    buildRecipients($campaign);

    expect(row($campaign, $withEmail)->status)->toBe('pending')
        ->and(row($campaign, $withEmail)->phone)->toBe('+2348031234567') // kept for fallback
        ->and(row($campaign, $smsA)->status)->toBe('pending')
        ->and(row($campaign, $smsB)->status)->toBe('skipped_shared_number');
});

test('the admin build message reports the shared-number count', function () {
    User::factory()->count(2)->create(['email' => null, 'telephone1' => '08031234567']);
    $campaign = ($this->campaignFor)('sms');

    $this->actingAs($this->approver)
        ->post(route('campaigns.admin.buildRecipients', $campaign), ['only_contactable' => 1])
        ->assertSessionHas('success', fn ($msg) => str_contains($msg, 'Shared SMS number (SMS to one recipient only): 1'));
});

test('send time: an SMS is skipped when another recipient was already texted at that number', function () {
    $campaign = ($this->campaignFor)('sms', 'sending');
    $already = User::factory()->create(['email' => null]);
    $late = User::factory()->create(['email' => null]);

    MessagingRecipient::create([
        'messaging_campaign_id' => $campaign->id, 'recipient_type' => User::class, 'recipient_id' => $already->id,
        'phone' => '+2348031234567', 'status' => 'sent', 'channel_used' => 'sms',
    ]);
    $lateRow = MessagingRecipient::create([
        'messaging_campaign_id' => $campaign->id, 'recipient_type' => User::class, 'recipient_id' => $late->id,
        'phone' => '+2348031234567', 'status' => 'pending',
    ]);

    $sms = recordingChannel('sms');
    (new CampaignSendRunner(new CampaignDeliveryService([$sms])))->runOneBatch($campaign->fresh(), batch: 50, force: true);

    expect($sms->delivered)->toBeEmpty()
        ->and($lateRow->fresh()->status)->toBe('skipped_shared_number');
});
