<?php

/**
 * A second approve/reject/queue/start on a campaign that has already moved on
 * (double-click, or two reviewers with the same page open — the 14 Oct 2026
 * training incident) gets a friendly message instead of a bare 422, and never
 * notifies, audits or changes the campaign a second time.
 */

use App\Models\Log as AuditLog;
use App\Models\MessagingCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
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

    $this->first = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Obi']);
    $this->first->assignRole('national_db_administrator');
    $this->second = User::factory()->create(['first_name' => 'Bello', 'last_name' => 'Musa']);
    $this->second->assignRole('national_db_administrator');
    $this->submitter = User::factory()->create();

    User::factory()->create(['email' => null, 'telephone1' => '08031111111']);

    $this->campaign = MessagingCampaign::create([
        'title' => 'Flood appeal',
        'channel' => 'sms',
        'audience_type' => 'member',
        'body' => 'Hello from the Red Cross.',
        'filter_json' => ['_content' => ['sms_body' => 'Hello from the Red Cross.']],
        'status' => 'proposed',
        'submitted_by' => $this->submitter->id,
        'created_by' => $this->submitter->id,
        'scope_level' => 'national',
    ]);
    $this->showUrl = route('campaigns.admin.show', $this->campaign);
});

function campaignDecidedCount(int $campaignId): int
{
    return DB::table('notifications')
        ->where('data', 'like', '%"type":"campaign_decided"%')
        ->where('data', 'like', '%"campaign_id":'.$campaignId.',%')
        ->count();
}

test('approving a campaign another reviewer already approved shows who approved it, not a 422', function () {
    $this->actingAs($this->first)->from($this->showUrl)
        ->post(route('campaigns.admin.approve', $this->campaign))
        ->assertRedirect($this->showUrl)
        ->assertSessionHas('success');

    $this->actingAs($this->second)->from($this->showUrl)
        ->post(route('campaigns.admin.approve', $this->campaign))
        ->assertRedirect($this->showUrl)
        ->assertSessionHas('error', fn ($m) => str_starts_with($m, 'This campaign has already been approved by Ada Obi at '));

    $campaign = $this->campaign->fresh();
    expect($campaign->status)->toBe('approved')
        ->and($campaign->approved_by)->toBe($this->first->id)
        ->and(campaignDecidedCount($campaign->id))->toBe(1)
        ->and(AuditLog::where('action', 'campaign_approved')->count())->toBe(1);
});

test('a double-click on approve tells the same reviewer they already approved it', function () {
    $this->actingAs($this->first)->from($this->showUrl)->post(route('campaigns.admin.approve', $this->campaign));

    $this->actingAs($this->first)->from($this->showUrl)
        ->post(route('campaigns.admin.approve', $this->campaign))
        ->assertRedirect($this->showUrl)
        ->assertSessionHas('error', fn ($m) => str_starts_with($m, 'You already approved this campaign at '));

    expect(campaignDecidedCount($this->campaign->id))->toBe(1)
        ->and(AuditLog::where('action', 'campaign_approved')->count())->toBe(1);
});

test('rejecting a campaign that was just approved keeps the approval and does not notify a rejection', function () {
    $this->actingAs($this->first)->from($this->showUrl)->post(route('campaigns.admin.approve', $this->campaign));

    $this->actingAs($this->second)->from($this->showUrl)
        ->post(route('campaigns.admin.reject', $this->campaign), ['review_note' => 'Wrong audience selected.'])
        ->assertRedirect($this->showUrl)
        ->assertSessionHas('error', fn ($m) => str_starts_with($m, 'This campaign has already been approved by Ada Obi'));

    $campaign = $this->campaign->fresh();
    expect($campaign->status)->toBe('approved')
        ->and($campaign->rejected_by)->toBeNull()
        ->and(campaignDecidedCount($campaign->id))->toBe(1)
        ->and(AuditLog::where('action', 'campaign_rejected')->count())->toBe(0);
});

test('approving a campaign that was just rejected shows who rejected it', function () {
    $this->actingAs($this->first)->from($this->showUrl)
        ->post(route('campaigns.admin.reject', $this->campaign), ['review_note' => 'Wrong audience selected.'])
        ->assertSessionHas('success');

    $this->actingAs($this->second)->from($this->showUrl)
        ->post(route('campaigns.admin.approve', $this->campaign))
        ->assertSessionHas('error', fn ($m) => str_starts_with($m, 'This campaign has already been rejected by Ada Obi at '));

    expect($this->campaign->fresh()->status)->toBe('rejected')
        ->and(campaignDecidedCount($this->campaign->id))->toBe(1);
});

test('rejecting a campaign is audited with the review note', function () {
    $this->actingAs($this->first)->from($this->showUrl)
        ->post(route('campaigns.admin.reject', $this->campaign), ['review_note' => 'Wrong audience selected.'])
        ->assertSessionHas('success');

    $entries = AuditLog::where('action', 'campaign_rejected')->get();
    expect($entries)->toHaveCount(1)
        ->and($entries->first()->user_id)->toBe($this->first->id)
        ->and($entries->first()->subject_id)->toBe($this->campaign->id)
        ->and($entries->first()->new_values)->toMatchArray(['status' => 'rejected', 'review_note' => 'Wrong audience selected.']);
});

test('queueing twice gives a message the second time', function () {
    $this->campaign->update(['status' => 'approved', 'approved_by' => $this->first->id, 'approved_at' => now()]);
    $listUrl = route('campaigns.admin.proposed');

    $this->actingAs($this->first)->from($listUrl)->post(route('campaigns.admin.queue', $this->campaign))
        ->assertSessionHas('success');

    $this->actingAs($this->second)->from($listUrl)->post(route('campaigns.admin.queue', $this->campaign))
        ->assertRedirect($listUrl)
        ->assertSessionHas('error', 'This campaign has already been queued.');

    expect($this->campaign->fresh()->status)->toBe('queued');
});

test('starting to send twice gives a message and runs only one start', function () {
    $this->campaign->update(['status' => 'approved', 'approved_by' => $this->first->id, 'approved_at' => now()]);
    $this->actingAs($this->first)->post(route('campaigns.admin.queue', $this->campaign));
    $this->actingAs($this->first)->post(route('campaigns.admin.buildRecipients', $this->campaign), ['only_contactable' => 1])
        ->assertSessionHas('success');

    $start = fn (User $user) => $this->actingAs($user)->from($this->showUrl)
        ->post(route('campaigns.admin.startSending', $this->campaign), ['batch' => 1, 'dry_run' => 1]);

    $start($this->first)->assertSessionHas('success');
    $start($this->second)
        ->assertRedirect($this->showUrl)
        ->assertSessionHas('error', fn ($m) => in_array($m, [
            'Sending has already started for this campaign.',
            'This campaign has already been sent.',
        ], true));

    expect(AuditLog::where('action', 'campaign_send_started')->count())->toBe(1);
});

test('a bare abort(422) shows the friendly 422 page, not a debug page', function () {
    Route::get('/_test/abort-422', fn () => abort(422, 'This record was already handled.'))->middleware('web');

    $this->get('/_test/abort-422')
        ->assertStatus(422)
        ->assertSee('This Action Could Not Be Completed')
        ->assertSee('This record was already handled.')
        ->assertSee('Go Back');
});
