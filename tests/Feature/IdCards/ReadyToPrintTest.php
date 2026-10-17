<?php

/**
 * "Mark as ready to print" (id_card_print_requests) and the two new bulk
 * print filters:
 * - staff with request_idcard_print (who cannot print) tick cards and mark
 *   them; only complete, unrejected, unmarked cards in their own scope
 * - printers do not get the button, and the route refuses them
 * - Mark as Printed closes the mark as 'printed' (linked to the print row);
 *   rejecting an image cancels it; markers and printers can remove it
 * - "ready" rows never land in id_card_prints
 * - filters: ready_to_print (pending marks) and has_rejection
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\IdCardPrint;
use App\Models\IdCardPrintRequest;
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

    $base = ['manage-admin-panel', 'view_idcards', 'view_user', 'reject_images'];
    foreach ([...$base, 'print_idcards', 'request_idcard_print'] as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions([...$base, 'print_idcards']);
    Role::findOrCreate('branch_db_administrator', 'web')->syncPermissions([...$base, 'request_idcard_print']);
    Role::findOrCreate('division_db_assistant_finance', 'web')->syncPermissions([...$base, 'request_idcard_print']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);
    $this->otherDivision = Division::create(['name' => 'Alpha Two', 'branch_id' => $this->branch->id]);
    $this->otherBranch = Branch::create(['name' => 'Beta Branch', 'code' => 'BET']);
    $this->otherBranchDivision = Division::create(['name' => 'Beta Division', 'branch_id' => $this->otherBranch->id]);

    $this->printer = User::factory()->create(['first_name' => 'Pat', 'last_name' => 'Printer']);
    $this->printer->assignRole('national_db_administrator');

    $this->marker = User::factory()->create(['first_name' => 'Bea', 'last_name' => 'Branch', 'branch_id' => $this->branch->id]);
    $this->marker->assignRole('branch_db_administrator');
});

/** A printable member: valid payment, photo, signature, national ID. */
function makeReadyCandidate(Branch $branch, Division $division, array $attributes = []): User
{
    $user = User::factory()->withNationalId()->create([
        'branch_id' => $branch->id,
        'division_id' => $division->id,
        'picture' => 'pic.jpg',
        'signature' => 'sig.jpg',
        ...$attributes,
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

function userIdsJson(User ...$users): string
{
    return json_encode(array_map(fn (User $u) => ['id' => $u->id, 'validity' => ''], $users));
}

function markReady(User $user, User $by): IdCardPrintRequest
{
    return IdCardPrintRequest::create([
        'user_id' => $user->id,
        'requested_by_user_id' => $by->id,
        'requested_at' => now()->subDay(),
        'status' => IdCardPrintRequest::STATUS_PENDING,
    ]);
}

/*
|--------------------------------------------------------------------------
| Marking
|--------------------------------------------------------------------------
*/

test('a branch admin marks complete cards as ready, recording who and when', function () {
    $this->freezeTime();
    $a = makeReadyCandidate($this->branch, $this->division);
    $b = makeReadyCandidate($this->branch, $this->otherDivision);

    $this->actingAs($this->marker)
        ->post(route('id-cards.mark-ready'), ['user_ids' => userIdsJson($a, $b)])
        ->assertRedirect()
        ->assertSessionHas('success', '2 cards marked as ready to print.');

    $requests = IdCardPrintRequest::orderBy('user_id')->get();
    expect($requests)->toHaveCount(2)
        ->and($requests->pluck('user_id')->all())->toBe([$a->id, $b->id])
        ->and($requests->every(fn ($r) => $r->status === 'pending'
            && $r->requested_by_user_id === $this->marker->id
            && $r->requested_at->toDateTimeString() === now()->toDateTimeString()))->toBeTrue();

    // Nothing is written to id_card_prints — a mark is not a print.
    expect(IdCardPrint::count())->toBe(0);
});

test('incomplete, rejected, already-marked and out-of-scope cards are skipped', function () {
    $ok = makeReadyCandidate($this->branch, $this->division);
    $incomplete = makeReadyCandidate($this->branch, $this->division, ['picture' => null]);
    $rejected = makeReadyCandidate($this->branch, $this->division);
    $rejected->forceFill(['signature_rejected_at' => now(), 'signature_rejection_reason' => 'not_readable'])->save();
    $alreadyMarked = makeReadyCandidate($this->branch, $this->division);
    markReady($alreadyMarked, $this->marker);
    $otherBranch = makeReadyCandidate($this->otherBranch, $this->otherBranchDivision);

    $this->actingAs($this->marker)
        ->post(route('id-cards.mark-ready'), ['user_ids' => userIdsJson($ok, $incomplete, $rejected, $alreadyMarked, $otherBranch)])
        ->assertSessionHas('success', fn ($m) => str_starts_with($m, '1 card marked as ready to print.') && str_contains($m, '4 skipped'));

    expect(IdCardPrintRequest::pending()->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$ok->id, $alreadyMarked->id])->sort()->values()->all())
        ->and(IdCardPrintRequest::where('user_id', $alreadyMarked->id)->count())->toBe(1);
});

test('a division assistant can mark only inside their division', function () {
    $divisionAdmin = User::factory()->create(['branch_id' => $this->branch->id, 'division_id' => $this->division->id]);
    $divisionAdmin->assignRole('division_db_assistant_finance');

    $inside = makeReadyCandidate($this->branch, $this->division);
    $outside = makeReadyCandidate($this->branch, $this->otherDivision);

    $this->actingAs($divisionAdmin)
        ->post(route('id-cards.mark-ready'), ['user_ids' => userIdsJson($inside, $outside)]);

    expect(IdCardPrintRequest::pluck('user_id')->all())->toBe([$inside->id]);
});

test('a printer (no request_idcard_print) cannot use the mark-ready route or see its button', function () {
    $member = makeReadyCandidate($this->branch, $this->division);

    $this->actingAs($this->printer)
        ->post(route('id-cards.mark-ready'), ['user_ids' => userIdsJson($member)])
        ->assertForbidden();

    $html = $this->actingAs($this->printer)->get(route('id-cards.prepare-bulk-print'))->assertOk()->getContent();
    expect($html)->not->toContain('id="mark-ready-btn"')
        ->toContain('id="print-selected-btn"');
});

test('a marker sees tick boxes and the Mark as ready button, but no print buttons', function () {
    $member = makeReadyCandidate($this->branch, $this->division);
    $marked = makeReadyCandidate($this->branch, $this->division);
    markReady($marked, $this->marker);

    $html = $this->actingAs($this->marker)->get(route('id-cards.prepare-bulk-print'))->assertOk()->getContent();

    expect($html)->toContain('id="mark-ready-btn"')
        ->toContain('Mark as ready to print')
        ->toContain('id="user-'.$member->id.'"')
        ->not->toContain('id="user-'.$marked->id.'"') // already marked: badge instead of tick box
        ->toContain('data-ready-to-print="'.$marked->id.'"')
        ->toContain('by Bea Branch')
        ->not->toContain('id="print-selected-btn"')
        ->not->toContain('id="record-prints-btn"');
});

/*
|--------------------------------------------------------------------------
| Closing a mark
|--------------------------------------------------------------------------
*/

test('Mark as Printed closes the mark as printed and links the print record', function () {
    $member = makeReadyCandidate($this->branch, $this->division);
    $request = markReady($member, $this->marker);

    $this->actingAs($this->printer)
        ->post(route('id-cards.record-bulk-prints'), ['user_ids' => userIdsJson($member)])
        ->assertSessionHas('success');

    $request->refresh();
    $print = IdCardPrint::where('user_id', $member->id)->sole();
    expect($request->status)->toBe('printed')
        ->and($request->id_card_print_id)->toBe($print->id)
        ->and($request->closed_by_user_id)->toBe($this->printer->id)
        ->and($request->closed_at)->not->toBeNull();
});

test('rejecting an image cancels the mark', function () {
    $member = makeReadyCandidate($this->branch, $this->division);
    $request = markReady($member, $this->marker);

    $this->actingAs($this->marker)
        ->postJson(route('id-cards.reject-image', [$member, 'photo']), ['reason' => 'dark_or_blurry'])
        ->assertOk();

    $request->refresh();
    expect($request->status)->toBe('cancelled')
        ->and($request->cancel_reason)->toBe('image_rejected')
        ->and($request->closed_by_user_id)->toBe($this->marker->id);
});

test('a marker and a printer can remove a mark; outside the branch is 403', function () {
    $member = makeReadyCandidate($this->branch, $this->division);

    $first = markReady($member, $this->marker);
    $this->actingAs($this->marker)
        ->delete(route('id-cards.unmark-ready', $member))
        ->assertSessionHas('success');
    expect($first->fresh()->status)->toBe('cancelled')
        ->and($first->fresh()->cancel_reason)->toBe('removed');

    $second = markReady($member, $this->marker);
    $this->actingAs($this->printer)->delete(route('id-cards.unmark-ready', $member))->assertSessionHas('success');
    expect($second->fresh()->status)->toBe('cancelled');

    $otherAdmin = User::factory()->create(['branch_id' => $this->otherBranch->id]);
    $otherAdmin->assignRole('branch_db_administrator');
    $third = markReady($member, $this->marker);
    $this->actingAs($otherAdmin)->delete(route('id-cards.unmark-ready', $member))->assertForbidden();
    expect($third->fresh()->status)->toBe('pending');
});

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

test('the "Marked for printing" filter shows only cards with a pending mark', function () {
    $pending = makeReadyCandidate($this->branch, $this->division);
    markReady($pending, $this->marker);
    $printed = makeReadyCandidate($this->branch, $this->division);
    markReady($printed, $this->marker)->update(['status' => 'printed']);
    $none = makeReadyCandidate($this->branch, $this->division);

    $html = $this->actingAs($this->printer)
        ->get(route('id-cards.prepare-bulk-print', ['ready_to_print' => 1]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('href="'.route('users.show', $pending).'"')
        ->not->toContain('href="'.route('users.show', $printed).'"')
        ->not->toContain('href="'.route('users.show', $none).'"')
        ->toContain('id="user-'.$pending->id.'"'); // the printer can tick it
});

test('the "Has rejection" filter shows only users with a rejected photo or signature', function () {
    $photo = makeReadyCandidate($this->branch, $this->division);
    $photo->forceFill(['picture_rejected_at' => now()])->save();
    $signature = makeReadyCandidate($this->branch, $this->division);
    $signature->forceFill(['signature_rejected_at' => now()])->save();
    $clean = makeReadyCandidate($this->branch, $this->division);

    $html = $this->actingAs($this->printer)
        ->get(route('id-cards.prepare-bulk-print', ['has_rejection' => 1]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('href="'.route('users.show', $photo).'"')
        ->toContain('href="'.route('users.show', $signature).'"')
        ->not->toContain('href="'.route('users.show', $clean).'"');
});

test('the bulk print page runs no membership query per card', function () {
    foreach (range(1, 6) as $i) {
        makeReadyCandidate($this->branch, $this->division);
    }

    $count = function () {
        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $this->actingAs($this->printer)->get(route('id-cards.prepare-bulk-print'))->assertOk();
        $queries = collect(\Illuminate\Support\Facades\DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'from `membership_payments`'))
            ->count();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        return $queries;
    };

    $withSix = $count();
    foreach (range(1, 6) as $i) {
        makeReadyCandidate($this->branch, $this->division);
    }

    expect($count())->toBe($withSix);
});
