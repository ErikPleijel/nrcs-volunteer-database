<?php

/**
 * Feature tests for photo and signature rejection ("upload again"):
 * - IdCardController::rejectImage() / restoreImage() set/clear
 *   users.{picture|signature}_rejected_at, _by_id, _rejection_reason and
 *   _rejection_note; the reason comes from a pick list and "Other" needs a
 *   note; no audit entry; 403 outside the admin's branch/division or
 *   without reject_images
 * - User::booted() clears the rejection whenever a new image is saved
 * - a rejection BLOCKS printing: no tick box, excluded from printable_only,
 *   skipped by print-bulk / record-bulk-prints, single print refused
 * - the banners on profile/show (members too) and users/show, with reason
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\IdCardPrint;
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

    $adminPermissions = ['manage-admin-panel', 'view_idcards', 'view_user', 'edit_user', 'reject_images'];
    foreach ([...$adminPermissions, 'print_idcards', 'request_idcard_print'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions([...$adminPermissions, 'print_idcards']);
    Role::findOrCreate('branch_db_administrator', 'web')->syncPermissions([...$adminPermissions, 'request_idcard_print']);
    Role::findOrCreate('division_db_assistant_operations', 'web')->syncPermissions([...$adminPermissions, 'request_idcard_print']);
    Role::findOrCreate('observer_national_level', 'web')->syncPermissions(['manage-admin-panel', 'view_idcards', 'view_user']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);
    $this->otherDivision = Division::create(['name' => 'Alpha Two', 'branch_id' => $this->branch->id]);
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

/**
 * Flag set in a second save: User::booted() clears it whenever the image
 * column is dirty, which includes the initial create.
 */
function markImageRejected(User $user, User $by, string $column = 'signature', ?string $reason = null, ?string $note = null): User
{
    $user->forceFill([
        $column.'_rejected_at' => now()->subDay(),
        $column.'_rejected_by_id' => $by->id,
        $column.'_rejection_reason' => $reason,
        $column.'_rejection_note' => $note,
    ])->save();

    return $user->fresh();
}

function bulkPrintHtml(User $as, array $query = []): string
{
    return test()->actingAs($as)
        ->get(route('id-cards.prepare-bulk-print', $query))
        ->assertOk()
        ->getContent();
}

/*
|--------------------------------------------------------------------------
| Reject / restore endpoints
|--------------------------------------------------------------------------
*/

test('rejecting a photo stores reason, note, who and when, writes no audit entry and returns JSON', function () {
    $this->freezeTime();
    $member = makeSignedMember($this->branch, $this->division);

    $response = $this->actingAs($this->admin)
        ->postJson(route('id-cards.reject-image', [$member, 'photo']), [
            'reason' => 'dark_or_blurry',
            'note' => '  taken at night  ',
        ])
        ->assertOk();

    // Everything the page needs to update the card in place.
    expect(array_keys($response->json()))->toEqualCanonicalizing([
        'rejected', 'type', 'reason', 'rejected_at', 'rejected_by', 'ready_mark_cancelled',
        'message', 'photo_rejected', 'signature_rejected', 'html',
    ]);
    $response->assertJson([
        'rejected' => true,
        'type' => 'photo',
        'reason' => 'Too dark or blurry — taken at night',
        'rejected_at' => now()->format('d M Y'),
        'rejected_by' => 'Ada Admin',
        'ready_mark_cancelled' => false,
        'photo_rejected' => true,
        'signature_rejected' => false,
    ]);
    expect($response->json('message'))->toContain('photo marked "upload again"');

    // The re-rendered review box: "Upload again", the reason, who/when, and Undo — no Reject button.
    expect($response->json('html'))
        ->toContain('data-image-review="photo-'.$member->id.'"')
        ->toContain('Upload again')
        ->toContain('Too dark or blurry — taken at night')
        ->toContain(now()->format('d M').' · Ada Admin')
        ->toContain('js-undo-form')
        ->toContain(route('id-cards.restore-image', [$member, 'photo']))
        ->not->toContain('js-reject-btn');

    $member->refresh();
    expect($member->picture_rejected_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($member->picture_rejected_by_id)->toBe($this->admin->id)
        ->and($member->picture_rejection_reason)->toBe('dark_or_blurry')
        ->and($member->picture_rejection_note)->toBe('taken at night')
        ->and($member->needsPictureReupload())->toBeTrue()
        ->and($member->needsSignatureReupload())->toBeFalse()
        ->and($member->picture)->toBe('pic.jpg'); // the image itself is untouched

    expect(AuditLog::where('action', 'like', '%rejected%')->exists())->toBeFalse();
});

test('rejecting a signature from the page form stores the reason and redirects back', function () {
    $member = makeSignedMember($this->branch, $this->division);

    $this->actingAs($this->admin)
        ->from(route('id-cards.prepare-bulk-print'))
        ->post(route('id-cards.reject-image', [$member, 'signature']), ['reason' => 'too_faint'])
        ->assertRedirect(route('id-cards.prepare-bulk-print'))
        ->assertSessionHas('success');

    $member->refresh();
    expect($member->signature_rejection_reason)->toBe('too_faint')
        ->and($member->signature_rejection_note)->toBeNull()
        ->and($member->signature_rejected_by_id)->toBe($this->admin->id);
});

test('a reason is required, must come from the type\'s list, and "Other" needs a note', function () {
    $member = makeSignedMember($this->branch, $this->division);
    $url = route('id-cards.reject-image', [$member, 'photo']);

    $this->actingAs($this->admin)->postJson($url, [])->assertJsonValidationErrors('reason');
    // A signature reason is not valid for a photo.
    $this->actingAs($this->admin)->postJson($url, ['reason' => 'too_faint'])->assertJsonValidationErrors('reason');
    // Retired reasons can no longer be picked.
    $this->actingAs($this->admin)->postJson($url, ['reason' => 'wrong_person'])->assertJsonValidationErrors('reason');
    $this->actingAs($this->admin)
        ->postJson(route('id-cards.reject-image', [$member, 'signature']), ['reason' => 'not_readable'])
        ->assertJsonValidationErrors('reason');
    $this->actingAs($this->admin)->postJson($url, ['reason' => 'other'])->assertJsonValidationErrors('note');
    $this->actingAs($this->admin)->postJson($url, ['reason' => 'other', 'note' => str_repeat('x', 501)])->assertJsonValidationErrors('note');

    expect($member->fresh()->picture_rejected_at)->toBeNull()
        ->and($member->fresh()->signature_rejected_at)->toBeNull();

    $this->actingAs($this->admin)->postJson($url, ['reason' => 'other', 'note' => 'Wearing sunglasses'])
        ->assertOk()
        ->assertJsonPath('reason', 'Wearing sunglasses');
});

test('a stored rejection with a retired or unknown reason still displays sensibly', function () {
    $wrongPerson = markImageRejected(makeSignedMember($this->branch, $this->division), $this->admin, 'picture', 'wrong_person');
    $oldSignature = markImageRejected(makeSignedMember($this->branch, $this->division), $this->admin, 'signature', 'too_small_or_cut_off', 'left edge missing');
    $unknown = markImageRejected(makeSignedMember($this->branch, $this->division), $this->admin, 'signature', 'smudged_ink');

    expect(\App\Support\ImageRejection::describe($wrongPerson, 'photo'))->toBe('Wrong person')
        ->and(\App\Support\ImageRejection::describe($oldSignature, 'signature'))->toBe('Too small or cut off — left edge missing')
        ->and(\App\Support\ImageRejection::describe($unknown, 'signature'))->toBe('Smudged ink')
        ->and(\App\Support\ImageRejection::label('photo', null))->toBeNull();

    $html = bulkPrintHtml($this->admin);
    expect($html)->toContain('Wrong person')
        ->toContain('Too small or cut off — left edge missing')
        ->toContain('Smudged ink');

    $this->actingAs($wrongPerson)->get(route('profile.show'))->assertOk()->assertSee('Wrong person');
    $this->actingAs($this->admin)->get(route('users.show', $unknown))->assertOk()->assertSee('Smudged ink');
});

test('a refused reject (outside the area) returns a JSON error and changes nothing', function () {
    $branchAdmin = User::factory()->create(['branch_id' => $this->otherBranch->id]);
    $branchAdmin->assignRole('branch_db_administrator');
    $member = makeSignedMember($this->branch, $this->division);

    $response = $this->actingAs($branchAdmin)
        ->postJson(route('id-cards.reject-image', [$member, 'photo']), ['reason' => 'face_not_visible'])
        ->assertForbidden();

    expect($response->json('message'))->toBe('This user is outside your branch or division.')
        ->and($member->fresh()->picture_rejected_at)->toBeNull();
});

test('an unknown image type is a 404', function () {
    $member = makeSignedMember($this->branch, $this->division);

    $this->actingAs($this->admin)
        ->postJson('/id-cards/'.$member->id.'/reject-image/passport', ['reason' => 'other', 'note' => 'x'])
        ->assertNotFound();
});

test('rejecting an image the user does not have returns 422 and sets nothing', function () {
    $member = makeSignedMember($this->branch, $this->division);
    $member->forceFill(['picture' => null])->save();

    $this->actingAs($this->admin)
        ->postJson(route('id-cards.reject-image', [$member, 'photo']), ['reason' => 'face_not_visible'])
        ->assertStatus(422);

    expect($member->fresh()->picture_rejected_at)->toBeNull();
});

test('restoring clears all rejection fields for that image only', function () {
    $member = makeSignedMember($this->branch, $this->division);
    $member = markImageRejected($member, $this->admin, 'picture', 'not_passport_style', 'cropped');
    $member = markImageRejected($member, $this->admin, 'signature', 'too_faint');

    $response = $this->actingAs($this->admin)
        ->deleteJson(route('id-cards.restore-image', [$member, 'photo']))
        ->assertOk()
        ->assertJson([
            'rejected' => false,
            'type' => 'photo',
            'photo_rejected' => false,
            'signature_rejected' => true, // the other image is still rejected
        ]);
    expect($response->json('message'))->toContain('Photo rejection undone')
        // The review box is back to a Reject button.
        ->and($response->json('html'))->toContain('js-reject-btn')
        ->toContain('Reject photo')
        ->not->toContain('Upload again');

    $member->refresh();
    expect($member->picture_rejected_at)->toBeNull()
        ->and($member->picture_rejected_by_id)->toBeNull()
        ->and($member->picture_rejection_reason)->toBeNull()
        ->and($member->picture_rejection_note)->toBeNull()
        ->and($member->needsSignatureReupload())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Permissions and geography
|--------------------------------------------------------------------------
*/

test('a branch admin gets 403 on reject and restore for a user in another branch', function () {
    $branchAdmin = User::factory()->create(['branch_id' => $this->otherBranch->id]);
    $branchAdmin->assignRole('branch_db_administrator');

    $member = makeSignedMember($this->branch, $this->division);

    $this->actingAs($branchAdmin)
        ->postJson(route('id-cards.reject-image', [$member, 'photo']), ['reason' => 'face_not_visible'])
        ->assertForbidden();
    expect($member->fresh()->picture_rejected_at)->toBeNull();

    $member = markImageRejected($member, $this->admin, 'signature');
    $this->actingAs($branchAdmin)->deleteJson(route('id-cards.restore-image', [$member, 'signature']))->assertForbidden();
    expect($member->fresh()->signature_rejected_at)->not->toBeNull();
});

test('a branch admin (who cannot print) can reject inside their own branch', function () {
    $branchAdmin = User::factory()->create(['branch_id' => $this->branch->id]);
    $branchAdmin->assignRole('branch_db_administrator');

    $member = makeSignedMember($this->branch, $this->division);

    $this->actingAs($branchAdmin)
        ->postJson(route('id-cards.reject-image', [$member, 'photo']), ['reason' => 'face_not_visible'])
        ->assertOk();
    expect($member->fresh()->picture_rejected_by_id)->toBe($branchAdmin->id);
});

test('a division assistant can reject only inside their own division', function () {
    $divisionAdmin = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);
    $divisionAdmin->assignRole('division_db_assistant_operations');

    $inside = makeSignedMember($this->branch, $this->division);
    $outside = makeSignedMember($this->branch, $this->otherDivision);

    $this->actingAs($divisionAdmin)
        ->postJson(route('id-cards.reject-image', [$inside, 'signature']), ['reason' => 'too_small'])
        ->assertOk();
    $this->actingAs($divisionAdmin)
        ->postJson(route('id-cards.reject-image', [$outside, 'signature']), ['reason' => 'too_small'])
        ->assertForbidden();
});

test('a role without reject_images (observer) cannot reject and sees no reject buttons', function () {
    $observer = User::factory()->create();
    $observer->assignRole('observer_national_level');

    $member = makeSignedMember($this->branch, $this->division);

    $this->actingAs($observer)
        ->postJson(route('id-cards.reject-image', [$member, 'photo']), ['reason' => 'face_not_visible'])
        ->assertForbidden();

    expect(bulkPrintHtml($observer))->not->toContain(route('id-cards.reject-image', [$member, 'photo']))
        ->not->toContain('Reject photo');
});

/*
|--------------------------------------------------------------------------
| Model hook — a new image clears the rejection on every upload path
|--------------------------------------------------------------------------
*/

test('uploading a new signature on My Profile clears the rejection and its reason', function () {
    $member = markImageRejected(makeSignedMember($this->branch, $this->division), $this->admin, 'signature', 'other', 'smudged');

    $this->actingAs($member)
        ->post(route('profile.update-signature'), ['signature_file' => UploadedFile::fake()->image('new-sig.png', 300, 100)])
        ->assertSessionHas('success');

    $member->refresh();
    expect($member->signature)->not->toBe('sig.jpg')
        ->and($member->signature_rejected_at)->toBeNull()
        ->and($member->signature_rejected_by_id)->toBeNull()
        ->and($member->signature_rejection_reason)->toBeNull()
        ->and($member->signature_rejection_note)->toBeNull();
});

test('an admin uploading a new signature via users/edit clears the rejection', function () {
    $member = markImageRejected(makeSignedMember($this->branch, $this->division), $this->admin);

    $this->actingAs($this->admin)
        ->post(route('users.update-signature', $member), ['signature_file' => UploadedFile::fake()->image('new-sig.png', 300, 100)])
        ->assertSessionHas('success');

    expect($member->fresh()->signature_rejected_at)->toBeNull();
});

test('uploading a new photo on My Profile clears the photo rejection', function () {
    $member = markImageRejected(makeSignedMember($this->branch, $this->division), $this->admin, 'picture', 'not_passport_style');

    $this->actingAs($member)
        ->post(route('profile.update-profile-picture'), ['picture' => UploadedFile::fake()->image('me.jpg', 300, 300)])
        ->assertSessionHas('success');

    $member->refresh();
    expect($member->picture)->not->toBe('pic.jpg')
        ->and($member->picture_rejected_at)->toBeNull()
        ->and($member->picture_rejection_reason)->toBeNull();
});

test('saving other fields leaves the rejections in place', function () {
    $member = markImageRejected(makeSignedMember($this->branch, $this->division), $this->admin, 'picture', 'not_passport_style');
    $member = markImageRejected($member, $this->admin);

    $member->update(['first_name' => 'Renamed']);

    $member->refresh();
    expect($member->needsPictureReupload())->toBeTrue()
        ->and($member->needsSignatureReupload())->toBeTrue()
        ->and($member->picture_rejection_reason)->toBe('not_passport_style');
});

/*
|--------------------------------------------------------------------------
| A rejection blocks printing
|--------------------------------------------------------------------------
*/

/**
 * The tick box's <input> tag for a user, or null. Rejected cards keep a
 * disabled, hidden tick box so Undo can re-enable it without a reload.
 */
function tickBoxTag(string $html, User $user): ?string
{
    return preg_match('/<input type="checkbox"\s+id="user-'.$user->id.'"[^>]*>/', $html, $m) ? $m[0] : null;
}

test('a rejected photo or signature disables the tick box and fails printable_only', function () {
    $ok = makeSignedMember($this->branch, $this->division);
    $photo = markImageRejected(makeSignedMember($this->branch, $this->division), $this->admin, 'picture');
    $signature = markImageRejected(makeSignedMember($this->branch, $this->division), $this->admin);

    $html = bulkPrintHtml($this->admin);
    expect(tickBoxTag($html, $ok))->not->toBeNull()->not->toContain('disabled')
        ->and(tickBoxTag($html, $photo))->toContain('disabled')->toContain('hidden')
        ->and(tickBoxTag($html, $signature))->toContain('disabled')->toContain('hidden')
        // the validity input is there for Undo, but hidden
        ->and($html)->toMatch('/class="flex-shrink-0 hidden" data-validity-block="'.$photo->id.'"/')
        ->and($html)->toMatch('/class="flex-shrink-0 " data-validity-block="'.$ok->id.'"/')
        // card state the page script reads
        ->and($html)->toMatch('/id="card-'.$photo->id.'"\s+data-missing="0"\s+data-photo-rejected="1"\s+data-signature-rejected="0"/');

    $html = bulkPrintHtml($this->admin, ['printable_only' => 1]);
    expect($html)->toContain('id="user-'.$ok->id.'"')
        ->and($html)->not->toContain('href="'.route('users.show', $photo).'"')
        ->and($html)->not->toContain('href="'.route('users.show', $signature).'"');
});

test('print-bulk and record-bulk-prints skip rejected cards; single print is refused', function () {
    $ok = makeSignedMember($this->branch, $this->division);
    $rejected = markImageRejected(makeSignedMember($this->branch, $this->division), $this->admin, 'picture');
    $ids = json_encode([['id' => $ok->id, 'validity' => ''], ['id' => $rejected->id, 'validity' => '']]);

    $html = $this->actingAs($this->admin)
        ->post(route('id-cards.print-bulk'), ['user_ids' => $ids])
        ->assertOk()
        ->getContent();
    expect($html)->toContain(strtoupper($ok->full_name))
        ->and($html)->not->toContain(strtoupper($rejected->full_name));

    $this->actingAs($this->admin)
        ->post(route('id-cards.print-bulk'), ['user_ids' => json_encode([['id' => $rejected->id]])])
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->actingAs($this->admin)
        ->post(route('id-cards.record-bulk-prints'), ['user_ids' => $ids])
        ->assertSessionHas('success', fn ($m) => str_contains($m, '1 skipped'));
    expect(IdCardPrint::where('user_id', $ok->id)->count())->toBe(1)
        ->and(IdCardPrint::where('user_id', $rejected->id)->count())->toBe(0);

    $this->actingAs($this->admin)->get(route('id-card.print', $rejected))->assertStatus(422);
});

test('the bulk-print card shows visible reject buttons, or "Upload again" with reason and undo', function () {
    $fresh = makeSignedMember($this->branch, $this->division);
    $rejected = markImageRejected(makeSignedMember($this->branch, $this->division), $this->admin, 'signature', 'background_not_white');

    $html = bulkPrintHtml($this->admin);

    expect($html)->toContain(route('id-cards.reject-image', [$fresh, 'photo']))
        ->and($html)->toContain(route('id-cards.reject-image', [$fresh, 'signature']))
        ->and($html)->toContain('Reject photo')
        ->and($html)->toContain('Reject signature')
        ->and($html)->toMatch('/id="signature-box-'.$rejected->id.'"\s+class="[^"]*border-2 border-amber-500/')
        ->and($html)->toContain('Upload again: <span data-upload-again-text>signature</span>')
        ->and($html)->toContain('Background is not plain white')
        ->and($html)->toContain(route('id-cards.restore-image', [$rejected, 'signature']))
        // the modal lists each type's current reasons, not the retired ones
        ->and($html)->toContain('Face not clearly visible')
        ->and($html)->toContain('Signature is too faint')
        ->and($html)->not->toContain('Wrong person');
});

test('the photo age indicator sits below the Reject photo button', function () {
    $member = makeSignedMember($this->branch, $this->division);
    $member->forceFill(['image_upload_date' => now()->subYears(2)])->save();

    $html = bulkPrintHtml($this->admin);

    // Within the photo column: photo, then the review box, then the age indicator.
    preg_match('/id="photo-box-'.$member->id.'".*?id="signature-box-'.$member->id.'"/s', $html, $m);
    $column = $m[0] ?? '';
    // <x-photo-age-indicator compact> renders a <p class="inline-flex items-center gap-1 ...">.
    $indicator = '<p class="inline-flex items-center gap-1';
    $reviewAt = strpos($column, 'data-image-review="photo-'.$member->id.'"');

    expect($reviewAt)->not->toBeFalse()
        ->and(substr_count($column, $indicator))->toBe(1)
        ->and(strpos($column, $indicator))->toBeGreaterThan($reviewAt);
});

/*
|--------------------------------------------------------------------------
| Banners
|--------------------------------------------------------------------------
*/

test('My Profile shows the signature banner with its reason, only when rejected', function () {
    $member = makeSignedMember($this->branch, $this->division);
    expect($member->isVolunteer())->toBeFalse();

    $this->actingAs($member)->get(route('profile.show'))
        ->assertOk()
        ->assertDontSee('Please upload a new signature');

    markImageRejected($member, $this->admin, 'signature', 'too_faint');

    $this->actingAs($member->fresh())->get(route('profile.show'))
        ->assertOk()
        ->assertSee('Please upload a new signature')
        ->assertSee('Signature is too faint')
        ->assertSee('Upload again')
        ->assertSee(route('profile.edit-signature'), false);
});

test('My Profile shows the photo banner with its reason, only when rejected', function () {
    $member = makeSignedMember($this->branch, $this->division);

    $this->actingAs($member)->get(route('profile.show'))
        ->assertOk()
        ->assertDontSee('Please upload a new photo');

    markImageRejected($member, $this->admin, 'picture', 'other', 'Wearing sunglasses');

    $this->actingAs($member->fresh())->get(route('profile.show'))
        ->assertOk()
        ->assertSee('Please upload a new photo')
        ->assertSee('Wearing sunglasses')
        ->assertSee(route('profile.edit-photo'), false);
});

test('users/show shows the Rejected banners with date, admin and reason, only when rejected', function () {
    $member = makeSignedMember($this->branch, $this->division);

    $this->actingAs($this->admin)->get(route('users.show', $member))
        ->assertOk()
        ->assertDontSee('Signature Rejected')
        ->assertDontSee('Photo Rejected');

    $member = markImageRejected($member, $this->admin, 'signature', 'too_small');
    $member = markImageRejected($member, $this->admin, 'picture', 'face_not_visible');

    $this->actingAs($this->admin)->get(route('users.show', $member))
        ->assertOk()
        ->assertSee('Signature Rejected')
        ->assertSee('Photo Rejected')
        ->assertSee('Rejected '.$member->signature_rejected_at->format('d M Y'))
        ->assertSee('by Ada Admin')
        ->assertSee('Signature is too small')
        ->assertSee('Face not clearly visible')
        ->assertSee(route('users.edit', $member), false);
});
