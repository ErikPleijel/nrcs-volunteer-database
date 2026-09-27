<?php

/**
 * users:report-phone-duplicates — the read-only dry run that picks, per
 * duplicate-Telephone1 group, which EMAIL-LESS account to keep, which would
 * be archived and which records would move to the winner first. Covers the
 * same-person gate, emailed accounts being left alone, the ranking order
 * (record strength > latest login > newest id), the move plan, the
 * later-assignment-wins RCU conflict rule and that the command never writes.
 */

use App\Models\Activity;
use App\Models\MembershipPayment;
use App\Models\RedCrossUnit;
use App\Models\Training;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** Run the report and return its CSV rows keyed by account id. */
function phoneDuplicateReport(): array
{
    $path = storage_path('framework/testing/phone-duplicates-'.uniqid().'.csv');
    @mkdir(dirname($path), 0775, true);

    test()->artisan('users:report-phone-duplicates', ['--output' => $path, '--samples' => 0])->assertSuccessful();

    $f = fopen($path, 'r');
    $header = fgetcsv($f, null, ',', '"', '');
    $rows = [];
    while (($r = fgetcsv($f, null, ',', '"', '')) !== false) {
        $row = array_combine($header, $r);
        $rows[(int) $row['account_id']] = $row;
    }
    fclose($f);
    unlink($path);

    return $rows;
}

function phoneDupUser(string $phone, array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'first_name' => 'Amina',
        'last_name' => 'Bello',
        'gender' => 'female',
        'telephone1' => $phone,
        'email' => null,
    ], $overrides));
}

test('emailed accounts are never candidates, and one email-less account needs no action', function () {
    $phoneOnly = phoneDupUser('08011111111');
    $emailedA = phoneDupUser('+234 801 111 1111', ['email' => 'a@example.test']);
    $emailedB = phoneDupUser('08011111111', ['email' => 'b@example.test']);

    $rows = phoneDuplicateReport();

    expect($rows[$phoneOnly->id]['role'])->toBe('keep (only email-less)')
        ->and($rows[$phoneOnly->id]['group_verdict'])->toBe('no action: only one email-less account')
        ->and($rows[$emailedA->id]['role'])->toBe('left alone (has email)')
        ->and($rows[$emailedB->id]['role'])->toBe('left alone (has email)');
});

test('record strength beats recency, and the loser\'s records would move to the winner', function () {
    $strong = phoneDupUser('08022222222');
    MembershipPayment::factory()->create(['user_id' => $strong->id]);
    $weak = phoneDupUser('08022222222', ['last_login_at' => now()]);
    $training = Training::factory()->create(['user_id' => $weak->id]);
    $emailed = phoneDupUser('08022222222', ['email' => 'c@example.test']);
    Activity::factory()->create(['user_id' => $emailed->id]);

    $rows = phoneDuplicateReport();

    expect($rows[$strong->id]['role'])->toBe('keep')
        ->and($rows[$strong->id]['winner_basis'])->toBe('record strength')
        ->and($rows[$weak->id]['role'])->toBe('archive')
        ->and($rows[$weak->id]['would_move_to_winner'])->toBe("trainings #{$training->id}")
        ->and($rows[$weak->id]['types_winner_lacked'])->toBe('training')
        ->and($rows[$weak->id]['rcu_conflict'])->toBe('')
        ->and($rows[$emailed->id]['role'])->toBe('left alone (has email)');
});

test('equal record profiles fall back to latest login, then newest id', function () {
    $older = phoneDupUser('08033333333', ['last_login_at' => now()->subYear()]);
    $recent = phoneDupUser('08033333333', ['last_login_at' => now()->subDay()]);

    $first = phoneDupUser('08044444444');
    $newest = phoneDupUser('08044444444');

    $rows = phoneDuplicateReport();

    expect($rows[$recent->id]['role'])->toBe('keep')
        ->and($rows[$older->id]['reason'])->toContain('older last login')
        ->and($rows[$newest->id]['role'])->toBe('keep')
        ->and($rows[$first->id]['role'])->toBe('archive')
        ->and($rows[$first->id]['winner_basis'])->toBe('recency (all empty)');
});

test('groups that are not the same person are skipped entirely', function () {
    $a = phoneDupUser('08055555555', ['first_name' => 'Musa', 'last_name' => 'Ali', 'gender' => 'male']);
    $b = phoneDupUser('08055555555', ['first_name' => 'Grace', 'last_name' => 'Okon']);
    // Same name (either order), different gender.
    $c = phoneDupUser('08066666666');
    $d = phoneDupUser('08066666666', ['first_name' => 'Bello', 'last_name' => 'Amina', 'gender' => 'male']);

    $rows = phoneDuplicateReport();

    foreach ([$a, $b, $c, $d] as $u) {
        expect($rows[$u->id]['role'])->toBe('skipped');
    }
    expect($rows[$a->id]['group_verdict'])->toContain('names differ')
        ->and($rows[$c->id]['group_verdict'])->toBe('skipped: genders differ');
});

test('a current RCU moves to a winner without one; a different one keeps the later assignment', function () {
    $unitA = RedCrossUnit::create(['name' => 'Unit A']);
    $unitB = RedCrossUnit::create(['name' => 'Unit B']);

    $winner = phoneDupUser('08077777777');
    MembershipPayment::factory()->create(['user_id' => $winner->id]);
    $movable = phoneDupUser('08077777777', ['red_cross_unit_id' => $unitA->id]);

    $conflictWinner = phoneDupUser('08088888888', ['red_cross_unit_id' => $unitA->id, 'assigned_rcu_date' => '2020-01-01']);
    MembershipPayment::factory()->create(['user_id' => $conflictWinner->id]);
    $conflict = phoneDupUser('08088888888', ['red_cross_unit_id' => $unitB->id, 'assigned_rcu_date' => '2021-01-01']);

    $rows = phoneDuplicateReport();

    expect($rows[$winner->id]['role'])->toBe('keep')
        ->and($rows[$movable->id]['would_move_to_winner'])->toContain("users.red_cross_unit_id {$unitA->id}")
        ->and($rows[$movable->id]['rcu_conflict'])->toBe('')
        ->and($rows[$conflictWinner->id]['role'])->toBe('keep')
        ->and($rows[$conflict->id]['rcu_conflict'])->toBe('took_newer')
        ->and($rows[$conflict->id]['would_move_to_winner'])->toContain("winner takes newer RCU {$unitB->id}");
});

test('the report never writes to the database', function () {
    phoneDupUser('08099999999');
    phoneDupUser('08099999999');
    $before = DB::table('users')->orderBy('id')->get()->toJson();

    $writes = 0;
    DB::listen(function ($q) use (&$writes) {
        if (! preg_match('/^\s*select\b/i', $q->sql)) {
            $writes++;
        }
    });

    phoneDuplicateReport();

    expect($writes)->toBe(0)
        ->and(DB::table('users')->orderBy('id')->get()->toJson())->toBe($before);
});

test('a group whose email-less accounts include a role holder is skipped, not split', function () {
    Spatie\Permission\Models\Role::findOrCreate('division_db_assistant_finance', 'web');

    $winner = phoneDupUser('07012121212');
    MembershipPayment::factory()->create(['user_id' => $winner->id]);
    $holder = phoneDupUser('07012121212');
    $holder->assignRole('division_db_assistant_finance');

    $rows = phoneDuplicateReport();

    expect($rows[$holder->id]['group_verdict'])->toBe('skipped: loser holds a role')
        ->and($rows[$holder->id]['role'])->toBe('skipped (holds a role)')
        ->and($rows[$winner->id]['role'])->toBe('skipped')
        ->and(array_column($rows, 'role'))->not->toContain('archive');
});

test('emailed and already-archived accounts are counted separately', function () {
    phoneDupUser('07013131313');
    phoneDupUser('07013131313', ['email' => 'e@example.test']);
    phoneDupUser('07013131313', ['lifecycle_status' => 'archived']);
    // An archived account that also has an email counts as archived, not emailed.
    phoneDupUser('07013131313', ['lifecycle_status' => 'archived', 'email' => 'old@example.test']);

    $path = storage_path('framework/testing/phone-duplicates-'.uniqid().'.csv');
    @mkdir(dirname($path), 0775, true);

    $this->artisan('users:report-phone-duplicates', ['--output' => $path, '--samples' => 0])
        ->expectsOutputToContain('emailed accounts, left alone (never touched): 1')
        ->expectsOutputToContain('already archived (e.g. by previous runs):     2')
        ->assertSuccessful();

    unlink($path);
});

test('a role holder that would win does not block the group', function () {
    Spatie\Permission\Models\Role::findOrCreate('division_db_assistant_finance', 'web');

    $holder = phoneDupUser('07014141414');
    $holder->assignRole('division_db_assistant_finance');
    MembershipPayment::factory()->create(['user_id' => $holder->id]);
    $loser = phoneDupUser('07014141414');

    $rows = phoneDuplicateReport();

    expect($rows[$holder->id]['role'])->toBe('keep')
        ->and($rows[$loser->id]['role'])->toBe('archive');
});
