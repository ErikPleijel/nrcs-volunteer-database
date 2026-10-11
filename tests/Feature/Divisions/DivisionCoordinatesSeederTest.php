<?php

/**
 * DivisionCoordinatesSeeder matches each LGA coordinates row to a division
 * through DivisionCoordinateAliases. Covers the alias fixes: Ikwo matches
 * its corrected name, Izzi gets Izzi's coordinates (no longer Ngbo), and
 * "Nasarawa" means Kano's Nassarawa only inside Kano.
 */

use App\Support\Divisions\DivisionCoordinateAliases;
use Database\Seeders\DivisionCoordinatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function coordinatesOf(string $branch, string $division): array
{
    $row = DB::table('divisions')
        ->join('branches', 'branches.id', '=', 'divisions.branch_id')
        ->where('branches.name', $branch)
        ->where('divisions.name', $division)
        ->first(['divisions.latitude', 'divisions.longitude']);

    return [$row->latitude === null ? null : (float) $row->latitude, $row->longitude === null ? null : (float) $row->longitude];
}

beforeEach(function () {
    $divisions = [
        'Ebonyi' => ['Ikwo', 'Izzi', 'Ngbo', 'Ohaukwu'],
        'Kano' => ['Nassarawa'],
        'Nasarawa' => ['Nasarawa'],
        'Ekiti' => ['Ijero,'],
        'Kaduna' => ['jaba'],
    ];

    foreach ($divisions as $branch => $names) {
        $branchId = DB::table('branches')->insertGetId(['name' => $branch, 'code' => strtoupper(substr($branch, 0, 3)), 'is_active' => true]);
        foreach ($names as $name) {
            DB::table('divisions')->insert(['name' => $name, 'branch_id' => $branchId, 'is_active' => true]);
        }
    }

    $this->seed(DivisionCoordinatesSeeder::class);
});

test('Ikwo, now spelled correctly, gets its coordinates without an alias', function () {
    expect(coordinatesOf('Ebonyi', 'Ikwo'))->toBe([12.473, 7.487]);
});

test('Izzi gets Izzi\'s coordinates and Ngbo no longer takes them', function () {
    expect(coordinatesOf('Ebonyi', 'Izzi'))->toBe([6.385765, 8.02549])
        ->and(coordinatesOf('Ebonyi', 'Ngbo'))->toBe([null, null]);
});

test('Nasarawa State\'s Nasarawa gets its own row; Kano\'s Nassarawa still gets the Kano row', function () {
    expect(coordinatesOf('Nasarawa', 'Nasarawa'))->toBe([8.53333333, 8.3])
        ->and(coordinatesOf('Kano', 'Nassarawa'))->toBe([8.53333333, 8.3]);
});

test('names that differ only in case or punctuation still match', function () {
    expect(coordinatesOf('Ekiti', 'Ijero,'))->toBe([7.81667, 5.08333])
        ->and(coordinatesOf('Kaduna', 'jaba'))->toBe([9.44818, 8.00845]);
});

test('the Nasarawa alias only applies in Kano', function () {
    expect(DivisionCoordinateAliases::division('Nasarawa', 'Kano'))->toBe('Nassarawa')
        ->and(DivisionCoordinateAliases::division('Nasarawa', 'Nasarawa'))->toBe('Nasarawa')
        ->and(DivisionCoordinateAliases::division('Izzi', 'Ebonyi'))->toBe('Izzi')
        ->and(DivisionCoordinateAliases::division('Ikwo', 'Ebonyi'))->toBe('Ikwo')
        ->and(DivisionCoordinateAliases::division('Abuja', 'Federal Capital Territory'))->toBe('Abuja Municipal')
        ->and(DivisionCoordinateAliases::key('Enugu South,'))->toBe(DivisionCoordinateAliases::key('enugu south'));
});
