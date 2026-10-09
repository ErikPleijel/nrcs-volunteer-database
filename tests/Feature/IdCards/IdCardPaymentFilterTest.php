<?php

/**
 * Which ID card payments put a person in the print queue
 * (id-cards.prepare-bulk-print?needs_id_card_printed=1) and make
 * User::needsIdCardPrinted() / last_id_card_payment_date see them:
 * only APPROVED (ApprovedScope), non-deleted, personal payments — the same
 * filters in IdCardController::showBulkPrintForm() and
 * User::getLastIdCardPaymentDateAttribute().
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\Organisation;
use App\Models\RedCrossUnit;
use App\Models\User;
use Database\Factories\MembershipFeeFactory;
use Database\Factories\MembershipPaymentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    foreach (['manage-admin-panel', 'view_idcards', 'print_idcards'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions(['manage-admin-panel', 'view_idcards', 'print_idcards']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);
    $this->unit = RedCrossUnit::create(['name' => 'Alpha RC Unit', 'division_id' => $this->division->id, 'is_active' => true]);

    $this->volunteer = User::factory()->withNationalId()->create([
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
        'red_cross_unit_id' => $this->unit->id,
        'picture' => 'pic.jpg',
        'signature' => 'sig.jpg',
    ]);
});

/** An id_card_included payment for the volunteer, never printed. */
function makeIdCardPayment(User $user, array $overrides = [], bool $approved = true): void
{
    $factory = MembershipPaymentFactory::new();
    if ($approved) {
        $factory = $factory->approved();
    }

    $factory->create(array_merge([
        'user_id' => $user->id,
        'membership_fee_id' => MembershipFeeFactory::new()->create(['is_volunteer_fee' => true])->id,
        'payment_date' => now()->subMonth()->toDateString(),
        'expiry_date' => now()->subMonth()->addYear()->toDateString(),
        'id_card_included' => true,
    ], $overrides));
}

/**
 * Whether $user has a card in the "needs printing" queue. Keyed on the
 * card's signature box, which every listed card renders.
 */
function inIdCardQueue(TestCase $test, User $admin, User $user): bool
{
    $html = $test->actingAs($admin)
        ->get(route('id-cards.prepare-bulk-print', ['needs_id_card_printed' => 1]))
        ->assertOk()
        ->getContent();

    return str_contains($html, 'id="signature-box-'.$user->id.'"');
}

test('a pending ID card payment does not queue the card', function () {
    makeIdCardPayment($this->volunteer, approved: false);

    expect(inIdCardQueue($this, $this->admin, $this->volunteer))->toBeFalse()
        ->and($this->volunteer->fresh()->needsIdCardPrinted())->toBeFalse();
});

test('a rejected ID card payment does not queue the card', function () {
    makeIdCardPayment($this->volunteer, ['approval_status' => 'rejected', 'decided_at' => now()], approved: false);

    expect(inIdCardQueue($this, $this->admin, $this->volunteer))->toBeFalse()
        ->and($this->volunteer->fresh()->needsIdCardPrinted())->toBeFalse();
});

test('a deleted approved ID card payment is ignored', function () {
    makeIdCardPayment($this->volunteer, ['is_deleted' => true]);

    $volunteer = $this->volunteer->fresh();

    expect($volunteer->last_id_card_payment_date)->toBeNull()
        ->and($volunteer->needsIdCardPrinted())->toBeFalse()
        ->and(inIdCardQueue($this, $this->admin, $this->volunteer))->toBeFalse();
});

test('an approved organisation ID card payment is ignored', function () {
    $organisation = Organisation::create(['name' => 'Sponsor Org']);
    makeIdCardPayment($this->volunteer, ['organisation_id' => $organisation->id]);

    $volunteer = $this->volunteer->fresh();

    expect($volunteer->last_id_card_payment_date)->toBeNull()
        ->and($volunteer->needsIdCardPrinted())->toBeFalse()
        ->and(inIdCardQueue($this, $this->admin, $this->volunteer))->toBeFalse();
});

test('an approved Red Cross Unit ID card payment is ignored', function () {
    makeIdCardPayment($this->volunteer, [
        'red_cross_unit_id' => $this->unit->id,
        'membership_fee_id' => MembershipFeeFactory::new()->forRedCrossUnits()->create()->id,
    ]);

    $volunteer = $this->volunteer->fresh();

    expect($volunteer->last_id_card_payment_date)->toBeNull()
        ->and($volunteer->needsIdCardPrinted())->toBeFalse()
        ->and(inIdCardQueue($this, $this->admin, $this->volunteer))->toBeFalse();
});

test('an approved, current, personal ID card payment with no print queues the card', function () {
    makeIdCardPayment($this->volunteer);

    $volunteer = $this->volunteer->fresh();

    expect($volunteer->last_id_card_payment_date)->not->toBeNull()
        ->and($volunteer->needsIdCardPrinted())->toBeTrue()
        ->and(inIdCardQueue($this, $this->admin, $this->volunteer))->toBeTrue();
});
