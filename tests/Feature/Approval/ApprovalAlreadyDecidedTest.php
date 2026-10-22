<?php

/**
 * Approving or rejecting a record someone else already decided (stale page,
 * double-click) redirects to the approvals tab with "already approved by …"
 * instead of a 404, and the Approvable trait re-reads the row under a lock so a
 * stale in-memory model cannot decide it a second time.
 */

use App\Exceptions\RecordAlreadyDecided;
use App\Models\Activity;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $permissions = ['manage-admin-panel', 'approve_volunteering', 'view_volunteering', 'add_volunteering', 'edit_volunteering'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('branch_secretary', 'web')->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);

    $this->first = User::factory()->inBranch($branch)->create(['first_name' => 'Ada', 'last_name' => 'Obi']);
    $this->first->assignRole('branch_secretary');
    $this->second = User::factory()->inBranch($branch)->create(['first_name' => 'Bello', 'last_name' => 'Musa']);
    $this->second->assignRole('branch_secretary');
    $submitter = User::factory()->inBranch($branch)->create();
    $member = User::factory()->inBranch($branch)->create();

    $this->activity = Activity::factory()->create([
        'user_id' => $member->id,
        'submitted_by_user_id' => $submitter->id,
        'branch_id' => $branch->id,
    ]);
    $this->reviewUrl = route('activities.review', $this->activity->id);
});

test('approving an activity another reviewer already approved says who approved it instead of a 404', function () {
    $this->actingAs($this->first)->from($this->reviewUrl)
        ->post(route('activities.approve', $this->activity->id))
        ->assertSessionHas('success');

    $this->actingAs($this->second)->from($this->reviewUrl)
        ->post(route('activities.approve', $this->activity->id))
        ->assertRedirect(route('activities.approvals'))
        ->assertSessionHas('warning', fn ($m) => str_starts_with($m, 'This activity has already been approved by Ada Obi at '));

    expect(Activity::withAnyApprovalStatus()->find($this->activity->id)->decided_by_user_id)->toBe($this->first->id);
});

test('rejecting an activity that was just approved keeps the approval', function () {
    $this->actingAs($this->first)->post(route('activities.approve', $this->activity->id));

    $this->actingAs($this->second)->from($this->reviewUrl)
        ->post(route('activities.reject', $this->activity->id), ['reason' => 'Duplicate entry'])
        ->assertRedirect(route('activities.approvals'))
        ->assertSessionHas('warning', fn ($m) => str_starts_with($m, 'This activity has already been approved by Ada Obi'));

    $fresh = Activity::withAnyApprovalStatus()->find($this->activity->id);
    expect($fresh->approval_status)->toBe(Activity::APPROVED)
        ->and($fresh->rejection_reason)->toBeNull();
});

test('a double-click on approve tells the same reviewer they already approved it', function () {
    $this->actingAs($this->first)->post(route('activities.approve', $this->activity->id));

    $this->actingAs($this->first)->from($this->reviewUrl)
        ->post(route('activities.approve', $this->activity->id))
        ->assertRedirect(route('activities.approvals'))
        ->assertSessionHas('warning', fn ($m) => str_starts_with($m, 'You already approved this activity at '));
});

test('approving a withdrawn activity says it no longer exists', function () {
    $id = $this->activity->id;
    Activity::withAnyApprovalStatus()->whereKey($id)->delete();

    $this->actingAs($this->first)->post(route('activities.approve', $id))
        ->assertRedirect(route('activities.approvals'))
        ->assertSessionHas('warning', fn ($m) => str_contains($m, 'no longer exists'));
});

test('a stale in-memory record cannot be decided again once another reviewer decided it', function () {
    // Both reviewers loaded the record while it was still pending.
    $stale = Activity::withAnyApprovalStatus()->find($this->activity->id);
    Activity::withAnyApprovalStatus()->find($this->activity->id)->approve($this->first);

    expect(fn () => $stale->reject($this->second, 'Duplicate entry'))
        ->toThrow(RecordAlreadyDecided::class);

    $fresh = Activity::withAnyApprovalStatus()->find($this->activity->id);
    expect($fresh->approval_status)->toBe(Activity::APPROVED)
        ->and($fresh->decided_by_user_id)->toBe($this->first->id);
});
