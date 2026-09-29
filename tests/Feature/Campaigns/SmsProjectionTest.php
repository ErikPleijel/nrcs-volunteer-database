<?php

/**
 * Estimated SMS volume at wizard Step 5 and on the admin approval screen:
 * SMS recipients (after opt-outs, invalid numbers and shared-number
 * deduplication) × pages per message = SMS pages, and cost when a price is set.
 */

use App\Campaigns\Recipients\CampaignRecipientBuilder;
use App\Models\MessagingCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $permissions = ['manage-admin-panel', 'campaign_request_create', 'campaign_request_approve'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    // Audience: 2 share a number, 1 invalid number, 1 opted out, 1 unique → 2 SMS recipients.
    User::factory()->create(['email' => null, 'telephone1' => '08031111111']);
    User::factory()->create(['email' => null, 'telephone1' => '08031111111']);
    User::factory()->create(['email' => null, 'telephone1' => '0803111']);
    User::factory()->create(['email' => null, 'telephone1' => '08032222222', 'sms_opt_out' => true]);
    User::factory()->create(['email' => null, 'telephone1' => '08033333333']);

    $this->campaignWithSms = fn (string $sms, string $status = 'draft') => MessagingCampaign::create([
        'channel' => 'sms',
        'audience_type' => 'member',
        'body' => $sms,
        'filter_json' => ['_content' => ['sms_body' => $sms]],
        'status' => $status,
        'created_by' => $this->admin->id,
        'scope_level' => 'national',
    ]);
});

test('Step 5 shows SMS recipients × pages after opt-outs, invalid and shared numbers', function () {
    $campaign = ($this->campaignWithSms)('Hello from the Red Cross.');

    $projection = $this->actingAs($this->admin)
        ->get(route('campaigns.wizard.step5', $campaign))
        ->assertOk()
        ->assertSee('Estimated SMS volume')
        ->viewData('smsProjection');

    expect($projection)->toMatchArray([
        'recipients' => 2,
        'recipients_source' => 'audience',
        'segments' => 1,
        'encoding' => 'GSM-7',
        'pages' => 2,
        'cost' => null,
    ]);
});

test('a Unicode message is flagged and its pages counted at 70/67', function () {
    // 60 characters + ~69-character footer: > 70 UTF-16 units, so 2 pages as Unicode (1 as GSM).
    $campaign = ($this->campaignWithSms)('Sannu! Muna godiya da ƙoƙarinku a wannan makon, ɗan uwa ɓata.');

    $projection = $this->actingAs($this->admin)
        ->get(route('campaigns.wizard.step5', $campaign))
        ->assertOk()
        ->assertSee('Unicode SMS')
        ->viewData('smsProjection');

    expect($projection['encoding'])->toBe('UCS-2')
        ->and($projection['non_gsm'])->toContain('ƙ', 'ɗ', 'ɓ')
        ->and($projection['segments'])->toBe(2)
        ->and($projection['pages'])->toBe(4);
});

test('with a price per page configured the approval screen shows the estimated cost', function () {
    config(['sms.price_per_segment' => 4.5, 'sms.currency' => 'NGN']);
    $campaign = ($this->campaignWithSms)('Hello from the Red Cross.', 'proposed');

    $this->actingAs($this->admin)
        ->get(route('campaigns.admin.show', $campaign))
        ->assertOk()
        ->assertViewHas('smsProjection', fn ($p) => $p['pages'] === 2 && $p['cost'] === 9.0)
        ->assertSee('NGN 9.00');
});

test('once recipients are built the approval screen counts from the recipient rows', function () {
    $campaign = ($this->campaignWithSms)('Hello from the Red Cross.', 'queued');
    app(CampaignRecipientBuilder::class)->build($campaign, onlyContactable: true);

    $this->actingAs($this->admin)
        ->get(route('campaigns.admin.show', $campaign))
        ->assertOk()
        ->assertViewHas('smsProjection', fn ($p) => $p['recipients_source'] === 'recipients' && $p['recipients'] === 2);
});

test('an email-only campaign has no SMS projection', function () {
    $campaign = MessagingCampaign::create([
        'channel' => 'email', 'audience_type' => 'member', 'subject' => 'Hi', 'body' => '<p>Hi</p>',
        'filter_json' => ['_content' => ['email_subject' => 'Hi', 'email_body' => '<p>Hi</p>']],
        'status' => 'proposed', 'created_by' => $this->admin->id, 'scope_level' => 'national',
    ]);

    $this->actingAs($this->admin)
        ->get(route('campaigns.admin.show', $campaign))
        ->assertOk()
        ->assertViewHas('smsProjection', null)
        ->assertDontSee('Estimated SMS volume');
});
