<?php

namespace App\Console\Commands\OldDbMigration;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MigrateOrganisations extends Command
{
    protected $signature = 'migrate:organisations
                            {--chunk=1000 : Number of records to process per chunk}
                            {--clear : Clear existing organisations before migration}
                            {--dry-run : Run without making changes}';

    protected $description = 'Migrate organisations from old persons table where IsOrganisation=1, Inactive<>1, AccountActivated=1';

    public function handle()
    {
        $chunk  = $this->option('chunk');
        $clear  = $this->option('clear');
        $dryRun = $this->option('dry-run');

        $this->info('ðŸ¢ Starting organisations migration...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made to the database');
        }

        // Clear existing data if requested
        if ($clear && ! $dryRun && $this->confirm('This will delete all existing organisations. Continue?')) {
            DB::table('organisations')->delete();
            $this->info('âœ… Existing organisations cleared');

            if ($this->confirm('Also delete users linked to organisations (organisation_id IS NOT NULL)?')) {
                DB::table('users')->whereNotNull('organisation_id')->delete();
                $this->info('âœ… Existing organisation-linked users cleared');
            }
        }

        // Check if organisations table exists
        if (! Schema::hasTable('organisations')) {
            $this->error('âŒ Organisations table does not exist. Please run migrations first.');
            return Command::FAILURE;
        }

        try {
            // Check if old table exists first
            $oldTableExists = DB::connection('old_db')->getSchemaBuilder()->hasTable('persons');

            if (! $oldTableExists) {
                // Try alternative table names
                $alternativeNames = ['person', 'Person', 'Persons'];
                $foundTable       = null;

                foreach ($alternativeNames as $tableName) {
                    if (DB::connection('old_db')->getSchemaBuilder()->hasTable($tableName)) {
                        $foundTable = $tableName;
                        break;
                    }
                }

                if (! $foundTable) {
                    $this->error('âŒ Could not find persons table in old database. Tried: persons, person, Person, Persons');
                    return Command::FAILURE;
                }

                $oldTableName = $foundTable;
            } else {
                $oldTableName = 'persons';
            }

            $this->info("ðŸ“‹ Using old table: {$oldTableName}");

            // Column discovery
            $columns       = DB::connection('old_db')->getSchemaBuilder()->getColumnListing($oldTableName);
            $orgColumnName = null;
            $inactiveCol   = null;
            $activatedCol  = null;

            $possibleOrgColumns = ['IsOrganisation', 'isorganisation', 'is_organisation', 'IsOrganization', 'isorganization', 'is_organization'];
            foreach ($possibleOrgColumns as $colName) {
                if (in_array($colName, $columns)) {
                    $orgColumnName = $colName;
                    break;
                }
            }

            $possibleInactive = ['Inactive', 'inactive', 'in_active'];
            foreach ($possibleInactive as $colName) {
                if (in_array($colName, $columns)) {
                    $inactiveCol = $colName;
                    break;
                }
            }

            $possibleActivated = ['AccountActivated', 'accountactivated', 'account_activated'];
            foreach ($possibleActivated as $colName) {
                if (in_array($colName, $columns)) {
                    $activatedCol = $colName;
                    break;
                }
            }

            if (! $orgColumnName) {
                $this->error('âŒ Could not find IsOrganisation column in old table. Available columns: ' . implode(', ', $columns));
                return Command::FAILURE;
            }

            if (! $inactiveCol) {
                $this->warn('âš ï¸ Could not find Inactive column. Will NOT filter on inactivity.');
            }

            if (! $activatedCol) {
                $this->warn('âš ï¸ Could not find AccountActivated column. Will NOT filter on activation.');
            }

            $this->info("ðŸ“‹ Using organisation column: {$orgColumnName}");
            if ($inactiveCol) {
                $this->info("ðŸ“‹ Using inactive column: {$inactiveCol}");
            }
            if ($activatedCol) {
                $this->info("ðŸ“‹ Using account activated column: {$activatedCol}");
            }

            // Base query: only organisations, active and account-activated
            $baseQuery = DB::connection('old_db')
                ->table($oldTableName)
                ->where($orgColumnName, 1);

            if ($inactiveCol) {
                $baseQuery->where(function ($q) use ($inactiveCol) {
                    $q->where($inactiveCol, '<>', 1)
                        ->orWhereNull($inactiveCol);
                });
            }

            if ($activatedCol) {
                $baseQuery->where($activatedCol, 1);
            }

            $totalPotentialOrgs = $baseQuery->count();

            // Filter for valid names and exclude NRCS / Red Cross variants
            $migrationQuery = clone $baseQuery;

            $bannedNameSubstrings = [
                'nigeria red cross',
                'nigeria red cross society',
                'nigerian red cross',
                'nigerian red cross society',
                'red cross society',
                'nigeria redcross',
                'nigeria redcross society',
                'nigerian redcross',
                'nigerian redcross society',
                'nrcs',
                'nrc',
                'red cross',
                'redcross',
            ];

            $migrationQuery
                ->whereNotNull('Organisation')
                ->where(DB::raw("TRIM(Organisation)"), '!=', '')
                ->where(function ($q) use ($bannedNameSubstrings) {
                    foreach ($bannedNameSubstrings as $term) {
                        $q->whereRaw('LOWER(Organisation) NOT LIKE ?', ['%' . strtolower($term) . '%']);
                    }
                });

            $totalCount          = $migrationQuery->count();
            $skippedForNameFilter = $totalPotentialOrgs - $totalCount;

            $migratedCount       = 0;
            $skippedCount        = 0;
            $errorCount          = 0;

            if ($totalCount === 0) {
                $this->warn('âš ï¸ No organisations with valid names found to migrate.');
                if ($skippedForNameFilter > 0) {
                    $this->line("({$skippedForNameFilter} organisations were skipped due to name filters / empty names.)");
                }
                return Command::SUCCESS;
            }

            $this->info("ðŸ“Š Found {$totalCount} organisations to migrate ({$skippedForNameFilter} skipped due to name/filters).");

            $progressBar = $this->output->createProgressBar($totalCount);
            $progressBar->start();

            // Process in chunks
            // Shadow "user" rows representing organisations are no longer created
            // here — organisation contacts are re-registered fresh by NRCS admins
            // via the app's own OrganisationController::linkUser() flow. This
            // command now only imports the real Organisation records.
            $migrationQuery->orderBy('PersonID')
                ->chunk($chunk, function ($organisations) use (
                    &$migratedCount,
                    &$skippedCount,
                    &$errorCount,
                    $progressBar,
                    $dryRun
                ) {
                    $organisationData = [];

                    foreach ($organisations as $org) {
                        try {
                            $orgExists = false;

                            if (! $dryRun) {
                                $orgExists = DB::table('organisations')
                                    ->where('id', $org->PersonID)
                                    ->exists();
                            }

                            if ($orgExists) {
                                $skippedCount++;
                            } else {
                                $newOrganisation = [
                                    'id'          => $org->PersonID, // Preserve original PersonID as id
                                    'name'        => $this->cleanString($org->Organisation),
                                    'description' => $this->cleanString($org->Personal_info ?? null),
                                    'address'     => $this->cleanString($org->Workplace_address ?? null),
                                    'email'       => $this->cleanEmail($org->Email ?? null),
                                    'branch_id'   => $this->getBranchId($org),
                                    'created_at'  => now(),
                                    'updated_at'  => now(),
                                ];

                                $organisationData[] = $newOrganisation;
                                $migratedCount++;
                            }
                        } catch (\Exception $e) {
                            $errorCount++;
                            $this->error("Failed to process organisation {$org->PersonID}: " . $e->getMessage());
                        }

                        $progressBar->advance();
                    }

                    // Insert batch if not dry run
                    if (! $dryRun && ! empty($organisationData)) {
                        try {
                            DB::table('organisations')->insert($organisationData);
                        } catch (\Exception $e) {
                            $this->error("Failed to insert organisation batch: " . $e->getMessage());
                            $errorCount += count($organisationData);
                        }
                    }
                });

            $progressBar->finish();
            $this->newLine(2);

            // Reset auto-increment to continue from the highest ID + 1
            if (! $dryRun) {
                $maxOrgId = DB::table('organisations')->max('id') ?? 0;
                DB::statement("ALTER TABLE organisations AUTO_INCREMENT = " . ($maxOrgId + 1));
                $this->info("âœ… Set organisations AUTO_INCREMENT to " . ($maxOrgId + 1));
            }

            // Show results
            $this->info('ðŸŽ‰ Organisations migration completed!');

            $this->table(['Metric', 'Count'], [
                ['Total Potential Organisations (after IsOrganisation + active filters)', $totalPotentialOrgs],
                ['Records Actually Processed (after name filters)', $totalCount],
                ['Successfully Migrated (Orgs)', $migratedCount],
                ['Skipped (Orgs Already Existed)', $skippedCount],
                ['Org Errors', $errorCount],
            ]);

            if ($dryRun) {
                $this->warn('This was a DRY RUN - no data was actually migrated');
            }

            $this->showStatistics();

        } catch (\Exception $e) {
            $this->error('âŒ Migration failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function cleanString($value)
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $cleaned = trim($value);
        return strlen($cleaned) > 255 ? substr($cleaned, 0, 255) : $cleaned;
    }

    private function cleanEmail($email)
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        $cleaned = trim(strtolower($email));

        if (! filter_var($cleaned, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $cleaned;
    }

    private function getBranchId($org)
    {
        static $branchExistsCache = [];

        $branchId = $org->BranchID ?? null;

        if (empty($branchId) || $branchId <= 0) {
            return null;
        }

        if (array_key_exists($branchId, $branchExistsCache)) {
            return $branchExistsCache[$branchId] ? $branchId : null;
        }

        $exists = Branch::where('id', $branchId)->exists();
        $branchExistsCache[$branchId] = $exists;

        return $exists ? $branchId : null;
    }

    private function showStatistics()
    {
        $totalOrganisations = DB::table('organisations')->count();

        $byBranch = DB::table('organisations')
            ->select('branch_id', DB::raw('COUNT(*) as org_count'))
            ->groupBy('branch_id')
            ->orderBy('org_count', 'desc')
            ->get();

        $withEmail = DB::table('organisations')
            ->whereNotNull('email')
            ->count();

        $withAddress = DB::table('organisations')
            ->whereNotNull('address')
            ->count();

        $this->info('ðŸ“ˆ Organisation Statistics:');
        $this->line("  - Total organisations: {$totalOrganisations}");
        $this->line("  - With email: {$withEmail}");
        $this->line("  - With address: {$withAddress}");

        if ($byBranch->isNotEmpty()) {
            $this->line("  - Organisations by branch:");
            foreach ($byBranch as $branch) {
                $branchIdForDisplay = $branch->branch_id ?? 'None';
                $this->line("    â€¢ Branch ID {$branchIdForDisplay}: {$branch->org_count} organisations");
            }
        }

        $sampleOrganisations = DB::table('organisations')
            ->select('id', 'name', 'email', 'branch_id')
            ->limit(5)
            ->get();

        if ($sampleOrganisations->isNotEmpty()) {
            $this->line("  - Sample organisations:");
            foreach ($sampleOrganisations as $org) {
                $email              = $org->email ?? 'No email';
                $branchIdForDisplay = $org->branch_id ?? 'None';
                $this->line("    â€¢ ID {$org->id}: {$org->name} - {$email} (Branch: {$branchIdForDisplay})");
            }
        }
    }
}
