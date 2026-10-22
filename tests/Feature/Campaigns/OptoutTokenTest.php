<?php

/**
 * Campaign unsubscribe links use users.optout_token (16 letters/digits),
 * separate from id_check_token (ID cards, certificates). Links already sent
 * with id_check_token keep working.
 */

use App\Campaigns\Delivery\CampaignDeliveryService;
use App\Campaigns\Delivery\DeliveryAttempt;
use App\Campaigns\Delivery\DeliveryChannel;
use App\Campaigns\Delivery\DeliveryMessage;
use App\Campaigns\Sending\CampaignSendRunner;
use App\Campaigns\Sending\SmsFooter;
use App\Models\MessagingCampaign;
use App\Models\MessagingRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** Records the message handed to it instead of sending. */
function capturingChannel(string $name): DeliveryChannel
{
    return new class($name) implements DeliveryChannel
    {
        public ?DeliveryMessage $message = null;

        public function __construct(private string $channelName) {}

        public function name(): string
        {
            return $this->channelName;
        }

        public function supports(MessagingRecipient $recipient): bool
        {
            return true;
        }

        public function deliver(MessagingRecipient $recipient, DeliveryMessage $message): DeliveryAttempt
        {
            $this->message = $message;

            return DeliveryAttempt::success($this->channelName, 'fake-'.$recipient->id);
        }
    };
}

function sendOneTo(User $member, string $channel): DeliveryMessage
{
    $admin = User::factory()->create();
    $campaign = MessagingCampaign::create([
        'channel' => $channel, 'audience_type' => 'member', 'body' => 'Hello',
        'filter_json' => ['_content' => ['sms_body' => 'Hello', 'email_body' => '<p>Hello</p>']],
        'status' => 'sending', 'created_by' => $admin->id, 'scope_level' => 'national',
    ]);
    MessagingRecipient::create([
        'messaging_campaign_id' => $campaign->id, 'recipient_type' => User::class,
        'recipient_id' => $member->id, 'email' => 'member@example.com',
        'phone' => '+2348031234567', 'status' => 'pending',
    ]);

    $fake = capturingChannel($channel);
    (new CampaignSendRunner(new CampaignDeliveryService([$fake])))
        ->runOneBatch($campaign->fresh(), batch: 50, force: true);

    return $fake->message;
}

test('new users get a 16-character letters/digits opt-out token separate from id_check_token', function () {
    $user = User::factory()->create();

    expect($user->optout_token)->toMatch('/^[A-Za-z0-9]{16}$/')
        ->and(strlen($user->id_check_token))->toBe(32)
        ->and(SmsFooter::TOKEN_LENGTH)->toBe(16);
});

test('the back-fill command gives users without a token one and skips anonymised users', function () {
    $user = User::factory()->create();
    $anonymised = User::factory()->create();
    DB::table('users')->where('id', $user->id)->update(['optout_token' => null]);
    DB::table('users')->where('id', $anonymised->id)->update(['optout_token' => null, 'anonymized_at' => now()]);

    Artisan::call('users:backfill-optout-tokens');

    expect(DB::table('users')->where('id', $user->id)->value('optout_token'))->toMatch('/^[A-Za-z0-9]{16}$/')
        ->and(DB::table('users')->where('id', $anonymised->id)->value('optout_token'))->toBeNull();
});

test('a campaign SMS links to the opt-out token, not id_check_token', function () {
    $member = User::factory()->create();

    $sms = sendOneTo($member, 'sms')->smsBody;

    expect($sms)->toEndWith(SmsFooter::for($member->optout_token))
        ->and($sms)->toContain('/u/'.$member->optout_token.'/sms')
        ->and($sms)->not->toContain($member->id_check_token)
        ->and(mb_strlen(SmsFooter::for($member->optout_token)))->toBe(mb_strlen(SmsFooter::preview()));
});

test('a campaign email links to the opt-out token, not id_check_token', function () {
    $member = User::factory()->create();

    $email = sendOneTo($member, 'email')->emailBody;

    expect($email)->toContain(route('unsubscribe.email.show', $member->optout_token))
        ->and($email)->not->toContain($member->id_check_token);
});

test('the opt-out pages accept the new token and the old id_check_token', function (string $channel) {
    $member = User::factory()->create();

    foreach ([$member->optout_token, $member->id_check_token] as $token) {
        $this->get("/u/{$token}/{$channel}")->assertOk()->assertSee('Unsubscribe from');
    }

    // Opting out through an old link still works.
    $this->post("/u/{$member->id_check_token}/{$channel}", ['action' => 'optout'])
        ->assertRedirect("/u/{$member->id_check_token}/{$channel}");
    expect($member->fresh()->{$channel.'_opt_out'})->toBeTrue();

    // And back in through the new one.
    $this->post("/u/{$member->optout_token}/{$channel}", ['action' => 'optin'])
        ->assertRedirect("/u/{$member->optout_token}/{$channel}");
    expect($member->fresh()->{$channel.'_opt_out'})->toBeFalse();
})->with(['email', 'sms']);

test('the opt-out token is case-sensitive', function () {
    $member = User::factory()->create(['optout_token' => 'AbcdEfghIjklMnop']);

    $this->get('/u/AbcdEfghIjklMnop/sms')->assertOk();
    $this->get('/u/abcdefghijklmnop/sms')->assertNotFound();
});

test('an unknown token gives 404', function (string $channel) {
    User::factory()->create();

    $this->get("/u/NoSuchToken12345/{$channel}")->assertNotFound()->assertSee('invalid');
    $this->post("/u/NoSuchToken12345/{$channel}", ['action' => 'optout'])->assertNotFound();
})->with(['email', 'sms']);

test('wizard step 4 previews the opt-out link with 16 X\'s', function () {
    $this->withoutVite();
    $permissions = ['manage-admin-panel', 'campaign_request_create'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin = User::factory()->create();
    $admin->assignRole('national_db_administrator');

    $campaign = MessagingCampaign::create([
        'channel' => 'sms', 'audience_type' => 'member', 'body' => 'Hello',
        'filter_json' => ['_content' => ['sms_body' => 'Hello']],
        'status' => 'draft', 'created_by' => $admin->id, 'scope_level' => 'national',
    ]);

    $html = $this->actingAs($admin)->get(route('campaigns.wizard.step4', $campaign))->assertOk()->getContent();

    expect($html)->toContain('>'.str_repeat('X', 16).'</span>/sms')
        ->not->toContain(str_repeat('X', 17))
        ->toContain('unique 16-character token');
});
