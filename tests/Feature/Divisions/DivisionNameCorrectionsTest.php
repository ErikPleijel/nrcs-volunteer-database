<?php

/**
 * Division name corrections (DivisionNameCorrections): applied by the legacy
 * import when it inserts a division, and by a data migration for databases
 * imported earlier. Only the exact legacy spelling is ever replaced.
 */

use App\Console\Commands\OldDbMigration\MigrateDivisions;
use App\Support\Divisions\DivisionNameCorrections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function insertDivision(int $id, string $name, int $branchId = 844): void
{
    DB::table('divisions')->insert(['id' => $id, 'name' => $name, 'branch_id' => $branchId, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
}

function correctionsMigration(): object
{
    return require database_path('migrations/2026_10_11_150000_correct_legacy_division_names.php');
}

beforeEach(function () {
    DB::table('branches')->insert(['id' => 844, 'name' => 'Ebonyi', 'code' => 'EBO', 'is_active' => true]);
});

test('the list covers the eight known misspellings and the Ile Oluji/Okeigbo rename', function () {
    expect(collect(DivisionNameCorrections::BY_ID)->map(fn ($pair) => $pair[1])->all())->toBe([
        3289 => 'Ikwo',
        3317 => 'Ijero',
        3325 => 'Ilejemeje',
        3326 => 'Enugu South',
        3341 => 'Udenu',
        3411 => 'Jaba',
        3454 => 'Kumbotso',
        3774 => 'Kebbe',
        3653 => 'Ile Oluji/Okeigbo',
    ]);
});

test('apply() replaces only the exact legacy spelling of that id', function () {
    expect(DivisionNameCorrections::apply(3289, 'lkwo'))->toBe('Ikwo')
        ->and(DivisionNameCorrections::apply(3317, 'Ijero,'))->toBe('Ijero')
        ->and(DivisionNameCorrections::apply(3411, 'jaba'))->toBe('Jaba')
        // Already right, or changed some other way: left alone.
        ->and(DivisionNameCorrections::apply(3411, 'Jaba'))->toBe('Jaba')
        ->and(DivisionNameCorrections::apply(3289, 'Ikwo North'))->toBe('Ikwo North')
        // Another id with the same text, or no name at all: left alone.
        ->and(DivisionNameCorrections::apply(9999, 'lkwo'))->toBe('lkwo')
        ->and(DivisionNameCorrections::apply(3289, null))->toBeNull();
});

test('the legacy import stores the corrected, trimmed name and keeps the id', function () {
    $row = app(MigrateDivisions::class)->buildDivisionRow((object) [
        'DivisionID' => 3289,
        'Division' => '  lkwo ',
        'BranchID' => 844,
        'Include_in_list' => 1,
    ]);

    expect($row['id'])->toBe(3289)
        ->and($row['name'])->toBe('Ikwo')
        ->and($row['branch_id'])->toBe(844)
        ->and($row['is_active'])->toBeTrue();

    $other = app(MigrateDivisions::class)->buildDivisionRow((object) [
        'DivisionID' => 3292,
        'Division' => 'Ohaukwu',
        'BranchID' => 844,
        'Include_in_list' => 1,
    ]);
    expect($other['name'])->toBe('Ohaukwu');
});

test('the data migration corrects only rows that still have the legacy spelling, and can be undone', function () {
    insertDivision(3289, 'lkwo');
    insertDivision(3411, 'jaba');
    insertDivision(3454, 'Kumbotso');            // already correct
    insertDivision(3317, 'Ijero Ekiti');         // changed some other way
    insertDivision(3292, 'Ohaukwu');             // not in the list

    correctionsMigration()->up();

    expect(DB::table('divisions')->orderBy('id')->pluck('name', 'id')->all())->toBe([
        3289 => 'Ikwo',
        3292 => 'Ohaukwu',
        3317 => 'Ijero Ekiti',
        3411 => 'Jaba',
        3454 => 'Kumbotso',
    ]);

    // Running it again changes nothing.
    correctionsMigration()->up();
    expect(DB::table('divisions')->where('id', 3289)->value('name'))->toBe('Ikwo');

    correctionsMigration()->down();

    expect(DB::table('divisions')->orderBy('id')->pluck('name', 'id')->all())->toBe([
        3289 => 'lkwo',
        3292 => 'Ohaukwu',
        3317 => 'Ijero Ekiti',
        3411 => 'jaba',
        // Was already correct before up(), so down() restores the legacy
        // spelling it would have had — acceptable for a rollback.
        3454 => 'kumbotso',
    ]);
});

test('an already-correct name is not touched, despite the case-insensitive collation', function () {
    // With a plain WHERE name = 'jaba', MySQL would match 'Jaba' too and
    // rewrite the row; the byte-for-byte comparison must leave it alone.
    insertDivision(3411, 'Jaba');
    DB::table('divisions')->where('id', 3411)->update(['updated_at' => '2020-01-01 00:00:00']);

    correctionsMigration()->up();

    expect(DB::table('divisions')->where('id', 3411)->value('updated_at'))->toBe('2020-01-01 00:00:00');
});
