<?php

/**
 * Organisation certificate flow — regression coverage for four fixes made
 * before the RCU certificate work copies this code:
 *
 *  1. The bulk page's card details (fee / expiry / donation totals) render —
 *     the @switch used to test 'membership'/'donation', never the real
 *     'organisation_membership'/'organisation_donation' values.
 *  2. organisations/show's print buttons are only enabled when the
 *     certificates list would actually contain the organisation.
 *  3. The print endpoints skip organisations with no active membership
 *     instead of 500-ing on a null payment.
 *  4. The plain template's "Ref:" shows the payment reference for an
 *     organisation (it has no user reference), not "—".
 */

use App\Models\Branch;
use App\Models\Donation;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $permissions = ['manage-admin-panel', 'print_certificates', 'view_certificates'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->orgFee = MembershipFee::factory()->create(['name' => 'Corporate Gold', 'for_organizations' => true, 'amount' => 50000]);
});

function certOrg(string $name): Organisation
{
    return Organisation::create(['name' => $name, 'branch_id' => test()->branch->id]);
}

function certOrgPayment(Organisation $org, array $overrides = [], bool $approved = true): MembershipPayment
{
    $factory = $approved ? MembershipPayment::factory()->approved() : MembershipPayment::factory();

    return $factory->create(array_merge([
        'user_id' => User::factory()->create()->id,
        'organisation_id' => $org->id,
        'membership_fee_id' => test()->orgFee->id,
        'branch_id' => test()->branch->id,
        'payment_date' => now()->subMonth()->toDateString(),
        'expiry_date' => now()->addMonths(11)->toDateString(),
    ], $overrides));
}

/*
|--------------------------------------------------------------------------
| 1 — bulk page card details
|--------------------------------------------------------------------------
*/

test('membership cards on the bulk page show the fee name and expiry', function () {
    $org = certOrg('Acme Ltd');
    $payment = certOrgPayment($org);

    $this->actingAs($this->admin)
        ->get(route('organisations.certificates.index', ['certificate_type' => 'organisation_membership']))
        ->assertOk()
        ->assertSeeInOrder(['Acme Ltd', 'Active Member', 'Corporate Gold', $payment->expiry_date->format('M d, Y')]);
});

test('donation cards on the bulk page show the cash total and in-kind count', function () {
    $org = certOrg('Giving Co');
    Donation::factory()->approved()->create(['organisation_id' => $org->id, 'amount' => 12000, 'in_kind_donation' => false]);
    Donation::factory()->approved()->create(['organisation_id' => $org->id, 'amount' => 3000, 'in_kind_donation' => false]);
    Donation::factory()->approved()->create(['organisation_id' => $org->id, 'amount' => 5, 'in_kind_donation' => true, 'donation_item' => 'Blankets']);

    $this->actingAs($this->admin)
        ->get(route('organisations.certificates.index', ['certificate_type' => 'organisation_donation']))
        ->assertOk()
        ->assertSeeInOrder(['Giving Co', 'Cash Donated:', '₦15,000', 'In-Kind:', '1', 'item']);
});

/*
|--------------------------------------------------------------------------
| 2 — organisations/show print buttons
|--------------------------------------------------------------------------
*/

function membershipCertLink(Organisation $org): string
{
    return e(route('organisations.certificates.index', [
        'certificate_type' => 'organisation_membership',
        'search' => $org->id,
        'branch_id' => '',
    ]));
}

test('the membership certificate button is enabled for an active membership', function () {
    $org = certOrg('Active Org');
    certOrgPayment($org);

    $this->actingAs($this->admin)
        ->get(route('organisations.show', $org))
        ->assertOk()
        ->assertSee(membershipCertLink($org), false);
});

test('the membership certificate button is disabled with only a pending or an expired payment', function (string $case) {
    $org = certOrg('Inactive Org');
    $case === 'pending'
        ? certOrgPayment($org, approved: false)
        : certOrgPayment($org, ['payment_date' => now()->subYears(2)->toDateString(), 'expiry_date' => now()->subYear()->toDateString()]);

    $this->actingAs($this->admin)
        ->get(route('organisations.show', $org))
        ->assertOk()
        ->assertSee('Print membership certificate')
        ->assertDontSee(membershipCertLink($org), false);

    // ...and the list the button would have opened is indeed empty.
    $this->actingAs($this->admin)
        ->get(route('organisations.certificates.index', ['certificate_type' => 'organisation_membership', 'search' => $org->id]))
        ->assertSee('No organisations found matching your criteria.');
})->with(['pending', 'expired']);

test('the donation certificate button is disabled when the only donation is pending', function () {
    $org = certOrg('Pending Giver');
    Donation::factory()->create(['organisation_id' => $org->id]); // pending (factory default)

    $link = e(route('organisations.certificates.index', ['certificate_type' => 'organisation_donation', 'search' => $org->id, 'branch_id' => '']));

    $this->actingAs($this->admin)
        ->get(route('organisations.show', $org))
        ->assertOk()
        ->assertDontSee($link, false);

    Donation::factory()->approved()->create(['organisation_id' => $org->id]);

    $this->actingAs($this->admin)
        ->get(route('organisations.show', $org))
        ->assertSee($link, false);
});

/*
|--------------------------------------------------------------------------
| 3 — print endpoints skip ineligible organisations
|--------------------------------------------------------------------------
*/

test('printing only ineligible organisations redirects back with a message instead of a 500', function (string $routeName) {
    $expired = certOrg('Lapsed Org');
    certOrgPayment($expired, ['payment_date' => now()->subYears(2)->toDateString(), 'expiry_date' => now()->subYear()->toDateString()]);
    $never = certOrg('Never Paid Org');

    $this->actingAs($this->admin)
        ->from(route('organisations.certificates.index'))
        ->post(route($routeName), [
            'certificate_type' => 'organisation_membership',
            'training_ids' => [$expired->id, $never->id],
        ])
        ->assertRedirect(route('organisations.certificates.index'))
        ->assertSessionHas('error', 'None of the selected organisations are currently eligible for this certificate.');

    // The message is shown on the page.
    $this->actingAs($this->admin)
        ->withSession(['error' => 'None of the selected organisations are currently eligible for this certificate.'])
        ->get(route('organisations.certificates.index'))
        ->assertSee('None of the selected organisations are currently eligible for this certificate.');
})->with(['organisations.certificates.print.plain', 'organisations.certificates.print.branded']);

test('a mixed selection prints only the eligible organisations', function (string $routeName) {
    $active = certOrg('Active Org');
    certOrgPayment($active);
    $never = certOrg('Never Paid Org');

    $this->actingAs($this->admin)
        ->post(route($routeName), [
            'certificate_type' => 'organisation_membership',
            'training_ids' => [$active->id, $never->id],
        ])
        ->assertOk()
        ->assertSee('Active Org')
        ->assertDontSee('Never Paid Org');
})->with(['organisations.certificates.print.plain', 'organisations.certificates.print.branded']);

/*
|--------------------------------------------------------------------------
| 4 — plain template Ref line
|--------------------------------------------------------------------------
*/

test('the plain organisation certificate shows the payment reference, not a dash', function () {
    $org = certOrg('Ref Org');
    $payment = certOrgPayment($org);

    $expectedRef = str_replace('/', '/<wbr>', e($payment->payment_reference));

    $this->actingAs($this->admin)
        ->post(route('organisations.certificates.print.plain'), [
            'certificate_type' => 'organisation_membership',
            'training_ids' => [$org->id],
        ])
        ->assertOk()
        ->assertSee('Ref: '.$expectedRef, false)
        ->assertDontSee('Ref: —', false);
});

test('regression: branded organisation certificate still renders name, fee, dates and payment reference', function () {
    $org = certOrg('Branded Org');
    $payment = certOrgPayment($org);

    $this->actingAs($this->admin)
        ->post(route('organisations.certificates.print.branded'), [
            'certificate_type' => 'organisation_membership',
            'training_ids' => [$org->id],
        ])
        ->assertOk()
        ->assertSee('Branded Org')
        ->assertSee('Corporate Gold Member')
        ->assertSee('from '.$payment->payment_date->format('F j, Y').' to '.$payment->expiry_date->format('F j, Y'))
        ->assertSee('Ref: '.$payment->payment_reference);
});

test('regression: a personal plain membership certificate still shows the holder\'s reference', function () {
    $member = User::factory()->create();
    $payment = MembershipPayment::factory()->approved()->create([
        'user_id' => $member->id,
        'membership_fee_id' => MembershipFee::factory()->create()->id,
        'payment_date' => now()->subMonth()->toDateString(),
        'expiry_date' => now()->addMonths(11)->toDateString(),
    ]);

    $expectedRef = str_replace('/', '/<wbr>', e($member->user_id_reference));

    $this->actingAs($this->admin)
        ->post(route('certificates.bulk.print.plain'), [
            'certificate_type' => 'membership',
            'training_ids' => [$payment->id],
        ])
        ->assertOk()
        ->assertSee('Ref: '.$expectedRef, false);
});

/*
|--------------------------------------------------------------------------
| Donation certificates: Ref is the organisation's own reference
|--------------------------------------------------------------------------
*/

test('organisation donation certificates show the ORG reference, plain and branded', function (string $routeName) {
    $org = certOrg('Donor Org');
    Donation::factory()->approved()->create(['organisation_id' => $org->id, 'amount' => 7000, 'in_kind_donation' => false]);

    $expectedRef = str_replace('/', '/<wbr>', e($org->fresh()->org_reference));
    expect($org->fresh()->org_reference)->toStartWith('ORG-');

    $this->actingAs($this->admin)
        ->post(route($routeName), [
            'certificate_type' => 'organisation_donation',
            'training_ids' => [$org->id],
        ])
        ->assertOk()
        ->assertSee('Ref: '.$expectedRef, false)
        ->assertDontSee('Ref: —', false);
})->with(['organisations.certificates.print.plain', 'organisations.certificates.print.branded']);

test('regression: the plain membership certificate still uses the payment reference, not the ORG reference', function () {
    $org = certOrg('Member Org');
    $payment = certOrgPayment($org);

    $this->actingAs($this->admin)
        ->post(route('organisations.certificates.print.plain'), [
            'certificate_type' => 'organisation_membership',
            'training_ids' => [$org->id],
        ])
        ->assertOk()
        ->assertSee('Ref: '.str_replace('/', '/<wbr>', e($payment->payment_reference)), false)
        ->assertDontSee('Ref: '.str_replace('/', '/<wbr>', e($org->fresh()->org_reference)), false);
});

test('regression: a personal branded donation certificate still shows the holder\'s reference', function () {
    $donor = User::factory()->create();
    Donation::factory()->approved()->create(['user_id' => $donor->id, 'amount' => 2500, 'in_kind_donation' => false]);

    $this->actingAs($this->admin)
        ->post(route('certificates.bulk_print_branded_portrait'), [
            'certificate_type' => 'donation',
            'training_ids' => [$donor->id],
        ])
        ->assertOk()
        ->assertSee('Ref: '.str_replace('/', '/<wbr>', e($donor->user_id_reference)), false);
});
