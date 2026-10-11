<?php

use App\Support\Naira;

test('kobo is formatted as naira with two decimals using integer arithmetic', function (int $kobo, string $expected) {
    expect(Naira::format($kobo))->toBe($expected);
})->with([
    'zero' => [0, '₦0.00'],
    'one kobo' => [1, '₦0.01'],
    'ten kobo' => [10, '₦0.10'],
    'thousands' => [1_234_567, '₦12,345.67'],
    'billions' => [123_456_789_012_345, '₦1,234,567,890,123.45'],
    'negative' => [-150, '-₦1.50'],
]);

test('the symbol can be left out', function () {
    expect(Naira::format(1_234_567, symbol: false))->toBe('12,345.67');
});

test('decimal() gives a plain number for CSV cells', function (int $kobo, string $expected) {
    expect(Naira::decimal($kobo))->toBe($expected);
})->with([
    'zero' => [0, '0.00'],
    'one kobo' => [1, '0.01'],
    'thousands, no separator' => [1_234_567, '12345.67'],
    'billions' => [123_456_789_012_345, '1234567890123.45'],
    'negative' => [-150, '-1.50'],
]);
