<?php

/**
 * ParsedSearch: what a users-list search term is, by its shape.
 */

use App\Support\Filters\ParsedSearch;

dataset('db codes', [
    'bare' => ['2246', '2246'],
    'spaces inside' => ['22 46', '2246'],
    'DB- prefix' => ['DB-2246', '2246'],
    'DB prefix, lower case, spaces' => ['db 22 46', '2246'],
    'card form' => ['DB-2246-ABIA-ABA', '2246'],
    'nine digits' => ['123456789', '123456789'],
    'leading zero' => ['02246', '2246'],
]);

test('short numbers and DB-prefixed terms are DB codes', function (string $term, string $id) {
    $parsed = ParsedSearch::parse($term);

    expect($parsed->type)->toBe(ParsedSearch::DB_CODE)
        ->and($parsed->value)->toBe($id)
        ->and($parsed->isSensitive())->toBeFalse();
})->with('db codes');

test('a 10-digit number starting 7/8/9 is a phone without its trunk 0', function () {
    $parsed = ParsedSearch::parse('803 123 4567');

    expect($parsed->type)->toBe(ParsedSearch::PHONE)
        ->and($parsed->phoneForms())->toBe(['08031234567', '8031234567', '2348031234567', '23408031234567'])
        ->and($parsed->isSensitive())->toBeTrue();
});

test('an 11-digit number is a phone or NIN', function () {
    $parsed = ParsedSearch::parse('0803-123-4567');

    expect($parsed->type)->toBe(ParsedSearch::PHONE_OR_NIN)
        ->and($parsed->value)->toBe('08031234567')
        ->and($parsed->phoneForms())->toContain('08031234567', '2348031234567');
});

test('an 11-digit number without a leading 0 only matches a phone stored as typed', function () {
    expect(ParsedSearch::parse('12345678901')->phoneForms())->toBe(['12345678901']);
});

test('234… and +234… (13 digits) are phones', function (string $term) {
    $parsed = ParsedSearch::parse($term);

    expect($parsed->type)->toBe(ParsedSearch::PHONE)
        ->and($parsed->phoneForms())->toContain('08031234567');
})->with(['+2348031234567', '+234 803 123 4567', '2348031234567']);

test('other numbers are no DB code, phone or NIN', function (string $term) {
    expect(ParsedSearch::parse($term)->type)->toBe(ParsedSearch::BAD_NUMBER);
})->with([
    '10 digits starting 1' => '1234567890',
    '12 digits' => '123456789012',
    '+ with 11 digits' => '+08031234567',
    'zero' => '0',
]);

test('terms with letters are text, or email when they contain @', function () {
    expect(ParsedSearch::parse('Ada Obi')->type)->toBe(ParsedSearch::TEXT)
        ->and(ParsedSearch::parse('ada@example')->type)->toBe(ParsedSearch::EMAIL)
        ->and(ParsedSearch::parse('DB-X')->type)->toBe(ParsedSearch::TEXT);
});
