<?php

/**
 * Personal data must not leak into the Audit Log (`logs`) old/new values:
 * - membership_payment_deleted stores whitelisted payment columns only
 *   (MembershipPayment::auditSnapshot()), never the loaded member
 * - Log::write() redacts Log::REDACTED_KEYS at any depth
 * - membership_payment_created still records the payment's own fields
 */

use App\Models\Log as AuditLog;
use App\Models\MembershipPayment;
use App\Models\User;
use Database\Factories\MembershipFeeFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    $permissions = ['manage-admin-panel', 'add_payments', 'remove_payments', 'view_payments'];
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }
    Role::findOrCreate('national_db_administrator', 'web')->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->actor = User::factory()->create();
    $this->actor->assignRole('national_db_administrator');
});

test('deleting a payment logs no NIN, email, phone or address of the member', function () {
    $member = User::factory()->create([
        'national_id_number' => '73918264051',
        'email' => 'leak.check@example.org',
        'telephone1' => '08039182640',
        'residential_address' => '14 Leakcheck Crescent, Garki',
        'personal_info' => 'Private note for leak check',
    ]);
    $payment = MembershipPayment::factory()->approved()->create([
        'user_id' => $member->id,
        'payment_date' => now()->subYear()->toDateString(),
        'expiry_date' => now()->addYear()->toDateString(),
    ]);

    $this->actingAs($this->actor)
        ->delete(route('membership-payments.destroy', $payment))
        ->assertRedirect(route('membership-payments.show', $payment));

    expect(DB::table('membership_payments')->where('id', $payment->id)->value('is_deleted'))->toEqual(1);

    $raw = DB::table('logs')->where('action', 'membership_payment_deleted')->sole()->old_values;

    foreach (['73918264051', 'leak.check@example.org', '08039182640', 'Leakcheck Crescent', 'Private note'] as $secret) {
        expect(str_contains($raw, $secret))->toBeFalse("old_values contains '{$secret}'");
    }

    $old = json_decode($raw, true);
    expect($old)->not->toHaveKey('user')
        ->and($old)->not->toHaveKey('gateway_response')
        ->and($old['id'])->toBe($payment->id)
        ->and($old['user_id'])->toBe($member->id)
        ->and($old)->toHaveKeys(['payment_date', 'expiry_date', 'membership_fee_id', 'approval_status']);
});

test('Log::write redacts sensitive keys at any depth and leaves other values alone', function () {
    $log = AuditLog::write(
        'test_action',
        null,
        null,
        [
            'national_id_number' => '73918264051',
            'payment' => [
                'id' => 5,
                'user' => [
                    'national_id_number' => '73918264051',
                    'personal_info' => 'secret',
                    'password' => 'hash',
                    'first_name' => 'Ada',
                ],
            ],
        ],
        [
            'rows' => [['personal_info' => 'secret', 'national_id_number_hash' => 'h', 'remember_token' => 't']],
            'legacy_password_hash' => 'x',
            'national_id_number' => null,
        ],
    );

    $old = json_decode(DB::table('logs')->where('id', $log->id)->value('old_values'), true);
    $new = json_decode(DB::table('logs')->where('id', $log->id)->value('new_values'), true);

    expect($old['national_id_number'])->toBe('[redacted]')
        ->and($old['payment']['id'])->toBe(5)
        ->and($old['payment']['user']['national_id_number'])->toBe('[redacted]')
        ->and($old['payment']['user']['personal_info'])->toBe('[redacted]')
        ->and($old['payment']['user']['password'])->toBe('[redacted]')
        ->and($old['payment']['user']['first_name'])->toBe('Ada')
        ->and($new['rows'][0])->toBe([
            'personal_info' => '[redacted]',
            'national_id_number_hash' => '[redacted]',
            'remember_token' => '[redacted]',
        ])
        ->and($new['legacy_password_hash'])->toBe('[redacted]')
        ->and($new['national_id_number'])->toBeNull();
});

test('the sensitive_fields_updated hook still logs field names with [redacted] values', function () {
    $member = User::factory()->create();

    $member->update(['national_id_number' => '73918264051']);

    $log = AuditLog::where('action', 'sensitive_fields_updated')->where('subject_id', $member->id)->sole();
    expect($log->old_values)->toBe(['national_id_number' => '[redacted]'])
        ->and($log->new_values)->toBe(['national_id_number' => '[redacted]']);
});

test('creating a payment still logs membership_payment_created with the payment fields', function () {
    $member = User::factory()->create(['national_id_number' => '73918264051']);
    $fee = MembershipFeeFactory::new()->create(['is_volunteer_fee' => false, 'validity_years' => 2]);

    $this->actingAs($this->actor)->post(route('membership-payments.store'), [
        'user_id' => $member->id,
        'membership_fee_id' => $fee->id,
        'payment_date' => now()->toDateString(),
        'reference' => 'REF-123',
    ])->assertSessionHas('success');

    $payment = MembershipPayment::withAnyApprovalStatus()->where('user_id', $member->id)->sole();
    $log = AuditLog::where('action', 'membership_payment_created')->sole();

    expect($log->subject_id)->toBe($payment->id)
        ->and($log->user_id)->toBe($this->actor->id)
        ->and($log->new_values)->not->toHaveKey('user')
        ->and($log->new_values['user_id'])->toBe($member->id)
        ->and($log->new_values['membership_fee_id'])->toBe($fee->id)
        ->and($log->new_values['reference'])->toBe('REF-123')
        ->and($log->new_values['submitted_by_user_id'])->toBe($this->actor->id)
        ->and($log->new_values)->toHaveKeys(['payment_date', 'expiry_date'])
        ->and(str_contains(DB::table('logs')->where('id', $log->id)->value('new_values'), '73918264051'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| audit-log:redact-sensitive — cleanup of rows written before redaction
|--------------------------------------------------------------------------
*/

function insertLegacyLeakyLog(): int
{
    return DB::table('logs')->insertGetId([
        'action' => 'membership_payment_deleted',
        'description' => 'Membership payment #9 deleted.',
        'old_values' => json_encode(['id' => 9, 'user' => ['id' => 3, 'national_id_number' => '73918264051', 'first_name' => 'Ada']]),
        'new_values' => null,
        'created_at' => '2026-01-02 03:04:05',
        'updated_at' => '2026-01-02 03:04:05',
    ]);
}

test('audit-log:redact-sensitive is a dry run by default', function () {
    $id = insertLegacyLeakyLog();
    $before = (array) DB::table('logs')->find($id);

    $this->artisan('audit-log:redact-sensitive')
        ->expectsOutputToContain('Dry run: 1 row(s) would change')
        ->assertSuccessful();

    expect((array) DB::table('logs')->find($id))->toBe($before);
});

test('audit-log:redact-sensitive --force redacts old/new values and changes nothing else', function () {
    $id = insertLegacyLeakyLog();
    $before = (array) DB::table('logs')->find($id);

    $this->artisan('audit-log:redact-sensitive', ['--force' => true])
        ->expectsOutputToContain('Redacted 1 row(s)')
        ->assertSuccessful();

    $after = (array) DB::table('logs')->find($id);

    expect(json_decode($after['old_values'], true))
        ->toBe(['id' => 9, 'user' => ['id' => 3, 'national_id_number' => '[redacted]', 'first_name' => 'Ada']])
        ->and(collect($after)->except('old_values')->all())->toBe(collect($before)->except('old_values')->all());

    $this->artisan('audit-log:redact-sensitive')
        ->expectsOutputToContain('No Audit Log rows need redacting.')
        ->assertSuccessful();
});
