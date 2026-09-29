<?php

/**
 * PhoneNumber::toE164() — the send-time conversion of stored numbers (see the
 * format breakdown in the SMS readiness report: mostly 0XXXXXXXXXX, plus
 * +234, 234 without plus, 10 digits, formatted, +2340 and junk).
 */

use App\Rules\NigerianMobileNumber;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class);

dataset('valid numbers', [
    'local 0-prefixed' => ['08031234567', '+2348031234567'],
    'already E.164' => ['+2348031234567', '+2348031234567'],
    '234 without plus' => ['2348031234567', '+2348031234567'],
    '+2340 (trunk 0 kept after country code)' => ['+23408031234567', '+2348031234567'],
    '2340 without plus' => ['23408031234567', '+2348031234567'],
    '00234 international prefix' => ['002348031234567', '+2348031234567'],
    '10 digits, no leading 0' => ['8031234567', '+2348031234567'],
    'spaces' => ['0803 123 4567', '+2348031234567'],
    'dashes' => ['0803-123-4567', '+2348031234567'],
    'dots' => ['0803.123.4567', '+2348031234567'],
    'brackets and spaces' => ['(0803) 123 4567', '+2348031234567'],
    'E.164 with spaces' => ['+234 803 123 4567', '+2348031234567'],
    'surrounding whitespace' => ['  08031234567 ', '+2348031234567'],
    '070 range' => ['07031234567', '+2347031234567'],
    '081 range' => ['08131234567', '+2348131234567'],
    '090 range' => ['09031234567', '+2349031234567'],
    '091 range' => ['09131234567', '+2349131234567'],
]);

dataset('invalid numbers', [
    'null' => [null],
    'empty' => [''],
    'whitespace only' => ['   '],
    'junk word' => ['nil'],
    'letters mixed in' => ['0803123456a'],
    'too short (local)' => ['0803123456'],
    'too long (local)' => ['080312345678'],
    'too short fragment' => ['080'],
    'non-mobile prefix 071' => ['07131234567'],
    'landline (Abuja 09 + 7 digits)' => ['092345678'],
    'landline (Lagos 01)' => ['012345678'],
    'non-mobile prefix 060' => ['06031234567'],
    'foreign number (UK)' => ['+447911123456'],
    'foreign number (US)' => ['+12025550123'],
    'two numbers in one field' => ['08031234567/08051234567'],
    'two numbers with comma' => ['08031234567, 08051234567'],
    'plus in the middle' => ['0803+1234567'],
    '234 but too short' => ['234803123456'],
]);

test('valid Nigerian mobile numbers convert to E.164', function (string $raw, string $expected) {
    expect(PhoneNumber::toE164($raw))->toBe($expected);
})->with('valid numbers');

test('anything else is null', function (?string $raw) {
    expect(PhoneNumber::toE164($raw))->toBeNull();
})->with('invalid numbers');

test('normalize() is unchanged (login and duplicate matching still use it)', function () {
    expect(PhoneNumber::normalize('+234 803 123 4567'))->toBe('8031234567')
        ->and(PhoneNumber::normalize('08031234567'))->toBe('8031234567')
        ->and(PhoneNumber::normalize('nil'))->toBe('');
});

test('stripFormatting keeps digits and a leading plus, and keeps a local 0 prefix', function () {
    expect(PhoneNumber::stripFormatting('0803 123-4567'))->toBe('08031234567')
        ->and(PhoneNumber::stripFormatting(' +234 (803) 123.4567 '))->toBe('+2348031234567')
        ->and(PhoneNumber::stripFormatting(null))->toBeNull();

    expect(PhoneNumber::stripFormattingFields(['telephone1' => '0803 123 4567', 'other' => 'x y']))
        ->toBe(['telephone1' => '08031234567']);
});

test('the form rule accepts Nigerian mobiles and rejects foreign, landline and junk numbers', function () {
    $passes = fn ($value, ?string $current = null) => Validator::make(
        ['telephone1' => $value],
        ['telephone1' => ['nullable', new NigerianMobileNumber($current)]]
    )->passes();

    expect($passes('08031234567'))->toBeTrue()
        ->and($passes('+2348031234567'))->toBeTrue()
        ->and($passes(null))->toBeTrue()
        ->and($passes(''))->toBeTrue()
        ->and($passes('+447911123456'))->toBeFalse()
        ->and($passes('012345678'))->toBeFalse()
        ->and($passes('nil'))->toBeFalse();
});

test('the form rule lets an unchanged legacy invalid number through, but not a changed one', function () {
    $passes = fn ($value, ?string $current) => Validator::make(
        ['telephone1' => $value],
        ['telephone1' => [new NigerianMobileNumber($current)]]
    )->passes();

    expect($passes('0803123456', '0803123456'))->toBeTrue()          // legacy, untouched
        ->and($passes('0803 123 456', '0803123456'))->toBeTrue()     // same number, reformatted
        ->and($passes('0803123457', '0803123456'))->toBeFalse()      // edited to another invalid value
        ->and($passes('08031234567', '0803123456'))->toBeTrue();     // fixed
});
