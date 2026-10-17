<?php

/**
 * users:fix-phone-letter-o: O/o typed for 0 in telephone1/2 is replaced only
 * where that gives a valid phone number, nothing else in the value changes,
 * dry-run writes nothing, no audit log or updated_at change, and a second
 * run finds nothing left.
 */

use App\Models\Log;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->leading = User::factory()->create(['telephone1' => 'O8031234567', 'telephone2' => null]);
    $this->spaced = User::factory()->create(['telephone1' => 'o803 123 4568', 'telephone2' => '+234 8O5 555 0000']);
    $this->tooLong = User::factory()->create(['telephone1' => 'O80312345690', 'telephone2' => '0O9080228068']);
    $this->word = User::factory()->create(['telephone1' => 'Adebola', 'telephone2' => '08031234569 EDO']);
    $this->clean = User::factory()->create(['telephone1' => '08031234570', 'telephone2' => null]);

    DB::table('users')->update(['updated_at' => '2020-01-01 00:00:00']);
});

function phones(User $user): array
{
    $row = DB::table('users')->where('id', $user->id)->first(['telephone1', 'telephone2', 'updated_at']);

    return [$row->telephone1, $row->telephone2, $row->updated_at];
}

test('dry-run is the default, reports and changes nothing', function () {
    $this->artisan('users:fix-phone-letter-o')
        ->expectsOutputToContain('[dry-run]')
        ->expectsOutputToContain('Would fix: 3 (telephone1: 2, telephone2: 1)')
        ->expectsOutputToContain('Skipped (still not a valid phone number): 4')
        ->expectsOutputToContain("DB-{$this->leading->id} telephone1: O8031234567 → 08031234567")
        ->assertSuccessful();

    expect(phones($this->leading)[0])->toBe('O8031234567')
        ->and(phones($this->spaced)[1])->toBe('+234 8O5 555 0000');
});

test('--dry-run with --commit still writes nothing', function () {
    $this->artisan('users:fix-phone-letter-o --dry-run --commit')->assertSuccessful();

    expect(phones($this->leading)[0])->toBe('O8031234567');
});

test('--commit replaces only the letter, keeps the rest as typed, and skips invalid values', function () {
    $logsBefore = Log::count();

    $this->artisan('users:fix-phone-letter-o --commit')
        ->expectsOutputToContain('Fixed: 3')
        ->expectsOutputToContain("DB-{$this->tooLong->id} telephone1: O80312345690")
        ->expectsOutputToContain("DB-{$this->word->id} telephone1: Adebola")
        ->assertSuccessful();

    expect(phones($this->leading))->toBe(['08031234567', null, '2020-01-01 00:00:00'])
        ->and(phones($this->spaced))->toBe(['0803 123 4568', '+234 805 555 0000', '2020-01-01 00:00:00'])
        ->and(phones($this->tooLong))->toBe(['O80312345690', '0O9080228068', '2020-01-01 00:00:00'])
        ->and(phones($this->word))->toBe(['Adebola', '08031234569 EDO', '2020-01-01 00:00:00'])
        ->and(phones($this->clean)[0])->toBe('08031234570')
        ->and(Log::count())->toBe($logsBefore);
});

test('a second run fixes nothing', function () {
    $this->artisan('users:fix-phone-letter-o --commit')->assertSuccessful();

    $this->artisan('users:fix-phone-letter-o --commit')
        ->expectsOutputToContain('Fixed: 0')
        ->expectsOutputToContain('Skipped (still not a valid phone number): 4')
        ->assertSuccessful();
});
