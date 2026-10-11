<?php

/**
 * migrate:old-db runs db:seed itself, and runs payments:backfill-manual-shares
 * last — after db:seed — so the Income Distribution shares on the imported
 * manual payments cannot be forgotten. The sub-commands are recorded, not
 * run (no legacy database in tests).
 */

use App\Console\Commands\OldDbMigration\MigrateOldDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Run migrate:old-db with the given options and return the Artisan
 * commands it calls, in order (a seeder class is kept, so the main db:seed
 * can be told apart).
 */
function recordedImportCalls(array $options): array
{
    $calls = [];

    Artisan::shouldReceive('call')->andReturnUsing(function (string $command, array $parameters = []) use (&$calls) {
        // "db:seed --class=RolesTableSeeder" vs the main "db:seed".
        $calls[] = isset($parameters['--class']) ? "{$command} --class={$parameters['--class']}" : $command;

        return 0;
    });
    Artisan::shouldReceive('output')->andReturn('');

    $command = app(MigrateOldDatabase::class);
    $command->setLaravel(app());
    $command->run(new ArrayInput($options), new BufferedOutput);

    return $calls;
}

test('the full import runs db:seed itself and the payment backfill last, after it', function () {
    $calls = recordedImportCalls(['--table' => 'all']);

    expect($calls[0])->toBe('db:seed --class=RolesTableSeeder')
        ->and($calls)->toContain('db:seed')
        ->and($calls)->toContain('migrate:membership-payments')
        ->and(array_count_values($calls)['payments:backfill-manual-shares'] ?? 0)->toBe(1)
        ->and(end($calls))->toBe('payments:backfill-manual-shares');

    $seed = array_search('db:seed', $calls, true);
    $payments = array_search('migrate:membership-payments', $calls, true);
    $backfill = array_search('payments:backfill-manual-shares', $calls, true);

    expect($payments)->toBeLessThan($seed)
        ->and($seed)->toBeLessThan($backfill);
});

test('re-importing membership payments on their own also fills their shares', function () {
    $calls = recordedImportCalls(['--table' => 'membership-payments']);

    expect($calls)->toBe(['migrate:membership-payments', 'payments:backfill-manual-shares']);
});
