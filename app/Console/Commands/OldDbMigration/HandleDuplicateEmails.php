<?php

namespace App\Console\Commands\OldDbMigration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HandleDuplicateEmails extends Command
{
    protected $signature = 'users:handle-duplicates
                            {--dry-run : Show what would be done without making changes}
                            {--email= : Handle specific email only}
                            {--include-inactive : Include inactive accounts}
                            {--include-unactivated : Include unactivated accounts}
                            {--simple : Use simple email matching (no regex validation)}
                            {--table=persons : Table to check duplicates in (e.g. persons_copy)}';

    protected $description = 'Handle duplicate email addresses by keeping the most recently used account';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $specificEmail = $this->option('email');
        $includeInactive = $this->option('include-inactive');
        $includeUnactivated = $this->option('include-unactivated');
        $simple = $this->option('simple');
        $tableName = $this->option('table') ?: 'persons';

        $this->info("Analyzing duplicate emails in table: {$tableName}");

        // Get old database name from config
        $oldDbName = config('database.connections.old_db.database');

        // Build dynamic table reference: old_db.tableName
        $qualifiedTable = "{$oldDbName}.{$tableName}";

        // Build the query with optional filters
        $whereConditions = [
            "IsOrganisation = 0",
            "Email IS NOT NULL",
            "TRIM(Email) != ''",
        ];

        if (!$includeUnactivated) {
            $whereConditions[] = "AccountActivated = 1";
        }

        if (!$includeInactive) {
            $whereConditions[] = "Inactive = 0";
        }

        // ðŸ”§ IMPORTANT FIX: validate TRIM(Email), not raw Email
        if (!$simple) {
            $whereConditions[] = "TRIM(Email) REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$'";
        } else {
            $whereConditions[] = "TRIM(Email) LIKE '%@%'";
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Duplicate detection query
        $duplicateQuery = "
            SELECT
                LOWER(TRIM(Email)) as normalized_email,
                COUNT(*) as duplicate_count,
                GROUP_CONCAT(PersonID ORDER BY
                    CASE
                        WHEN Lastlogin IS NOT NULL AND Lastlogin != '0000-00-00 00:00:00' THEN Lastlogin
                        WHEN LastActivity IS NOT NULL AND LastActivity != '0000-00-00' THEN LastActivity
                        WHEN Timestamp IS NOT NULL THEN Timestamp
                        ELSE '1970-01-01'
                    END DESC
                ) as person_ids
            FROM {$qualifiedTable}
            WHERE {$whereClause}
        ";

        if ($specificEmail) {
            $duplicateQuery .= " AND LOWER(TRIM(Email)) = '" . strtolower(addslashes($specificEmail)) . "'";
        }

        $duplicateQuery .= "
            GROUP BY LOWER(TRIM(Email))
            HAVING duplicate_count > 1
            ORDER BY duplicate_count DESC, normalized_email
        ";

        $this->line("Filters applied:");
        if (!$includeInactive) $this->line(" - excluding inactive accounts");
        if (!$includeUnactivated) $this->line(" - excluding unactivated accounts");
        if (!$simple) $this->line(" - strict email regex check on TRIM(Email)");
        if ($simple) $this->line(" - simple email check (TRIM(Email) LIKE '%@%')");
        if ($specificEmail) $this->line(" - restricted to email: {$specificEmail}");
        $this->line('');

        $duplicates = DB::connection('old_db')->select($duplicateQuery);

        if (empty($duplicates)) {
            $this->info("No duplicates found in {$tableName} with these filters.");
            $this->info('You might try: --include-inactive --include-unactivated --simple');
            return Command::SUCCESS;
        }

        $this->info('Found ' . count($duplicates) . ' duplicated email groups');

        $totalDuplicates = 0;
        $totalToDeactivate = 0;
        $processedEmails = [];

        foreach ($duplicates as $duplicate) {
            $normalizedEmail = $duplicate->normalized_email;
            $duplicateCount = $duplicate->duplicate_count;

            $totalDuplicates += $duplicateCount;
            $totalToDeactivate += ($duplicateCount - 1);

            // Determine priority order: Lastlogin â†’ PersonID
            $personsQuery = "
                SELECT
                    PersonID, FirstName, LastName, Email, Lastlogin, LastActivity,
                    Timestamp, AccountActivated, Inactive
                FROM {$qualifiedTable}
                WHERE LOWER(TRIM(Email)) = ?
                  AND IsOrganisation = 0
                ORDER BY
                  (Lastlogin IS NOT NULL AND Lastlogin != '0000-00-00 00:00:00') DESC,
                  Lastlogin DESC,
                  PersonID DESC
            ";

            $persons = DB::connection('old_db')->select($personsQuery, [$normalizedEmail]);

            $this->info("\nðŸ“§ Email: {$normalizedEmail} ({$duplicateCount} accounts)");
            $this->line("   Priority (winner first):");

            $keepPersonId = null;

            foreach ($persons as $index => $person) {
                $status = $index === 0 ? 'âœ… KEEP' : 'âŒ DEACTIVATE';
                if ($index === 0) {
                    $keepPersonId = $person->PersonID;
                }

                // Human readable last activity
                $lastActivity = 'Never';
                if ($person->Lastlogin && $person->Lastlogin !== '0000-00-00 00:00:00') {
                    $lastActivity = "Login: {$person->Lastlogin}";
                } elseif ($person->LastActivity && $person->LastActivity !== '0000-00-00') {
                    $lastActivity = "Activity: {$person->LastActivity}";
                } elseif ($person->Timestamp) {
                    $lastActivity = "Created: {$person->Timestamp}";
                }

                $accountStatus = [];
                if (!$person->AccountActivated) $accountStatus[] = 'Unactivated';
                if ($person->Inactive) $accountStatus[] = 'Inactive';
                $statusStr = empty($accountStatus) ? '' : ' [' . implode(', ', $accountStatus) . ']';

                $this->line("   {$status} ID {$person->PersonID} | {$person->FirstName} {$person->LastName} | {$lastActivity}{$statusStr}");
            }

            $processedEmails[] = [
                'email'          => $normalizedEmail,
                'keep_id'        => $keepPersonId,
                'deactivate_ids' => array_slice(array_column($persons, 'PersonID'), 1),
            ];
        }

        $this->info("\nðŸ“Š Summary:");
        $this->info("Duplicate email groups: " . count($duplicates));
        $this->info("Total accounts in those groups: {$totalDuplicates}");
        $this->info("Accounts to deactivate: {$totalToDeactivate}");

        if ($dryRun) {
            $this->warn("\nDRY RUN â€” no data was modified.");
            return Command::SUCCESS;
        }

        // APPLY DEACTIVATION
        $this->info("\nâš™ï¸ Deactivating accounts in table '{$tableName}'...");

        DB::connection('old_db')->transaction(function () use ($processedEmails, $tableName) {
            foreach ($processedEmails as $item) {
                $email = $item['email'];
                $keepId = $item['keep_id'];
                $deactivateIds = $item['deactivate_ids'];

                if (empty($deactivateIds)) {
                    continue;
                }

                $affected = DB::connection('old_db')
                    ->table($tableName)
                    ->whereIn('PersonID', $deactivateIds)
                    ->update(['Inactive' => 1]);

                $idsList = implode(', ', $deactivateIds);
                $this->line("   {$email}: deactivated {$affected} accounts (kept ID {$keepId}; deactivated IDs: {$idsList})");
            }
        });

        $this->info("\nâœ… Deactivation complete.");

        return Command::SUCCESS;
    }
}
