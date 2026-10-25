<?php

/**
 * Dashboard, Database Administration row: campaign messages sent, ID cards
 * printed and certificates printed in the last 7 days.
 * - only up to now: a date in the future (bad imported data) never counts
 * - for the dashboard's branch, like the rest of the page; branch attribution
 *   follows the Admin Activities report (person, organisation, or the Red
 *   Cross unit's division)
 */

use App\Models\Branch;
use App\Models\CertificatePrint;
use App\Models\Division;
use App\Models\IdCardPrint;
use App\Models\MessagingCampaign;
use App\Models\MessagingRecipient;
use App\Models\Organisation;
use App\Models\RedCrossUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Cache::flush();

    foreach (['manage-admin-panel', 'view_reports', 'view_user'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions(['manage-admin-panel', 'view_reports', 'view_user']);
    Role::findOrCreate('branch_db_administrator', 'web')->syncPermissions(['manage-admin-panel', 'view_reports', 'view_user']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branchA = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->branchB = Branch::create(['name' => 'Beta Branch', 'code' => 'BET']);
    $this->divisionA = Division::create(['name' => 'Alpha One', 'branch_id' => $this->branchA->id]);
    $this->divisionB = Division::create(['name' => 'Beta One', 'branch_id' => $this->branchB->id]);

    $this->national = User::factory()->create();
    $this->national->assignRole('national_db_administrator');
    $this->branchAdmin = User::factory()->inBranch($this->branchA)->create();
    $this->branchAdmin->assignRole('branch_db_administrator');

    $this->personA = User::factory()->inDivision($this->divisionA)->create();
    $this->personB = User::factory()->inDivision($this->divisionB)->create();
    $this->orgA = Organisation::create(['name' => 'Alpha Org', 'branch_id' => $this->branchA->id]);
    $this->unitA = RedCrossUnit::create(['name' => 'Alpha Unit', 'division_id' => $this->divisionA->id, 'is_active' => true]);

    $this->campaign = MessagingCampaign::create([
        'channel' => 'email', 'audience_type' => 'member', 'subject' => 'Hi', 'body' => '<p>Hi</p>',
        'filter_json' => [], 'status' => 'sending', 'created_by' => $this->national->id, 'scope_level' => 'national',
    ]);
});

function activityFigures(User $viewer, array $query = []): array
{
    $data = test()->actingAs($viewer)->get(route('reports.dashboard', $query))->assertOk()->viewData('dashboardData');

    return [$data['messagesSentLast7'], $data['idCardsPrintedLast7'], $data['certificatesPrintedLast7']];
}

function idCard(User $person, $printedAt): void
{
    IdCardPrint::create(['user_id' => $person->id, 'printed_by_user_id' => test()->national->id, 'printed_at' => $printedAt, 'status' => 'printed', 'validity_months' => 36]);
}

function certificate(array $attributes, $printedAt): void
{
    CertificatePrint::create(['certificate_type' => 'membership', 'printed_at' => $printedAt, 'printed_by_user_id' => test()->national->id, ...$attributes]);
}

function message($recipient, $sentAt): void
{
    MessagingRecipient::create([
        'messaging_campaign_id' => test()->campaign->id,
        'recipient_type' => $recipient::class,
        'recipient_id' => $recipient->id,
        'email' => 'x@example.org',
        'status' => 'sent',
        'sent_at' => $sentAt,
    ]);
}

it('counts only the last 7 days up to now, never a date in the future', function () {
    idCard($this->personA, now()->subDays(2));
    idCard($this->personA, now()->subDays(10));
    idCard($this->personA, now()->addYears(2));
    certificate(['user_id' => $this->personA->id], now()->subDay());
    certificate(['user_id' => $this->personA->id], now()->addMonth());
    message($this->personA, now()->subHour());
    message($this->personB, now()->addDay());

    expect(activityFigures($this->national, ['branch_id' => '']))->toBe([1, 1, 1]);
});

it('follows the dashboard\'s branch, attributing by person, organisation or unit', function () {
    foreach ([$this->personA, $this->personB] as $person) {
        idCard($person, now()->subDay());
        message($person, now()->subDay());
        certificate(['user_id' => $person->id], now()->subDay());
    }
    message($this->orgA, now()->subDay());
    certificate(['certificate_type' => 'organisation_membership', 'organisation_id' => $this->orgA->id], now()->subDay());
    certificate(['certificate_type' => 'rcu_membership', 'red_cross_unit_id' => $this->unitA->id], now()->subDay());

    // National view: everything.
    expect(activityFigures($this->national, ['branch_id' => '']))->toBe([3, 2, 4]);

    // A branch admin starts on their own branch; the branch selector changes it, as for the rest of the page.
    expect(activityFigures($this->branchAdmin))->toBe([2, 1, 3])
        ->and(activityFigures($this->branchAdmin, ['branch_id' => $this->branchB->id]))->toBe([1, 1, 1]);
});
