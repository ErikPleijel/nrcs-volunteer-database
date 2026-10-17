<?php

/**
 * /my-unit/report (ID Card Completeness):
 * - the "Upload again" tile counts the whole unit's members with a rejected
 *   photo or signature (each person once), and they are not "Complete"
 * - each card shows "Upload again: Photo/Signature"; the reason only for
 *   admins (reject_images), never for ordinary members
 * - 100 cards per page
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\RedCrossUnit;
use App\Models\User;
use Database\Factories\MembershipFeeFactory;
use Database\Factories\MembershipPaymentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    Permission::findOrCreate('reject_images', 'web');
    Role::findOrCreate('branch_db_administrator', 'web')->syncPermissions(['reject_images']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha One', 'branch_id' => $this->branch->id]);
    $this->unit = RedCrossUnit::create(['name' => 'Report Unit', 'division_id' => $this->division->id, 'is_active' => true]);
});

function reportMember(array $attributes = []): User
{
    $user = User::factory()->withNationalId()->create([
        'branch_id' => test()->branch->id,
        'division_id' => test()->division->id,
        'red_cross_unit_id' => test()->unit->id,
        'picture' => 'pic.jpg',
        'signature' => 'sig.jpg',
        ...$attributes,
    ]);

    MembershipPaymentFactory::new()->approved()->create([
        'user_id' => $user->id,
        'membership_fee_id' => MembershipFeeFactory::new()->create(['is_volunteer_fee' => false])->id,
        'payment_date' => now()->subMonth(),
        'expiry_date' => now()->addYear(),
    ]);

    return $user->fresh();
}

function reportHtml(User $viewer, array $query = []): string
{
    return test()->actingAs($viewer)
        ->get(route('red-cross-units.my-unit-report', $query))
        ->assertOk()
        ->getContent();
}

/** The number in a summary tile, found by its label. */
function reportTile(string $html, string $label): ?int
{
    return preg_match('/'.preg_quote($label, '/').'<\/p>\s*<p[^>]*>(\d+)<\/p>/', $html, $m) ? (int) $m[1] : null;
}

test('the Upload again tile counts each rejected person once and they are not complete', function () {
    $viewer = reportMember();
    reportMember();
    $both = reportMember();
    $both->forceFill(['picture_rejected_at' => now(), 'signature_rejected_at' => now()])->save();
    $photoOnly = reportMember();
    $photoOnly->forceFill(['picture_rejected_at' => now()])->save();

    $html = reportHtml($viewer);

    expect(reportTile($html, 'Total members'))->toBe(4)
        ->and(reportTile($html, 'Upload again'))->toBe(2)
        ->and(reportTile($html, 'Complete'))->toBe(2)
        ->and(reportTile($html, 'Incomplete'))->toBe(2);
});

/** The "Upload again" overlay element for one image, or null. */
function uploadAgainOverlay(string $html, string $type, User $user): ?string
{
    return preg_match('/<div data-upload-again-overlay="'.$type.'-'.$user->id.'".*?<\/div>/s', $html, $m) ? $m[0] : null;
}

test('a rejected photo and signature show an "Upload again" overlay inside the image area', function () {
    $viewer = reportMember();
    $clean = reportMember();
    $rejected = reportMember();
    $rejected->forceFill([
        'picture_rejected_at' => now(), 'picture_rejection_reason' => 'dark_or_blurry',
        'signature_rejected_at' => now(), 'signature_rejection_reason' => 'too_faint',
    ])->save();

    $html = reportHtml($viewer);

    expect(uploadAgainOverlay($html, 'photo', $rejected))->toContain('Upload again')
        ->and(uploadAgainOverlay($html, 'signature', $rejected))->toContain('Upload again')
        ->and(uploadAgainOverlay($html, 'photo', $clean))->toBeNull()
        ->and(uploadAgainOverlay($html, 'signature', $clean))->toBeNull()
        // the photo stays underneath (loaded by "Show photos"), so it can be seen faintly
        ->and($html)->toContain('data-src="'.$rejected->profile_photo_url.'"')
        // the old text line below the card is gone
        ->and($html)->not->toContain('Upload again: Photo')
        ->and($html)->not->toContain('Upload again: Signature');
});

test('a member sees only "Upload again"; an admin also sees the reason and the rejected signature', function () {
    $viewer = reportMember();
    $rejected = reportMember();
    $rejected->forceFill([
        'picture_rejected_at' => now(), 'picture_rejection_reason' => 'wrong_person', // retired reason
        'signature_rejected_at' => now(), 'signature_rejection_reason' => 'background_not_white',
    ])->save();

    $html = reportHtml($viewer);
    expect(uploadAgainOverlay($html, 'photo', $rejected))->not->toContain('Wrong person')
        ->and(uploadAgainOverlay($html, 'signature', $rejected))->not->toContain('Background is not plain white')
        ->and($html)->not->toContain('Wrong person')
        ->and($html)->not->toContain('Rejected')
        // members never load someone else's signature image
        ->and($html)->not->toContain($rejected->signature_url);

    $admin = reportMember();
    $admin->assignRole('branch_db_administrator');
    $html = reportHtml($admin);

    expect(uploadAgainOverlay($html, 'photo', $rejected))->toContain('Wrong person')
        ->and(uploadAgainOverlay($html, 'signature', $rejected))->toContain('Background is not plain white')
        ->and($html)->toContain('data-src="'.$rejected->signature_url.'"');
});

test('the report shows 100 cards per page', function () {
    $viewer = reportMember(['first_name' => 'Aaa']);
    foreach (range(1, 104) as $i) {
        User::factory()->create([
            'first_name' => 'Member',
            'last_name' => sprintf('%03d', $i),
            'branch_id' => $this->branch->id,
            'division_id' => $this->division->id,
            'red_cross_unit_id' => $this->unit->id,
        ]);
    }

    $page1 = reportHtml($viewer);
    expect($page1)->toContain('MEMBER') // sanity
        ->and(substr_count($page1, 'Surname:'))->toBe(100);

    $page2 = reportHtml($viewer, ['members_page' => 2]);
    expect(substr_count($page2, 'Surname:'))->toBe(5);
});
