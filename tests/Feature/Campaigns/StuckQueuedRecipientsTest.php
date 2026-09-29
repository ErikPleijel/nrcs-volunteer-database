<?php

/**
 * Sending is at-most-once: a recipient claimed ('queued') whose result was never
 * recorded is never retried automatically. Admins see how many are stuck and
 * can mark them failed (audited); "Reset failed" must not bring them back.
 */

use App\Campaigns\Delivery\CampaignDeliveryService;
use App\Campaigns\Delivery\LogEmailChannel;
use App\Campaigns\Sending\CampaignSendRunner;
use App\Models\Log as AuditLog;
use App\Models\MessagingCampaign;
use App\Models\MessagingRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $permissions = ['manage-admin-panel', 'campaign_request_create', 'campaign_request_approve'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    $this->campaign = MessagingCampaign::create([
        'channel' => 'email',
        'audience_type' => 'member',
        'subject' => 'Hello',
        'body' => '<p>Hello</p>',
        'filter_json' => ['_content' => ['email_subject' => 'Hello', 'email_body' => '<p>Hello</p>']],
        'status' => 'sent',
        'created_by' => $this->admin->id,
        'scope_level' => 'national',
    ]);

    $recipient = function (string $status, int $minutesAgo) {
        $u = User::factory()->create();
        $r = MessagingRecipient::create([
            'messaging_campaign_id' => $this->campaign->id,
            'recipient_type' => User::class,
            'recipient_id' => $u->id,
            'email' => $u->email,
            'status' => $status,
        ]);
        DB::table('messaging_recipients')->where('id', $r->id)->update(['updated_at' => now()->subMinutes($minutesAgo)]);

        return $r;
    };

    $this->stuck = $recipient('queued', 20);
    $this->inFlight = $recipient('queued', 5); // under the 15-minute threshold
    $this->failed = $recipient('failed', 30);
});

test('the admin campaign page and monitor show the stuck count', function () {
    $this->actingAs($this->admin)
        ->get(route('campaigns.admin.show', $this->campaign))
        ->assertOk()
        ->assertViewHas('stuckQueuedCount', 1)
        ->assertSee('1 recipient(s) stuck in “queued”', false);

    $this->actingAs($this->admin)
        ->get(route('campaigns.admin.monitor', $this->campaign))
        ->assertOk()
        ->assertViewHas('stuckQueuedCount', 1);
});

test('marking stuck rows failed only touches rows past the threshold, and is audited', function () {
    $this->actingAs($this->admin)
        ->post(route('campaigns.admin.recipients.failStuck', $this->campaign))
        ->assertRedirect(route('campaigns.admin.show', $this->campaign));

    expect($this->stuck->fresh()->status)->toBe('failed')
        ->and($this->stuck->fresh()->last_error)->toBe(MessagingRecipient::STUCK_MARKED_FAILED_ERROR)
        ->and($this->inFlight->fresh()->status)->toBe('queued')
        ->and($this->campaign->fresh()->stats_failed)->toBe(2);

    $log = AuditLog::where('action', 'campaign_stuck_queued_marked_failed')->sole();
    expect($log->user_id)->toBe($this->admin->id)
        ->and($log->subject_id)->toBe($this->campaign->id)
        ->and($log->new_values['count'])->toBe(1);
});

test('reset failed never re-queues rows that were stuck', function () {
    $this->actingAs($this->admin)->post(route('campaigns.admin.recipients.failStuck', $this->campaign));
    $this->actingAs($this->admin)->post(route('campaigns.admin.recipients.resetFailed', $this->campaign));

    expect($this->stuck->fresh()->status)->toBe('failed')
        ->and($this->failed->fresh()->status)->toBe('pending');
});

test('marking stuck rows failed requires campaign_request_approve', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('campaigns.admin.recipients.failStuck', $this->campaign))
        ->assertForbidden();

    expect($this->stuck->fresh()->status)->toBe('queued');
});

test('a campaign with only queued rows left completes instead of hanging in sending', function () {
    $this->campaign->update(['status' => 'sending']);
    $this->failed->delete();

    $runner = new CampaignSendRunner(new CampaignDeliveryService([new LogEmailChannel]));
    $runner->runOneBatch($this->campaign->fresh(), batch: 50, force: true);

    expect($this->campaign->fresh()->status)->toBe('sent')
        ->and($this->stuck->fresh()->status)->toBe('queued');
});
