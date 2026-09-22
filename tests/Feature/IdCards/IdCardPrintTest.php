<?php

/**
 * Feature tests for the member/volunteer ID card rendering changes:
 * - the header banner text ("MEMBERSHIP IDENTITY CARD" vs "VOLUNTEER IDENTITY CARD")
 * - the "Memb. category" box, which shows the fee name (members) or the Red
 *   Cross unit name (volunteers), with a matching caption swap
 * - the full-name row (first + last name only, no middle name)
 * - the DB code, which must no longer include the Red Cross unit segment
 *
 * Both printCard() (single) and printBulkCards() (bulk) render the same
 * id-cards.print view, so both are covered here to keep them from drifting
 * out of sync with each other.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\RedCrossUnit;
use App\Models\Setting;
use App\Models\User;
use Database\Factories\MembershipFeeFactory;
use Database\Factories\MembershipPaymentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // The id-card print routes sit inside routes/web.php's outer
    // can:manage-admin-panel group as well as their own can:print_idcards
    // group — both permissions are required.
    foreach (['manage-admin-panel', 'print_idcards'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions(['manage-admin-panel', 'print_idcards']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);

    // Setting::get() caches forever, keyed setting.{key}; RefreshDatabase
    // resets the DB between tests but not this cache, so start each test
    // with a clean slate regardless of what an earlier test left behind.
    Cache::forget('setting.site.hq_address');
});

/** A member: valid personal payment, no Red Cross unit. */
function makeMemberForCard(Branch $branch, Division $division, ?MembershipFee $fee = null): User
{
    $user = User::factory()->withNationalId()->create([
        'first_name' => 'Chidinma',
        'middle_name' => 'Obiageli',
        'last_name' => 'Okonkwo',
        'branch_id' => $branch->id,
        'division_id' => $division->id,
        'picture' => 'pic.jpg',
        'signature' => 'sig.jpg',
    ]);

    $fee ??= MembershipFeeFactory::new()->create(['name' => 'Ordinary Member', 'is_volunteer_fee' => false]);

    MembershipPaymentFactory::new()->approved()->create([
        'user_id' => $user->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->subMonth(),
        'expiry_date' => now()->addYear(),
    ]);

    return $user->fresh();
}

/** A volunteer: attached to an active Red Cross unit, no payment. */
function makeVolunteerForCard(Branch $branch, Division $division, ?RedCrossUnit $unit = null): User
{
    $unit ??= RedCrossUnit::create(['name' => 'Holy Mary RC Unit', 'division_id' => $division->id]);

    return User::factory()->withNationalId()->create([
        'first_name' => 'Chidinma',
        'middle_name' => 'Obiageli',
        'last_name' => 'Okonkwo',
        'branch_id' => $branch->id,
        'division_id' => $division->id,
        'red_cross_unit_id' => $unit->id,
        'picture' => 'pic.jpg',
        'signature' => 'sig.jpg',
    ])->fresh();
}

/*
|--------------------------------------------------------------------------
| Single print — printCard()
|--------------------------------------------------------------------------
*/

test('single card header and category box for a member', function () {
    $member = makeMemberForCard($this->branch, $this->division);

    $html = $this->actingAs($this->admin)
        ->get(route('id-card.print', $member))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('MEMBERSHIP IDENTITY CARD')
        ->not->toContain('VOLUNTEER IDENTITY CARD')
        ->toContain('Memb. category')
        ->toContain('ORDINARY MEMBER');
});

test('single card header and category box for a volunteer', function () {
    $volunteer = makeVolunteerForCard($this->branch, $this->division);

    $html = $this->actingAs($this->admin)
        ->get(route('id-card.print', $volunteer))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('VOLUNTEER IDENTITY CARD')
        ->not->toContain('MEMBERSHIP IDENTITY CARD')
        ->toContain('Red Cross unit')
        ->not->toContain('Memb. category')
        ->toContain('HOLY MARY RC UNIT');
});

test('single card shows one full-name field, first + last only, no middle name', function () {
    $member = makeMemberForCard($this->branch, $this->division);

    $html = $this->actingAs($this->admin)
        ->get(route('id-card.print', $member))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('FULL NAME')
        ->toContain('CHIDINMA OKONKWO')
        ->not->toContain('OBIAGELI')
        ->not->toContain('>Surname<')
        ->not->toContain('>Firstname<');
});

test('single card DB code omits the Red Cross unit segment', function () {
    $volunteer = makeVolunteerForCard($this->branch, $this->division);

    $html = $this->actingAs($this->admin)
        ->get(route('id-card.print', $volunteer))
        ->assertOk()
        ->getContent();

    // Short format: DB-{id}-{BRANCH}-{DIVISION} — no unit segment, no slashes.
    expect($html)->toContain($volunteer->user_id_reference_short)
        ->and($volunteer->user_id_reference_short)->not->toContain('/')
        ->and($html)->not->toContain($volunteer->user_id_reference);
});

/*
|--------------------------------------------------------------------------
| HQ address — sourced from the site.hq_address setting, not hardcoded
|--------------------------------------------------------------------------
*/

test('single card back side renders the address from the site.hq_address setting', function () {
    Setting::updateOrCreate(
        ['key' => 'site.hq_address'],
        ['value' => 'TEST HQ ADDRESS, Some Street, Some City.', 'type' => 'string', 'group' => 'site']
    );
    Cache::forget('setting.site.hq_address'); // mirrors SettingController::update()

    $member = makeMemberForCard($this->branch, $this->division);

    $html = $this->actingAs($this->admin)
        ->get(route('id-card.print', $member))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('TEST HQ ADDRESS, Some Street, Some City.')
        ->not->toContain('Plot 589')
        ->not->toContain('Benson Street')
        ->not->toContain('Okanjo Iwaela');
});

test('single card falls back to the correct default address when the setting is missing', function () {
    Setting::where('key', 'site.hq_address')->delete();

    $member = makeMemberForCard($this->branch, $this->division);

    $html = $this->actingAs($this->admin)
        ->get(route('id-card.print', $member))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('National Headquarters Plot 589 T.O.S Benson Crescent Off Ngozi Okonjo Iweala, Utako District, Abuja.');
});

test('changing the HQ address via the real admin settings update flow updates the printed card end to end', function () {
    // Goes through the actual admin/settings/edit form submission — including
    // its own Cache::forget() — rather than editing the Setting row directly,
    // to prove there's no stale-cache gap between "admin saves" and "card
    // reflects the change."
    Permission::findOrCreate('change_settings', 'web');
    $this->admin->givePermissionTo('change_settings');

    Setting::updateOrCreate(
        ['key' => 'site.hq_address'],
        ['value' => 'ORIGINAL ADDRESS BEFORE UPDATE', 'type' => 'string', 'group' => 'site']
    );
    Cache::forget('setting.site.hq_address');

    $member = makeMemberForCard($this->branch, $this->division);

    $before = $this->actingAs($this->admin)
        ->get(route('id-card.print', $member))
        ->getContent();
    expect($before)->toContain('ORIGINAL ADDRESS BEFORE UPDATE');

    $this->actingAs($this->admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.settings.update'), [
            'settings' => ['site.hq_address' => 'UPDATED ADDRESS AFTER ADMIN EDIT'],
        ])
        ->assertRedirect(route('admin.settings.index'));

    $after = $this->actingAs($this->admin)
        ->get(route('id-card.print', $member))
        ->getContent();

    expect($after)
        ->toContain('UPDATED ADDRESS AFTER ADMIN EDIT')
        ->not->toContain('ORIGINAL ADDRESS BEFORE UPDATE');
});

/*
|--------------------------------------------------------------------------
| Bulk print — printBulkCards()
|--------------------------------------------------------------------------
*/

test('bulk print renders member and volunteer cards correctly in the same batch', function () {
    $member = makeMemberForCard($this->branch, $this->division);
    $volunteer = makeVolunteerForCard($this->branch, $this->division);

    $payload = [
        'user_ids' => json_encode([
            ['id' => $member->id, 'validity' => 36],
            ['id' => $volunteer->id, 'validity' => 36],
        ]),
    ];

    $html = $this->actingAs($this->admin)
        ->post(route('id-cards.print-bulk'), $payload)
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('MEMBERSHIP IDENTITY CARD')
        ->toContain('VOLUNTEER IDENTITY CARD')
        ->toContain('Memb. category')
        ->toContain('Red Cross unit')
        ->toContain('ORDINARY MEMBER')
        ->toContain('HOLY MARY RC UNIT')
        ->toContain('CHIDINMA OKONKWO');
});

test('bulk print renders the address from the site.hq_address setting on every card', function () {
    Setting::updateOrCreate(
        ['key' => 'site.hq_address'],
        ['value' => 'TEST HQ ADDRESS, Some Street, Some City.', 'type' => 'string', 'group' => 'site']
    );
    Cache::forget('setting.site.hq_address'); // mirrors SettingController::update()

    $member = makeMemberForCard($this->branch, $this->division);
    $volunteer = makeVolunteerForCard($this->branch, $this->division);

    $payload = [
        'user_ids' => json_encode([
            ['id' => $member->id, 'validity' => 36],
            ['id' => $volunteer->id, 'validity' => 36],
        ]),
    ];

    $html = $this->actingAs($this->admin)
        ->post(route('id-cards.print-bulk'), $payload)
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'TEST HQ ADDRESS, Some Street, Some City.'))->toBe(2)
        ->and($html)->not->toContain('Plot 589');
});
