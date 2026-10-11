<?php

/**
 * organisations.division_id: an organisation belongs to a division, and
 * deleting the division leaves the organisation with no division.
 */

use App\Models\Division;
use App\Models\Organisation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    DB::table('branches')->insert(['id' => 848, 'name' => 'FCT', 'code' => 'FCT', 'is_active' => true]);
    DB::table('divisions')->insert(['id' => 3069, 'name' => 'Abuja Municipal', 'branch_id' => 848, 'is_active' => true]);
});

test('an organisation belongs to a division, and a division has organisations', function () {
    $organisation = Organisation::create(['name' => 'Acme', 'branch_id' => 848, 'division_id' => 3069]);

    expect($organisation->division->name)->toBe('Abuja Municipal')
        ->and(Division::find(3069)->organisations->pluck('id')->all())->toBe([$organisation->id]);
});

test('division_id is optional on the column', function () {
    $organisation = Organisation::create(['name' => 'Acme', 'branch_id' => 848]);

    expect($organisation->fresh()->division_id)->toBeNull()
        ->and($organisation->division)->toBeNull();
});

test('deleting the division sets the organisation\'s division to null', function () {
    $organisation = Organisation::create(['name' => 'Acme', 'branch_id' => 848, 'division_id' => 3069]);

    DB::table('divisions')->where('id', 3069)->delete();

    expect($organisation->fresh())->not->toBeNull()
        ->and($organisation->fresh()->division_id)->toBeNull();
});
