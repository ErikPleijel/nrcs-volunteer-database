<?php

/**
 * Okeigbo (3654) merged into Ile-Oluji (3653), renamed "Ile Oluji/Okeigbo":
 * DivisionMerges (the list, used by the legacy import), DivisionMerger (moves
 * every record in an existing database) and the data migration.
 */

use App\Console\Commands\OldDbMigration\MigrateDivisions;
use App\Models\Activity;
use App\Models\Donation;
use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\RedCrossUnit;
use App\Models\Training;
use App\Models\User;
use App\Services\DivisionMerger;
use App\Support\Divisions\DivisionMerges;
use Database\Seeders\DivisionCoordinatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    DB::table('branches')->insert([
        ['id' => 862, 'name' => 'Ondo', 'code' => 'OND', 'is_active' => true],
        ['id' => 846, 'name' => 'Ekiti', 'code' => 'EKI', 'is_active' => true],
    ]);
    DB::table('divisions')->insert([
        ['id' => 3653, 'name' => 'Ile-Oluji', 'branch_id' => 862, 'is_active' => true],
        ['id' => 3654, 'name' => 'Okeigbo', 'branch_id' => 862, 'is_active' => true],
        ['id' => 3655, 'name' => 'Irele', 'branch_id' => 862, 'is_active' => true],
        ['id' => 3317, 'name' => 'Ijero', 'branch_id' => 846, 'is_active' => true],
    ]);
});

function mergeMigration(): object
{
    return require database_path('migrations/2026_10_11_160000_merge_okeigbo_into_ile_oluji.php');
}

/**
 * One record of every kind pointing at $divisionId; returns their ids.
 */
function recordsIn(int $divisionId): array
{
    $user = User::factory()->create(['branch_id' => 862, 'division_id' => $divisionId]);

    $campaignId = DB::table('messaging_campaigns')->insertGetId([
        'channel' => 'email',
        'audience_type' => 'filter',
        'body' => 'Hello',
        'filter_json' => json_encode(['branch_id' => '862', 'division_id' => (string) $divisionId, 'gender' => null]),
        'created_by' => $user->id,
        'scope_level' => 'division',
        'scope_id' => $divisionId,
    ]);

    return [
        'users' => $user->id,
        'red_cross_units' => RedCrossUnit::create(['name' => "Unit {$divisionId}", 'division_id' => $divisionId, 'is_active' => true])->id,
        'organisations' => Organisation::create(['name' => "Org {$divisionId}", 'branch_id' => 862, 'division_id' => $divisionId])->id,
        'membership_payments' => MembershipPayment::factory()->approved()->create(['user_id' => $user->id, 'branch_id' => 862, 'division_id' => $divisionId])->id,
        'donations' => Donation::factory()->create(['user_id' => $user->id, 'branch_id' => 862, 'division_id' => $divisionId])->id,
        'activities' => Activity::factory()->create(['user_id' => $user->id, 'branch_id' => 862, 'division_id' => $divisionId])->id,
        'trainings' => Training::factory()->create(['user_id' => $user->id, 'branch_id' => 862, 'division_id' => $divisionId])->id,
        'logs' => DB::table('logs')->insertGetId(['action' => 'test', 'division_id' => $divisionId, 'created_at' => now(), 'updated_at' => now()]),
        'messaging_campaigns' => $campaignId,
    ];
}

function divisionOf(string $table, int $id): ?int
{
    $value = DB::table($table)->where('id', $id)->value('division_id');

    return $value === null ? null : (int) $value;
}

test('the merge list maps Okeigbo to Ile-Oluji and leaves other ids alone', function () {
    expect(DivisionMerges::INTO)->toBe([3654 => 3653])
        ->and(DivisionMerges::target(3654))->toBe(3653)
        ->and(DivisionMerges::target('3654'))->toBe(3653)
        ->and(DivisionMerges::target(3655))->toBe(3655)
        ->and(DivisionMerges::target(null))->toBeNull()
        ->and(DivisionMerges::isMergedAway(3654))->toBeTrue()
        ->and(DivisionMerges::isMergedAway(3653))->toBeFalse();
});

test('a fresh legacy import names 3653 "Ile Oluji/Okeigbo"', function () {
    $row = app(MigrateDivisions::class)->buildDivisionRow((object) [
        'DivisionID' => 3653, 'Division' => 'Ile-Oluji', 'BranchID' => 862, 'Include_in_list' => 1,
    ]);

    expect($row['name'])->toBe('Ile Oluji/Okeigbo');
});

test('every record in Okeigbo moves to Ile-Oluji, and Okeigbo is deleted', function () {
    $moved = recordsIn(3654);
    $control = recordsIn(3655);

    $counts = app(DivisionMerger::class)->merge(3654, 3653);

    foreach (DivisionMerger::DIVISION_ID_TABLES as $table) {
        expect(divisionOf($table, $moved[$table]))->toBe(3653, "{$table} not moved")
            ->and(divisionOf($table, $control[$table]))->toBe(3655, "{$table} control changed")
            ->and($counts[$table])->toBe(1);
    }

    $campaign = DB::table('messaging_campaigns')->where('id', $moved['messaging_campaigns'])->first();
    expect((int) $campaign->scope_id)->toBe(3653)
        ->and(json_decode($campaign->filter_json, true)['division_id'])->toBe('3653');

    $controlCampaign = DB::table('messaging_campaigns')->where('id', $control['messaging_campaigns'])->first();
    expect((int) $controlCampaign->scope_id)->toBe(3655)
        ->and(json_decode($controlCampaign->filter_json, true)['division_id'])->toBe('3655');

    expect(DB::table('divisions')->where('id', 3654)->exists())->toBeFalse()
        ->and(DB::table('divisions')->where('id', 3653)->exists())->toBeTrue()
        ->and(DB::table('logs')->where('action', 'division_merged')->count())->toBe(1);
});

test('snapshot rows are added into Ile-Oluji\'s row for the same date, or relabelled', function () {
    $row = fn (int $division, string $date, array $counts) => DB::table('stats_snapshots')->insert(array_merge([
        'snapshot_date' => $date, 'branch_id' => 862, 'division_id' => $division,
        'pending_engagement' => 0, 'active' => 0, 'dormant' => 0, 'archived' => 0,
        'members_total' => 0, 'members_men' => 0, 'members_women' => 0,
        'volunteers_total' => 0, 'volunteers_men' => 0, 'volunteers_women' => 0,
        'dormant_avg_days_inactive' => null, 'is_backfilled' => false,
    ], $counts));

    $row(3653, '2026-09-30', ['active' => 10, 'dormant' => 3, 'members_total' => 5, 'dormant_avg_days_inactive' => 100.0]);
    $row(3654, '2026-09-30', ['active' => 2, 'dormant' => 1, 'members_total' => 1, 'dormant_avg_days_inactive' => 200.0, 'is_backfilled' => true]);
    $row(3654, '2026-08-31', ['active' => 4]);

    app(DivisionMerger::class)->merge(3654, 3653);

    expect(DB::table('stats_snapshots')->where('division_id', 3654)->count())->toBe(0);

    $september = DB::table('stats_snapshots')->where('division_id', 3653)->where('snapshot_date', '2026-09-30')->sole();
    expect((int) $september->active)->toBe(12)
        ->and((int) $september->dormant)->toBe(4)
        ->and((int) $september->members_total)->toBe(6)
        // (100 × 3 + 200 × 1) / 4
        ->and((float) $september->dormant_avg_days_inactive)->toBe(125.0)
        ->and((bool) $september->is_backfilled)->toBeTrue();

    $august = DB::table('stats_snapshots')->where('division_id', 3653)->where('snapshot_date', '2026-08-31')->sole();
    expect((int) $august->active)->toBe(4);
});

test('merging again does nothing, and a merge across branches is refused', function () {
    app(DivisionMerger::class)->merge(3654, 3653);

    expect(app(DivisionMerger::class)->merge(3654, 3653))->toBe([]);

    expect(fn () => app(DivisionMerger::class)->merge(3655, 3317))->toThrow(InvalidArgumentException::class);
    expect(DB::table('divisions')->where('id', 3655)->exists())->toBeTrue();
});

test('the data migration renames Ile-Oluji and merges Okeigbo into it', function () {
    $moved = recordsIn(3654);

    mergeMigration()->up();

    expect(DB::table('divisions')->where('id', 3653)->value('name'))->toBe('Ile Oluji/Okeigbo')
        ->and(DB::table('divisions')->where('id', 3654)->exists())->toBeFalse()
        ->and(divisionOf('users', $moved['users']))->toBe(3653);

    // Safe to run again.
    mergeMigration()->up();
    expect(DB::table('divisions')->where('id', 3653)->value('name'))->toBe('Ile Oluji/Okeigbo');
});

test('the data migration leaves a 3653 that was renamed some other way', function () {
    DB::table('divisions')->where('id', 3653)->update(['name' => 'Ile Oluji']);

    mergeMigration()->up();

    expect(DB::table('divisions')->where('id', 3653)->value('name'))->toBe('Ile Oluji');
});

test('the coordinates seeder gives "Ile Oluji/Okeigbo" its LGA coordinates, with no special case left', function () {
    mergeMigration()->up();

    $this->seed(DivisionCoordinatesSeeder::class);

    $division = DB::table('divisions')->where('id', 3653)->first();
    expect((float) $division->latitude)->toBe(7.166442)
        ->and((float) $division->longitude)->toBe(4.724235);
});
