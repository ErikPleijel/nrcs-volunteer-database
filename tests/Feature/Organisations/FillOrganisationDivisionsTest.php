<?php

/**
 * organisations:fill-divisions — fills organisations.division_id from the
 * legacy persons.DivisionID, via an exported CSV file or the legacy
 * database, and never guesses: anything that does not match is listed and
 * left alone.
 */

use App\Console\Commands\FillOrganisationDivisions;
use App\Models\Organisation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    DB::table('branches')->insert([
        ['id' => 848, 'name' => 'FCT', 'code' => 'FCT', 'is_active' => true],
        ['id' => 858, 'name' => 'Lagos', 'code' => 'LAG', 'is_active' => true],
    ]);
    DB::table('divisions')->insert([
        ['id' => 3069, 'name' => 'Abuja Municipal', 'branch_id' => 848, 'is_active' => true],
        ['id' => 3070, 'name' => 'Bwari', 'branch_id' => 848, 'is_active' => true],
        ['id' => 3582, 'name' => 'Oshodi-Isolo', 'branch_id' => 858, 'is_active' => true],
    ]);

    $this->files = [];
});

afterEach(function () {
    foreach ($this->files as $file) {
        @unlink($file);
    }
});

function orgDivisionsCsv(object $test, array $rows, ?array $header = null): string
{
    $path = tempnam(sys_get_temp_dir(), 'orgdiv');
    $test->files[] = $path;

    $out = fopen($path, 'w');
    fputcsv($out, $header ?? FillOrganisationDivisions::HEADER);
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    fclose($out);

    return $path;
}

function makeOrg(int $id, string $name, ?int $branchId, ?int $divisionId = null): Organisation
{
    DB::table('organisations')->insert([
        'id' => $id, 'name' => $name, 'branch_id' => $branchId, 'division_id' => $divisionId,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return Organisation::find($id);
}

test('it fills each organisation\'s division from the file and logs every change', function () {
    makeOrg(2263, 'De bright schools', 858);
    makeOrg(2330, 'Tunnyville', 848);

    $file = orgDivisionsCsv($this, [
        [2263, 'De bright schools', 858, 3582],
        [2330, 'Tunnyville', 848, 3069],
        [9999, 'Not imported', 848, 3069], // in the file, not here: ignored
    ]);

    $this->artisan('organisations:fill-divisions', ['--file' => $file])->assertSuccessful();

    expect(Organisation::find(2263)->division_id)->toBe(3582)
        ->and(Organisation::find(2330)->division_id)->toBe(3069)
        ->and(DB::table('logs')->where('action', 'organisation_division_filled')->count())->toBe(2);

    $log = DB::table('logs')->where('action', 'organisation_division_filled')->where('subject_id', 2263)->sole();
    expect((int) $log->division_id)->toBe(3582)
        ->and(json_decode($log->new_values, true))->toBe(['division_id' => 3582]);
});

test('archived organisations are filled too', function () {
    makeOrg(2263, 'De bright schools', 858)->delete();

    $file = orgDivisionsCsv($this, [[2263, 'De bright schools', 858, 3582]]);

    $this->artisan('organisations:fill-divisions', ['--file' => $file])->assertSuccessful();

    expect(Organisation::withTrashed()->find(2263)->division_id)->toBe(3582);
});

test('a dry run reports but writes nothing', function () {
    makeOrg(2263, 'De bright schools', 858);

    $file = orgDivisionsCsv($this, [[2263, 'De bright schools', 858, 3582]]);

    $this->artisan('organisations:fill-divisions', ['--file' => $file, '--dry-run' => true])
        ->expectsOutputToContain('DRY RUN')
        ->assertSuccessful();

    expect(Organisation::find(2263)->division_id)->toBeNull()
        ->and(DB::table('logs')->where('action', 'organisation_division_filled')->count())->toBe(0);
});

test('every mismatch is listed by id and left alone, and the run fails', function () {
    makeOrg(101, 'Not in file', 848);
    makeOrg(102, 'Renamed here', 848);
    makeOrg(103, 'Moved branch', 848);
    makeOrg(104, 'No division', 848);
    makeOrg(105, 'Unknown division', 848);
    makeOrg(106, 'Other branch', 848);
    makeOrg(107, 'Fine', 848);

    $file = orgDivisionsCsv($this, [
        [102, 'Old name', 848, 3069],
        [103, 'Moved branch', 858, 3582],
        [104, 'No division', 848, ''],
        [105, 'Unknown division', 848, 4000],
        [106, 'Other branch', 848, 3582],
        [107, 'Fine', 848, 3070],
    ]);

    $this->artisan('organisations:fill-divisions', ['--file' => $file])
        ->expectsOutputToContain('Not in the file / legacy data: 101')
        ->expectsOutputToContain('Name differs from the file: 102')
        ->expectsOutputToContain('Branch differs from the file: 103')
        ->expectsOutputToContain('No division in the legacy data: 104')
        ->expectsOutputToContain('Division does not exist here: 105')
        ->expectsOutputToContain('Division belongs to a different branch: 106')
        ->assertFailed();

    expect(Organisation::whereKey([101, 102, 103, 104, 105, 106])->whereNotNull('division_id')->count())->toBe(0)
        ->and(Organisation::find(107)->division_id)->toBe(3070);
});

test('names are compared trimmed, as the legacy import stores them', function () {
    makeOrg(2263, 'De bright schools', 858);

    $file = orgDivisionsCsv($this, [[2263, '  De bright schools ', 858, 3582]]);

    $this->artisan('organisations:fill-divisions', ['--file' => $file])->assertSuccessful();

    expect(Organisation::find(2263)->division_id)->toBe(3582);
});

test('an organisation that already has a division is skipped and listed, unless --force', function () {
    makeOrg(2330, 'Tunnyville', 848, 3070);
    makeOrg(2331, 'Same already', 848, 3069);

    $file = orgDivisionsCsv($this, [
        [2330, 'Tunnyville', 848, 3069],
        [2331, 'Same already', 848, 3069],
    ]);

    $this->artisan('organisations:fill-divisions', ['--file' => $file])
        ->expectsOutputToContain('Already has a different division (use --force): 2330')
        ->assertFailed();

    expect(Organisation::find(2330)->division_id)->toBe(3070);

    $this->artisan('organisations:fill-divisions', ['--file' => $file, '--force' => true])->assertSuccessful();

    expect(Organisation::find(2330)->division_id)->toBe(3069)
        ->and(Organisation::find(2331)->division_id)->toBe(3069)
        // Only the real change is logged, with the old division.
        ->and(DB::table('logs')->where('action', 'organisation_division_filled')->count())->toBe(1)
        ->and(json_decode(DB::table('logs')->where('action', 'organisation_division_filled')->value('old_values'), true))
        ->toBe(['division_id' => 3070]);
});

test('running it again after a fill changes nothing and succeeds', function () {
    makeOrg(2263, 'De bright schools', 858);
    $file = orgDivisionsCsv($this, [[2263, 'De bright schools', 858, 3582]]);

    $this->artisan('organisations:fill-divisions', ['--file' => $file])->assertSuccessful();
    $this->artisan('organisations:fill-divisions', ['--file' => $file])->assertSuccessful();

    expect(DB::table('logs')->where('action', 'organisation_division_filled')->count())->toBe(1);
});

test('a file with the wrong header or a bad row is refused before anything is written', function () {
    makeOrg(2263, 'De bright schools', 858);

    $wrongHeader = orgDivisionsCsv($this, [[2263, 'De bright schools', 858, 3582]], ['id', 'division_id']);
    $this->artisan('organisations:fill-divisions', ['--file' => $wrongHeader])
        ->expectsOutputToContain('Unexpected header')
        ->assertFailed();

    $badRow = orgDivisionsCsv($this, [[2263, 'De bright schools', 858, 3582], ['abc', 'x', 1, 1]]);
    $this->artisan('organisations:fill-divisions', ['--file' => $badRow])
        ->expectsOutputToContain('Line 3')
        ->assertFailed();

    $this->artisan('organisations:fill-divisions', ['--file' => '/no/such/file.csv'])->assertFailed();

    expect(Organisation::find(2263)->division_id)->toBeNull();
});

test('exactly one source must be given', function () {
    $this->artisan('organisations:fill-divisions')->assertFailed();
    $this->artisan('organisations:fill-divisions', ['--legacy' => true, '--file' => 'x.csv'])->assertFailed();
});

describe('legacy database', function () {
    beforeEach(function () {
        // Point the legacy connection at the test database, with its own
        // persons table (created outside the test transaction, so dropped
        // again afterwards).
        config(['database.connections.old_db' => config('database.connections.'.config('database.default'))]);
        DB::purge('old_db');

        Schema::connection('old_db')->dropIfExists('persons');
        Schema::connection('old_db')->create('persons', function ($table) {
            $table->integer('PersonID')->primary();
            $table->boolean('IsOrganisation')->default(false);
            $table->string('Organisation')->nullable();
            $table->integer('BranchID')->nullable();
            $table->integer('DivisionID')->nullable();
        });

        DB::connection('old_db')->table('persons')->insert([
            ['PersonID' => 2263, 'IsOrganisation' => 1, 'Organisation' => ' De bright schools ', 'BranchID' => 858, 'DivisionID' => 3582],
            ['PersonID' => 2330, 'IsOrganisation' => 1, 'Organisation' => 'Tunnyville', 'BranchID' => 848, 'DivisionID' => 0],
            ['PersonID' => 2400, 'IsOrganisation' => 1, 'Organisation' => 'Okeigbo org', 'BranchID' => 862, 'DivisionID' => 3654],
            ['PersonID' => 3573, 'IsOrganisation' => 0, 'Organisation' => null, 'BranchID' => 848, 'DivisionID' => 3069],
        ]);
    });

    afterEach(function () {
        Schema::connection('old_db')->dropIfExists('persons');
    });

    test('--export writes every legacy organisation, names cleaned and merged divisions mapped', function () {
        $path = tempnam(sys_get_temp_dir(), 'orgdiv');
        $this->files[] = $path;

        $this->artisan('organisations:fill-divisions', ['--export' => $path])
            ->expectsOutputToContain('Wrote 3 legacy organisations')
            ->assertSuccessful();

        $lines = array_map('str_getcsv', file($path, FILE_IGNORE_NEW_LINES));

        expect($lines)->toBe([
            ['id', 'name', 'branch_id', 'division_id'],
            ['2263', 'De bright schools', '858', '3582'],
            ['2330', 'Tunnyville', '848', ''],
            ['2400', 'Okeigbo org', '862', '3653'], // Okeigbo merged into Ile Oluji/Okeigbo
        ]);
    });

    test('--legacy fills straight from the legacy database', function () {
        makeOrg(2263, 'De bright schools', 858);
        makeOrg(2330, 'Tunnyville', 848);

        $this->artisan('organisations:fill-divisions', ['--legacy' => true])
            ->expectsOutputToContain('No division in the legacy data: 2330')
            ->assertFailed();

        expect(Organisation::find(2263)->division_id)->toBe(3582)
            ->and(Organisation::find(2330)->division_id)->toBeNull();
    });

    test('an export read back with --file gives the same result as --legacy', function () {
        makeOrg(2263, 'De bright schools', 858);
        $path = tempnam(sys_get_temp_dir(), 'orgdiv');
        $this->files[] = $path;

        $this->artisan('organisations:fill-divisions', ['--export' => $path])->assertSuccessful();
        $this->artisan('organisations:fill-divisions', ['--file' => $path])->assertSuccessful();

        expect(Organisation::find(2263)->division_id)->toBe(3582);
    });
});
