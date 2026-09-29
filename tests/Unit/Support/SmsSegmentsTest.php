<?php

/**
 * SmsSegments::analyse(): GSM-7 160/153 with extension characters counting double,
 * otherwise UCS-2 70/67 counted in UTF-16 units.
 */

use App\Campaigns\Sending\SmsFooter;
use App\Support\SmsSegments;
use Tests\TestCase;

uses(TestCase::class);

test('empty text needs no pages', function () {
    expect(SmsSegments::analyse('')['segments'])->toBe(0);
});

test('GSM-7 boundaries: 160 in one page, then 153 per page', function (int $length, int $segments) {
    $result = SmsSegments::analyse(str_repeat('a', $length));

    expect($result['encoding'])->toBe('GSM-7')
        ->and($result['segments'])->toBe($segments);
})->with([
    [1, 1], [160, 1], [161, 2], [306, 2], [307, 3], [459, 3], [460, 4],
]);

test('GSM extension characters count double', function () {
    expect(SmsSegments::analyse(str_repeat('€', 80)))->toMatchArray(['encoding' => 'GSM-7', 'units' => 160, 'segments' => 1])
        ->and(SmsSegments::analyse(str_repeat('€', 81))['segments'])->toBe(2)
        ->and(SmsSegments::analyse('{[~]}|^\\')['units'])->toBe(16);
});

test('GSM basic accented letters and symbols stay GSM-7; others do not', function () {
    expect(SmsSegments::analyse('Hello Adé, £5 @ 50%. Ñandù? ¿Sì! ÄÖÜäöüß')['encoding'])->toBe('GSM-7');

    // Look-alikes outside the GSM table: acute ú/í/á and the em dash.
    expect(SmsSegments::analyse('Sí')['encoding'])->toBe('UCS-2')
        ->and(SmsSegments::analyse('50% — ok')['non_gsm'])->toBe(['—']);
});

test('Hausa letters make the message Unicode', function (string $char) {
    $result = SmsSegments::analyse("Sannu da {$char}oƙari");

    expect($result['encoding'])->toBe('UCS-2')
        ->and($result['non_gsm'])->toContain('ƙ');
})->with(['ɗ', 'ɓ', 'ƙ']);

test('UCS-2 boundaries: 70 in one page, then 67 per page', function (int $length, int $segments) {
    $result = SmsSegments::analyse('ƙ'.str_repeat('a', $length - 1));

    expect($result['encoding'])->toBe('UCS-2')
        ->and($result['units'])->toBe($length)
        ->and($result['segments'])->toBe($segments);
})->with([
    [70, 1], [71, 2], [134, 2], [135, 3],
]);

test('emoji are Unicode and take two UTF-16 units', function () {
    $result = SmsSegments::analyse('Hi 😀');

    expect($result['encoding'])->toBe('UCS-2')
        ->and($result['chars'])->toBe(4)
        ->and($result['units'])->toBe(5)
        ->and($result['non_gsm'])->toBe(['😀']);
});

test('the opt-out footer preview is as long as a real footer', function () {
    expect(mb_strlen(SmsFooter::preview()))->toBe(mb_strlen(SmsFooter::for(str_repeat('a', 32))));
});
