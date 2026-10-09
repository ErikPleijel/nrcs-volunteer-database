<?php

/**
 * Division contact details on the home page map: the public
 * /api/branches/{branch}/divisions endpoint, the "&amp;" name fix
 * (2026_10_09_100000_decode_amp_entities_in_org_names) and the
 * public-visibility note on divisions/edit.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\RedCrossUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const DECODE_AMP_MIGRATION = 'database/migrations/2026_10_09_100000_decode_amp_entities_in_org_names.php';

beforeEach(function () {
    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
});

test('the public divisions endpoint returns the office contact fields and nothing else', function () {
    $division = Division::create([
        'name' => 'Alpha One',
        'branch_id' => $this->branch->id,
        'physical_address' => '1 Office Road',
        'postal_address' => 'PMB 12',
        'telephone' => '0803 000 0000',
        'email' => 'alpha.one@example.org',
        'latitude' => 9.1,
        'longitude' => 7.4,
    ]);
    DB::table('divisions')->where('id', $division->id)->update(['heat_score' => 0.5, 'first_aid_count' => 3]);

    $response = $this->getJson("/api/branches/{$this->branch->id}/divisions")->assertOk();

    $row = $response->json('divisions.0');
    expect(array_keys($row))->toEqualCanonicalizing([
        'id', 'name', 'branch_id', 'physical_address', 'postal_address', 'telephone', 'email', 'latitude', 'longitude',
    ]);
    expect($row)->toMatchArray([
        'physical_address' => '1 Office Road',
        'postal_address' => 'PMB 12',
        'telephone' => '0803 000 0000',
        'email' => 'alpha.one@example.org',
    ]);
});

test('the public divisions endpoint returns null contact fields when they are empty', function () {
    Division::create(['name' => 'Alpha Two', 'branch_id' => $this->branch->id]);

    $row = $this->getJson("/api/branches/{$this->branch->id}/divisions")->assertOk()->json('divisions.0');

    expect($row['physical_address'])->toBeNull()
        ->and($row['postal_address'])->toBeNull()
        ->and($row['telephone'])->toBeNull()
        ->and($row['email'])->toBeNull();
});

test('the public division units endpoint returns the division and its units with member counts', function () {
    $division = Division::create(['name' => 'Alpha One', 'branch_id' => $this->branch->id]);
    $big = RedCrossUnit::create(['name' => 'Big Unit', 'division_id' => $division->id, 'is_active' => true]);
    $small = RedCrossUnit::create(['name' => 'Small Unit', 'division_id' => $division->id, 'is_active' => true]);
    RedCrossUnit::create(['name' => 'Empty Unit', 'division_id' => $division->id, 'is_active' => true]);
    User::factory()->count(2)->create(['red_cross_unit_id' => $big->id, 'lifecycle_status' => 'active']);
    User::factory()->create(['red_cross_unit_id' => $small->id, 'lifecycle_status' => 'active']);

    $response = $this->getJson("/api/divisions/{$division->id}/units")->assertOk();

    expect($response->json('id'))->toBe($division->id)
        ->and($response->json('name'))->toBe('Alpha One')
        ->and($response->json('units'))->toBe([
            ['id' => $big->id, 'name' => 'Big Unit', 'members_count' => 2],
            ['id' => $small->id, 'name' => 'Small Unit', 'members_count' => 1],
        ]);
});

test('the migration decodes &amp; in unit, branch and division names', function () {
    $division = Division::create(['name' => 'Arts &amp; Crafts', 'branch_id' => $this->branch->id]);
    $unit = RedCrossUnit::create(['name' => 'School of Health Technology &amp; Nursing', 'division_id' => $division->id, 'is_active' => true]);
    $plain = RedCrossUnit::create(['name' => 'St. Clare Nursery & Pry', 'division_id' => $division->id, 'is_active' => true]);
    $this->branch->update(['name' => 'Alpha &amp; Beta']);

    (require base_path(DECODE_AMP_MIGRATION))->up();

    expect($unit->fresh()->name)->toBe('School of Health Technology & Nursing')
        ->and($plain->fresh()->name)->toBe('St. Clare Nursery & Pry')
        ->and($division->fresh()->name)->toBe('Arts & Crafts')
        ->and($this->branch->fresh()->name)->toBe('Alpha & Beta');
});

test('the division edit form says the contact details are public', function () {
    $this->withoutVite();

    foreach (['manage-admin-panel', 'edit_division_information', 'view_division_information'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')
        ->syncPermissions(['manage-admin-panel', 'edit_division_information', 'view_division_information']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $admin = User::factory()->create();
    $admin->assignRole('national_db_administrator');
    $division = Division::create(['name' => 'Alpha One', 'branch_id' => $this->branch->id]);

    $this->actingAs($admin)
        ->get(route('divisions.edit', $division))
        ->assertOk()
        ->assertSee("These details are shown publicly on the home page map. Use the division office's contact details, not personal phone numbers or email addresses.", false);
});
