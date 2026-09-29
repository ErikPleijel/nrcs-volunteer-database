<?php

/**
 * NDPA hygiene in the campaign delivery logs (masked numbers/emails, no message
 * bodies) and the audit trail for approve / start / stop / run-once.
 */

use App\Campaigns\Delivery\CampaignDeliveryService;
use App\Campaigns\Delivery\DeliveryMessage;
use App\Campaigns\Delivery\LogSmsChannel;
use App\Models\Log as AuditLog;
use App\Models\MessagingCampaign;
use App\Models\MessagingRecipient;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('phone numbers and emails are masked for logs', function () {
    expect(PhoneNumber::mask('+2348031234567'))->toBe('+234803****567')
        ->and(PhoneNumber::mask('08031234567'))->toBe('0803123*567')
        ->and(PhoneNumber::mask('12345'))->toBe('**345')
        ->and(PhoneNumber::mask(null))->toBeNull()
        ->and(PhoneNumber::maskEmail('ada.obi@example.org'))->toBe('a***@example.org')
        ->and(PhoneNumber::maskEmail(''))->toBeNull();
});

test('the SMS log channel and the delivery service log no full number, email or message body', function () {
    $logger = Mockery::spy();
    Log::shouldReceive('channel')->with('campaign_deliveries')->andReturn($logger);

    $recipient = new MessagingRecipient([
        'messaging_campaign_id' => 1, 'email' => 'ada.obi@example.org', 'phone' => '+2348031234567',
    ]);
    $recipient->id = 7;
    $secret = 'Dear Ada, your membership 12345 expires on 1 Oct.';
    $message = new DeliveryMessage(subject: null, body: $secret, smsBody: $secret);

    // email_fallback_sms with no email channel: logs the fallback, then sends via SMS.
    (new CampaignDeliveryService([new LogSmsChannel]))->deliver('email_fallback_sms', $recipient, $message);

    $logged = [];
    $logger->shouldHaveReceived('info')->withArgs(function ($msg, $context = []) use (&$logged) {
        $logged[] = json_encode([$msg, $context], JSON_UNESCAPED_UNICODE);

        return true;
    });

    $all = implode("\n", $logged);
    expect($all)->not->toContain('+2348031234567')
        ->and($all)->not->toContain('ada.obi@example.org')
        ->and($all)->not->toContain($secret)
        ->and($all)->toContain('+234803****567')
        ->and($all)->toContain('a***@example.org');
});

test('the unused SmsService is gone', function () {
    expect(file_exists(app_path('Services/SmsService.php')))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Audit trail
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    $permissions = ['manage-admin-panel', 'campaign_request_approve'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->approver = User::factory()->create();
    $this->approver->assignRole('national_db_administrator');
    $this->submitter = User::factory()->create();

    User::factory()->create(['email' => null, 'telephone1' => '08031111111']);
    User::factory()->create(['email' => null, 'telephone1' => '08032222222']);
    User::factory()->create(['email' => null, 'telephone1' => '08033333333']);

    $this->campaign = MessagingCampaign::create([
        'channel' => 'sms',
        'audience_type' => 'member',
        'body' => 'Hello from the Red Cross.',
        'filter_json' => ['_content' => ['sms_body' => 'Hello from the Red Cross.']],
        'status' => 'proposed',
        'submitted_by' => $this->submitter->id,
        'created_by' => $this->submitter->id,
        'scope_level' => 'national',
    ]);
});

function auditEntry(string $action): AuditLog
{
    $entries = AuditLog::where('action', $action)->get();

    expect($entries)->toHaveCount(1, "Expected one '{$action}' audit entry; logged: ".AuditLog::pluck('action')->implode(', '));

    return $entries->first();
}

test('approve, start, run-once and stop are audited with channel, recipients and projected SMS pages', function () {
    $this->actingAs($this->approver)->post(route('campaigns.admin.approve', $this->campaign))
        ->assertRedirect()
        ->assertSessionHas('success');
    $approved = auditEntry('campaign_approved');
    expect($approved->user_id)->toBe($this->approver->id)
        ->and($approved->subject_id)->toBe($this->campaign->id)
        ->and($approved->new_values)->toMatchArray(['channel' => 'sms', 'sms_recipients_projected' => 3, 'sms_pages_projected' => 3]);

    $this->actingAs($this->approver)->post(route('campaigns.admin.queue', $this->campaign))->assertSessionHas('success');
    $this->actingAs($this->approver)->post(route('campaigns.admin.buildRecipients', $this->campaign), ['only_contactable' => 1])->assertSessionHas('success');

    $this->actingAs($this->approver)->post(route('campaigns.admin.startSending', $this->campaign), ['batch' => 1, 'dry_run' => 1])->assertSessionHas('success');
    expect(auditEntry('campaign_send_started')->new_values)
        ->toMatchArray(['recipients' => 3, 'sms_pages_projected' => 3, 'batch' => 1, 'dry_run' => true]);

    $this->actingAs($this->approver)->post(route('campaigns.admin.runOnce', $this->campaign), ['batch' => 1, 'dry_run' => 1]);
    expect(auditEntry('campaign_run_once')->new_values)->toMatchArray(['batch' => 1, 'dry_run' => true]);

    $this->actingAs($this->approver)->post(route('campaigns.admin.stopSending', $this->campaign));
    expect(auditEntry('campaign_stopped')->new_values)
        ->toMatchArray(['status' => 'cancelled', 'previous_status' => 'sending']);
});
