<?php

/**
 * Feature tests for the bulk-print eligibility fix: a volunteer (Red Cross
 * unit assigned, no membership payment — volunteers pay no fee) must be
 * selectable for bulk printing exactly like a member with a valid payment,
 * instead of being blocked by the old "must have a membership payment" gate.
 *
 * Covers both:
 * - IdCardController::showBulkPrintForm()'s printable_only query filter
 * - prepare-bulk-print.blade.php's per-row $hasMissingData / checkbox logic
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\RedCrossUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    // The bulk-print dashboard sits inside routes/web.php's outer
    // can:manage-admin-panel group as well as its own can:view_idcards
    // group; the per-row checkbox additionally requires can:print_idcards.
    foreach (['manage-admin-panel', 'view_idcards', 'print_idcards'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions(['manage-admin-panel', 'view_idcards', 'print_idcards']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);
});

test('a volunteer with a Red Cross unit but no payment gets a selectable checkbox on the dashboard', function () {
    $unit = RedCrossUnit::create(['name' => 'Holy Mary RC Unit', 'division_id' => $this->division->id]);

    $volunteer = User::factory()->withNationalId()->create([
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
        'red_cross_unit_id' => $unit->id,
        'picture' => 'pic.jpg',
        'signature' => 'sig.jpg',
    ]);

    $html = $this->actingAs($this->admin)
        ->get(route('id-cards.prepare-bulk-print'))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('id="user-'.$volunteer->id.'"') // checkbox rendered
        ->toContain('Red Cross unit:')
        ->toContain('HOLY MARY RC UNIT');
});

test('a member with no payment does NOT get a selectable checkbox on the dashboard', function () {
    $memberWithoutPayment = User::factory()->withNationalId()->create([
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
        'picture' => 'pic.jpg',
        'signature' => 'sig.jpg',
    ]);

    $html = $this->actingAs($this->admin)
        ->get(route('id-cards.prepare-bulk-print'))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('id="user-'.$memberWithoutPayment->id.'"');
});

test('printable_only includes a volunteer with a unit but no payment', function () {
    $unit = RedCrossUnit::create(['name' => 'Holy Mary RC Unit', 'division_id' => $this->division->id]);

    $volunteer = User::factory()->withNationalId()->create([
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
        'red_cross_unit_id' => $unit->id,
        'picture' => 'pic.jpg',
        'signature' => 'sig.jpg',
    ]);

    $html = $this->actingAs($this->admin)
        ->get(route('id-cards.prepare-bulk-print', ['printable_only' => 1]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('id="user-'.$volunteer->id.'"');
});

test('printable_only excludes a member with no payment', function () {
    $memberWithoutPayment = User::factory()->withNationalId()->create([
        'branch_id' => $this->branch->id,
        'division_id' => $this->division->id,
        'picture' => 'pic.jpg',
        'signature' => 'sig.jpg',
    ]);

    $html = $this->actingAs($this->admin)
        ->get(route('id-cards.prepare-bulk-print', ['printable_only' => 1]))
        ->assertOk()
        ->getContent();

    expect($html)->not->toContain('id="user-'.$memberWithoutPayment->id.'"');
});
