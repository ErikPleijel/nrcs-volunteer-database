<?php

/**
 * Feature tests for users/index's photo_signature_filter=sign_rejected option:
 * - the query (UserFilterService) returns only users whose signature was
 *   rejected on the ID card bulk-print page, and sign_yes still includes them
 *   (rejected is a subset of "has a signature", not an exclusion)
 * - the active-filter summary (UserFilterDescriber) label
 * - the ID Card Status view's Signature line shows "Rejected {d M Y}", not "OK"
 * - the same filter reused through UserFilterService for campaign audiences
 * Plus the hasSignature() guard on IdCardController::rejectSignature().
 */

use App\Models\User;
use App\Services\UserFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $permissions = ['manage-admin-panel', 'view_user', 'view_idcards', 'print_idcards'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('national_db_administrator');

    // Flag set in a second save: User::booted() clears it whenever `signature`
    // is dirty, which includes the initial create.
    $this->rejected = User::factory()->create(['first_name' => 'Rejectedsig', 'signature' => 'sig.jpg']);
    $this->rejected->forceFill(['signature_rejected_at' => now()->subDays(3), 'signature_rejected_by_id' => $this->admin->id])->save();
    $this->rejected->refresh();

    $this->signedOk = User::factory()->create(['first_name' => 'Goodsig', 'signature' => 'sig.jpg']);
    $this->unsigned = User::factory()->create(['first_name' => 'Nosig', 'signature' => null]);
});

test('sign_rejected returns only users with a rejected signature', function () {
    $this->actingAs($this->admin)
        ->get(route('users.index', ['photo_signature_filter' => 'sign_rejected']))
        ->assertOk()
        ->assertSee('Rejectedsig')
        ->assertDontSee('Goodsig')
        ->assertDontSee('Nosig');
});

test('sign_yes still includes users whose signature was rejected', function () {
    $this->actingAs($this->admin)
        ->get(route('users.index', ['photo_signature_filter' => 'sign_yes']))
        ->assertOk()
        ->assertSee('Rejectedsig')
        ->assertSee('Goodsig')
        ->assertDontSee('Nosig');
});

test('the dropdown offers the option and the active-filter summary reads "Signature rejected"', function () {
    $html = $this->actingAs($this->admin)
        ->get(route('users.index', ['photo_signature_filter' => 'sign_rejected']))
        ->assertOk()
        ->assertSee('<option value="sign_rejected" selected>Signature rejected</option>', false)
        ->getContent();

    // The results-summary line under "Found N result(s)".
    preg_match('/<div class="text-lg text-gray-700 font-medium">(.*?)<\/div>/s', $html, $m);

    expect(trim(strip_tags($m[1] ?? '')))->toContain('Signature rejected')
        ->not->toContain('Showing all users');
});

test('the ID Card Status view shows "Rejected {date}" instead of "OK" for a rejected signature', function () {
    $html = $this->actingAs($this->admin)
        ->get(route('users.index', ['view_mode' => 'id_cards', 'photo_signature_filter' => 'sign_rejected']))
        ->assertOk()
        ->getContent();

    $text = preg_replace('/\s+/', ' ', strip_tags($html));

    expect($text)->toContain('Signature: Rejected '.$this->rejected->signature_rejected_at->format('d M Y'))
        ->not->toContain('Signature: OK');
});

test('the same filter works for campaign audiences built via UserFilterService', function () {
    $ids = app(UserFilterService::class)
        ->apply(User::query(), ['photo_signature_filter' => 'sign_rejected'], 'national', null)
        ->pluck('id');

    expect($ids->all())->toBe([$this->rejected->id]);
});

test('rejecting a user who has no signature returns 422 and sets nothing', function () {
    $this->actingAs($this->admin)
        ->postJson(route('id-cards.reject-signature', $this->unsigned))
        ->assertStatus(422);

    expect($this->unsigned->fresh()->signature_rejected_at)->toBeNull();
});
