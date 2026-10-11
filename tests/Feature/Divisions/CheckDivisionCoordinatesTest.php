<?php

/**
 * divisions:check-coordinates (DivisionCoordinateCheck): read-only list of
 * divisions with missing, out-of-country, duplicated or far-off coordinates.
 */

use App\Services\DivisionCoordinateCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function placeDivision(int $branchId, string $name, ?float $lat, ?float $lng): int
{
    return DB::table('divisions')->insertGetId(['name' => $name, 'branch_id' => $branchId, 'is_active' => true, 'latitude' => $lat, 'longitude' => $lng]);
}

beforeEach(function () {
    // Ebonyi-like cluster around 6.2N 8.0E.
    $this->ebonyi = DB::table('branches')->insertGetId(['name' => 'Ebonyi', 'code' => 'EBO', 'is_active' => true]);
    placeDivision($this->ebonyi, 'Abakaliki', 6.33, 8.10);
    placeDivision($this->ebonyi, 'Afikpo North', 5.89, 7.95);
    placeDivision($this->ebonyi, 'Ezza North', 6.08, 8.00);
    placeDivision($this->ebonyi, 'Ishielu', 6.43, 7.82);
});

function findings(float $maxKm = 150): array
{
    return app(DivisionCoordinateCheck::class)->run($maxKm)->keyBy('name')->map(fn ($row) => $row['problems'])->all();
}

test('plausible coordinates give no findings and the command succeeds', function () {
    expect(findings())->toBe([]);

    $this->artisan('divisions:check-coordinates')
        ->expectsOutput('All division coordinates look plausible.')
        ->assertSuccessful();
});

test('a division far from the middle of its branch is reported with the distance', function () {
    placeDivision($this->ebonyi, 'Ikwo', 12.473, 7.487);

    $problems = findings()['Ikwo'];

    expect($problems)->toHaveCount(1)
        ->and($problems[0])->toBe('699 km from the middle of the other Ebonyi divisions');
});

test('missing, outside-Nigeria and shared points are reported', function () {
    placeDivision($this->ebonyi, 'Izzi', null, null);
    placeDivision($this->ebonyi, 'Ohaozara', 6.030218, 7.71407);
    placeDivision($this->ebonyi, 'Ohaukwu', 6.030218, 7.71407);
    $kano = DB::table('branches')->insertGetId(['name' => 'Kano', 'code' => 'KAN', 'is_active' => true]);
    placeDivision($kano, 'Nowhere', 51.5, -0.12);

    $all = findings();

    expect($all['Izzi'])->toBe(['No coordinates'])
        ->and($all['Ohaozara'][0])->toStartWith('Same point as Ohaukwu #')
        ->and($all['Ohaukwu'][0])->toStartWith('Same point as Ohaozara #')
        ->and($all['Nowhere'])->toBe(['Outside Nigeria']);
});

test('a point shared across branches names the other branch', function () {
    $nasarawa = DB::table('branches')->insertGetId(['name' => 'Nasarawa', 'code' => 'NAS', 'is_active' => true]);
    $ebonyiObi = placeDivision($this->ebonyi, 'Obi', 6.2, 8.0);
    $nasarawaObi = placeDivision($nasarawa, 'Obi', 6.2, 8.0);

    $byId = app(DivisionCoordinateCheck::class)->run()->keyBy('id');

    expect($byId[$ebonyiObi]['problems'])->toBe(["Same point as Obi #{$nasarawaObi} (Nasarawa)"])
        ->and($byId[$nasarawaObi]['problems'])->toBe(["Same point as Obi #{$ebonyiObi} (Ebonyi)"]);
});

test('--max-km changes what counts as far, and branches with too few divisions are not distance-checked', function () {
    placeDivision($this->ebonyi, 'Onicha', 6.9, 7.8);   // ~80 km from the middle

    expect(findings(150))->toBe([])
        ->and(array_keys(findings(50)))->toContain('Onicha');

    $small = DB::table('branches')->insertGetId(['name' => 'Bayelsa', 'code' => 'BAY', 'is_active' => true]);
    placeDivision($small, 'Yenegoa', 4.9, 6.3);
    placeDivision($small, 'Far Away', 12.0, 13.0);

    expect(array_keys(findings()))->not->toContain('Far Away');
});

test('the command lists findings in a table and fails, so scripts notice', function () {
    placeDivision($this->ebonyi, 'Ikwo', 12.473, 7.487);

    $this->artisan('divisions:check-coordinates')
        ->expectsOutputToContain('Ikwo')
        ->expectsOutputToContain('1 division with suspect coordinates')
        ->assertFailed();

    $this->artisan('divisions:check-coordinates', ['--max-km' => '0'])
        ->expectsOutput('--max-km must be a positive number.')
        ->assertFailed();
});

test('the check is read-only', function () {
    placeDivision($this->ebonyi, 'Ikwo', 12.473, 7.487);
    $before = DB::table('divisions')->orderBy('id')->get()->toArray();

    $this->artisan('divisions:check-coordinates');

    expect(DB::table('divisions')->orderBy('id')->get()->toArray())->toEqual($before);
});

test('the distance formula is right', function () {
    // Abakaliki to Kano, roughly 680 km.
    expect(DivisionCoordinateCheck::distanceKm(6.33, 8.10, 12.0, 8.52))->toBeGreaterThan(620)->toBeLessThan(640);
});
