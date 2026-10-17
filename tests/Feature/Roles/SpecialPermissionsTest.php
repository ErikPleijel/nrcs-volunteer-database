<?php

/**
 * The special-permission tick boxes on users/roles (App\Support\SpecialPermissions):
 *  - branch_db_administrator: "Can approve financial records" (approve_payments +
 *    approve_donations given directly; ON by default; only national may change it);
 *  - division DB assistants: "Can upload photos and signatures" (upload_user_images)
 *    and "Can register new users" (add_user); OFF by default; set by branch or national.
 * Plus the approval queues / four-eyes rule with the tick box off, and the
 * migration that moved the two approve permissions off the role.
 *
 * Roles are hand-built (as in RoleAssignmentTest): PermissionsTableSeeder issues a
 * TRUNCATE, which would end RefreshDatabase's transaction.
 */

use App\Http\Controllers\UserController;
use App\Models\Branch;
use App\Models\Division;
use App\Models\Donation;
use App\Models\MembershipPayment;
use App\Models\User;
use App\Support\SpecialPermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

const SP_FINANCIAL = SpecialPermissions::FINANCIAL_APPROVAL;
const SP_UPLOAD = SpecialPermissions::UPLOAD_IMAGES;
const SP_REGISTER = SpecialPermissions::REGISTER_USERS;
const SP_HEADS_UP = 'Without this permission, this person cannot approve';
const SP_NOBODY = 'No one in this branch will be able to approve financial records. All approvals will go to HQ.';

function spRolesPost(User $actor, array $payload)
{
    return test()->actingAs($actor)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('users.roles.update'), $payload);
}

function spRolesPage(User $actor, User $target)
{
    return test()->actingAs($actor)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('users.roles.edit', ['user_id' => $target->id]))
        ->assertOk();
}

function spUser(string $role, Branch $branch, ?Division $division = null): User
{
    $user = ($division ? User::factory()->inDivision($division) : User::factory()->inBranch($branch))->create();
    $user->assignRole($role);

    return $user;
}

/** A branch DB administrator as the migration leaves them: role + the tick box ON. */
function spBranchAdmin(Branch $branch): User
{
    $user = spUser('branch_db_administrator', $branch);
    SpecialPermissions::grantDefaults($user);

    return $user->fresh();
}

function spDirect(User $user): array
{
    return $user->fresh()->getDirectPermissions()->pluck('name')->sort()->values()->all();
}

beforeEach(function () {
    $this->withoutVite();

    $approve = ['approve_payments', 'approve_donations'];
    $records = ['view_payments', 'add_payments', 'view_donations', 'add_donations'];
    foreach (array_merge([
        'manage-admin-panel', 'manage_roles_and_permissions', 'view_user', 'edit_user', 'add_user',
        'upload_user_images',
        'authorize_branch_secretary', 'authorize_branch_db_administrator', 'authorize_national_db_assistant',
        'authorize_branch_db_assistant', 'authorize_division_db_assistant_finance',
        'authorize_division_db_assistant_operations',
        'revoke_branch_db_assistant', 'revoke_division_db_assistant_finance', 'revoke_division_db_assistant_operations',
        'print_idcards',
    ], $approve, $records) as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions(array_merge([
        'manage-admin-panel', 'manage_roles_and_permissions', 'view_user', 'edit_user', 'add_user',
        'authorize_branch_secretary', 'authorize_branch_db_administrator', 'authorize_national_db_assistant',
        'revoke_branch_db_assistant', 'revoke_division_db_assistant_finance', 'revoke_division_db_assistant_operations',
    ], $approve, $records));

    $branchCore = array_merge([
        'manage-admin-panel', 'manage_roles_and_permissions', 'view_user', 'edit_user', 'add_user',
        'authorize_branch_db_assistant', 'authorize_division_db_assistant_finance',
        'authorize_division_db_assistant_operations',
    ], $records);
    Role::findOrCreate('branch_secretary', 'web')->syncPermissions(array_merge($branchCore, $approve));
    // As PermissionsTableSeeder now has it: no approve_payments / approve_donations on the role.
    Role::findOrCreate('branch_db_administrator', 'web')->syncPermissions($branchCore);

    Role::findOrCreate('branch_db_assistant', 'web')->syncPermissions(['manage-admin-panel', 'view_user', 'edit_user', 'add_user']);
    Role::findOrCreate('division_db_assistant_finance', 'web')->syncPermissions(array_merge(['manage-admin-panel', 'view_user'], $records));
    Role::findOrCreate('division_db_assistant_operations', 'web')->syncPermissions(['manage-admin-panel', 'view_user']);
    Role::findOrCreate('national_db_assistant', 'web')->syncPermissions(['manage-admin-panel', 'view_user']);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branchA = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->branchB = Branch::create(['name' => 'Beta Branch', 'code' => 'BET']);
    $this->divisionA = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branchA->id]);
    $this->divisionB = Division::create(['name' => 'Beta Division', 'branch_id' => $this->branchB->id]);

    $this->national = User::factory()->create();
    $this->national->assignRole('national_db_administrator');
});

/*
|--------------------------------------------------------------------------
| Branch DB administrator: "Can approve financial records"
|--------------------------------------------------------------------------
*/

test('a new branch DB administrator gets the financial approval tick box ON by default', function (array $extra) {
    $target = User::factory()->inBranch($this->branchA)->create();

    spRolesPost($this->national, ['user_id' => $target->id, 'role' => 'branch_db_administrator'] + $extra)
        ->assertSessionHasNoErrors();

    $target = $target->fresh();
    expect(spDirect($target))->toBe(['approve_donations', 'approve_payments'])
        ->and($target->can('approve_payments'))->toBeTrue()
        ->and($target->can('approve_donations'))->toBeTrue();
})->with([
    'form with the box ticked' => [['special_permissions_shown' => '1', 'permissions' => [SP_FINANCIAL]]],
    'posted without the tick boxes' => [[]],
]);

test('national can untick and re-tick financial approval for a branch DB administrator', function () {
    $target = spBranchAdmin($this->branchA);

    spRolesPost($this->national, [
        'user_id' => $target->id, 'role' => 'branch_db_administrator', 'special_permissions_shown' => '1',
    ])->assertSessionHasNoErrors();

    $target = $target->fresh();
    expect(spDirect($target))->toBe([])
        ->and($target->hasRole('branch_db_administrator'))->toBeTrue()
        ->and($target->can('approve_payments'))->toBeFalse()
        ->and($target->can('approve_donations'))->toBeFalse();

    $log = DB::table('logs')->where('action', 'user_roles_updated')->latest('id')->first();
    expect($log->description)->toContain('special permissions revoked: approve_donations, approve_payments');

    spRolesPost($this->national, [
        'user_id' => $target->id, 'role' => 'branch_db_administrator',
        'special_permissions_shown' => '1', 'permissions' => [SP_FINANCIAL],
    ])->assertSessionHasNoErrors();

    expect($target->fresh()->can('approve_payments'))->toBeTrue();
});

test('saving a branch DB administrator without the tick boxes keeps their current setting', function () {
    $target = spUser('branch_db_administrator', $this->branchA); // currently OFF

    spRolesPost($this->national, ['user_id' => $target->id, 'role' => 'branch_db_administrator'])
        ->assertSessionHasNoErrors();

    expect(spDirect($target))->toBe([]);
});

test('branch-level users cannot change a branch DB administrator\'s financial approval', function () {
    $secretary = spUser('branch_secretary', $this->branchA);
    $target = spBranchAdmin($this->branchA);

    spRolesPost($secretary, [
        'user_id' => $target->id, 'role' => 'branch_db_administrator', 'special_permissions_shown' => '1',
    ])->assertSessionHasErrors('user_id');

    expect(spDirect($target))->toBe(['approve_donations', 'approve_payments']);
});

test('a branch user viewing a branch DB administrator sees the tick box read-only', function () {
    $secretary = spUser('branch_secretary', $this->branchA);
    $target = spBranchAdmin($this->branchA);

    spRolesPage($secretary, $target)
        ->assertSee('Can approve financial records')
        ->assertSee('Only HQ can change this.')
        ->assertDontSee('id="financial-approval-warning"', false);
});

test('national sees the heads-up warning, and the "no one" line only when nobody else in the branch can approve', function () {
    $target = spBranchAdmin($this->branchA);

    spRolesPage($this->national, $target)
        ->assertSee('id="financial-approval-warning"', false)
        ->assertSee(SP_HEADS_UP)
        ->assertSee(SP_NOBODY);

    spUser('branch_secretary', $this->branchA);

    spRolesPage($this->national, $target)
        ->assertSee(SP_HEADS_UP)
        ->assertDontSee(SP_NOBODY);
});

test('the Users by Role table tags only branch DB administrators whose financial approval is off', function () {
    $on = spBranchAdmin($this->branchA);
    $off = spUser('branch_db_administrator', $this->branchB);

    $page = test()->actingAs($this->national)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('users.roles.edit'))
        ->assertOk();

    expect(substr_count($page->getContent(), 'cannot approve financial records'))->toBe(1)
        ->and($page->getContent())->not->toContain('approve payments');
});

test('another branch DB administrator counts as an approver only while their tick box is on', function () {
    $target = spBranchAdmin($this->branchA);
    $other = spUser('branch_db_administrator', $this->branchA); // OFF
    spUser('branch_secretary', $this->branchB); // other branch: never counts

    expect(SpecialPermissions::otherBranchFinancialApprovers($target))->toBe(0);

    SpecialPermissions::grantDefaults($other);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect(SpecialPermissions::otherBranchFinancialApprovers($target))->toBe(1);
});

test('removing or changing the branch DB administrator role removes the tick box permissions', function (string $role) {
    $target = spBranchAdmin($this->branchA);

    spRolesPost($this->national, ['user_id' => $target->id, 'role' => $role])->assertSessionHasNoErrors();

    expect(spDirect($target))->toBe([]);
})->with([
    'removed' => [UserController::REMOVE_ROLE],
    'changed to branch secretary' => ['branch_secretary'],
]);

test('a tick box that does not belong to the selected role is refused', function () {
    $target = User::factory()->inBranch($this->branchA)->create();

    spRolesPost($this->national, [
        'user_id' => $target->id, 'role' => 'branch_db_administrator',
        'special_permissions_shown' => '1', 'permissions' => [SP_FINANCIAL, SP_UPLOAD],
    ])->assertSessionHasErrors('permissions');

    expect($target->fresh()->getRoleNames()->all())->toBe([]);
});

/*
|--------------------------------------------------------------------------
| Division DB assistants: upload images / register users
|--------------------------------------------------------------------------
*/

test('a new division assistant gets both division tick boxes OFF by default', function () {
    $secretary = spUser('branch_secretary', $this->branchA);
    $target = User::factory()->inDivision($this->divisionA)->create();

    spRolesPost($secretary, ['user_id' => $target->id, 'role' => 'division_db_assistant_finance'])
        ->assertSessionHasNoErrors();

    expect(spDirect($target))->toBe([]);
});

test('branch level can tick and untick the two division tick boxes', function (string $role) {
    $admin = spBranchAdmin($this->branchA);
    $target = spUser($role, $this->branchA, $this->divisionA);

    spRolesPost($admin, [
        'user_id' => $target->id, 'role' => $role,
        'special_permissions_shown' => '1', 'permissions' => [SP_UPLOAD, SP_REGISTER],
    ])->assertSessionHasNoErrors();

    $target = $target->fresh();
    expect(spDirect($target))->toBe(['add_user', 'upload_user_images'])
        ->and($target->can('add_user'))->toBeTrue()
        ->and($target->can('upload_user_images'))->toBeTrue();

    spRolesPost($admin, [
        'user_id' => $target->id, 'role' => $role,
        'special_permissions_shown' => '1', 'permissions' => [SP_REGISTER],
    ])->assertSessionHasNoErrors();

    expect(spDirect($target))->toBe(['add_user']);
})->with(['division_db_assistant_finance', 'division_db_assistant_operations']);

test('national can set the division tick boxes while keeping the role it cannot appoint', function () {
    $target = spUser('division_db_assistant_operations', $this->branchA, $this->divisionA);

    spRolesPost($this->national, [
        'user_id' => $target->id, 'role' => 'division_db_assistant_operations',
        'special_permissions_shown' => '1', 'permissions' => [SP_UPLOAD],
    ])->assertSessionHasNoErrors();

    $target = $target->fresh();
    expect($target->getRoleNames()->all())->toBe(['division_db_assistant_operations'])
        ->and(spDirect($target))->toBe(['upload_user_images']);
});

test('national still cannot appoint a division assistant', function () {
    $target = User::factory()->inDivision($this->divisionA)->create();

    spRolesPost($this->national, [
        'user_id' => $target->id, 'role' => 'division_db_assistant_finance',
        'special_permissions_shown' => '1', 'permissions' => [SP_UPLOAD],
    ])->assertSessionHasErrors('role');

    expect($target->fresh()->getRoleNames()->all())->toBe([])
        ->and(spDirect($target))->toBe([]);
});

test('switching between the two division roles keeps the division tick boxes', function () {
    $secretary = spUser('branch_secretary', $this->branchA);
    $target = spUser('division_db_assistant_finance', $this->branchA, $this->divisionA);
    $target->givePermissionTo(['upload_user_images', 'add_user']);

    spRolesPost($secretary, ['user_id' => $target->id, 'role' => 'division_db_assistant_operations'])
        ->assertSessionHasNoErrors();

    $target = $target->fresh();
    expect($target->hasRole('division_db_assistant_operations'))->toBeTrue()
        ->and(spDirect($target))->toBe(['add_user', 'upload_user_images']);
});

test('removing a division assistant role removes the division tick boxes', function () {
    $secretary = spUser('branch_secretary', $this->branchA);
    $target = spUser('division_db_assistant_finance', $this->branchA, $this->divisionA);
    $target->givePermissionTo(['upload_user_images', 'add_user']);

    spRolesPost($secretary, ['user_id' => $target->id, 'role' => UserController::REMOVE_ROLE])
        ->assertSessionHasNoErrors();

    expect(spDirect($target))->toBe([]);
});

test('a branch admin cannot change roles or tick boxes of someone in another branch', function () {
    $secretary = spUser('branch_secretary', $this->branchA);
    $target = spUser('division_db_assistant_finance', $this->branchB, $this->divisionB);

    spRolesPost($secretary, [
        'user_id' => $target->id, 'role' => 'division_db_assistant_finance',
        'special_permissions_shown' => '1', 'permissions' => [SP_UPLOAD, SP_REGISTER],
    ])->assertSessionHasErrors(['user_id' => 'Branch-level administrators can only modify users in their own branch.']);

    expect(spDirect($target))->toBe([]);
});

test('the roles page shows the division tick boxes for a division assistant', function () {
    $secretary = spUser('branch_secretary', $this->branchA);
    $target = spUser('division_db_assistant_finance', $this->branchA, $this->divisionA);

    spRolesPage($secretary, $target)
        ->assertSee('Can upload photos and signatures')
        ->assertSee('Can register new users')
        ->assertDontSee('Only HQ can change this.');
});

/*
|--------------------------------------------------------------------------
| Approvals with the tick box off, and the four-eyes rule
|--------------------------------------------------------------------------
*/

function spDonation(Branch $branch, User $submitter): Donation
{
    return Donation::factory()->create([
        'user_id' => User::factory()->inBranch($branch)->create()->id,
        'entered_by_user_id' => $submitter->id,
        'branch_id' => $branch->id,
        'amount' => 500,
    ]);
}

function spPayment(Branch $branch, User $submitter): MembershipPayment
{
    return MembershipPayment::factory()->create([
        'user_id' => User::factory()->inBranch($branch)->create()->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $branch->id,
    ]);
}

test('a branch DB administrator with the tick box can approve donations and payments', function () {
    $secretary = spUser('branch_secretary', $this->branchA);
    $admin = spBranchAdmin($this->branchA);
    $donation = spDonation($this->branchA, $secretary);
    $payment = spPayment($this->branchA, $secretary);

    $this->actingAs($admin)->get(route('donations.approvals'))->assertOk();
    $this->actingAs($admin)->post(route('donations.approve', $donation->id))->assertSessionHas('success');
    $this->actingAs($admin)->post(route('membership-payments.approve', $payment->id))->assertSessionHas('success');

    expect(Donation::withAnyApprovalStatus()->find($donation->id)->approval_status)->toBe(Donation::APPROVED)
        ->and(MembershipPayment::withAnyApprovalStatus()->find($payment->id)->approval_status)->toBe(MembershipPayment::APPROVED);
});

test('a branch DB administrator without the tick box is refused everywhere, also by URL', function () {
    $secretary = spUser('branch_secretary', $this->branchA);
    $admin = spUser('branch_db_administrator', $this->branchA); // OFF
    $donation = spDonation($this->branchA, $secretary);
    $payment = spPayment($this->branchA, $secretary);

    $this->actingAs($admin)->get(route('donations.approvals'))->assertForbidden();
    $this->actingAs($admin)->get(route('membership-payments.approvals'))->assertForbidden();
    $this->actingAs($admin)->post(route('donations.approve', $donation->id))->assertForbidden();
    $this->actingAs($admin)->post(route('donations.reject', $donation->id), ['reason' => 'x'])->assertForbidden();
    $this->actingAs($admin)->post(route('donations.bulk-approve'), ['ids' => [$donation->id]])->assertForbidden();
    $this->actingAs($admin)->post(route('membership-payments.approve', $payment->id))->assertForbidden();
    $this->actingAs($admin)->post(route('membership-payments.bulk-approve'), ['ids' => [$payment->id]])->assertForbidden();

    expect(Donation::withAnyApprovalStatus()->find($donation->id)->approval_status)->toBe(Donation::PENDING)
        ->and(MembershipPayment::withAnyApprovalStatus()->find($payment->id)->approval_status)->toBe(MembershipPayment::PENDING);
});

test('with the branch DB administrator off, the secretary\'s own record still needs a second person: HQ', function () {
    $secretary = spUser('branch_secretary', $this->branchA);
    $admin = spUser('branch_db_administrator', $this->branchA); // OFF
    $donation = spDonation($this->branchA, $secretary);

    // Four-eyes: the secretary cannot approve their own record.
    $this->actingAs($secretary)->post(route('donations.approve', $donation->id))->assertForbidden();
    // The branch DB administrator cannot approve it either.
    $this->actingAs($admin)->post(route('donations.approve', $donation->id))->assertForbidden();
    // HQ can.
    $this->actingAs($this->national)->post(route('donations.approve', $donation->id))->assertSessionHas('success');

    expect(Donation::withAnyApprovalStatus()->find($donation->id)->decided_by_user_id)->toBe($this->national->id);
});

test('the four-eyes rule still applies to a branch DB administrator with the tick box', function () {
    $admin = spBranchAdmin($this->branchA);
    $donation = spDonation($this->branchA, $admin);

    $this->actingAs($admin)->post(route('donations.approve', $donation->id))->assertForbidden();

    expect(Donation::withAnyApprovalStatus()->find($donation->id)->approval_status)->toBe(Donation::PENDING);
});

test('the record submitter without the tick box still sees their own record read-only', function () {
    $admin = spUser('branch_db_administrator', $this->branchA); // OFF
    $donation = spDonation($this->branchA, $admin);

    $this->actingAs($admin)->get(route('donations.review', $donation->id))
        ->assertOk()
        ->assertDontSee(route('donations.approve', $donation->id), false);
});

/*
|--------------------------------------------------------------------------
| Migration + defaults for other assignment paths
|--------------------------------------------------------------------------
*/

test('the migration moves financial approval from the role to each branch DB administrator, and is safe to run twice', function () {
    $role = Role::findByName('branch_db_administrator', 'web');
    $role->givePermissionTo(['approve_payments', 'approve_donations']); // the old seeder's state
    $first = spUser('branch_db_administrator', $this->branchA);
    $second = spUser('branch_db_administrator', $this->branchB);
    $secretary = spUser('branch_secretary', $this->branchA);

    $migration = require database_path('migrations/2026_10_17_120000_move_branch_financial_approval_to_direct_permissions.php');
    $migration->up();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($role->fresh()->permissions->pluck('name')->intersect(['approve_payments', 'approve_donations'])->all())->toBe([])
        ->and(spDirect($first))->toBe(['approve_donations', 'approve_payments'])
        ->and(spDirect($second))->toBe(['approve_donations', 'approve_payments'])
        ->and(spDirect($secretary))->toBe([])
        ->and($first->fresh()->can('approve_payments'))->toBeTrue()
        ->and(Permission::where('name', 'upload_user_images')->exists())->toBeTrue();

    // HQ unticks one; running the migration again must not re-tick it.
    $second->revokePermissionTo(['approve_payments', 'approve_donations']);
    $migration->up();

    expect(spDirect($first))->toBe(['approve_donations', 'approve_payments'])
        ->and(spDirect($second))->toBe([]);
});

test('PermissionsTableSeeder refuses to run before the financial approval migration, changing nothing', function () {
    $role = Role::findByName('branch_db_administrator', 'web');
    $role->givePermissionTo(['approve_payments', 'approve_donations']); // pre-migration state
    DB::table('migrations')->where('migration', '2026_10_17_120000_move_branch_financial_approval_to_direct_permissions')->delete();

    expect(fn () => (new \Database\Seeders\PermissionsTableSeeder)->run())
        ->toThrow(RuntimeException::class, 'run "php artisan migrate" first');

    expect($role->fresh()->permissions->pluck('name')->all())->toContain('approve_payments', 'approve_donations');
});

test('grantDefaults (legacy migration) turns financial approval on for branch DB administrators only', function () {
    $admin = spUser('branch_db_administrator', $this->branchA);
    $assistant = spUser('division_db_assistant_finance', $this->branchA, $this->divisionA);
    $secretary = spUser('branch_secretary', $this->branchA);

    foreach ([$admin, $assistant, $secretary] as $user) {
        SpecialPermissions::grantDefaults($user);
    }

    expect(spDirect($admin))->toBe(['approve_donations', 'approve_payments'])
        ->and(spDirect($assistant))->toBe([])
        ->and(spDirect($secretary))->toBe([]);
});

test('national DB assistant tick boxes still work as before', function () {
    $target = User::factory()->create();

    spRolesPost($this->national, [
        'user_id' => $target->id, 'role' => 'national_db_assistant',
        'special_permissions_shown' => '1', 'permissions' => ['print_idcards'],
    ])->assertSessionHasNoErrors();

    expect(spDirect($target))->toBe(['print_idcards']);
});
