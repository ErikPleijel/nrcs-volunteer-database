<?php

/**
 * RCU certificate Group 1: certificate data for paid Red Cross Units, their
 * QR verification (?rcu={id_check_token}&type=rcu_membership&payment={id}),
 * the friendly failure page for a tampered certificates.verify link, and
 * regression checks that personal certificates' QR/verify flow is unchanged.
 */

use App\Http\Controllers\CertificateController;
use App\Models\Branch;
use App\Models\CertificatePrint;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\MembershipPayment;
use App\Models\RedCrossUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
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
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);
    $this->leader = User::factory()->create(['first_name' => 'Lena', 'last_name' => 'Leaderperson']);
    $this->unit = RedCrossUnit::create([
        'name' => 'Unit Verify',
        'division_id' => $this->division->id,
        'team_leader_user_id' => $this->leader->id,
        'is_active' => true,
    ]);
    $this->rcuFee = MembershipFee::factory()->forRedCrossUnits()->create(['name' => 'Unit Yearly', 'amount' => 20000]);
});

/** Calls a protected CertificateController method. */
function certCall(string $method, ...$args)
{
    $controller = app(CertificateController::class);

    return (fn () => $this->{$method}(...$args))->call($controller);
}

function verifyRcuPayment(RedCrossUnit $unit, array $overrides = [], bool $approved = true): MembershipPayment
{
    $factory = $approved ? MembershipPayment::factory()->approved() : MembershipPayment::factory();

    return $factory->create(array_merge([
        'user_id' => test()->leader->id,
        'red_cross_unit_id' => $unit->id,
        'membership_fee_id' => test()->rcuFee->id,
        'branch_id' => test()->branch->id,
        'division_id' => test()->division->id,
        'payment_date' => '2026-01-15',
        'expiry_date' => now()->addMonths(6)->toDateString(),
    ], $overrides));
}

function rcuVerifyUrl(RedCrossUnit $unit, MembershipPayment $payment): string
{
    return URL::signedRoute('certificates.verify', [
        'rcu' => $unit->id_check_token,
        'type' => 'rcu_membership',
        'payment' => $payment->id,
    ]);
}

/*
|--------------------------------------------------------------------------
| Model / schema
|--------------------------------------------------------------------------
*/

test('a new Red Cross Unit gets a unique 32-character id_check_token', function () {
    $other = RedCrossUnit::create(['name' => 'Unit Two', 'division_id' => $this->division->id]);

    expect($this->unit->id_check_token)->toHaveLength(32)
        ->and($other->id_check_token)->toHaveLength(32)
        ->and($other->id_check_token)->not->toBe($this->unit->id_check_token)
        ->and($this->unit->fresh()->rcu_reference)->toBe('RCU-'.$this->unit->id.'/ALP');
});

test('markAsPrinted records RCU prints against the unit', function () {
    // Only printable (paid, in-scope) units are recorded — see
    // RcuCertificateBulkPrintTest for the filtering itself.
    verifyRcuPayment($this->unit);

    $this->actingAs($this->admin)
        ->postJson(route('certificates.mark-as-printed'), [
            'certificate_type' => 'rcu_membership',
            'training_ids' => [$this->unit->id],
        ])
        ->assertOk();

    $print = CertificatePrint::sole();
    expect($print->red_cross_unit_id)->toBe($this->unit->id)
        ->and($print->user_id)->toBeNull()
        ->and($print->organisation_id)->toBeNull()
        ->and($print->certificate_type)->toBe('rcu_membership')
        ->and($this->unit->certificatePrints()->count())->toBe(1)
        ->and($print->redCrossUnit->is($this->unit))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Certificate data
|--------------------------------------------------------------------------
*/

test('RCU certificate data carries the agreed wording, reference and a signed verification URL', function () {
    $payment = verifyRcuPayment($this->unit);

    [$cert] = certCall('buildRcuCertificatesData', collect([$this->unit->fresh()]));

    expect($cert['certificate_type'])->toBe('rcu_membership')
        ->and($cert['recipientName'])->toBe('Unit Verify')
        ->and($cert['certifyText'])->toBe('is a registered Red Cross Unit of the')
        ->and($cert['courseTitle'])->toBe('Nigerian Red Cross Society, Alpha Division, Alpha Branch')
        ->and($cert['dateLine'])->toBe('valid from January 15, 2026 to '.$payment->expiry_date->format('F j, Y'))
        ->and($cert['defaultSign1'])->toBe('Secretary General')
        ->and($cert['defaultSign2'])->toBe('Branch Chairman')
        ->and($cert['reference'])->toBe($this->unit->fresh()->rcu_reference)
        ->and($cert['user'])->toBeNull();

    $url = $cert['verificationUrl'];
    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    expect(URL::hasValidSignature(Request::create($url)))->toBeTrue()
        ->and($query['rcu'])->toBe($this->unit->id_check_token)
        ->and($query['type'])->toBe('rcu_membership')
        ->and((int) $query['payment'])->toBe($payment->id)
        ->and($query)->not->toHaveKey('expires');
});

test('buildRcuCertificatesData silently skips units that are not currently paid', function () {
    verifyRcuPayment($this->unit);
    $neverPaid = RedCrossUnit::create(['name' => 'Unit Never', 'division_id' => $this->division->id]);
    $lapsed = RedCrossUnit::create(['name' => 'Unit Lapsed', 'division_id' => $this->division->id]);
    verifyRcuPayment($lapsed, ['payment_date' => '2024-01-01', 'expiry_date' => '2025-01-01']);
    $pendingOnly = RedCrossUnit::create(['name' => 'Unit Pending', 'division_id' => $this->division->id]);
    verifyRcuPayment($pendingOnly, approved: false);

    $certs = certCall('buildRcuCertificatesData', RedCrossUnit::whereIn('id', [$this->unit->id, $neverPaid->id, $lapsed->id, $pendingOnly->id])->get());

    expect(collect($certs)->pluck('recipientName')->all())->toBe(['Unit Verify']);
});

test('the branded and plain templates render an RCU certificate with its QR code', function () {
    verifyRcuPayment($this->unit);
    $certs = certCall('buildRcuCertificatesData', collect([$this->unit->fresh()]));

    $branded = view('certificates.print-branded', ['certificates' => $certs])->render();
    $payment = $this->unit->fresh()->activeMembership;
    expect($branded)->toContain('Ref: '.e($this->unit->fresh()->rcu_reference).'<br>')
        ->not->toContain('Ref: '.e($payment->payment_reference));
    expect($branded)->toContain('Unit Verify')
        ->toContain('is a registered Red Cross Unit of the')
        ->toContain('data:image/svg+xml;base64')
        ->not->toContain('>QR<');

    $plain = view('certificates.print-plain', [
        'certificates' => $certs,
        'layout' => certCall('buildPlainLayout', []),
    ])->render();
    expect($plain)->toContain('Unit Verify')
        ->toContain('is a registered Red Cross Unit of the')
        ->toContain('data:image/svg+xml;base64')
        ->toContain('Ref: '.str_replace('/', '/<wbr>', e($this->unit->fresh()->rcu_reference)));
});

/*
|--------------------------------------------------------------------------
| verify() — RCU
|--------------------------------------------------------------------------
*/

test('the RCU verification URL shows the unit, fee period and currently-paid status', function () {
    $payment = verifyRcuPayment($this->unit);
    [$cert] = certCall('buildRcuCertificatesData', collect([$this->unit->fresh()]));

    $this->get($cert['verificationUrl'])
        ->assertOk()
        ->assertSee('Certificate Verified')
        ->assertSee('Unit Verify')
        ->assertSee($this->unit->fresh()->rcu_reference)
        ->assertSee('RCU Membership')
        ->assertSee('15 Jan 2026 – '.$payment->expiry_date->format('d M Y'))
        ->assertSee('Currently paid')
        ->assertSee('Alpha Branch')
        ->assertSee('Alpha Division')
        ->assertDontSee('Leaderperson');
});

test('a lapsed fee period shows as ended', function () {
    $payment = verifyRcuPayment($this->unit, ['payment_date' => '2024-01-01', 'expiry_date' => '2025-01-01']);

    $this->get(rcuVerifyUrl($this->unit, $payment))
        ->assertOk()
        ->assertSee('Certificate Verified')
        ->assertSee('Period ended')
        ->assertDontSee('Currently paid');
});

test('an unknown unit token fails cleanly', function () {
    $payment = verifyRcuPayment($this->unit);

    $this->get(URL::signedRoute('certificates.verify', ['rcu' => str_repeat('x', 32), 'type' => 'rcu_membership', 'payment' => $payment->id]))
        ->assertOk()
        ->assertSee('Verification Failed')
        ->assertSee('Red Cross Unit not found')
        ->assertDontSee('Unit Verify');
});

test('a payment that does not belong to the unit, or is only pending, fails without leaking data', function () {
    $otherUnit = RedCrossUnit::create(['name' => 'Unit Secret', 'division_id' => $this->division->id]);
    $foreignPayment = verifyRcuPayment($otherUnit);
    $pendingPayment = verifyRcuPayment($this->unit, approved: false);

    foreach ([$foreignPayment, $pendingPayment] as $payment) {
        $this->get(rcuVerifyUrl($this->unit, $payment))
            ->assertOk()
            ->assertSee('Verification Failed')
            ->assertSee('could not be confirmed')
            ->assertDontSee('Unit Secret')
            ->assertDontSee('Unit Verify');
    }
});

/*
|--------------------------------------------------------------------------
| Tampered links
|--------------------------------------------------------------------------
*/

test('a tampered RCU or personal verification link shows the friendly failure page', function () {
    $payment = verifyRcuPayment($this->unit);
    $member = User::factory()->create();

    $tamperedRcu = str_replace('payment='.$payment->id, 'payment='.($payment->id + 1), rcuVerifyUrl($this->unit, $payment));
    $tamperedPersonal = URL::signedRoute('certificates.verify', ['u' => $member->id_check_token, 'type' => 'membership']).'x';

    foreach ([$tamperedRcu, $tamperedPersonal] as $url) {
        $this->get($url)
            ->assertForbidden()
            ->assertSee('Verification Failed')
            ->assertSee('The link does not match the one printed on the certificate.');
    }
});

test('other signed routes keep the default 403 for an invalid signature', function () {
    Route::middleware('signed')->get('/_test-signed-route', fn () => 'ok')->name('test.signed-route');
    Route::getRoutes()->refreshNameLookups();

    $this->get('/_test-signed-route')
        ->assertForbidden()
        ->assertDontSee('Verification Failed');
});

/*
|--------------------------------------------------------------------------
| Regression: personal certificates
|--------------------------------------------------------------------------
*/

test('regression: a personal membership QR link still verifies the holder', function () {
    $member = User::factory()->create(['first_name' => 'Pat', 'last_name' => 'Holder']);

    $this->get(URL::signedRoute('certificates.verify', ['u' => $member->id_check_token, 'type' => 'membership']))
        ->assertOk()
        ->assertSee('Certificate Verified')
        ->assertSee('Pat Holder')
        ->assertSee('membership');

    $this->get(URL::signedRoute('certificates.verify', ['u' => str_repeat('y', 32), 'type' => 'membership']))
        ->assertSee('User not found in the Red Cross system.');
});

test('regression: a personal membership certificate still gets its holder QR code and reference', function (string $routeName) {
    $member = User::factory()->create();
    $payment = MembershipPayment::factory()->approved()->create([
        'user_id' => $member->id,
        'membership_fee_id' => MembershipFee::factory()->create()->id,
        'payment_date' => now()->subMonth()->toDateString(),
        'expiry_date' => now()->addMonths(11)->toDateString(),
    ]);

    $html = $this->actingAs($this->admin)
        ->post(route($routeName), ['certificate_type' => 'membership', 'training_ids' => [$payment->id]])
        ->assertOk()
        ->assertSee('data:image/svg+xml;base64', false)
        ->getContent();

    // The personal certificate's own certify text now appears on the plain
    // template too (it used to hardcode the training wording).
    expect($html)->toContain('is a registered');
    if ($routeName === 'certificates.bulk.print.plain') {
        expect($html)->toContain('Ref: '.str_replace('/', '/<wbr>', e($member->user_id_reference)))
            ->not->toContain('has successfully completed the training course on');
    } else {
        // Branded personal membership keeps the payment reference.
        expect($html)->toContain('Ref: '.e($payment->payment_reference).'<br>');
    }
})->with(['certificates.bulk.print.plain', 'certificates.bulk.print.branded']);
