<?php

namespace App\Console\Commands;

use App\Models\Log as AuditLog;
use App\Models\Organisation;
use App\Support\Divisions\DivisionMerges;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Fills organisations.division_id from the legacy persons.DivisionID
 * (IsOrganisation = 1), matching on organisation id: the legacy import
 * keeps PersonID as the organisation id.
 *
 * Built for a database that was imported before organisations had a
 * division (the sandbox), which usually cannot reach the legacy database:
 *   1. on the migration machine: --export=organisation-divisions.csv
 *      (id, name, branch_id, division_id, read from legacy);
 *   2. copy the file to the server: --file=organisation-divisions.csv
 *      --dry-run first, then without.
 * --legacy reads the legacy database directly instead of a file.
 *
 * Never guesses: an organisation is only filled when its name and branch
 * match the file and the division exists and is in the organisation's
 * branch; every other case is listed by id and left alone. Organisations
 * that already have a division are skipped and listed (--force
 * overwrites them). Each change is written to the Audit Log. A fresh
 * legacy import sets the division itself (MigrateOrganisations).
 */
class FillOrganisationDivisions extends Command
{
    protected $signature = 'organisations:fill-divisions
                            {--export= : Write the legacy organisation divisions to this CSV file and stop}
                            {--file= : Read organisation divisions from this CSV file (made with --export)}
                            {--legacy : Read organisation divisions from the legacy database}
                            {--force : Also overwrite organisations that already have a division}
                            {--dry-run : Work everything out and report, but write nothing}';

    protected $description = 'Fill organisations.division_id from the legacy persons.DivisionID (via an exported file or the legacy database)';

    public const HEADER = ['id', 'name', 'branch_id', 'division_id'];

    /** Problem lists, in report order: label => [organisation ids]. */
    private const PROBLEMS = [
        'not_in_source' => 'Not in the file / legacy data',
        'name_differs' => 'Name differs from the file',
        'branch_differs' => 'Branch differs from the file',
        'empty' => 'No division in the legacy data',
        'unknown_division' => 'Division does not exist here',
        'other_branch' => 'Division belongs to a different branch',
        'already_set' => 'Already has a different division (use --force)',
    ];

    public function handle(): int
    {
        $sources = array_filter([
            filled($this->option('export')),
            filled($this->option('file')),
            (bool) $this->option('legacy'),
        ]);
        if (count($sources) !== 1) {
            $this->error('Give exactly one of --export=path, --file=path or --legacy.');

            return self::FAILURE;
        }

        if (filled($this->option('export'))) {
            return $this->export((string) $this->option('export'));
        }

        $rows = filled($this->option('file'))
            ? $this->readFile((string) $this->option('file'))
            : $this->legacyRows();

        if ($rows === null) {
            return self::FAILURE;
        }

        return $this->fill($rows, (bool) $this->option('force'), (bool) $this->option('dry-run'));
    }

    /**
     * Every legacy organisation as id => [name, branch_id, division_id],
     * the name cleaned as MigrateOrganisations stores it and the division
     * mapped through DivisionMerges.
     *
     * @return Collection<int, array{name: ?string, branch_id: ?int, division_id: ?int}>
     */
    public function legacyRows(): Collection
    {
        return DB::connection('old_db')->table('persons')
            ->where('IsOrganisation', 1)
            ->orderBy('PersonID')
            ->get(['PersonID', 'Organisation', 'BranchID', 'DivisionID'])
            ->mapWithKeys(fn ($person) => [(int) $person->PersonID => [
                'name' => self::cleanName($person->Organisation),
                'branch_id' => self::positiveId($person->BranchID),
                'division_id' => self::positiveId(DivisionMerges::target(self::positiveId($person->DivisionID))),
            ]]);
    }

    /** Same cleaning as MigrateOrganisations::cleanString(). */
    public static function cleanName(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $cleaned = trim($value);

        return strlen($cleaned) > 255 ? substr($cleaned, 0, 255) : $cleaned;
    }

    private static function positiveId(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function export(string $path): int
    {
        $rows = $this->legacyRows();

        $out = @fopen($path, 'w');
        if ($out === false) {
            $this->error("Cannot write {$path}.");

            return self::FAILURE;
        }

        fputcsv($out, self::HEADER);
        foreach ($rows as $id => $row) {
            fputcsv($out, [$id, $row['name'], $row['branch_id'], $row['division_id']]);
        }
        fclose($out);

        $this->info(sprintf(
            'Wrote %d legacy organisations to %s (%d with a division).',
            $rows->count(),
            $path,
            $rows->whereNotNull('division_id')->count()
        ));

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, array{name: ?string, branch_id: ?int, division_id: ?int}>|null null when the file is unusable
     */
    private function readFile(string $path): ?Collection
    {
        $in = is_readable($path) ? fopen($path, 'r') : false;
        if ($in === false) {
            $this->error("Cannot read {$path}.");

            return null;
        }

        $header = fgetcsv($in);
        if ($header !== self::HEADER) {
            fclose($in);
            $this->error('Unexpected header in '.$path.'; expected: '.implode(',', self::HEADER).'.');

            return null;
        }

        $rows = collect();
        $line = 1;
        while (($fields = fgetcsv($in)) !== false) {
            $line++;
            if ($fields === [null]) {
                continue; // blank line
            }
            if (count($fields) !== count(self::HEADER) || ! ctype_digit((string) $fields[0])) {
                fclose($in);
                $this->error("Line {$line} of {$path} is not a valid row.");

                return null;
            }

            $rows[(int) $fields[0]] = [
                'name' => self::cleanName($fields[1]),
                'branch_id' => self::positiveId($fields[2]),
                'division_id' => self::positiveId($fields[3]),
            ];
        }
        fclose($in);

        return $rows;
    }

    /**
     * @param  Collection<int, array{name: ?string, branch_id: ?int, division_id: ?int}>  $rows
     */
    private function fill(Collection $rows, bool $force, bool $dryRun): int
    {
        if ($dryRun) {
            $this->warn('DRY RUN — nothing will be written.');
        }

        $divisionBranch = DB::table('divisions')->pluck('branch_id', 'id')
            ->map(fn ($branchId) => $branchId === null ? null : (int) $branchId);

        $problems = array_fill_keys(array_keys(self::PROBLEMS), []);
        $alreadySame = 0;
        $changes = [];

        // Archived organisations too: they keep their place in the hierarchy.
        $organisations = Organisation::withTrashed()->orderBy('id')->get(['id', 'name', 'branch_id', 'division_id']);

        foreach ($organisations as $organisation) {
            $row = $rows[$organisation->id] ?? null;
            $branchId = $organisation->branch_id === null ? null : (int) $organisation->branch_id;
            $current = $organisation->division_id === null ? null : (int) $organisation->division_id;

            $problem = match (true) {
                $row === null => 'not_in_source',
                $row['name'] !== self::cleanName($organisation->name) => 'name_differs',
                $row['branch_id'] !== $branchId => 'branch_differs',
                $row['division_id'] === null => 'empty',
                ! $divisionBranch->has($row['division_id']) => 'unknown_division',
                $divisionBranch[$row['division_id']] !== $branchId => 'other_branch',
                $current === $row['division_id'] => 'same',
                $current !== null && ! $force => 'already_set',
                default => null,
            };

            if ($problem === 'same') {
                $alreadySame++;
            } elseif ($problem !== null) {
                $problems[$problem][] = $organisation->id;
            } else {
                $changes[] = [$organisation, $current, $row['division_id']];
            }
        }

        if (! $dryRun && $changes !== []) {
            DB::transaction(function () use ($changes) {
                foreach ($changes as [$organisation, $old, $new]) {
                    DB::table('organisations')->where('id', $organisation->id)->update(['division_id' => $new]);

                    AuditLog::write(
                        'organisation_division_filled',
                        $organisation,
                        ['branch_id' => $organisation->branch_id, 'division_id' => $new],
                        ['division_id' => $old],
                        ['division_id' => $new],
                        "Organisation #{$organisation->id} division set to #{$new} from the legacy data (organisations:fill-divisions)."
                    );
                }
            });
        }

        $overwritten = collect($changes)->filter(fn ($change) => $change[1] !== null)->count();

        $this->newLine();
        $this->table(['', 'Organisations'], array_merge(
            [
                [$dryRun ? 'Would be filled' : 'Filled', count($changes) - $overwritten],
                [$dryRun ? 'Would be overwritten (--force)' : 'Overwritten (--force)', $overwritten],
                ['Already had this division', $alreadySame],
            ],
            collect(self::PROBLEMS)->map(fn ($label, $key) => [$label, count($problems[$key])])->values()->all(),
            [['Total organisations here', $organisations->count()], ['Rows in the file / legacy data', $rows->count()]]
        ));

        $listed = false;
        foreach (self::PROBLEMS as $key => $label) {
            if ($problems[$key] !== []) {
                $this->warn("{$label}: ".implode(', ', $problems[$key]));
                $listed = true;
            }
        }

        if ($listed) {
            $this->warn('The organisations listed above were left alone; set their division by hand.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
