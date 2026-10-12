<?php

/**
 * Places that follow the organisation's division (step 8, 2026-10-11):
 * campaign org-rep emails by campaign scope and filter, the certificate
 * prints report (division filter; nothing for division viewers), admin
 * activity message counts by division, and staff-entered organisation
 * donations taking the organisation's branch and division.
 */

use App\Campaigns\Recipients\CampaignRecipientBuilder;
use App\Models\Donation;
use App\Models\MessagingCampaign;
use App\Models\MessagingRecipient;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    DB::table('branches')->insert([
        ['id' => 848, 'name' => 'FCT', 'code' => 'FCT', 'is_active' => true],
        ['id' => 858, 'name' => 'Lagos', 'code' => 'LAG', 'is_active' => true],
    ]);
    DB::table('divisions')->insert([
        ['id' => 3069, 'name' => 'Abuja Municipal', 'branch_id' => 848, 'is_active' => true],
        ['id' => 3070, 'name' => 'Bwari', 'branch_id' => 848, 'is_active' => true],
        ['id' => 3582, 'name' => 'Oshodi-Isolo', 'branch_id' => 858, 'is_active' => true],
    ]);

    $permissions = ['manage-admin-panel', 'view_certificates', 'view_reports', 'add_donations', 'view_organisation'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    foreach (['national_db_administrator', 'branch_db_administrator', 'division_db_assistant_finance'] as $role) {
        Role::findOrCreate($role, 'web')->syncPermissions($permissions);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->national = User::factory()->create();
    $this->national->assignRole('national_db_administrator');
    $this->branchUser = User::factory()->create(['branch_id' => 848, 'division_id' => 3069]);
    $this->branchUser->assignRole('branch_db_administrator');
    $this->divisionUser = User::factory()->create(['branch_id' => 848, 'division_id' => 3069]);
    $this->divisionUser->assignRole('division_db_assistant_finance');

    // One organisation per division, each with a linked person and an email.
    $this->orgs = collect([3069 => 848, 3070 => 848, 3582 => 858])->map(function ($branchId, $divisionId) {
        $organisation = Organisation::create([
            'name' => "Org {$divisionId}", 'email' => "org{$divisionId}@example.org",
            'branch_id' => $branchId, 'division_id' => $divisionId,
        ]);
        $organisation->users()->attach(User::factory()->create(['branch_id' => $branchId, 'division_id' => $divisionId])->id);

        return $organisation;
    });
});

function orgRepCampaign(User $creator, string $scopeLevel, ?int $scopeId, array $filter = []): MessagingCampaign
{
    return MessagingCampaign::create([
        'channel' => 'email',
        'audience_type' => 'filter',
        'subject' => 'Hello',
        'body' => 'Hello',
        'filter_json' => ['org_representatives' => '1', '_content' => ['email_subject' => 'Hello', 'email_body' => '<p>Hello</p>']] + $filter,
        'status' => 'queued',
        'created_by' => $creator->id,
        'scope_level' => $scopeLevel,
        'scope_id' => $scopeId,
    ]);
}

function orgRecipientIds(MessagingCampaign $campaign): array
{
    app(CampaignRecipientBuilder::class)->build($campaign->fresh());

    return MessagingRecipient::where('messaging_campaign_id', $campaign->id)
        ->where('recipient_type', Organisation::class)
        ->orderBy('recipient_id')
        ->pluck('recipient_id')->map(fn ($id) => (int) $id)->all();
}

test('org-rep campaign emails go to the organisations in the campaign\'s scope and filter only', function () {
    expect(orgRecipientIds(orgRepCampaign($this->national, 'national', null)))
        ->toBe([$this->orgs[3069]->id, $this->orgs[3070]->id, $this->orgs[3582]->id]);

    expect(orgRecipientIds(orgRepCampaign($this->branchUser, 'branch', 848)))
        ->toBe([$this->orgs[3069]->id, $this->orgs[3070]->id]);

    // Division-scoped: before, this reached every organisation nationwide.
    expect(orgRecipientIds(orgRepCampaign($this->divisionUser, 'division', 3070)))
        ->toBe([$this->orgs[3070]->id]);

    // A national campaign made from a filtered organisation list.
    expect(orgRecipientIds(orgRepCampaign($this->national, 'national', null, ['branch_id' => '848'])))
        ->toBe([$this->orgs[3069]->id, $this->orgs[3070]->id]);
    expect(orgRecipientIds(orgRepCampaign($this->national, 'national', null, ['branch_id' => '848', 'division_id' => '3069'])))
        ->toBe([$this->orgs[3069]->id]);
});

function userRecipientIds(MessagingCampaign $campaign): array
{
    app(CampaignRecipientBuilder::class)->build($campaign->fresh());

    return MessagingRecipient::where('messaging_campaign_id', $campaign->id)
        ->where('recipient_type', User::class)
        ->pluck('recipient_id')->map(fn ($id) => (int) $id)->all();
}

test('org representatives are placed by their organisation\'s branch and division, not their own', function () {
    // Bwari's organisation gets a contact who lives in Lagos / Oshodi-Isolo.
    $farContact = User::factory()->create(['branch_id' => 858, 'division_id' => 3582, 'email' => 'far@example.org']);
    $this->orgs[3070]->users()->attach($farContact->id);

    expect(userRecipientIds(orgRepCampaign($this->national, 'national', null, ['branch_id' => '848', 'division_id' => '3070'])))
        ->toContain($farContact->id)
        ->and(userRecipientIds(orgRepCampaign($this->branchUser, 'branch', 848)))->toContain($farContact->id)
        ->and(userRecipientIds(orgRepCampaign($this->divisionUser, 'division', 3070)))->toContain($farContact->id)
        // The Lagos organisation's own contact is not in FCT's scope.
        ->and(userRecipientIds(orgRepCampaign($this->branchUser, 'branch', 848)))
        ->not->toContain($this->orgs[3582]->users()->first()->id);
});

test('personal recipients keep using the person\'s own division', function () {
    $farContact = User::factory()->create(['branch_id' => 858, 'division_id' => 3582, 'email' => 'far@example.org']);
    $this->orgs[3070]->users()->attach($farContact->id);

    $personal = MessagingCampaign::create([
        'channel' => 'email', 'audience_type' => 'filter', 'subject' => 'Hello', 'body' => 'Hello',
        'filter_json' => ['branch_id' => '848', 'division_id' => '3070', '_content' => ['email_subject' => 'Hello', 'email_body' => '<p>Hello</p>']],
        'status' => 'queued', 'created_by' => $this->national->id, 'scope_level' => 'national',
    ]);

    expect(userRecipientIds($personal))
        ->not->toContain($farContact->id)
        ->toContain($this->orgs[3070]->users()->where('users.id', '!=', $farContact->id)->first()->id);
});

test('the Users list has no Org Representatives filter for division staff', function () {
    Role::findOrCreate('division_db_assistant_finance', 'web')->givePermissionTo(Permission::findOrCreate('view_user', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $farContact = User::factory()->create(['branch_id' => 858, 'division_id' => 3582, 'first_name' => 'Faraway']);
    $this->orgs[3069]->users()->attach($farContact->id);

    $this->actingAs($this->divisionUser)
        ->get(route('users.index', ['org_representatives' => '1']))
        ->assertOk()
        ->assertDontSee('Org representatives only')
        ->assertDontSee('Faraway');
});

test('the organisation list passes its division filter on to the campaign', function () {
    Role::findOrCreate('national_db_administrator', 'web')->givePermissionTo(Permission::findOrCreate('campaign_request_create', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actingAs($this->national)
        ->get(route('organisations.index', ['branch_id' => 848, 'division_id' => 3070]))
        ->assertOk()
        ->assertSee('name="filter_json[division_id]" value="3070"', false);
});

function orgPrint(Organisation $organisation, User $by): void
{
    DB::table('certificates_print')->insert([
        'user_id' => null, 'organisation_id' => $organisation->id, 'training_id' => null,
        'printed_by_user_id' => $by->id, 'certificate_type' => 'organisation_membership',
        'printed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

test('the certificate prints report: division filter matches organisations, division viewers see none', function () {
    foreach ($this->orgs as $organisation) {
        orgPrint($organisation, $this->national);
    }
    $orgIds = fn ($response) => $response->viewData('certificatePrints')->pluck('organisation_id')->filter()->map(fn ($id) => (int) $id)->sort()->values()->all();

    $filtered = $this->actingAs($this->national)->get(route('certificates.prints-report', ['division_id' => 3070]))->assertOk();
    expect($orgIds($filtered))->toBe([$this->orgs[3070]->id]);
    $filtered->assertSee('Bwari');

    $branch = $this->actingAs($this->branchUser)->get(route('certificates.prints-report'))->assertOk();
    expect($orgIds($branch))->toBe([$this->orgs[3069]->id, $this->orgs[3070]->id]);

    $division = $this->actingAs($this->divisionUser)->get(route('certificates.prints-report'))->assertOk();
    expect($orgIds($division))->toBe([]);
});

test('admin activity: organisation-recipient messages count in their organisation\'s division', function () {
    // One row per organisation per campaign: Bwari's organisation in two campaigns.
    $first = orgRepCampaign($this->national, 'national', null);
    $second = orgRepCampaign($this->national, 'national', null);
    foreach ([[$first, 3069], [$first, 3070], [$second, 3070]] as [$campaign, $divisionId]) {
        MessagingRecipient::create([
            'messaging_campaign_id' => $campaign->id, 'recipient_type' => Organisation::class,
            'recipient_id' => $this->orgs[$divisionId]->id, 'email' => 'x@example.org',
            'status' => 'sent', 'sent_at' => now(),
        ]);
    }

    $response = $this->actingAs($this->national)
        ->get(route('reports.admin-activities.index', ['tab' => 'messages', 'branch_id' => 848]))
        ->assertOk()
        ->assertDontSee('totals per RC unit reflect individual recipients only');

    $rows = $response->viewData('drillRows')->keyBy('name');
    expect($rows['Bwari']['total'])->toBe(2)
        ->and($rows['Bwari']['org_total'])->toBe(2)
        ->and($rows['Abuja Municipal']['org_total'])->toBe(1);

    $this->actingAs($this->national)
        ->get(route('reports.admin-activities.index', ['tab' => 'messages', 'branch_id' => 848, 'division_id' => 3070]))
        ->assertOk()
        ->assertSee('totals per RC unit reflect individual recipients only');
});

test('a staff-entered organisation donation takes the organisation\'s branch and division, not the form\'s', function () {
    $organisation = $this->orgs[3070];
    $contact = $organisation->users()->first();

    $this->actingAs($this->branchUser)->post(route('donations.store'), [
        'user_id' => $contact->id,
        'organisation_id' => $organisation->id,
        'amount' => 5000,
        'date_donation' => now()->toDateString(),
        'in_kind_donation' => false,
        // Hand-crafted: another branch and division.
        'branch_id' => 858,
        'division_id' => 3582,
    ])->assertRedirect();

    $donation = Donation::withAnyApprovalStatus()->sole();
    expect($donation->branch_id)->toBe(848)
        ->and($donation->division_id)->toBe(3070);
});
