<?php

/**
 * idcards:make-test-users (dev-only):
 * - refuses to run, and changes nothing, unless APP_ENV is local or testing
 * - creates the four printtest users and can be run again without duplicates
 * - --remove deletes only those four users and their image files
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\MembershipFee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    $branch = Branch::create(['name' => 'FCT', 'code' => 'FCT']);
    Division::create(['name' => 'Abuja Municipal', 'branch_id' => $branch->id]);
    Division::create(['name' => 'Bwari', 'branch_id' => $branch->id]);
    MembershipFee::forceCreate(['name' => 'Silver', 'amount' => 5000, 'validity_years' => 1, 'is_volunteer_fee' => false]);

    Role::findOrCreate('national_db_administrator', 'web');
    $this->national = User::factory()->create();
    $this->national->assignRole('national_db_administrator');
});

function printtestUserCount(): int
{
    return User::where('email', 'like', '%@printtest.local')->count();
}

test('it refuses to run outside local/testing and changes nothing', function (string $env) {
    $this->app['env'] = $env;

    $this->artisan('idcards:make-test-users')
        ->expectsOutputToContain('Refusing to run: APP_ENV is "'.$env.'".')
        ->assertFailed();

    $this->artisan('idcards:make-test-users --remove')->assertFailed();

    expect(printtestUserCount())->toBe(0);
})->with(['production', 'staging']);

test('it creates four printable test users, two of them rejected, and re-runs without duplicates', function () {
    $this->artisan('idcards:make-test-users')->assertSuccessful();
    $ids = User::where('email', 'like', '%@printtest.local')->orderBy('id')->pluck('id')->all();

    $this->artisan('idcards:make-test-users')->assertSuccessful();

    expect(User::where('email', 'like', '%@printtest.local')->orderBy('id')->pluck('id')->all())->toBe($ids)
        ->and($ids)->toHaveCount(4)
        ->and(User::whereIn('id', $ids)->idCardPrintable()->count())->toBe(2)
        ->and(User::whereIn('id', $ids)->withImageRejection()->count())->toBe(2);

    Storage::disk('local')->assertExists('photos/profile/web/printtest-branch-photo.jpg');
    Storage::disk('local')->assertExists('photos/signatures/web/printtest-branch-sig.png');
});

test('--remove deletes only the printtest users and their files', function () {
    $this->artisan('idcards:make-test-users')->assertSuccessful();

    // Look-alikes that must survive: same domain but another address, and a similar domain.
    $sameDomain = User::factory()->create(['email' => 'someone@printtest.local']);
    $similarDomain = User::factory()->create(['email' => 'branch@printtest.local.org']);
    Storage::disk('local')->put('photos/profile/web/real-person.jpg', 'x');
    Storage::disk('local')->put('photos/profile/web/printtest-other.jpg', 'x');

    $this->artisan('idcards:make-test-users --remove')
        ->expectsOutputToContain('Removed 4 test users.')
        ->assertSuccessful();

    expect(User::whereKey([$sameDomain->id, $similarDomain->id, $this->national->id])->count())->toBe(3)
        ->and(User::where('email', 'branch@printtest.local')->exists())->toBeFalse();

    Storage::disk('local')->assertMissing('photos/profile/web/printtest-branch-photo.jpg');
    Storage::disk('local')->assertMissing('photos/signatures/original/printtest-rejected-signature-sig.png');
    Storage::disk('local')->assertExists('photos/profile/web/real-person.jpg');
    Storage::disk('local')->assertExists('photos/profile/web/printtest-other.jpg');
});
