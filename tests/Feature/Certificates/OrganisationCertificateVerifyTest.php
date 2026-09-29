<?php

/**
 * QR verification of organisation certificates:
 * ?org={id_check_token}&type=organisation_membership&payment={id} and
 * ?org={id_check_token}&type=organisation_donation&as_of={date}. Membership
 * shows the fee period and live status; donation shows donor status only
 * (no amounts, list or count). Deactivated / soft-deleted organisations fail
 * with their own reason. Plus regressions for RCU and personal donation links.
 */

use App\Http\Controllers\CertificateController;
use App\Models\Branch;
use App\Models\Division;
use App\Models\Donation;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\RedCrossUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->orgFee = MembershipFee::factory()->create(['name' => 'Corporate Gold', 'for_organizations' => true, 'amount' => 50000]);
    $this->org = orgVerifyOrg('Acme Relief Ltd');
});

function orgVerifyOrg(string $name): Organisation
{
    return Organisation::create(['name' => $name, 'branch_id' => test()->branch->id]);
}

function orgVerifyPayment(Organisation $org, array $overrides = [], bool $approved = true): MembershipPayment
{
    $factory = $approved ? MembershipPayment::factory()->approved() : MembershipPayment::factory();

    return $factory->create(array_merge([
        'user_id' => User::factory()->create()->id,
        'organisation_id' => $org->id,
        'membership_fee_id' => test()->orgFee->id,
        'branch_id' => test()->branch->id,
        'payment_date' => '2026-01-15',
        'expiry_date' => now()->addMonths(6)->toDateString(),
    ], $overrides));
}

function orgVerifyDonation(Organisation $org, array $overrides = [], bool $approved = true): Donation
{
    $factory = $approved ? Donation::factory()->approved() : Donation::factory();

    return $factory->create(array_merge([
        'organisation_id' => $org->id,
        'amount' => 12345,
        'in_kind_donation' => false,
    ], $overrides));
}

/** The certificate payloads the print endpoints would render. */
function orgVerifyCerts(string $type, Organisation ...$orgs): array
{
    $controller = app(CertificateController::class);
    $ids = array_map(fn ($o) => $o->id, $orgs);

    return (fn () => $this->buildOrganisationCertificatesData($type, $ids))->call($controller);
}

function orgMembershipUrl(Organisation $org, MembershipPayment $payment): string
{
    return URL::signedRoute('certificates.verify', [
        'org' => $org->id_check_token,
        'type' => 'organisation_membership',
        'payment' => $payment->id,
    ]);
}

function orgDonationUrl(Organisation $org): string
{
    return URL::signedRoute('certificates.verify', [
        'org' => $org->id_check_token,
        'type' => 'organisation_donation',
        'as_of' => '2026-09-29',
    ]);
}

/*
|--------------------------------------------------------------------------
| Token and QR codes
|--------------------------------------------------------------------------
*/

test('a new organisation gets a unique 32-character id_check_token', function () {
    $other = orgVerifyOrg('Other Org');

    expect($this->org->id_check_token)->toBeString()->toHaveLength(32)
        ->and($other->id_check_token)->toHaveLength(32)
        ->not->toBe($this->org->id_check_token);
});

test('both organisation certificate types render a QR code on all three templates', function (string $type) {
    orgVerifyPayment($this->org);
    orgVerifyDonation($this->org);

    $certs = orgVerifyCerts($type, $this->org->fresh());

    expect($certs)->toHaveCount(1)
        ->and($certs[0]['verificationUrl'])->toContain('org='.$this->org->id_check_token)
        ->toContain('type='.$type)
        ->toContain('signature=');

    $layout = (fn () => $this->buildPlainLayout([]))->call(app(CertificateController::class));

    foreach (['certificates.print-branded', 'certificates.print-branded-portrait', 'certificates.print-plain'] as $view) {
        $html = view($view, ['certificates' => $certs, 'layout' => $layout])->render();

        expect($html)->toContain('Acme Relief Ltd')
            ->toContain('data:image/svg+xml;base64');
    }
})->with(['organisation_membership', 'organisation_donation']);

test('the membership QR link carries the active approved payment', function () {
    orgVerifyPayment($this->org, ['payment_date' => '2024-01-01', 'expiry_date' => '2025-01-01']); // lapsed
    orgVerifyPayment($this->org, ['expiry_date' => now()->addYears(2)->toDateString()], approved: false); // pending
    $active = orgVerifyPayment($this->org);

    [$cert] = orgVerifyCerts('organisation_membership', $this->org->fresh());

    expect($cert['verificationUrl'])->toContain('payment='.$active->id);
});

/*
|--------------------------------------------------------------------------
| verify() — organisation
|--------------------------------------------------------------------------
*/

test('a membership certificate verifies with its fee period and paid status, and no other financial data', function () {
    $payment = orgVerifyPayment($this->org);
    [$cert] = orgVerifyCerts('organisation_membership', $this->org->fresh());

    $this->get($cert['verificationUrl'])
        ->assertOk()
        ->assertSee('Certificate Verified')
        ->assertSee('Acme Relief Ltd')
        ->assertSee($this->org->fresh()->org_reference)
        ->assertSee('Organisation Membership')
        ->assertSee('15 Jan 2026 – '.$payment->expiry_date->format('d M Y'))
        ->assertSee('Currently paid')
        ->assertSee('Alpha Branch')
        ->assertDontSee('50,000')
        ->assertDontSee('Corporate Gold')
        ->assertDontSee($payment->payment_reference);
});

test('a lapsed organisation membership period shows as ended', function () {
    $payment = orgVerifyPayment($this->org, ['payment_date' => '2024-01-01', 'expiry_date' => '2025-01-01']);

    $this->get(orgMembershipUrl($this->org, $payment))
        ->assertOk()
        ->assertSee('Certificate Verified')
        ->assertSee('Period ended')
        ->assertDontSee('Currently paid');
});

test('a donation certificate verifies donor status only — no amounts, items or count', function () {
    orgVerifyDonation($this->org, ['amount' => 12345]);
    orgVerifyDonation($this->org, ['amount' => 7, 'in_kind_donation' => true, 'donation_item' => 'Blankets']);
    [$cert] = orgVerifyCerts('organisation_donation', $this->org->fresh());

    $this->get($cert['verificationUrl'])
        ->assertOk()
        ->assertSee('Certificate Verified')
        ->assertSee('Acme Relief Ltd')
        ->assertSee('Organisation Donation')
        ->assertSee('Donations on record')
        ->assertSee(now()->format('d M Y'))
        ->assertDontSee('12,345')
        ->assertDontSee('12345')
        ->assertDontSee('Blankets')
        ->assertDontSee('₦')
        ->assertDontSee('Fee period');
});

test('a payment of another organisation, or a pending one, fails without leaking data', function () {
    $other = orgVerifyOrg('Secret Holdings');
    $foreign = orgVerifyPayment($other);
    $pending = orgVerifyPayment($this->org, approved: false);

    foreach ([$foreign, $pending] as $payment) {
        $this->get(orgMembershipUrl($this->org, $payment))
            ->assertOk()
            ->assertSee('Verification Failed')
            ->assertSee('could not be confirmed')
            ->assertDontSee('Secret Holdings')
            ->assertDontSee('Acme Relief Ltd');
    }
});

test('an organisation with no approved, live donations fails donation verification', function () {
    orgVerifyDonation($this->org, approved: false);
    orgVerifyDonation($this->org, ['is_deleted' => true]);

    $this->get(orgDonationUrl($this->org))
        ->assertOk()
        ->assertSee('Verification Failed')
        ->assertSee('No donations by this organisation could be confirmed.')
        ->assertDontSee('Acme Relief Ltd');
});

test('an unknown organisation token or type fails cleanly', function () {
    $payment = orgVerifyPayment($this->org);

    $this->get(URL::signedRoute('certificates.verify', ['org' => str_repeat('x', 32), 'type' => 'organisation_membership', 'payment' => $payment->id]))
        ->assertSee('Verification Failed')
        ->assertSee('Organisation not found in the Red Cross system.');

    $this->get(URL::signedRoute('certificates.verify', ['org' => $this->org->id_check_token, 'type' => 'membership', 'payment' => $payment->id]))
        ->assertSee('Verification Failed')
        ->assertSee('Invalid certificate parameters.')
        ->assertDontSee('Acme Relief Ltd');
});

test('a deactivated or soft-deleted organisation fails with its own reason, even with a valid payment', function (string $how) {
    $payment = orgVerifyPayment($this->org);
    orgVerifyDonation($this->org);
    $urls = [orgMembershipUrl($this->org, $payment), orgDonationUrl($this->org)];

    $how === 'deactivated'
        ? $this->org->update(['deactivated_date' => now()->toDateString()])
        : $this->org->delete();

    foreach ($urls as $url) {
        $this->get($url)
            ->assertOk()
            ->assertSee('Verification Failed')
            ->assertSee("This organisation's certificate is no longer active.", false)
            ->assertDontSee('Organisation not found')
            ->assertDontSee('Acme Relief Ltd');
    }
})->with(['deactivated', 'soft-deleted']);

test('a tampered organisation link gets the friendly 403', function () {
    $payment = orgVerifyPayment($this->org);

    $tampered = str_replace('payment='.$payment->id, 'payment='.($payment->id + 1), orgMembershipUrl($this->org, $payment));

    $this->get($tampered)
        ->assertForbidden()
        ->assertSee('Verification Failed')
        ->assertSee('The link does not match the one printed on the certificate.');
});

test('the organisation verify page shows its branch figures', function () {
    User::factory()->count(10)->create(['branch_id' => $this->branch->id, 'lifecycle_status' => 'active']);
    $payment = orgVerifyPayment($this->org);

    $response = $this->get(orgMembershipUrl($this->org, $payment))
        ->assertOk()
        ->assertSee('About Alpha Branch branch');

    expect($response->viewData('stats'))->toMatchArray(['scope' => 'branch', 'suppressed' => false]);
});

/*
|--------------------------------------------------------------------------
| Regression
|--------------------------------------------------------------------------
*/

test('regression: a personal donation QR link still verifies the holder, with no amounts', function () {
    $donor = User::factory()->create(['first_name' => 'Dana', 'last_name' => 'Donor']);
    Donation::factory()->approved()->create(['user_id' => $donor->id, 'amount' => 98765, 'in_kind_donation' => false]);

    $this->get(URL::signedRoute('certificates.verify', ['u' => $donor->id_check_token, 'type' => 'donation']))
        ->assertOk()
        ->assertSee('Certificate Verified')
        ->assertSee('Dana Donor')
        ->assertSee('donation')
        ->assertDontSee('98,765')
        ->assertDontSee('98765');
});

test('regression: an RCU certificate link still verifies the unit', function () {
    $division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);
    $unit = RedCrossUnit::create(['name' => 'Unit Still Works', 'division_id' => $division->id, 'is_active' => true]);
    $payment = MembershipPayment::factory()->approved()->create([
        'user_id' => User::factory()->create()->id,
        'red_cross_unit_id' => $unit->id,
        'membership_fee_id' => MembershipFee::factory()->forRedCrossUnits()->create()->id,
        'payment_date' => '2026-01-15',
        'expiry_date' => now()->addMonths(6)->toDateString(),
    ]);

    $this->get(URL::signedRoute('certificates.verify', ['rcu' => $unit->fresh()->id_check_token, 'type' => 'rcu_membership', 'payment' => $payment->id]))
        ->assertOk()
        ->assertSee('Certificate Verified')
        ->assertSee('Unit Still Works')
        ->assertSee('RCU Membership')
        ->assertSee('Currently paid');
});
