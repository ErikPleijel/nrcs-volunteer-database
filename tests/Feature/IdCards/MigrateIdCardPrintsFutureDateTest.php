<?php

/**
 * Legacy import (migrate:id-card-prints): a print date in the future is a
 * typo in the old system. It is logged and replaced by the import date, and
 * the expiry date is worked out from that (Decisions.md 2026-10-25).
 */

use App\Models\IdCardPrint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function legacyCardHolder(string $timestamp, int $validYears = 3): User
{
    $user = User::factory()->create();
    DB::table('users')->where('id', $user->id)->update(['id_card_timestamp' => $timestamp, 'id_card_valid_years' => $validYears]);

    return $user;
}

it('uses the import date for a print date in the future and logs it', function () {
    $this->travelTo('2026-10-25 12:00:00');
    Log::spy();

    $future = legacyCardHolder('2029-03-06');
    $normal = legacyCardHolder('2025-01-15', 1);

    $this->artisan('migrate:id-card-prints')
        ->expectsOutputToContain("users.id_card_timestamp (user {$future->id}) = '2029-03-06'")
        ->expectsOutputToContain('1 had a print date in the future')
        ->assertSuccessful();

    $fixed = IdCardPrint::where('user_id', $future->id)->sole();
    expect($fixed->printed_at->toDateTimeString())->toBe('2026-10-25 12:00:00')
        ->and($fixed->expiry_date->toDateString())->toBe('2029-10-25')
        ->and($fixed->validity_months)->toBe(36)
        ->and($fixed->notes)->toContain('Print date 2029-03-06 was in the future; import date used.');

    $kept = IdCardPrint::where('user_id', $normal->id)->sole();
    expect($kept->printed_at->toDateString())->toBe('2025-01-15')
        ->and($kept->expiry_date->toDateString())->toBe('2026-01-15')
        ->and($kept->notes)->toBe('Record migrated from old database user data.');

    Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, "user {$future->id}"))->once();
});
