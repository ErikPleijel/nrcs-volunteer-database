<?php

namespace App\Console\Commands;

use App\Services\DivisionCoordinateCheck;
use Illuminate\Console\Command;

/**
 * Lists divisions whose coordinates look wrong (DivisionCoordinateCheck).
 * Read-only. Run it after a legacy import or a change to the coordinates
 * seeder. Exits with failure when anything is found, so a deployment
 * script can notice.
 */
class CheckDivisionCoordinates extends Command
{
    protected $signature = 'divisions:check-coordinates
                            {--max-km=150 : How far from the middle of its branch\'s other divisions a division may be}';

    protected $description = 'List divisions with missing, duplicate, out-of-country or far-off coordinates (read-only)';

    public function handle(DivisionCoordinateCheck $check): int
    {
        $maxKm = (float) $this->option('max-km');
        if ($maxKm <= 0) {
            $this->error('--max-km must be a positive number.');

            return self::FAILURE;
        }

        $findings = $check->run($maxKm);

        if ($findings->isEmpty()) {
            $this->info('All division coordinates look plausible.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Division', 'Branch', 'Latitude', 'Longitude', 'Why it is suspect'],
            $findings->map(fn (array $row) => [
                $row['id'],
                $row['name'],
                $row['branch'],
                $row['latitude'] ?? '—',
                $row['longitude'] ?? '—',
                implode('; ', $row['problems']),
            ])->all()
        );

        $this->warn(sprintf(
            '%d %s with suspect coordinates (more than %s km from the middle counts as far). Large states can have genuine far-off LGAs; check each against a reliable source before changing it.',
            $findings->count(),
            $findings->count() === 1 ? 'division' : 'divisions',
            rtrim(rtrim(number_format($maxKm, 1, '.', ''), '0'), '.')
        ));

        return self::FAILURE;
    }
}
