<?php

/**
 * UserAnonymizer (2026-10-08): permanently removes everything that identifies
 * an archived person, keeping gender, branch/division/unit, the decade of
 * birth and the statistical/accounting rows.
 */

use App\Models\Branch;
use App\Models\Division;
use App\Models\Donation;
use App\Models\Log as AuditLog;
use App\Models\MembershipPayment;
use App\Models\RedCrossUnit;
use App\Models\User;
use App\Services\UserAnonymizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    $this->branch = Branch::create(['name' => 'Alpha Branch', 'code' => 'ALP']);
    $this->division = Division::create(['name' => 'Alpha Division', 'branch_id' => $this->branch->id]);
    $this->unit = RedCrossUnit::create(['name' => 'Unit A', 'division_id' => $this->division->id, 'is_active' => true]);
});

/** A fully filled-in archived person. */
function anonymizeTarget(array $overrides = []): User
{
    $user = User::factory()->withNationalId()->create([
        'first_name' => 'Amina',
        'middle_name' => 'Zainab',
        'last_name' => 'Bello',
        'title' => 'Mrs',
        'gender' => 'female',
        'birth_year' => 1984,
        'marital_status' => 'married',
        'email' => 'amina.bello@example.org',
        'user_code' => '123456',
        'telephone1' => '08031234567',
        'telephone2' => '08037654321',
        'red_cross_id_number' => 'RC-77',
        'organisation' => 'Bello Trading',
        'occupation' => 'Nurse',
        'disciplin' => 'Nursing',
        'residential_address' => '12 Palm Street, Abuja',
        'workplace_address' => '3 Clinic Road, Abuja',
        'is_public_contact' => true,
        'public_contact_position' => 'Volunteer lead',
        'picture' => 'upload_1_pic.jpg',
        'signature' => 'upload_1_sig.png',
        'image_upload_date' => '2025-01-01',
        'consent_notes' => 'verbal consent, Amina Bello',
        'legacy_password_hash' => md5('secret'),
        'legacy_role' => 'Volunteer',
        'branch_id' => test()->branch->id,
        'division_id' => test()->division->id,
        'red_cross_unit_id' => test()->unit->id,
        'can_contribute_volunteering' => true,
        'lifecycle_status' => 'active',
        ...$overrides,
    ]);
    $user->markArchived(null)->save();

    return $user->fresh();
}

function putImageFiles(): void
{
    foreach (['profile/original/upload_1_pic.jpg', 'profile/web/upload_1_pic.jpg',
        'signatures/original/upload_1_sig.png', 'signatures/web/upload_1_sig.png'] as $path) {
        Storage::disk('local')->put("photos/{$path}", 'x');
    }
}

test('personal fields are cleared and the statistical ones kept', function () {
    $user = anonymizeTarget();
    $createdAt = $user->created_at->toDateTimeString();
    $archivedAt = $user->archived_at->toDateTimeString();

    app(UserAnonymizer::class)->anonymize($user, null, 'scheduled');

    $row = (array) DB::table('users')->where('id', $user->id)->first();

    expect($row['first_name'])->toBe('Anonymized')
        ->and($row['last_name'])->toBe('Member')
        ->and($row['password'])->toBe('')
        ->and($row['is_public_contact'])->toBe(0)
        ->and($row['anonymized_at'])->not->toBeNull()
        ->and($row['anonymized_by_id'])->toBeNull();

    foreach (['middle_name', 'title', 'email', 'email_verified_at', 'user_code', 'telephone1', 'telephone2',
        'national_id_number', 'national_id_number_hash', 'red_cross_id_number', 'marital_status',
        'disciplin', 'occupation', 'personal_info', 'organisation', 'residential_address',
        'workplace_address', 'public_contact_position', 'picture', 'signature', 'image_upload_date',
        'consent_notes', 'form_reg_id', 'legacy_password_hash', 'legacy_role', 'remember_token',
        'id_check_token'] as $column) {
        expect($row[$column])->toBeNull("{$column} should be null");
    }

    // Kept.
    expect($row['gender'])->toBe('female')
        ->and($row['birth_year'])->toBe(1980)
        ->and($row['branch_id'])->toBe($this->branch->id)
        ->and($row['division_id'])->toBe($this->division->id)
        ->and($row['red_cross_unit_id'])->toBe($this->unit->id)
        ->and($row['can_contribute_volunteering'])->toBe(1)
        ->and($row['lifecycle_status'])->toBe('archived')
        ->and($row['created_at'])->toBe($createdAt)
        ->and($row['archived_at'])->toBe($archivedAt);
});

test('direct permissions are removed', function () {
    $user = anonymizeTarget();
    $user->givePermissionTo(Permission::findOrCreate('view_user', 'web'));

    app(UserAnonymizer::class)->anonymize($user, null, 'scheduled');

    expect(DB::table('model_has_permissions')->where('model_id', $user->id)->exists())->toBeFalse()
        ->and(DB::table('model_has_roles')->where('model_id', $user->id)->exists())->toBeFalse();
});

test('pointers to the person are cleared', function () {
    $user = anonymizeTarget();
    $this->branch->forceFill(['public_contact_user_id_2' => $user->id, 'public_contact_position_2' => 'Secretary'])->save();
    $this->unit->update(['team_leader_user_id' => $user->id, 'assistant_team_leader_user_id' => $user->id]);
    $typeId = DB::table('task_force_types')->insertGetId(['name' => 'ERT', 'level' => 1, 'include_in_list' => 1]);
    $taskForceId = DB::table('task_forces')->insertGetId([
        'name' => 'ERT Alpha', 'task_force_type_id' => $typeId, 'branch_id' => $this->branch->id,
        'team_leader_user_id' => $user->id, 'assist_team_leader_user_id' => $user->id,
    ]);

    app(UserAnonymizer::class)->anonymize($user, null, 'scheduled');

    $branch = DB::table('branches')->where('id', $this->branch->id)->first();
    $unit = DB::table('red_cross_units')->where('id', $this->unit->id)->first();
    $taskForce = DB::table('task_forces')->where('id', $taskForceId)->first();

    expect($branch->public_contact_user_id_2)->toBeNull()
        ->and($branch->public_contact_position_2)->toBeNull()
        ->and($unit->team_leader_user_id)->toBeNull()
        ->and($unit->assistant_team_leader_user_id)->toBeNull()
        ->and($taskForce->team_leader_user_id)->toBeNull()
        ->and($taskForce->assist_team_leader_user_id)->toBeNull();
});

test('sessions, password reset tokens and notifications are deleted', function () {
    $user = anonymizeTarget();
    $other = User::factory()->create();

    DB::table('sessions')->insert([
        ['id' => 'sess-a', 'user_id' => $user->id, 'ip_address' => '10.0.0.1', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()],
        ['id' => 'sess-b', 'user_id' => $other->id, 'ip_address' => '10.0.0.2', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()],
    ]);
    DB::table('password_reset_tokens')->insert(['email' => 'amina.bello@example.org', 'token' => 'x', 'created_at' => now()]);
    foreach ([$user, $other] as $notifiable) {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'type' => 'Test', 'notifiable_type' => User::class,
            'notifiable_id' => $notifiable->id, 'data' => '{}',
        ]);
    }

    app(UserAnonymizer::class)->anonymize($user, null, 'scheduled');

    expect(DB::table('sessions')->pluck('id')->all())->toBe(['sess-b'])
        ->and(DB::table('password_reset_tokens')->count())->toBe(0)
        ->and(DB::table('notifications')->pluck('notifiable_id')->all())->toBe([$other->id]);
});

test('campaign recipient copies and payment payloads are scrubbed, amounts and references kept', function () {
    $user = anonymizeTarget();

    $campaignId = DB::table('messaging_campaigns')->insertGetId([
        'channel' => 'sms', 'audience_type' => 'filter', 'body' => 'Hi', 'filter_json' => '{}', 'created_by' => $user->id,
    ]);
    $recipientId = DB::table('messaging_recipients')->insertGetId([
        'messaging_campaign_id' => $campaignId, 'recipient_type' => User::class, 'recipient_id' => $user->id,
        'email' => 'amina.bello@example.org', 'phone' => '+2348031234567', 'payload_json' => '{"user.first_name":"Amina"}',
        'status' => 'sent',
    ]);

    $gateway = [
        'reference' => 'PSK-1', 'amount' => 500000, 'status' => 'success', 'paid_at' => '2025-03-01T10:00:00Z',
        'ip_address' => '10.0.0.9',
        'customer' => ['email' => 'amina.bello@example.org', 'first_name' => 'Amina', 'phone' => '0803'],
        'authorization' => ['last4' => '4081', 'bank' => 'TEST BANK', 'account_name' => 'AMINA BELLO'],
        'metadata' => ['payment_type' => 'membership', 'custom_fields' => [['value' => 'Amina']]],
    ];
    $payment = MembershipPayment::factory()->create(['user_id' => $user->id]);
    DB::table('membership_payments')->where('id', $payment->id)->update(['gateway_response' => json_encode($gateway)]);
    $donation = Donation::factory()->create(['user_id' => $user->id]);
    DB::table('donations')->where('id', $donation->id)->update([
        'gateway_response' => json_encode($gateway), 'submission_name' => 'Amina Bello',
    ]);
    $transactionId = DB::table('payment_transactions')->insertGetId([
        'user_id' => $user->id, 'payable_type' => 'membership_payment', 'reference' => 'PSK-1', 'amount' => 500000,
        'status' => 'success', 'meta' => json_encode(['payment_type' => 'membership', 'email' => 'amina.bello@example.org']),
        'raw_payload' => json_encode($gateway),
    ]);

    app(UserAnonymizer::class)->anonymize($user, null, 'scheduled');

    $recipient = DB::table('messaging_recipients')->where('id', $recipientId)->first();
    expect($recipient->email)->toBeNull()
        ->and($recipient->phone)->toBeNull()
        ->and($recipient->payload_json)->toBeNull()
        ->and($recipient->status)->toBe('sent');

    $expected = [
        'reference' => 'PSK-1', 'amount' => 500000, 'status' => 'success', 'paid_at' => '2025-03-01T10:00:00Z',
        'authorization' => ['bank' => 'TEST BANK'],
        'metadata' => ['payment_type' => 'membership'],
    ];
    expect(json_decode(DB::table('membership_payments')->where('id', $payment->id)->value('gateway_response'), true))->toBe($expected)
        ->and(json_decode(DB::table('donations')->where('id', $donation->id)->value('gateway_response'), true))->toBe($expected)
        ->and(DB::table('donations')->where('id', $donation->id)->value('submission_name'))->toBeNull();

    $transaction = DB::table('payment_transactions')->where('id', $transactionId)->first();
    expect(json_decode($transaction->meta, true))->toBe(['payment_type' => 'membership'])
        ->and(json_decode($transaction->raw_payload, true))->toBe($expected)
        ->and($transaction->amount)->toBe(500000)
        ->and($transaction->reference)->toBe('PSK-1');

    // The payment rows themselves stay, still linked to the account.
    expect(DB::table('membership_payments')->where('id', $payment->id)->value('user_id'))->toBe($user->id);
});

test('audit rows about the person are scrubbed; another person with the same name is untouched', function () {
    $user = anonymizeTarget();
    $namesake = User::factory()->create(['first_name' => 'Amina', 'last_name' => 'Bello']);
    $staff = User::factory()->create();

    $insert = fn (array $row) => DB::table('logs')->insertGetId([
        'action' => 'test', 'created_at' => now(), 'updated_at' => now(), ...$row,
    ]);

    $about = $insert([
        'subject_type' => User::class, 'subject_id' => $user->id,
        'description' => "Amina Bello (DB-{$user->id}) moved; called Amina Bello on 0803.",
        'old_values' => json_encode(['branch_id' => 1, 'telephone1' => '08031234567', 'nested' => ['email' => 'a@b.c', 'name' => 'Amina Bello']]),
        'new_values' => json_encode(['branch_id' => 2, 'birth_year' => 1984]),
    ]);
    $mentions = $insert([
        'subject_type' => User::class, 'subject_id' => $staff->id,
        'description' => "Payment for Amina Bello (DB-{$user->id}) removed.",
    ]);
    $namesakeRow = $insert([
        'subject_type' => User::class, 'subject_id' => $namesake->id,
        'description' => "Amina Bello (DB-{$namesake->id}) archived their own account.",
        'new_values' => json_encode(['email' => 'other@example.org']),
    ]);
    $actorRow = $insert([
        'user_id' => $user->id, 'subject_type' => User::class, 'subject_id' => $staff->id,
        'description' => "DB-{$staff->id} edited.",
    ]);
    // DB-{id} followed by another digit is a different account.
    $longerId = $insert(['description' => "Amina Bello (DB-{$user->id}9) edited."]);

    app(UserAnonymizer::class)->anonymize($user, null, 'scheduled');

    $aboutRow = DB::table('logs')->where('id', $about)->first();
    expect($aboutRow->description)->toBe("DB-{$user->id} moved; called DB-{$user->id} on 0803.")
        ->and(json_decode($aboutRow->old_values, true))->toBe(['branch_id' => 1, 'nested' => []])
        ->and(json_decode($aboutRow->new_values, true))->toBe(['branch_id' => 2]);

    expect(DB::table('logs')->where('id', $mentions)->value('description'))->toBe("Payment for DB-{$user->id} removed.")
        ->and(DB::table('logs')->where('id', $namesakeRow)->value('description'))->toBe("Amina Bello (DB-{$namesake->id}) archived their own account.")
        ->and(json_decode(DB::table('logs')->where('id', $namesakeRow)->value('new_values'), true))->toBe(['email' => 'other@example.org'])
        ->and(DB::table('logs')->where('id', $actorRow)->value('user_id'))->toBe($user->id)
        ->and(DB::table('logs')->where('id', $longerId)->value('description'))->toBe("Amina Bello (DB-{$user->id}9) edited.");
});

test('photo and signature files are deleted, and a missing file is not an error', function () {
    $user = anonymizeTarget();
    putImageFiles();
    Storage::disk('local')->delete('photos/signatures/web/upload_1_sig.png');

    app(UserAnonymizer::class)->anonymize($user, null, 'scheduled');

    foreach (['profile/original/upload_1_pic.jpg', 'profile/web/upload_1_pic.jpg',
        'signatures/original/upload_1_sig.png', 'signatures/web/upload_1_sig.png'] as $path) {
        Storage::disk('local')->assertMissing("photos/{$path}");
    }
});

test('the user_anonymized entry records the trigger and the actor, and no personal data', function () {
    $user = anonymizeTarget();
    $admin = User::factory()->create();
    $this->actingAs($admin);

    app(UserAnonymizer::class)->anonymize($user, $admin, 'manual');

    $log = AuditLog::where('action', 'user_anonymized')->sole();
    expect($log->subject_id)->toBe($user->id)
        ->and($log->user_id)->toBe($admin->id)
        ->and($log->description)->toBe("DB-{$user->id} anonymized (manual)")
        ->and($log->old_values)->toBeNull()
        ->and($log->new_values)->toBe(['trigger' => 'manual'])
        ->and(json_encode($log->toArray()))->not->toContain('Amina')->not->toContain('Bello');

    expect(DB::table('users')->where('id', $user->id)->value('anonymized_by_id'))->toBe($admin->id);
});

test('it refuses accounts that are not archived, already anonymized, or hold a role', function () {
    $service = app(UserAnonymizer::class);

    $active = User::factory()->create(['lifecycle_status' => 'active']);
    expect(fn () => $service->anonymize($active, null, 'manual'))->toThrow(DomainException::class, 'not archived');

    $done = anonymizeTarget();
    $service->anonymize($done, null, 'manual');
    expect(fn () => $service->anonymize($done, null, 'manual'))->toThrow(DomainException::class, 'already anonymized');

    Role::findOrCreate('branch_db_assistant', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $staff = anonymizeTarget(['email' => 'staff@example.org', 'user_code' => null]);
    $staff->assignRole('branch_db_assistant');
    expect(fn () => $service->anonymize($staff, null, 'manual'))->toThrow(DomainException::class, 'administrative role');

    expect($active->fresh()->first_name)->not->toBe('Anonymized')
        ->and($staff->fresh()->first_name)->toBe('Amina');
});

test('an anonymized user cannot log in with the old email, phone or password', function () {
    $user = anonymizeTarget(['legacy_password_hash' => null, 'password' => 'secret-pass']);
    // The credentials are valid before anonymization.
    expect(Auth::validate(['email' => 'amina.bello@example.org', 'password' => 'secret-pass']))->toBeTrue();

    app(UserAnonymizer::class)->anonymize($user, null, 'scheduled');

    $this->post('/login', ['login' => 'amina.bello@example.org', 'password' => 'secret-pass']);
    $this->assertGuest();
    $this->post('/login', ['login' => '08031234567', 'password' => 'secret-pass']);
    $this->assertGuest();
    expect(Auth::validate(['email' => 'amina.bello@example.org', 'password' => 'secret-pass']))->toBeFalse();
});

test('approving a record for an anonymized member does not bring the account back', function () {
    $user = anonymizeTarget();
    app(UserAnonymizer::class)->anonymize($user, null, 'scheduled');

    $approver = User::factory()->create();
    $payment = MembershipPayment::factory()->create([
        'user_id' => $user->id, 'approval_status' => 'pending', 'submitted_by_user_id' => User::factory()->create()->id,
    ]);

    $payment->approve($approver);

    expect($payment->fresh()->approval_status)->toBe('approved')
        ->and(DB::table('users')->where('id', $user->id)->value('lifecycle_status'))->toBe('archived');
});
