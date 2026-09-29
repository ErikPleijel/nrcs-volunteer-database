<?php

/**
 * CampaignPlaceholderRenderer: per-token output, including the fallbacks.
 * Membership tokens resolve from the latest PERSONAL payment — the current
 * one when valid, otherwise the most recently expired — so the seeded
 * "membership expired" template reads correctly for real expired members.
 */

use App\Models\User;
use App\Support\CampaignPlaceholderRenderer;
use Database\Factories\MembershipFeeFactory;
use Database\Factories\MembershipPaymentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function renderToken(string $template, User $user): string
{
    return CampaignPlaceholderRenderer::render($template, $user);
}

function personalPayment(User $user, string $feeName, string $expiry, array $overrides = []): void
{
    MembershipPaymentFactory::new()->approved()->create(array_merge([
        'user_id' => $user->id,
        'membership_fee_id' => MembershipFeeFactory::new()->create(['name' => $feeName])->id,
        'payment_date' => Carbon::parse($expiry)->subYear()->toDateString(),
        'expiry_date' => $expiry,
    ], $overrides));
}

test('full_name prefixes the title when one is set', function () {
    $user = User::factory()->create(['title' => 'Dr.', 'first_name' => 'Ada', 'last_name' => 'Obi']);

    expect(renderToken('{{user.full_name}}', $user))->toBe('Dr. Ada Obi');
});

test('full_name without a title is just first and last name, with no stray space', function () {
    $user = User::factory()->create(['title' => null, 'first_name' => 'Ada', 'last_name' => 'Obi']);
    $blank = User::factory()->create(['title' => '  ', 'first_name' => 'Ada', 'last_name' => 'Obi']);

    expect(renderToken('[{{user.full_name}}]', $user))->toBe('[Ada Obi]')
        ->and(renderToken('[{{user.full_name}}]', $blank))->toBe('[Ada Obi]');
});

test('full_name leaves User::full_name (used on ID cards) untouched', function () {
    $user = User::factory()->create(['title' => 'Mrs', 'first_name' => 'Ada', 'last_name' => 'Obi']);

    expect($user->full_name)->toBe('Ada Obi');
});

test('membership tokens use the current payment when one is valid', function () {
    $user = User::factory()->create();
    personalPayment($user, 'Old Fee', now()->subYears(2)->toDateString());
    personalPayment($user, 'Supporting Member', now()->addMonths(5)->toDateString());

    expect(renderToken('{{user.current_membership}} / {{user.membership_expiry}}', $user))
        ->toBe('Supporting Member / '.now()->addMonths(5)->format('d M Y'));
});

test('membership tokens fall back to the latest expired payment instead of "current" / "N/A"', function () {
    $user = User::factory()->create();
    personalPayment($user, 'Detachment', '2017-11-24');
    personalPayment($user, 'Older Fee', '2015-01-10');

    expect(renderToken('{{user.current_membership}} / {{user.membership_expiry}}', $user))
        ->toBe('Detachment / 24 Nov 2017');
});

test('membership tokens ignore deleted payments', function () {
    $user = User::factory()->create();
    personalPayment($user, 'Detachment', '2017-11-24');
    personalPayment($user, 'Deleted Fee', now()->addYear()->toDateString(), ['is_deleted' => true]);

    expect(renderToken('{{user.current_membership}}', $user))->toBe('Detachment');
});

test('membership tokens use the fallbacks only for a user who never paid', function () {
    $user = User::factory()->create();

    expect(renderToken('{{user.current_membership}} / {{user.membership_expiry}}', $user))
        ->toBe('Red Cross / N/A');
});

test('the seeded "membership expired" template reads correctly for an expired member', function () {
    $this->seed(\Database\Seeders\CampaignPurposesSeeder::class);
    $purpose = \App\Models\CampaignPurpose::where('slug', 'membership_post_expiry')->firstOrFail();

    $user = User::factory()->create(['first_name' => 'Chijioke']);
    personalPayment($user, 'Detachment', '2017-11-24');

    expect(renderToken($purpose->default_sms_body, $user))
        ->toContain('your NRCS Detachment membership expired on 24 Nov 2017')
        ->and(renderToken($purpose->default_email_body, $user))
        ->toContain('your <strong>Detachment</strong> membership with the Nigerian Red Cross Society expired on 24 Nov 2017');
});

test('the retired db_code_long token still resolves, to the short DB code', function () {
    $user = User::factory()->create();

    expect(renderToken('{{user.db_code_long}}', $user))
        ->toBe($user->user_id_reference_short)
        ->toBe(renderToken('{{user.db_code_short}}', $user))
        ->not->toContain('{{');
});

test('time_since_last_first_aid renders the age of the stored first-aid date', function () {
    $user = User::factory()->create(['last_first_aid_at' => now()->subYears(2)->subMonths(3)->toDateString()]);

    expect(renderToken('{{user.time_since_last_first_aid}}', $user))->toBe('2 years, 3 months');
});

test('time_since_last_first_aid falls back when there is no first-aid record', function () {
    $user = User::factory()->create(['last_first_aid_at' => null]);

    expect(renderToken('{{user.time_since_last_first_aid}}', $user))->toBe('no first-aid training on record');
});

test('a user loaded with only the shared column list renders every token like a full model', function () {
    $user = User::factory()->create([
        'title' => 'Chief', 'last_first_aid_at' => now()->subYears(4)->toDateString(),
    ]);
    personalPayment($user, 'Detachment', '2017-11-24');

    $template = '{{user.full_name}}|{{user.current_membership}}|{{user.membership_expiry}}|{{user.time_since_last_first_aid}}|{{user.db_code_short}}|{{user.lifecycle}}';

    $narrow = User::select(CampaignPlaceholderRenderer::USER_COLUMNS)
        ->with(CampaignPlaceholderRenderer::USER_RELATIONS)
        ->find($user->id);

    expect(renderToken($template, $narrow))->toBe(renderToken($template, User::find($user->id)));
});
