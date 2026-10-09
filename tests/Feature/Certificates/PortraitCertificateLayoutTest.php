<?php

/**
 * Branded portrait certificates (volunteering and donation): long names get a
 * smaller font so they stay on one line, and long item lists get tighter
 * rows, so the pinned footer (signatures + QR code) stays inside the frame.
 */

use App\Models\Activity;
use App\Models\Donation;
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

    // 7 and 33 characters once joined.
    $this->shortNamed = User::factory()->create(['first_name' => 'Ann', 'last_name' => 'Lee']);
    $this->longNamed = User::factory()->create(['first_name' => 'Abcdefghijklmno', 'last_name' => 'Pqrstuvwxyzabcdef']);
});

function printPortrait(string $certificateType, User $user): string
{
    return test()->actingAs(test()->admin)
        ->post(route('certificates.bulk_print_branded_portrait'), [
            'certificate_type' => $certificateType,
            'training_ids' => [$user->id],
        ])
        ->assertOk()
        ->getContent();
}

test('a donation certificate renders, with the name size following its length', function () {
    Donation::factory()->approved()->create(['user_id' => $this->shortNamed->id]);
    Donation::factory()->approved()->create(['user_id' => $this->longNamed->id]);

    $short = printPortrait('donation', $this->shortNamed);
    expect($short)->toContain('certificate_of_appreciation.png')
        ->toContain('<h2 class="recipient-name" style="font-size: 44px;">')
        ->toContain('Ann Lee');

    expect(printPortrait('donation', $this->longNamed))
        ->toContain('<h2 class="recipient-name" style="font-size: 30px;">')
        ->not->toContain('font-size: 44px;">');
});

test('a volunteering certificate renders, with the name size following its length', function () {
    Activity::factory()->approved()->create(['user_id' => $this->shortNamed->id]);
    Activity::factory()->approved()->create(['user_id' => $this->longNamed->id]);

    expect(printPortrait('volunteering', $this->shortNamed))
        ->toContain('<h2 class="recipient-name" style="font-size: 44px;">')
        ->toContain('Total Hours');

    expect(printPortrait('volunteering', $this->longNamed))
        ->toContain('<h2 class="recipient-name" style="font-size: 30px;">');
});

test('the items table only gets compact rows when it has more than 8 body rows', function () {
    // 2 items + total row = 3 body rows.
    Donation::factory()->count(2)->approved()->create(['user_id' => $this->shortNamed->id]);
    expect(printPortrait('donation', $this->shortNamed))
        ->toContain('<table class="items-table">')
        ->not->toContain('<table class="items-table items-table--compact">');

    // 12 donations: 10 listed + summary + total = 12 body rows.
    Donation::factory()->count(12)->approved()->create(['user_id' => $this->longNamed->id]);
    expect(printPortrait('donation', $this->longNamed))
        ->toContain('<table class="items-table items-table--compact">');
});
