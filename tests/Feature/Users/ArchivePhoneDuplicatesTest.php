<?php

/**
 * users:archive-phone-duplicates — the writes behind the phone-duplicate
 * report: records move to the kept account, RCU conflicts keep the later
 * assignment, a qualifying pending winner is promoted, losers are archived
 * and audited, emailed accounts are never touched, a failing group rolls
 * back on its own, and nothing is written without --commit.
 */

use App\Models\Activity;
use App\Models\Donation;
use App\Models\MembershipPayment;
use App\Models\Organisation;
use App\Models\RedCrossUnit;
use App\Models\Training;
use App\Models\User;
use App\Services\PhoneDuplicates\PhoneDuplicateMerger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function archiveDupUser(string $phone, array $overrides = []): User
{
    return User::factory()->create(array_merge([
        'first_name' => 'Amina',
        'last_name' => 'Bello',
        'gender' => 'female',
        'telephone1' => $phone,
        'email' => null,
        'lifecycle_status' => 'dormant',
    ], $overrides));
}

function archiveDupCommit(array $options = []): void
{
    test()->artisan('users:archive-phone-duplicates', ['--commit' => true] + $options)->assertSuccessful();
}

test('without --commit nothing is written', function () {
    $winner = archiveDupUser('08011111111');
    MembershipPayment::factory()->create(['user_id' => $winner->id]);
    $loser = archiveDupUser('08011111111');
    Training::factory()->create(['user_id' => $loser->id]);

    $writes = 0;
    DB::listen(function ($q) use (&$writes) {
        if (! preg_match('/^\s*select\b/i', $q->sql)) {
            $writes++;
        }
    });

    $this->artisan('users:archive-phone-duplicates')->assertSuccessful();

    expect($writes)->toBe(0)
        ->and($loser->fresh()->lifecycle_status)->toBe('dormant')
        ->and(DB::table('trainings')->where('user_id', $loser->id)->count())->toBe(1);
});

test('all live records move to the winner, soft-deleted rows stay, the loser is archived and audited', function () {
    // Winner holds 6 record types (incl. RCU); the loser 5, so the winner ranks first.
    $unit = RedCrossUnit::create(['name' => 'Unit A']);
    $winner = archiveDupUser('08022222222', ['red_cross_unit_id' => $unit->id]);
    MembershipPayment::factory()->create(['user_id' => $winner->id]);
    Training::factory()->create(['user_id' => $winner->id]);
    Activity::factory()->create(['user_id' => $winner->id]);
    Donation::factory()->create(['user_id' => $winner->id]);
    DB::table('id_card_prints')->insert(['user_id' => $winner->id, 'printed_at' => now()]);
    $sharedOrg = Organisation::create(['name' => 'Shared Org']);
    $otherOrg = Organisation::create(['name' => 'Other Org']);
    DB::table('organisation_user')->insert(['organisation_id' => $sharedOrg->id, 'user_id' => $winner->id]);

    $loser = archiveDupUser('+234 802 222 2222');
    $payment = MembershipPayment::factory()->create(['user_id' => $loser->id]);
    $training = Training::factory()->create(['user_id' => $loser->id]);
    $deletedTraining = Training::factory()->create(['user_id' => $loser->id, 'is_deleted' => true]);
    $activity = Activity::factory()->create(['user_id' => $loser->id]);
    $donation = Donation::factory()->create(['user_id' => $loser->id]);
    $idCard = DB::table('id_card_prints')->insertGetId(['user_id' => $loser->id, 'printed_at' => now()]);
    DB::table('organisation_user')->insert([
        ['organisation_id' => $sharedOrg->id, 'user_id' => $loser->id],
        ['organisation_id' => $otherOrg->id, 'user_id' => $loser->id],
    ]);

    archiveDupCommit();

    expect($payment->fresh()->user_id)->toBe($winner->id)
        ->and($training->fresh()->user_id)->toBe($winner->id)
        ->and($activity->fresh()->user_id)->toBe($winner->id)
        ->and($donation->fresh()->user_id)->toBe($winner->id)
        ->and(DB::table('id_card_prints')->where('id', $idCard)->value('user_id'))->toBe($winner->id)
        ->and($deletedTraining->fresh()->user_id)->toBe($loser->id)
        // The new org link moves; the one the winner already had stays behind.
        ->and(DB::table('organisation_user')->where('user_id', $winner->id)->pluck('organisation_id')->sort()->values()->all())
            ->toBe([$sharedOrg->id, $otherOrg->id])
        ->and(DB::table('organisation_user')->where('user_id', $loser->id)->pluck('organisation_id')->all())
            ->toBe([$sharedOrg->id])
        ->and($loser->fresh()->lifecycle_status)->toBe('archived')
        ->and($winner->fresh()->lifecycle_status)->toBe('dormant');

    $audit = DB::table('logs')->where('action', 'user_phone_duplicate_archived')->where('subject_id', $loser->id)->first();
    $new = json_decode($audit->new_values, true);
    expect($new['merged_into_user_id'])->toBe($winner->id)
        ->and($new['moved']['membership_payments'])->toBe([$payment->id])
        ->and($new['moved']['trainings'])->toBe([$training->id])
        ->and($new['moved']['organisation_user'])->toBe([$otherOrg->id])
        ->and(json_decode($audit->old_values, true))->toBe(['lifecycle_status' => 'dormant'])
        ->and(DB::table('logs')->where('action', 'user_phone_duplicate_merged')->where('subject_id', $winner->id)->exists())->toBeTrue();
});

test('an RCU conflict keeps the later assignment; a tie keeps the winner\'s', function () {
    $unitA = RedCrossUnit::create(['name' => 'Unit A']);
    $unitB = RedCrossUnit::create(['name' => 'Unit B']);

    // Loser's assignment is newer: winner takes it.
    $takes = archiveDupUser('08033333333', ['red_cross_unit_id' => $unitA->id, 'assigned_rcu_date' => '2020-01-01']);
    MembershipPayment::factory()->create(['user_id' => $takes->id]);
    archiveDupUser('08033333333', ['red_cross_unit_id' => $unitB->id, 'assigned_rcu_date' => '2021-06-01']);

    // Loser's assignment is older: winner keeps its own.
    $keeps = archiveDupUser('08044444444', ['red_cross_unit_id' => $unitA->id, 'assigned_rcu_date' => '2022-01-01']);
    MembershipPayment::factory()->create(['user_id' => $keeps->id]);
    archiveDupUser('08044444444', ['red_cross_unit_id' => $unitB->id, 'assigned_rcu_date' => '2019-01-01']);

    // Same date: winner keeps its own.
    $tie = archiveDupUser('08055555555', ['red_cross_unit_id' => $unitA->id, 'assigned_rcu_date' => '2022-01-01']);
    MembershipPayment::factory()->create(['user_id' => $tie->id]);
    archiveDupUser('08055555555', ['red_cross_unit_id' => $unitB->id, 'assigned_rcu_date' => '2022-01-01']);

    archiveDupCommit();

    expect($takes->fresh()->red_cross_unit_id)->toBe($unitB->id)
        ->and((string) $takes->fresh()->assigned_rcu_date)->toStartWith('2021-06-01')
        ->and($keeps->fresh()->red_cross_unit_id)->toBe($unitA->id)
        ->and($tie->fresh()->red_cross_unit_id)->toBe($unitA->id);
});

test('a pending winner that gains an RCU from the loser is promoted to active', function () {
    $unit = RedCrossUnit::create(['name' => 'Unit A']);

    $winner = archiveDupUser('08066666666', ['lifecycle_status' => 'pending_engagement']);
    Training::factory()->create(['user_id' => $winner->id]);
    Activity::factory()->create(['user_id' => $winner->id]);
    $loser = archiveDupUser('08066666666', ['red_cross_unit_id' => $unit->id, 'assigned_rcu_date' => '2021-01-01']);

    archiveDupCommit();

    $winner->refresh();
    expect($winner->red_cross_unit_id)->toBe($unit->id)
        ->and($winner->lifecycle_status)->toBe('active')
        ->and($loser->fresh()->lifecycle_status)->toBe('archived');
});

test('emailed accounts are never touched', function () {
    // Neither has records: the newest (highest id) account wins.
    $loser = archiveDupUser('08077777777');
    $winner = archiveDupUser('08077777777');
    $emailed = archiveDupUser('08077777777', ['email' => 'amina@example.test']);
    $emailedPayment = MembershipPayment::factory()->create(['user_id' => $emailed->id]);
    $before = DB::table('users')->where('id', $emailed->id)->first();

    archiveDupCommit();

    expect(DB::table('users')->where('id', $emailed->id)->first())->toEqual($before)
        ->and($emailedPayment->fresh()->user_id)->toBe($emailed->id)
        ->and($loser->fresh()->lifecycle_status)->toBe('archived')
        ->and($winner->fresh()->lifecycle_status)->toBe('dormant');
});

test('after --commit the report finds nothing left to do and the winner can log in by phone', function () {
    $winner = archiveDupUser('08088888888', ['lifecycle_status' => 'active']);
    MembershipPayment::factory()->create(['user_id' => $winner->id]);
    archiveDupUser('08088888888');
    archiveDupUser('08088888888');

    archiveDupCommit();

    $path = storage_path('framework/testing/after-archive-'.uniqid().'.csv');
    @mkdir(dirname($path), 0775, true);
    $this->artisan('users:report-phone-duplicates', ['--output' => $path, '--samples' => 0])->assertSuccessful();
    $roles = array_map(fn ($line) => str_getcsv($line, ',', '"', '')[6], array_slice(file($path, FILE_IGNORE_NEW_LINES), 1));
    unlink($path);

    expect($roles)->not->toContain('archive')
        ->and(array_count_values($roles))->toBe(['keep (only email-less)' => 1, 'left alone (archived)' => 2]);

    $this->post('/login', ['login' => '08088888888', 'password' => 'password'])->assertRedirect('/profile');
    $this->assertAuthenticatedAs($winner);
});

test('a failure partway through a group rolls that group back and the run continues', function () {
    $failWinner = archiveDupUser('08099999999');
    MembershipPayment::factory()->create(['user_id' => $failWinner->id]);
    $failLoser = archiveDupUser('08099999999');
    $failTraining = Training::factory()->create(['user_id' => $failLoser->id]);

    $okWinner = archiveDupUser('07011111111');
    MembershipPayment::factory()->create(['user_id' => $okWinner->id]);
    $okLoser = archiveDupUser('07011111111');

    // Records move, then archiving blows up — but only for the first group.
    app()->instance(PhoneDuplicateMerger::class, new class ($failLoser->id) extends PhoneDuplicateMerger
    {
        public function __construct(private int $failFor) {}

        protected function archiveLoser(User $loser, User $winner, array $entry, string $phone): void
        {
            if ($loser->id === $this->failFor) {
                throw new RuntimeException('simulated failure');
            }
            parent::archiveLoser($loser, $winner, $entry, $phone);
        }
    });

    $this->artisan('users:archive-phone-duplicates', ['--commit' => true])->assertFailed();

    expect($failTraining->fresh()->user_id)->toBe($failLoser->id)
        ->and($failLoser->fresh()->lifecycle_status)->toBe('dormant')
        ->and(DB::table('logs')->where('subject_id', $failWinner->id)->exists())->toBeFalse()
        ->and($okLoser->fresh()->lifecycle_status)->toBe('archived');
});

test('--limit and --phones restrict which groups are processed', function () {
    $groups = [];
    foreach (['07022222222', '07033333333', '07044444444'] as $phone) {
        $winner = archiveDupUser($phone);
        MembershipPayment::factory()->create(['user_id' => $winner->id]);
        $groups[$phone] = archiveDupUser($phone);
    }

    archiveDupCommit(['--phones' => '+2347033333333']);
    expect($groups['07033333333']->fresh()->lifecycle_status)->toBe('archived')
        ->and($groups['07022222222']->fresh()->lifecycle_status)->toBe('dormant');

    archiveDupCommit(['--limit' => 1]);
    $archived = collect($groups)->filter(fn ($u) => $u->fresh()->lifecycle_status === 'archived')->count();
    expect($archived)->toBe(2);
});

test('a role-holder group is skipped without attempting a transaction', function () {
    Spatie\Permission\Models\Role::findOrCreate('division_db_assistant_finance', 'web');

    $winner = archiveDupUser('07015151515');
    MembershipPayment::factory()->create(['user_id' => $winner->id]);
    $holder = archiveDupUser('07015151515');
    $holder->assignRole('division_db_assistant_finance');
    $before = DB::table('users')->whereIn('id', [$winner->id, $holder->id])->orderBy('id')->get();

    $attempted = new ArrayObject();
    app()->instance(PhoneDuplicateMerger::class, new class ($attempted) extends PhoneDuplicateMerger
    {
        public function __construct(private ArrayObject $attempted) {}

        public function merge(array $group): array
        {
            $this->attempted[] = $group['norm'];

            return parent::merge($group);
        }
    });

    $this->artisan('users:archive-phone-duplicates', ['--commit' => true])
        ->expectsOutputToContain('Skipped, not attempted — loser holds a role')
        ->expectsOutputToContain("07015151515 (DB-{$holder->id})")
        ->assertSuccessful();

    expect($attempted->getArrayCopy())->toBe([])
        ->and(DB::table('users')->whereIn('id', [$winner->id, $holder->id])->orderBy('id')->get())->toEqual($before)
        ->and(DB::table('logs')->whereIn('subject_id', [$winner->id, $holder->id])->exists())->toBeFalse();
});

test('a run that commits nothing prints no audit-file path', function () {
    Spatie\Permission\Models\Role::findOrCreate('division_db_assistant_finance', 'web');
    // Both empty: the newer account wins, so the (older) role holder is the loser.
    archiveDupUser('07016161616')->assignRole('division_db_assistant_finance');
    archiveDupUser('07016161616');

    $this->artisan('users:archive-phone-duplicates', ['--commit' => true])
        ->expectsOutputToContain('Skipped, not attempted')
        ->doesntExpectOutputToContain('Audit:')
        ->assertSuccessful();
});

test('a role holder that wins is processed normally and keeps its role', function () {
    Spatie\Permission\Models\Role::findOrCreate('division_db_assistant_finance', 'web');

    $holder = archiveDupUser('07017171717');
    $holder->assignRole('division_db_assistant_finance');
    MembershipPayment::factory()->create(['user_id' => $holder->id]);
    $loser = archiveDupUser('07017171717');
    $training = Training::factory()->create(['user_id' => $loser->id]);
    $before = DB::table('users')->where('id', $holder->id)->first();

    $this->artisan('users:archive-phone-duplicates', ['--commit' => true])
        ->doesntExpectOutputToContain('Skipped, not attempted')
        ->assertSuccessful();

    expect($loser->fresh()->lifecycle_status)->toBe('archived')
        ->and($training->fresh()->user_id)->toBe($holder->id)
        ->and($holder->fresh()->hasRole('division_db_assistant_finance'))->toBeTrue()
        ->and(DB::table('users')->where('id', $holder->id)->first())->toEqual($before);
});
