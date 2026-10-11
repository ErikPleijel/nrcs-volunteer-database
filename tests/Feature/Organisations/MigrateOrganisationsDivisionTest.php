<?php

/**
 * The legacy organisation import sets division_id from persons.DivisionID,
 * only when that division was imported and is in the organisation's
 * branch — otherwise it is left empty with a warning, never guessed.
 */

use App\Console\Commands\OldDbMigration\MigrateOrganisations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    DB::table('branches')->insert([
        ['id' => 858, 'name' => 'Lagos', 'code' => 'LAG', 'is_active' => true],
        ['id' => 862, 'name' => 'Ondo', 'code' => 'OND', 'is_active' => true],
    ]);
    DB::table('divisions')->insert([
        ['id' => 3582, 'name' => 'Oshodi-Isolo', 'branch_id' => 858, 'is_active' => true],
        ['id' => 3653, 'name' => 'Ile Oluji/Okeigbo', 'branch_id' => 862, 'is_active' => true],
    ]);
});

function legacyOrg(array $overrides = []): object
{
    return (object) array_merge([
        'PersonID' => 2263,
        'Organisation' => ' De bright schools ',
        'Personal_info' => null,
        'Workplace_address' => null,
        'Email' => null,
        'BranchID' => 858,
        'DivisionID' => 3582,
    ], $overrides);
}

test('the division is imported when it exists and is in the organisation\'s branch', function () {
    $row = app(MigrateOrganisations::class)->buildOrganisationRow(legacyOrg());

    expect($row['id'])->toBe(2263)
        ->and($row['name'])->toBe('De bright schools')
        ->and($row['branch_id'])->toBe(858)
        ->and($row['division_id'])->toBe(3582);
});

test('a merged-away division is imported as the division it was merged into', function () {
    $row = app(MigrateOrganisations::class)->buildOrganisationRow(legacyOrg(['BranchID' => 862, 'DivisionID' => 3654]));

    expect($row['division_id'])->toBe(3653);
});

test('the division is left empty, with a warning, when it cannot be trusted', function (array $legacy, string $reason) {
    Log::spy();

    $row = app(MigrateOrganisations::class)->buildOrganisationRow(legacyOrg($legacy));

    expect($row['division_id'])->toBeNull();
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, $reason))->once();
})->with([
    'no division' => [['DivisionID' => null], 'has no division in the legacy data'],
    'division 0' => [['DivisionID' => 0], 'has no division in the legacy data'],
    'division not imported' => [['DivisionID' => 4000], 'division 4000 was not imported'],
    'division in another branch' => [['BranchID' => 862, 'DivisionID' => 3582], 'is not in its branch 862'],
    'organisation without a branch' => [['BranchID' => null], 'is not in its branch none'],
]);
