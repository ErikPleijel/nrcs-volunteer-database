<?php

/**
 * Feature tests for the warn-only "signature needs re-upload" flag:
 * - IdCardController::rejectSignature() / restoreSignature() set/clear
 *   users.signature_rejected_at + signature_rejected_by_id, write audit
 *   entries, return JSON, and 403 outside the admin's branch/division
 * - User::booted() clears the flag whenever a new signature is saved, on
 *   both upload paths (own profile, admin users/edit)
 * - a rejected signature does NOT make the card non-printable (checkbox and
 *   printable_only filter are unchanged)
 * - the warning banners on profile/show (members too) and users/show
 *
 * The "untick the checkbox on reject" behaviour is client-side JS and is
 * verified manually in the browser, not here.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\Log as AuditLog;
use App\Models\User;
use Database\Factories\MembershipFeeFactory;
use Database\Factories\MembershipPaymentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');

    $permissions = ['manage-admin-panel', 'view_idcards', 'print_idcards', 'view_user', 'edit_user'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($permissions);
    Role::findOrCreate('branch_db_administrator', 'web')->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);
    $this->otherBranch = Branch::create(['name' => 'Beta Branch', 'code' => 'BET']);

    $this->admin = User::factory()->create(['first_name' => 'Ada', 'last_name' => 'Admin']);
    $this->admin->assignRole('national_db_administrator');
});

/** A printable member (valid payment, photo, signature, national ID). */
function makeSignedMember(Branch $branch, Division $division): User
{
    $user = User::factory()->withNationalId()->create([
        'branch_id' => $branch->id,
        'division_id' => $division->id,
        'picture' => 'pic.jpg',
        'signature' => 'sig.jpg',
    ]);

    MembershipPaymentFactory::new()->approved()->create([
        'user_id' => $user->id,
        'membership_fee_id' => MembershipFeeFactory::new()->create(['is_volunteer_fee' => false])->id,
        'payment_date' => now()->subMonth(),
        'expiry_date' => now()->addYear(),
        'id_card_included' => true,
    ]);

    return $user->fresh();
}

function markSignatureRejected(User $user, User $by): User
{
    $user->signature_rejected_at = now()->subDay();
    $user->signature_rejected_by_id = $by->id;
    $user->save();

    return $user->fresh();
}

/*
|--------------------------------------------------------------------------
| Reject / restore endpoints
|--------------------------------------------------------------------------
*/

test('rejecting a signature sets both fields, writes an audit entry and returns JSON', function () {
    $this->freezeTime();
    $member = makeSignedMember($this->branch, $this->division);

    $this->actingAs($this->admin)
        ->postJson(route('id-cards.reject-signature', $member))
        ->assertOk()
        ->assertExactJson([
            'rejected' => true,
            'rejected_at' => now()->format('d M Y'),
            'rejected_by' => 'Ada Admin',
        ]);

    $member->refresh();
    expect($member->signature_rejected_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($member->signature_rejected_by_id)->toBe($this->admin->id)
        ->and($member->needsSignatureReupload())->toBeTrue()
        ->and($member->signature)->toBe('sig.jpg'); // the image itself is untouched

    expect(AuditLog::where('action', 'signature_rejected')->where('subject_id', $member->id)->exists())->toBeTrue();
});

test('restoring clears both fields, writes its own audit entry and returns JSON', function () {
    $member = markSignatureRejected(makeSignedMember($this->branch, $this->division), $this->admin);

    $this->actingAs($this->admin)
        ->deleteJson(route('id-cards.restore-signature', $member))
        ->assertOk()
        ->assertExactJson(['rejected' => false]);

    $member->refresh();
    expect($member->signature_rejected_at)->toBeNull()
        ->and($member->signature_rejected_by_id)->toBeNull();

    expect(AuditLog::where('action', 'signature_reupload_undone')->where('subject_id', $member->id)->exists())->toBeTrue();
});

test('a branch admin gets 403 on reject and restore for a user in another branch', function () {
    $branchAdmin = User::factory()->create(['branch_id' => $this->otherBranch->id]);
    $branchAdmin->assignRole('branch_db_administrator');

    $member = makeSignedMember($this->branch, $this->division);

    $this->actingAs($branchAdmin)->postJson(route('id-cards.reject-signature', $member))->assertForbidden();
    expect($member->fresh()->signature_rejected_at)->toBeNull();

    $member = markSignatureRejected($member, $this->admin);
    $this->actingAs($branchAdmin)->deleteJson(route('id-cards.restore-signature', $member))->assertForbidden();
    expect($member->fresh()->signature_rejected_at)->not->toBeNull();
});

test('a branch admin can reject a signature inside their own branch', function () {
    $branchAdmin = User::factory()->create(['branch_id' => $this->branch->id]);
    $branchAdmin->assignRole('branch_db_administrator');

    $member = makeSignedMember($this->branch, $this->division);

    $this->actingAs($branchAdmin)->postJson(route('id-cards.reject-signature', $member))->assertOk();
    expect($member->fresh()->signature_rejected_by_id)->toBe($branchAdmin->id);
});

/*
|--------------------------------------------------------------------------
| Model hook — a new signature clears the flag on every upload path
|--------------------------------------------------------------------------
*/

test('uploading a new signature on My Profile clears the rejection', function () {
    $member = markSignatureRejected(makeSignedMember($this->branch, $this->division), $this->admin);

    $this->actingAs($member)
        ->post(route('profile.update-signature'), ['signature_file' => UploadedFile::fake()->image('new-sig.png', 300, 100)])
        ->assertSessionHas('success');

    $member->refresh();
    expect($member->signature)->not->toBe('sig.jpg')
        ->and($member->signature_rejected_at)->toBeNull()
        ->and($member->signature_rejected_by_id)->toBeNull();
});

test('an admin uploading a new signature via users/edit clears the rejection', function () {
    $member = markSignatureRejected(makeSignedMember($this->branch, $this->division), $this->admin);

    $this->actingAs($this->admin)
        ->post(route('users.update-signature', $member), ['signature_file' => UploadedFile::fake()->image('new-sig.png', 300, 100)])
        ->assertSessionHas('success');

    $member->refresh();
    expect($member->signature)->not->toBe('sig.jpg')
        ->and($member->signature_rejected_at)->toBeNull()
        ->and($member->signature_rejected_by_id)->toBeNull();
});

test('saving other fields leaves the rejection in place', function () {
    $member = markSignatureRejected(makeSignedMember($this->branch, $this->division), $this->admin);

    $member->update(['first_name' => 'Renamed']);

    expect($member->fresh()->needsSignatureReupload())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Warn-only: printing is not blocked
|--------------------------------------------------------------------------
*/

test('a rejected signature still gets a checkbox and still passes printable_only', function () {
    $member = markSignatureRejected(makeSignedMember($this->branch, $this->division), $this->admin);

    foreach ([[], ['printable_only' => 1]] as $query) {
        $html = $this->actingAs($this->admin)
            ->get(route('id-cards.prepare-bulk-print', $query))
            ->assertOk()
            ->getContent();

        expect($html)->toContain('id="user-'.$member->id.'"')     // still selectable
            ->toContain('id="validity-'.$member->id.'"');          // still treated as printable
    }
});

test('the bulk-print card shows the reject control, or the rejected state with undo', function () {
    $fresh = makeSignedMember($this->branch, $this->division);
    $rejected = markSignatureRejected(makeSignedMember($this->branch, $this->division), $this->admin);

    $html = $this->actingAs($this->admin)
        ->get(route('id-cards.prepare-bulk-print'))
        ->assertOk()
        ->getContent();

    // Rejected card: red-bordered signature box, visible "Rejected {d M}" label.
    expect($html)->toMatch('/id="signature-box-'.$rejected->id.'"\s+class="[^"]*border-2 border-red-500/')
        ->and($html)->toContain('Rejected <span class="sig-rejected-date">'.$rejected->signature_rejected_at->format('d M').'</span>')
        ->and($html)->toMatch('/id="signature-box-'.$fresh->id.'"\s+class="[^"]*border border-gray-200/')
        ->and($html)->toContain(route('id-cards.reject-signature', $fresh));
});

/*
|--------------------------------------------------------------------------
| Banners
|--------------------------------------------------------------------------
*/

test('My Profile shows the re-upload banner for a member, only when rejected', function () {
    $member = makeSignedMember($this->branch, $this->division);
    expect($member->isVolunteer())->toBeFalse();

    $this->actingAs($member)->get(route('profile.show'))
        ->assertOk()
        ->assertDontSee('Please upload a new signature');

    markSignatureRejected($member, $this->admin);

    $this->actingAs($member->fresh())->get(route('profile.show'))
        ->assertOk()
        ->assertSee('Please upload a new signature')
        ->assertSee(route('profile.edit-signature'), false);
});

test('users/show shows the Signature Rejected banner with date and admin, only when rejected', function () {
    $member = makeSignedMember($this->branch, $this->division);

    $this->actingAs($this->admin)->get(route('users.show', $member))
        ->assertOk()
        ->assertDontSee('Signature Rejected');

    $member = markSignatureRejected($member, $this->admin);

    $this->actingAs($this->admin)->get(route('users.show', $member))
        ->assertOk()
        ->assertSee('Signature Rejected')
        ->assertSee('Rejected '.$member->signature_rejected_at->format('d M Y'))
        ->assertSee('by Ada Admin')
        ->assertSee(route('users.edit', $member), false);
});
