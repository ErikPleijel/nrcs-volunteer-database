<?php

/**
 * dates:shift (shift every date in every table) was a dev-only tool for making an old
 * database copy look recent. Removed 2026-10-22 so it can never run on live data.
 */

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

uses(TestCase::class);

test('dates:shift is no longer an artisan command', function () {
    expect(array_keys(Artisan::all()))->not->toContain('dates:shift')
        ->and(file_exists(app_path('Console/Commands/OldDbMigration/ShiftAllDates.php')))->toBeFalse();
});
