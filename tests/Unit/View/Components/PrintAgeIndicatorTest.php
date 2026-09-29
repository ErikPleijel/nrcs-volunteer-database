<?php

/**
 * Unit tests for <x-print-age-indicator> — rendered via Blade::render() on
 * an unsaved, in-memory IdCardPrint (no database needed; the component only
 * reads printed_at / status / notes).
 */

use App\Models\IdCardPrint;
use App\View\Components\PrintAgeIndicator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;

uses(Tests\TestCase::class);

function printAgeRecord(Carbon $printedAt, string $status = 'printed', ?string $notes = null): IdCardPrint
{
    $print = new IdCardPrint();
    $print->forceFill([
        'printed_at' => $printedAt,
        'status' => $status,
        'notes' => $notes,
    ]);

    return $print;
}

function renderPrintAgeIndicator(?IdCardPrint $print, bool $compact = false): string
{
    return trim(Blade::render(
        '<x-print-age-indicator :print="$print" '.($compact ? 'compact' : '').' />',
        ['print' => $print]
    ));
}

test('renders a gray "Not printed" pill when there is no print record', function () {
    $html = renderPrintAgeIndicator(null);

    expect($html)
        ->toContain('Not printed')
        ->toContain('text-gray-500')
        ->not->toContain('text-green-600')
        ->not->toContain('text-yellow-600')
        ->not->toContain('text-red-600');
});

test('renders "Printed this month" in green for a print in the current month', function () {
    $html = renderPrintAgeIndicator(printAgeRecord(Carbon::now()));

    expect($html)
        ->toContain('Printed this month')
        ->toContain('fa-circle-check')
        ->toContain('text-green-600');
});

test('renders green for a recent print under 12 months', function () {
    $html = renderPrintAgeIndicator(printAgeRecord(Carbon::now()->subMonths(5)));

    expect($html)
        ->toContain('Printed 5 mo ago')
        ->toContain('fa-circle-check')
        ->toContain('text-green-600');
});

test('renders amber for a print between 12 and 36 months old', function () {
    $html = renderPrintAgeIndicator(printAgeRecord(Carbon::now()->subMonths(20)));

    expect($html)
        ->toContain('Printed 20 mo ago')
        ->toContain('fa-circle-exclamation')
        ->toContain('text-yellow-600');
});

test('renders red for a print over 36 months old', function () {
    $html = renderPrintAgeIndicator(printAgeRecord(Carbon::now()->subMonths(40)));

    expect($html)
        ->toContain('Printed 40 mo ago')
        ->toContain('fa-triangle-exclamation')
        ->toContain('text-red-600');
});

test('boundary months: 12 and 36 are amber, 37 is red', function () {
    $at12 = renderPrintAgeIndicator(printAgeRecord(Carbon::now()->subMonths(12)));
    $at36 = renderPrintAgeIndicator(printAgeRecord(Carbon::now()->subMonths(36)));
    $at37 = renderPrintAgeIndicator(printAgeRecord(Carbon::now()->subMonths(37)));

    expect($at12)->toContain('text-yellow-600')
        ->and($at36)->toContain('text-yellow-600')
        ->and($at37)->toContain('text-red-600');
});

test('a print late last month reads "1 mo ago", never "0 mo ago"', function () {
    Carbon::setTestNow(Carbon::create(2026, 9, 1, 9));

    try {
        $html = renderPrintAgeIndicator(printAgeRecord(Carbon::create(2026, 8, 31, 17)));
    } finally {
        Carbon::setTestNow();
    }

    expect($html)->toContain('Printed 1 mo ago')->not->toContain('0 mo');
});

test('renders a neutral "Future print date" pill for a print dated after this month', function () {
    // Real case: a handful of migrated rows have printed_at years ahead.
    $html = renderPrintAgeIndicator(printAgeRecord(Carbon::now()->addMonths(18), status: 'Printed'));

    expect($html)
        ->toContain('Future print date')
        ->toContain('text-gray-500')
        ->toContain('legacy system migration')
        ->not->toContain('mo ago');
});

test('marks legacy-migrated records with a tooltip asterisk', function () {
    $byStatus = renderPrintAgeIndicator(printAgeRecord(Carbon::now()->subMonths(50), status: 'Printed'));
    $byNotes = renderPrintAgeIndicator(printAgeRecord(Carbon::now()->subMonths(50), notes: PrintAgeIndicator::LEGACY_NOTES));

    expect($byStatus)
        ->toContain('Date from legacy system migration, not a confirmed recent print')
        ->toContain('*')
        ->and($byNotes)
        ->toContain('Date from legacy system migration, not a confirmed recent print');
});

test('compact prop stacks the text on two lines and keeps the full text as a tooltip', function () {
    $default = renderPrintAgeIndicator(printAgeRecord(Carbon::now()->subMonths(20)));
    $compact = renderPrintAgeIndicator(printAgeRecord(Carbon::now()->subMonths(20), status: 'Printed'), compact: true);

    expect($default)
        ->toContain('rounded-full')
        ->not->toContain('flex-col')
        ->and($compact)
        ->toContain('flex-col')
        ->toContain('title="Printed 20 mo ago"')
        ->toMatch('/>\s*Printed<\/span>/')
        ->toContain('20 mo ago')
        ->toContain('text-yellow-600')
        ->toContain('legacy system migration');
});

test('does not mark prints recorded via "Mark as Printed" as legacy', function () {
    $html = renderPrintAgeIndicator(printAgeRecord(Carbon::now()->subMonths(3)));

    expect($html)->not->toContain('legacy system migration');
});
