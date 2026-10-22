<?php

/**
 * MembershipPaymentController::store() accepts id_card_included for any
 * person's own membership payment (User::canOrderIdCardWithPayment()), but
 * never for an organisation or Red Cross Unit payment — the same rule the
 * online Paystack flow enforces.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\RedCrossUnit;
use App\Models\User;
use Database\Factories\MembershipFeeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    foreach (['manage-admin-panel', 'add_payments', 'view_payments'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions([
        'manage-admin-panel', 'add_payments', 'view_payments',
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actor = User::factory()->create();
    $this->actor->assignRole('national_db_administrator');

    $this->branch = Branch::create(['name' => 'Kano', 'code' => 'KAN']);
    $this->division = Division::create(['name' => 'Kano Division', 'branch_id' => $this->branch->id]);
    $this->unit = RedCrossUnit::create(['name' => 'Kano Unit', 'division_id' => $this->division->id, 'is_active' => true]);
});

test('store saves an ID card for a supporting member', function () {
    $member = User::factory()->create();
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false]);

    $this->actingAs($this->actor)->post(route('membership-payments.store'), [
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
        'id_card_included' => '1',
    ])->assertSessionHasNoErrors();

    expect(MembershipPayment::withAnyApprovalStatus()->sole()->id_card_included)->toBeTrue();
});

test('store saves an ID card for a volunteer as before', function () {
    $volunteer = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id, 'red_cross_unit_id' => $this->unit->id]);
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => true]);

    $this->actingAs($this->actor)->post(route('membership-payments.store'), [
        'user_id' => $volunteer->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
        'id_card_included' => '1',
    ])->assertSessionHasNoErrors();

    expect(MembershipPayment::withAnyApprovalStatus()->sole()->id_card_included)->toBeTrue();
});

test('store still accepts an unticked ID card', function () {
    $member = User::factory()->create();
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false]);

    $this->actingAs($this->actor)->post(route('membership-payments.store'), [
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
        'id_card_included' => '0',
    ])->assertSessionHasNoErrors();

    expect(MembershipPayment::withAnyApprovalStatus()->sole()->id_card_included)->toBeFalse();
});

test('store refuses an ID card on an organisation payment', function () {
    $contact = User::factory()->create();
    $organisation = Organisation::create(['name' => 'Sponsor Org', 'branch_id' => $this->branch->id]);
    $fee = MembershipFeeFactory::new()->create(['for_organizations' => true, 'is_volunteer_fee' => false]);

    $this->actingAs($this->actor)->post(route('membership-payments.store'), [
        'user_id' => $contact->id,
        'organisation_id' => $organisation->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
        'id_card_included' => '1',
    ])->assertSessionHasErrors('id_card_included');

    expect(MembershipPayment::withAnyApprovalStatus()->count())->toBe(0);
});

test('store refuses an ID card on a Red Cross Unit payment', function () {
    $leader = User::factory()->create();
    $this->unit->update(['team_leader_user_id' => $leader->id]);
    $fee = MembershipFeeFactory::new()->forRedCrossUnits()->create();

    $this->actingAs($this->actor)->post(route('membership-payments.store'), [
        'user_id' => $leader->id,
        'red_cross_unit_id' => $this->unit->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
        'id_card_included' => '1',
    ])->assertSessionHasErrors('id_card_included');

    expect(MembershipPayment::withAnyApprovalStatus()->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Staff form: ID card default and notes (IdCardOrderAdvice via the
| current-membership endpoint, Decisions.md 2026-10-22)
|--------------------------------------------------------------------------
*/

test('the current-membership endpoint returns the ID card facts for a volunteer', function () {
    $volunteer = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id, 'red_cross_unit_id' => $this->unit->id]);

    $this->actingAs($this->actor)
        ->getJson(route('membership-payments.current-membership', $volunteer))
        ->assertOk()
        ->assertJsonPath('id_card.is_volunteer', true)
        ->assertJsonPath('id_card.default_on', true)
        ->assertJsonPath('id_card.has_photo', false)
        ->assertJsonPath('id_card.has_signature', false)
        ->assertJsonPath('id_card.has_nin', false)
        ->assertJsonPath('id_card.valid_until_display', null)
        ->assertJsonMissingPath('membership_fee_id');
});

test('the current-membership endpoint gives a supporting member, or a volunteer with a card valid beyond 28 days, no default', function () {
    $member = User::factory()->create();
    $this->actingAs($this->actor)
        ->getJson(route('membership-payments.current-membership', $member))
        ->assertJsonPath('id_card.is_volunteer', false)
        ->assertJsonPath('id_card.default_on', false);

    $volunteer = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id, 'red_cross_unit_id' => $this->unit->id]);
    \App\Models\IdCardPrint::create([
        'user_id' => $volunteer->id,
        'printed_by_user_id' => $this->actor->id,
        'printed_at' => now()->subMonth(),
        'status' => 'printed',
        'validity_months' => 12,
        'expiry_date' => now()->addDays(90)->startOfDay(),
    ]);

    $this->actingAs($this->actor)
        ->getJson(route('membership-payments.current-membership', $volunteer))
        ->assertJsonPath('id_card.default_on', false)
        ->assertJsonPath('id_card.expires_soon', false)
        ->assertJsonPath('id_card.valid_until_display', now()->addDays(90)->format('d M Y'));
});

test('the current-membership endpoint is limited to payment staff within their scope', function () {
    $target = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);

    // No add_payments permission.
    $this->actingAs(User::factory()->create())
        ->getJson(route('membership-payments.current-membership', $target))
        ->assertForbidden();

    // Branch staff of another branch.
    Role::findOrCreate('branch_db_administrator', 'web')->syncPermissions(['manage-admin-panel', 'add_payments']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $otherBranch = Branch::create(['name' => 'Lagos', 'code' => 'LAG']);
    $otherStaff = User::factory()->create(['branch_id' => $otherBranch->id]);
    $otherStaff->assignRole('branch_db_administrator');

    $this->actingAs($otherStaff)
        ->getJson(route('membership-payments.current-membership', $target))
        ->assertForbidden();

    $ownStaff = User::factory()->create(['branch_id' => $this->branch->id]);
    $ownStaff->assignRole('branch_db_administrator');

    $this->actingAs($ownStaff)
        ->getJson(route('membership-payments.current-membership', $target))
        ->assertOk();
});

test('the staff form has only the three short ID card notes, none of the payer wording', function () {
    $volunteer = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id, 'red_cross_unit_id' => $this->unit->id]);

    $html = $this->actingAs($this->actor)
        ->get(route('membership-payments.create', $volunteer))
        ->assertOk()
        ->assertSee('id="id-card-advice"', false)
        ->assertSee('Volunteers should have an ID card.')
        ->assertSee('Card valid until')
        ->assertSee('Missing for the ID card:')
        ->assertDontSee('About your ID card')
        ->assertDontSee('About the ID card')
        ->assertDontSee('branch office')
        ->assertDontSee('photo and signature in')
        ->assertDontSee('Only turn this off if you already have a valid ID card')
        ->assertDontSee('NIN needed to print the card')
        ->getContent();

    // All three start hidden; the form's JS shows them for the chosen person.
    $notes = \Illuminate\Support\Str::of($html)->matchAll('/data-id-card-advice="([a-z-]+)" class="hidden/');
    expect($notes->all())->toBe(['volunteer-off', 'valid-until', 'missing']);
});

test('the current-membership endpoint reports rejected images for the staff form\'s missing line', function () {
    $volunteer = User::factory()->create([
        'branch_id' => $this->branch->id, 'division_id' => $this->division->id, 'red_cross_unit_id' => $this->unit->id,
        'picture' => 'photos/me.jpg', 'signature' => 'signatures/me.png',
    ]);
    \Illuminate\Support\Facades\DB::table('users')->where('id', $volunteer->id)->update(['picture_rejected_at' => now()]);

    $this->actingAs($this->actor)
        ->getJson(route('membership-payments.current-membership', $volunteer))
        ->assertJsonPath('id_card.has_photo', true)
        ->assertJsonPath('id_card.photo_rejected', true)
        ->assertJsonPath('id_card.has_signature', true)
        ->assertJsonPath('id_card.signature_rejected', false);
});
