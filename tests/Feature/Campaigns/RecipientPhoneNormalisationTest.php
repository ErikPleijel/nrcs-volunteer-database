<?php

/**
 * Recipient building converts stored numbers to E.164 at send time only:
 * telephone1 when valid, else telephone2; recipients who can only be reached
 * by SMS but have no valid mobile get a visible skipped_invalid_number row.
 * Stored users.telephone1/2 are never rewritten.
 */

use App\Campaigns\Recipients\RecipientPhone;
use App\Models\Branch;
use App\Models\Division;
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
    $this->withoutVite();

    $permissions = ['manage-admin-panel', 'campaign_request_approve', 'edit_user'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Approver has no phone, so with only_contactable it never gets an SMS row.
    $this->approver = User::factory()->create();
    $this->approver->assignRole('national_db_administrator');

    $this->formatted = User::factory()->create(['email' => null, 'telephone1' => '0803 123 4567']);
    $this->fallsBack = User::factory()->create(['email' => null, 'telephone1' => 'nil', 'telephone2' => '08051234567']);
    $this->invalid = User::factory()->create(['email' => null, 'telephone1' => '0803123456']);
    $this->noPhone = User::factory()->create(['email' => null]);
    $this->emailAndBadPhone = User::factory()->create(['email' => 'has@example.com', 'telephone1' => '+447911123456']);

    $this->makeCampaign = fn (string $channel) => MessagingCampaign::create([
        'channel' => $channel,
        'audience_type' => 'member',
        'subject' => 'Hello',
        'body' => 'Hello',
        'filter_json' => ['_content' => ['email_subject' => 'Hello', 'email_body' => '<p>Hello</p>', 'sms_body' => 'Hello there']],
        'status' => 'queued',
        'created_by' => $this->approver->id,
        'scope_level' => 'national',
    ]);
});

function recipientRow(MessagingCampaign $campaign, User $user): ?MessagingRecipient
{
    return MessagingRecipient::where('messaging_campaign_id', $campaign->id)->where('recipient_id', $user->id)->first();
}

/** Both builders must produce the same rows. */
dataset('builders', [
    'admin Build button' => [fn (MessagingCampaign $c) => test()->actingAs(test()->approver)
        ->post(route('campaigns.admin.buildRecipients', $c), ['only_contactable' => 1])],
    'campaigns:build-recipients' => [fn (MessagingCampaign $c) => Artisan::call('campaigns:build-recipients', [
        'campaignId' => $c->id, '--only-contactable' => true,
    ])],
]);

test('sms campaign: numbers are converted, telephone2 is the fallback, invalid numbers are marked', function (Closure $build) {
    $campaign = ($this->makeCampaign)('sms');
    $build($campaign);

    expect(recipientRow($campaign, $this->formatted)->phone)->toBe('+2348031234567')
        ->and(recipientRow($campaign, $this->formatted)->status)->toBe('pending')
        ->and(recipientRow($campaign, $this->fallsBack)->phone)->toBe('+2348051234567');

    $invalid = recipientRow($campaign, $this->invalid);
    expect($invalid->status)->toBe('skipped_invalid_number')
        ->and($invalid->phone)->toBeNull()
        ->and($invalid->last_error)->toBe(RecipientPhone::INVALID_NUMBER_ERROR);

    // No number at all: unchanged behaviour, dropped by only_contactable.
    expect(recipientRow($campaign, $this->noPhone))->toBeNull();

    // Stored user data is never rewritten.
    expect($this->formatted->fresh()->telephone1)->toBe('0803 123 4567')
        ->and($this->fallsBack->fresh()->telephone1)->toBe('nil');
})->with('builders');

test('email_fallback_sms: a recipient with email and an invalid phone still gets email', function (Closure $build) {
    $campaign = ($this->makeCampaign)('email_fallback_sms');
    $build($campaign);

    $row = recipientRow($campaign, $this->emailAndBadPhone);
    expect($row->status)->toBe('pending')
        ->and($row->email)->toBe('has@example.com')
        ->and($row->phone)->toBeNull();

    // Email-less with an invalid number: SMS was the only way to reach them.
    expect(recipientRow($campaign, $this->invalid)->status)->toBe('skipped_invalid_number');
})->with('builders');

test('a rebuild moves a fixed number from skipped_invalid_number back to pending', function () {
    $campaign = ($this->makeCampaign)('sms');
    $build = fn () => $this->actingAs($this->approver)
        ->post(route('campaigns.admin.buildRecipients', $campaign), ['only_contactable' => 1]);

    $build();
    expect(recipientRow($campaign, $this->invalid)->status)->toBe('skipped_invalid_number');

    $this->invalid->update(['telephone1' => '08031234560']);
    $build();

    expect(recipientRow($campaign, $this->invalid)->status)->toBe('pending')
        ->and(recipientRow($campaign, $this->invalid)->phone)->toBe('+2348031234560');
});

/*
|--------------------------------------------------------------------------
| Admin user edit form: the phone rule only applies to changed values
|--------------------------------------------------------------------------
*/

function phoneUpdatePayload(Branch $branch, Division $division, array $overrides = []): array
{
    return array_merge([
        'first_name' => 'Test',
        'last_name' => 'User',
        'gender' => 'male',
        'birth_year' => 1990,
        'branch_id' => $branch->id,
        'division_id' => $division->id,
        'contribution_type' => 'volunteering',
    ], $overrides);
}

test('an unchanged legacy invalid number does not block an unrelated edit', function () {
    $branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $division = Division::create(['name' => 'Alpha Division', 'branch_id' => $branch->id]);
    $user = User::factory()->create(['telephone1' => '0803123456', 'branch_id' => $branch->id, 'division_id' => $division->id]);

    $this->actingAs($this->approver)
        ->put(route('users.update', $user), phoneUpdatePayload($branch, $division, [
            'first_name' => 'Renamed',
            'telephone1' => '0803123456',
        ]))
        ->assertSessionHasNoErrors();

    expect($user->fresh()->first_name)->toBe('Renamed')
        ->and($user->fresh()->telephone1)->toBe('0803123456');
});

test('a changed number must be a Nigerian mobile, and formatting is stripped on save', function () {
    $branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $division = Division::create(['name' => 'Alpha Division', 'branch_id' => $branch->id]);
    $user = User::factory()->create(['telephone1' => '0803123456', 'branch_id' => $branch->id, 'division_id' => $division->id]);

    $this->actingAs($this->approver)
        ->put(route('users.update', $user), phoneUpdatePayload($branch, $division, ['telephone1' => '+44 7911 123456']))
        ->assertSessionHasErrors('telephone1');
    expect($user->fresh()->telephone1)->toBe('0803123456');

    $this->actingAs($this->approver)
        ->put(route('users.update', $user), phoneUpdatePayload($branch, $division, ['telephone1' => '0803 123 4567']))
        ->assertSessionHasNoErrors();
    expect($user->fresh()->telephone1)->toBe('08031234567'); // still 0-prefixed, not rewritten to +234
});
